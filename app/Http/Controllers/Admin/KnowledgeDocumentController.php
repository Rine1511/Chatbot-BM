<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DocumentExtractionException;
use App\Exceptions\EmbeddingProviderException;
use App\Exceptions\VectorStoreException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreKnowledgeDocumentRequest;
use App\Http\Requests\Admin\UpdateKnowledgeDocumentRequest;
use App\Models\FaqCategory;
use App\Models\KnowledgeDocument;
use App\Services\Knowledge\KnowledgeIndexingService;
use App\Support\InputSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class KnowledgeDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $categoryId = $request->integer('category_id');
        $sourceType = trim((string) $request->string('source_type'));
        $processingStatus = trim((string) $request->string('processing_status'));
        $status = trim((string) $request->string('status'));

        $documents = KnowledgeDocument::query()
            ->with(['category', 'uploader'])
            ->withCount('chunks')
            ->when($search !== '', function ($query) use ($search) {
                $escaped = addcslashes($search, '%_\\');
                $like = "%{$escaped}%";
                $query->where(function ($sub) use ($like) {
                    $sub->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('source_label', 'like', $like)
                        ->orWhere('original_filename', 'like', $like);
                });
            })
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->when(in_array($sourceType, [KnowledgeDocument::SOURCE_TEXT, KnowledgeDocument::SOURCE_FILE, KnowledgeDocument::SOURCE_SIBBM], true), fn ($query) => $query->where('source_type', $sourceType))
            ->when(in_array($processingStatus, [
                KnowledgeDocument::STATUS_PENDING,
                KnowledgeDocument::STATUS_PROCESSING,
                KnowledgeDocument::STATUS_INDEXED,
                KnowledgeDocument::STATUS_FAILED,
            ], true), fn ($query) => $query->where('processing_status', $processingStatus))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $categories = FaqCategory::query()->ordered()->get();
        $pendingCount = KnowledgeDocument::query()
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->whereIn('processing_status', [KnowledgeDocument::STATUS_PENDING, KnowledgeDocument::STATUS_FAILED])
            ->count();

        return view('admin.knowledge.index', compact(
            'documents',
            'categories',
            'pendingCount',
            'search',
            'categoryId',
            'sourceType',
            'processingStatus',
            'status'
        ));
    }

    public function create(): View
    {
        $categories = FaqCategory::query()->ordered()->get();

        return view('admin.knowledge.create', compact('categories'));
    }

    public function store(StoreKnowledgeDocumentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $userId = $request->user()?->id;
        $storedPath = null;

        try {
            $payload = [
                'category_id' => $data['category_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'source_type' => $data['source_type'],
                'source_label' => $data['source_label'] ?? null,
                'source_url' => $data['source_url'] ?? null,
                'is_active' => $data['is_active'],
                'processing_status' => KnowledgeDocument::STATUS_PENDING,
                'chunk_count' => 0,
                'uploaded_by' => $userId,
            ];

            if ($data['source_type'] === KnowledgeDocument::SOURCE_FILE && $request->hasFile('document_file')) {
                $file = $request->file('document_file');
                $storedPath = $file->store('knowledge_docs', 'local');

                $payload['original_filename'] = basename($file->getClientOriginalName());
                $payload['file_path'] = $storedPath;
                $payload['mime_type'] = $file->getMimeType() ?: $file->getClientMimeType();
                $payload['file_size'] = $file->getSize();
                $payload['raw_content'] = $this->extractPlainTextPreview($file);
            } else {
                $payload['raw_content'] = $data['raw_content'] ?? null;
            }

            $document = DB::transaction(fn () => KnowledgeDocument::query()->create($payload));
        } catch (Throwable $e) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $e;
        }

        return redirect()
            ->route('admin.knowledge.show', $document)
            ->with('status', 'Dokumen Knowledge Base berhasil disimpan dan siap diproses ke indeks vektor.');
    }

    public function show(KnowledgeDocument $knowledge): View
    {
        $knowledge->load(['category', 'uploader', 'chunks.embedding']);

        return view('admin.knowledge.show', ['document' => $knowledge]);
    }

    public function download(KnowledgeDocument $knowledge): StreamedResponse
    {
        abort_unless(
            $knowledge->source_type === KnowledgeDocument::SOURCE_FILE
            && $knowledge->file_path
            && Storage::disk('local')->exists($knowledge->file_path),
            404
        );

        $downloadName = $knowledge->original_filename ?: ('dokumen-'.$knowledge->id);

        return Storage::disk('local')->download($knowledge->file_path, $downloadName);
    }

    public function edit(KnowledgeDocument $knowledge): View
    {
        $categories = FaqCategory::query()->ordered()->get();

        return view('admin.knowledge.edit', [
            'document' => $knowledge,
            'categories' => $categories,
        ]);
    }

    public function update(
        UpdateKnowledgeDocumentRequest $request,
        KnowledgeDocument $knowledge,
        KnowledgeIndexingService $indexingService,
    ): RedirectResponse {
        $data = $request->validated();
        $oldFilePath = $knowledge->file_path;
        $newStoredPath = null;
        $deleteOldFileAfterCommit = false;
        $shouldCleanupVectors = ! $data['is_active']
            || $data['source_type'] !== $knowledge->source_type
            || (int) $data['category_id'] !== (int) $knowledge->category_id
            || $data['title'] !== $knowledge->title
            || ($data['source_label'] ?? null) !== $knowledge->source_label;

        try {
            $payload = [
                'category_id' => $data['category_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'source_type' => $data['source_type'],
                'source_label' => $data['source_label'] ?? null,
                'source_url' => $data['source_url'] ?? null,
                'is_active' => $data['is_active'],
                'processing_status' => KnowledgeDocument::STATUS_PENDING,
                'error_message' => null,
            ];

            if ($data['source_type'] === KnowledgeDocument::SOURCE_FILE) {
                if ($request->hasFile('document_file')) {
                    $file = $request->file('document_file');
                    $newStoredPath = $file->store('knowledge_docs', 'local');

                    $payload['original_filename'] = basename($file->getClientOriginalName());
                    $payload['file_path'] = $newStoredPath;
                    $payload['mime_type'] = $file->getMimeType() ?: $file->getClientMimeType();
                    $payload['file_size'] = $file->getSize();
                    $payload['raw_content'] = $this->extractPlainTextPreview($file);
                    $payload['chunk_count'] = 0;
                    $shouldCleanupVectors = true;

                    if ($oldFilePath) {
                        $deleteOldFileAfterCommit = true;
                    }
                }
            } else {
                if ($knowledge->raw_content !== ($data['raw_content'] ?? null)) {
                    $shouldCleanupVectors = true;
                }

                $payload['raw_content'] = $data['raw_content'] ?? null;
                $payload['original_filename'] = null;
                $payload['file_path'] = null;
                $payload['mime_type'] = null;
                $payload['file_size'] = null;
                $payload['chunk_count'] = 0;

                if ($oldFilePath) {
                    $deleteOldFileAfterCommit = true;
                }
            }

            if ($shouldCleanupVectors) {
                $indexingService->cleanupDocumentVectors($knowledge);
            }

            DB::transaction(fn () => $knowledge->update($payload));
        } catch (Throwable $e) {
            if ($newStoredPath) {
                Storage::disk('local')->delete($newStoredPath);
            }

            throw $e;
        }

        if ($deleteOldFileAfterCommit && $oldFilePath) {
            Storage::disk('local')->delete($oldFilePath);
        }

        return redirect()
            ->route('admin.knowledge.show', $knowledge)
            ->with('status', 'Dokumen Knowledge Base berhasil diperbarui. Jalankan proses indeks ulang agar perubahan masuk ke vector store.');
    }

    public function indexDocument(
        Request $request,
        KnowledgeDocument $knowledge,
        KnowledgeIndexingService $indexingService,
    ): RedirectResponse {
        $async = $request->boolean('async', (bool) config('ai.rag.queue_indexing', false));
        $redirectTarget = $request->input('redirect') === 'index'
            ? route('admin.knowledge.index')
            : route('admin.knowledge.show', $knowledge);

        try {
            $mode = $indexingService->dispatch($knowledge, $async);
        } catch (DocumentExtractionException|EmbeddingProviderException|VectorStoreException $e) {
            return redirect($redirectTarget)->withErrors(['indexing' => $e->getMessage()]);
        }

        if ($mode === 'queued') {
            return redirect($redirectTarget)
                ->with('status', "Dokumen \"{$knowledge->title}\" berhasil dimasukkan ke antrean pemrosesan indeks.");
        }

        $knowledge->refresh();

        return redirect($redirectTarget)
            ->with('status', "Dokumen \"{$knowledge->title}\" berhasil diproses dan diindeks ({$knowledge->chunk_count} chunk).");
    }

    public function processPending(
        Request $request,
        KnowledgeIndexingService $indexingService,
    ): RedirectResponse {
        $async = $request->boolean('async', (bool) config('ai.rag.queue_indexing', false));

        $pendingDocs = KnowledgeDocument::query()
            ->with('category')
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->whereIn('processing_status', [KnowledgeDocument::STATUS_PENDING, KnowledgeDocument::STATUS_FAILED])
            ->get();

        if ($pendingDocs->isEmpty()) {
            return redirect()
                ->route('admin.knowledge.index')
                ->with('status', 'Tidak ada dokumen aktif berstatus pending atau gagal yang perlu diproses.');
        }

        $processed = 0;
        $failed = 0;
        $lastError = null;

        foreach ($pendingDocs as $doc) {
            try {
                $indexingService->dispatch($doc, $async);
                $processed++;
            } catch (DocumentExtractionException|EmbeddingProviderException|VectorStoreException $e) {
                $failed++;
                $lastError = $e->getMessage();
            }
        }

        $redirect = redirect()->route('admin.knowledge.index');

        if ($failed > 0 && $processed === 0) {
            return $redirect->withErrors(['indexing' => "Semua dokumen gagal diproses ({$failed} gagal): {$lastError}"]);
        }

        if ($failed > 0) {
            return $redirect
                ->with('status', "{$processed} dokumen diproses, {$failed} dokumen gagal. Periksa detail dokumen yang gagal.")
                ->withErrors(['indexing' => "Sebagian dokumen gagal diproses: {$lastError}"]);
        }

        return $redirect->with('status', "{$processed} dokumen Knowledge Base berhasil diproses.");
    }

    public function destroy(
        KnowledgeDocument $knowledge,
        KnowledgeIndexingService $indexingService,
    ): RedirectResponse {
        $filePath = $knowledge->file_path;

        $indexingService->cleanupDocumentVectors($knowledge);
        DB::transaction(fn () => $knowledge->delete());

        if ($filePath) {
            Storage::disk('local')->delete($filePath);
        }

        return redirect()
            ->route('admin.knowledge.index')
            ->with('status', 'Dokumen Knowledge Base beserta vector terkait berhasil dihapus.');
    }

    private function extractPlainTextPreview(UploadedFile $file): ?string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['txt', 'md', 'csv'], true)) {
            return null;
        }

        $raw = $file->get();
        if ($raw === false || $raw === '') {
            return null;
        }

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
        }

        return InputSanitizer::cleanMultiLine(mb_substr($raw, 0, 150000));
    }
}
