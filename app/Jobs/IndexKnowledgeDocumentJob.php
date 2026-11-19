<?php

namespace App\Jobs;

use App\Exceptions\DocumentExtractionException;
use App\Models\KnowledgeDocument;
use App\Services\Knowledge\KnowledgeIndexingService;
use App\Support\SecretRedactor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class IndexKnowledgeDocumentJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $documentId,
    ) {}

    public function uniqueId(): string
    {
        return 'index-kb-doc-'.$this->documentId;
    }

    public function handle(KnowledgeIndexingService $indexingService): void
    {
        $document = KnowledgeDocument::query()->with('category')->find($this->documentId);
        if (! $document) {
            return;
        }

        try {
            $indexingService->indexDocument($document);
        } catch (DocumentExtractionException $e) {
            // Kegagalan ekstraksi / format tidak valid / nonaktif bersifat permanen (tidak perlu retry 3x pada queue worker)
            if ($this->job && $this->job->getConnectionName() !== 'sync') {
                $this->fail($e);

                return;
            }

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $document = KnowledgeDocument::query()->find($this->documentId);
        if (! $document) {
            return;
        }

        $message = $exception
            ? Str::limit(trim(SecretRedactor::redact($exception->getMessage())), 2000)
            : 'Pemrosesan dokumen pada antrean gagal setelah beberapa kali percobaan.';

        $document->update([
            'processing_status' => KnowledgeDocument::STATUS_FAILED,
            'error_message' => $message,
        ]);
    }
}
