<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotSetting;
use App\Models\Conversation;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\Message;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_conversations' => Conversation::query()->count(),
            'total_questions' => Message::query()->where('sender_type', 'user')->count(),
            'total_faqs' => Faq::query()->count(),
            'active_faqs' => Faq::query()->active()->count(),
            'total_documents' => KnowledgeDocument::query()->count(),
            'pending_documents' => KnowledgeDocument::query()->where('processing_status', KnowledgeDocument::STATUS_PENDING)->count(),
            'indexed_documents' => KnowledgeDocument::query()->where('processing_status', KnowledgeDocument::STATUS_INDEXED)->count(),
            'failed_documents' => KnowledgeDocument::query()->where('processing_status', KnowledgeDocument::STATUS_FAILED)->count(),
            'total_chunks' => KnowledgeChunk::query()->count(),
            'total_categories' => FaqCategory::query()->active()->count(),
        ];

        $categories = FaqCategory::query()
            ->ordered()
            ->withCount(['faqs', 'knowledgeDocuments'])
            ->get();

        $recentConversations = Conversation::query()
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->limit(6)
            ->get();

        $systemReadiness = [
            'chatbot_name' => (string) ChatbotSetting::getValue('chatbot_name', 'Asisten Virtual Bahrul Maghfiroh'),
            'llm_driver' => (string) config('ai.llm.driver'),
            'llm_model' => (string) config('ai.llm.model'),
            'embedding_model' => (string) config('ai.embedding.model'),
            'embedding_dimensions' => (int) config('ai.embedding.dimensions'),
            'vector_driver' => (string) config('ai.vector.driver'),
            'qdrant_collection' => (string) config('ai.vector.qdrant.collection'),
            'gemini_configured' => $this->isSecretConfigured((string) config('ai.providers.gemini.api_key')),
            'sibbm_enabled' => (bool) config('sibbm.enabled'),
        ];

        return view('admin.dashboard.index', compact(
            'stats',
            'categories',
            'recentConversations',
            'systemReadiness'
        ));
    }

    public function modulePlaceholder(string $module): View
    {
        $modules = [
            'conversations' => [
                'title' => 'Riwayat & Evaluasi Percakapan',
                'stage' => 'Tahap 5',
                'description' => 'Peninjauan pertanyaan pengunjung, sumber konteks yang digunakan, dan evaluasi akurasi jawaban chatbot.',
            ],
            'settings' => [
                'title' => 'Pengaturan Chatbot & Integrasi',
                'stage' => 'Tahap 4 & 8',
                'description' => 'Konfigurasi nama chatbot, pesan sambutan, system prompt, fallback resmi, dan domain widget.',
            ],
        ];

        abort_unless(array_key_exists($module, $modules), 404);

        return view('admin.dashboard.module-roadmap', [
            'moduleKey' => $module,
            'moduleInfo' => $modules[$module],
        ]);
    }

    private function isSecretConfigured(string $value): bool
    {
        $trimmed = trim($value);

        return $trimmed !== ''
            && ! in_array($trimmed, ['MY_GEMINI_API_KEY', 'ISI_API_KEY_GEMINI_ANDA'], true);
    }
}
