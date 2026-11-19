<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFaqRequest;
use App\Http\Requests\Admin\UpdateFaqRequest;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Services\Knowledge\FaqIndexingService;
use App\Support\SecretRedactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $categoryId = $request->integer('category_id');
        $status = trim((string) $request->string('status'));
        $featured = trim((string) $request->string('featured'));

        $faqs = Faq::query()
            ->with(['category', 'creator', 'updater'])
            ->when($search !== '', function ($query) use ($search) {
                $escaped = addcslashes($search, '%_\\');
                $like = "%{$escaped}%";
                $query->where(function ($sub) use ($like) {
                    $sub->where('question', 'like', $like)
                        ->orWhere('answer', 'like', $like)
                        ->orWhere('keywords', 'like', $like)
                        ->orWhere('question_variations', 'like', $like);
                });
            })
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($featured === 'featured', fn ($query) => $query->where('is_featured', true))
            ->when($featured === 'standard', fn ($query) => $query->where('is_featured', false))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $categories = FaqCategory::query()->ordered()->get();

        return view('admin.faqs.index', compact(
            'faqs',
            'categories',
            'search',
            'categoryId',
            'status',
            'featured'
        ));
    }

    public function create(): View
    {
        $categories = FaqCategory::query()->ordered()->get();

        return view('admin.faqs.create', compact('categories'));
    }

    public function store(StoreFaqRequest $request, FaqIndexingService $faqIndexingService): RedirectResponse
    {
        $data = $request->validated();
        $userId = $request->user()?->id;

        $faq = DB::transaction(function () use ($data, $userId): Faq {
            return Faq::query()->create([
                ...$data,
                'embedding_status' => Faq::EMBEDDING_PENDING,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        });

        $this->tryAutoIndexFaq($faq, $faqIndexingService);

        return redirect()
            ->route('admin.faqs.index')
            ->with('status', 'FAQ baru berhasil disimpan.');
    }

    public function edit(Faq $faq): View
    {
        $categories = FaqCategory::query()->ordered()->get();

        return view('admin.faqs.edit', compact('faq', 'categories'));
    }

    public function update(UpdateFaqRequest $request, Faq $faq, FaqIndexingService $faqIndexingService): RedirectResponse
    {
        $data = $request->validated();
        $userId = $request->user()?->id;

        DB::transaction(function () use ($faq, $data, $userId) {
            $faq->update([
                ...$data,
                'embedding_status' => Faq::EMBEDDING_PENDING,
                'updated_by' => $userId,
            ]);
        });

        $faq->refresh()->loadMissing('category');
        if (! $faq->is_active || ! $faq->category?->is_active) {
            $faqIndexingService->cleanupFaqVectors($faq);
        } else {
            $faqIndexingService->cleanupFaqVectors($faq);
            $this->tryAutoIndexFaq($faq, $faqIndexingService);
        }

        return redirect()
            ->route('admin.faqs.index')
            ->with('status', 'FAQ berhasil diperbarui.');
    }

    public function toggleStatus(Request $request, Faq $faq, FaqIndexingService $faqIndexingService): RedirectResponse
    {
        $userId = $request->user()?->id;

        DB::transaction(function () use ($faq, $userId) {
            $faq->update([
                'is_active' => ! $faq->is_active,
                'embedding_status' => Faq::EMBEDDING_PENDING,
                'updated_by' => $userId,
            ]);
        });

        $faq->refresh()->loadMissing('category');
        if (! $faq->is_active || ! $faq->category?->is_active) {
            $faqIndexingService->cleanupFaqVectors($faq);
        } else {
            $this->tryAutoIndexFaq($faq, $faqIndexingService);
        }

        $label = $faq->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->route('admin.faqs.index')
            ->with('status', "Status FAQ berhasil {$label}.");
    }

    public function destroy(Faq $faq, FaqIndexingService $faqIndexingService): RedirectResponse
    {
        $faqIndexingService->cleanupFaqVectors($faq);

        DB::transaction(fn () => $faq->delete());

        return redirect()
            ->route('admin.faqs.index')
            ->with('status', 'FAQ berhasil dihapus.');
    }

    private function tryAutoIndexFaq(Faq $faq, FaqIndexingService $faqIndexingService): void
    {
        $faq->loadMissing('category');
        if (! $faq->is_active || ! $faq->category?->is_active) {
            return;
        }

        $driver = (string) config('ai.embedding.driver', 'gemini');
        $apiKey = $driver === 'gemini'
            ? trim((string) config('ai.providers.gemini.api_key', ''))
            : trim((string) config('ai.providers.openai_compatible.api_key', ''));

        if ($apiKey === '' || in_array($apiKey, ['MY_GEMINI_API_KEY', 'ISI_API_KEY_GEMINI_ANDA'], true)) {
            return;
        }

        try {
            $faqIndexingService->indexFaq($faq);
        } catch (Throwable $e) {
            $safeMsg = SecretRedactor::redact($e->getMessage());
            Log::warning("Auto-indexing FAQ #{$faq->id} ditunda/gagal: {$safeMsg}");
        }
    }
}
