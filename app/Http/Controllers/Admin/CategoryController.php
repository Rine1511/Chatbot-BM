<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\FaqCategory;
use App\Models\KnowledgeDocument;
use App\Services\Knowledge\KnowledgeIndexingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $status = trim((string) $request->string('status'));

        $categories = FaqCategory::query()
            ->withCount(['faqs', 'knowledgeDocuments'])
            ->when($search !== '', function ($query) use ($search) {
                $escaped = addcslashes($search, '%_\\');
                $like = "%{$escaped}%";
                $query->where(function ($sub) use ($like) {
                    $sub->where('name', 'like', $like)
                        ->orWhere('slug', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('admin.categories.index', compact('categories', 'search', 'status'));
    }

    public function create(): View
    {
        $nextOrder = ((int) FaqCategory::query()->max('sort_order')) + 1;

        return view('admin.categories.create', compact('nextOrder'));
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = DB::transaction(fn () => FaqCategory::query()->create($request->validated()));

        return redirect()
            ->route('admin.categories.index')
            ->with('status', "Kategori \"{$category->name}\" berhasil ditambahkan.");
    }

    public function edit(FaqCategory $category): View
    {
        $category->loadCount(['faqs', 'knowledgeDocuments']);

        return view('admin.categories.edit', compact('category'));
    }

    public function update(
        UpdateCategoryRequest $request,
        FaqCategory $category,
        KnowledgeIndexingService $indexingService,
        \App\Services\Knowledge\FaqIndexingService $faqIndexingService,
    ): RedirectResponse {
        $wasActive = $category->is_active;
        $validated = $request->validated();

        DB::transaction(fn () => $category->update($validated));

        if ($wasActive && ! $category->fresh()->is_active) {
            foreach ($category->knowledgeDocuments()->get() as $doc) {
                $indexingService->cleanupDocumentVectors($doc);
                $doc->update([
                    'processing_status' => KnowledgeDocument::STATUS_PENDING,
                    'error_message' => null,
                ]);
            }

            foreach ($category->faqs()->get() as $faq) {
                $faqIndexingService->cleanupFaqVectors($faq);
                $faq->update([
                    'embedding_status' => \App\Models\Faq::EMBEDDING_PENDING,
                ]);
            }
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('status', "Kategori \"{$category->name}\" berhasil diperbarui.");
    }

    public function destroy(FaqCategory $category): RedirectResponse
    {
        $faqCount = $category->faqs()->count();
        $documentCount = $category->knowledgeDocuments()->count();

        if ($faqCount > 0 || $documentCount > 0) {
            return redirect()
                ->route('admin.categories.index')
                ->withErrors([
                    'category' => "Kategori \"{$category->name}\" tidak dapat dihapus karena masih digunakan oleh {$faqCount} FAQ dan {$documentCount} dokumen Knowledge Base.",
                ]);
        }

        $name = $category->name;
        DB::transaction(fn () => $category->delete());

        return redirect()
            ->route('admin.categories.index')
            ->with('status', "Kategori \"{$name}\" berhasil dihapus.");
    }
}
