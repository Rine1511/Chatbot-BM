<?php

namespace App\Services\Chat;

use App\Contracts\EmbeddingProviderInterface;
use App\Contracts\LlmProviderInterface;
use App\Exceptions\EmbeddingProviderException;
use App\Models\ChatbotSetting;
use App\Models\Conversation;
use App\Models\Faq;
use App\Models\KnowledgeDocument;
use App\Models\Message;
use App\Services\VectorStore\VectorStoreManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RagService
{
    private const DEFAULT_CHATBOT_NAME = 'Asisten Virtual Bahrul Maghfiroh';

    private const DEFAULT_WELCOME_MESSAGE = "Assalamu'alaikum warahmatullahi wabarakatuh. Selamat datang di layanan informasi Pondok Pesantren Bahrul Maghfiroh Kota Malang. Ada yang dapat kami bantu terkait profil pondok, pendidikan, pendaftaran, kegiatan, fasilitas, atau informasi bangunan?";

    private const DEFAULT_SYSTEM_PROMPT = 'Anda adalah Asisten Virtual resmi Pondok Pesantren Bahrul Maghfiroh Kota Malang. Jawab pertanyaan pengunjung hanya berdasarkan konteks knowledge base resmi dan data SIB-BM yang diberikan. Jangan mengarang fakta, nama, jadwal, biaya, atau kebijakan pondok. Gunakan bahasa Indonesia yang sopan, natural, ringkas, mudah dibaca, dan sertakan tautan sumber resmi apabila tersedia.';

    private const DEFAULT_FALLBACK_MESSAGE = 'Mohon maaf, informasi spesifik mengenai hal tersebut belum tersedia pada basis pengetahuan resmi kami. Silakan menghubungi pihak sekretariat atau kontak resmi Pondok Pesantren Bahrul Maghfiroh melalui website https://bahrulmaghfirohmalang.or.id/ untuk informasi lebih lanjut.';

    public function __construct(
        private readonly EmbeddingProviderInterface $embeddingProvider,
        private readonly VectorStoreManager $vectorStoreManager,
        private readonly LlmProviderInterface $llmProvider,
    ) {}

    /**
     * @return array{chatbot_name: string, welcome_message: string}
     */
    public function publicMetadata(): array
    {
        return [
            'chatbot_name' => $this->settingValue('chatbot_name', self::DEFAULT_CHATBOT_NAME),
            'welcome_message' => $this->settingValue('welcome_message', self::DEFAULT_WELCOME_MESSAGE),
        ];
    }

    /**
     * Proses pertanyaan pengguna melalui pipeline RAG dan simpan percakapan ke database.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     conversation: Conversation,
     *     user_message: Message,
     *     assistant_message: Message,
     *     matched_sources: list<array<string, mixed>>
     * }
     */
    public function reply(Conversation $conversation, string $userMessage, array $filters = []): array
    {
        $startedAt = hrtime(true);
        $cleanQuestion = trim($userMessage);

        $contexts = $this->retrieveContext($cleanQuestion, $filters);

        if ($contexts === []) {
            $answer = $this->settingValue('fallback_message', self::DEFAULT_FALLBACK_MESSAGE);
            $isFallback = true;
            $matchedSources = [];
        } else {
            $systemPrompt = $this->buildGroundedSystemPrompt();
            $historyMessages = $this->recentHistoryMessages($conversation);
            $llmMessages = array_merge($historyMessages, [
                [
                    'role' => 'user',
                    'content' => $this->buildUserPromptWithContext($cleanQuestion, $contexts),
                ],
            ]);

            $answer = trim($this->llmProvider->generateReply($systemPrompt, $llmMessages));
            $isFallback = false;
            $matchedSources = array_values(array_map(static function (array $ctx): array {
                return [
                    'source_type' => $ctx['source_type'],
                    'faq_id' => $ctx['faq_id'],
                    'document_id' => $ctx['document_id'],
                    'chunk_id' => $ctx['chunk_id'],
                    'title' => $ctx['title'],
                    'category' => $ctx['category'],
                    'category_slug' => $ctx['category_slug'],
                    'source_label' => $ctx['source_label'],
                    'source_url' => $ctx['source_url'],
                    'score' => $ctx['score'],
                    'excerpt' => $ctx['excerpt'],
                ];
            }, $contexts));
        }

        $elapsedMs = (int) max(1, round((hrtime(true) - $startedAt) / 1_000_000));

        return DB::transaction(function () use (
            $conversation,
            $cleanQuestion,
            $answer,
            $matchedSources,
            $isFallback,
            $elapsedMs,
        ): array {
            $userMsg = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'user',
                'content' => $cleanQuestion,
                'is_fallback' => false,
            ]);

            $assistantMsg = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'assistant',
                'content' => $answer,
                'matched_sources' => $matchedSources,
                'is_fallback' => $isFallback,
                'response_time_ms' => $elapsedMs,
                'evaluation_status' => 'unreviewed',
            ]);

            $now = now();
            $conversation->update([
                'last_message_at' => $now,
                'message_count' => $conversation->messages()->count(),
            ]);

            return [
                'conversation' => $conversation->fresh(),
                'user_message' => $userMsg,
                'assistant_message' => $assistantMsg,
                'matched_sources' => $matchedSources,
            ];
        });
    }

    /**
     * Ambil konteks terverifikasi dari FAQ aktif dan Knowledge Base aktif via vector search.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function retrieveContext(
        string $question,
        array $filters = [],
        ?int $topK = null,
        ?float $similarityThreshold = null,
    ): array {
        $topK = max(1, (int) ($topK ?? config('ai.rag.top_k', 5)));
        $threshold = (float) ($similarityThreshold ?? config('ai.rag.similarity_threshold', 0.65));

        $queryVector = $this->embeddingProvider->embed($question, 'RETRIEVAL_QUERY');
        $expectedDim = $this->embeddingProvider->dimensions();

        if (count($queryVector) !== $expectedDim) {
            throw new EmbeddingProviderException(
                sprintf('Dimensi embedding query (%d) tidak cocok dengan konfigurasi (%d).', count($queryVector), $expectedDim)
            );
        }

        $vectorStore = $this->vectorStoreManager->resolveWithFallback();
        $rawCandidates = $vectorStore->search($queryVector, max($topK * 2, 10), $filters);

        $filteredByScore = array_values(array_filter(
            $rawCandidates,
            static fn (array $item): bool => (float) ($item['score'] ?? 0.0) >= $threshold
        ));

        if ($filteredByScore === []) {
            return [];
        }

        $docIds = [];
        $faqIds = [];
        foreach ($filteredByScore as $item) {
            $payload = $item['payload'] ?? [];
            if (! empty($payload['document_id'])) {
                $docIds[] = (int) $payload['document_id'];
            }
            if (! empty($payload['faq_id'])) {
                $faqIds[] = (int) $payload['faq_id'];
            }
        }

        $activeDocs = $docIds === []
            ? collect()
            : KnowledgeDocument::query()
                ->with('category')
                ->whereIn('id', array_unique($docIds))
                ->where('is_active', true)
                ->whereHas('category', fn ($q) => $q->where('is_active', true))
                ->get()
                ->keyBy('id');

        $activeFaqs = $faqIds === []
            ? collect()
            : Faq::query()
                ->with('category')
                ->whereIn('id', array_unique($faqIds))
                ->where('is_active', true)
                ->whereHas('category', fn ($q) => $q->where('is_active', true))
                ->get()
                ->keyBy('id');

        $verified = [];
        foreach ($filteredByScore as $item) {
            $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];
            $content = trim((string) ($payload['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $faqId = ! empty($payload['faq_id']) ? (int) $payload['faq_id'] : null;
            $docId = ! empty($payload['document_id']) ? (int) $payload['document_id'] : null;

            if ($faqId !== null) {
                /** @var Faq|null $faq */
                $faq = $activeFaqs->get($faqId);
                if (! $faq) {
                    continue;
                }

                $verified[] = [
                    'source_type' => 'faq',
                    'faq_id' => $faq->id,
                    'document_id' => null,
                    'chunk_id' => isset($payload['chunk_id']) ? (int) $payload['chunk_id'] : null,
                    'title' => $faq->question,
                    'category' => $faq->category?->name,
                    'category_slug' => $faq->category?->slug,
                    'source_label' => 'FAQ Resmi',
                    'source_url' => $faq->source_url,
                    'score' => round((float) ($item['score'] ?? 0.0), 4),
                    'content' => $content,
                    'excerpt' => Str::limit($content, 200),
                ];
            } elseif ($docId !== null) {
                /** @var KnowledgeDocument|null $doc */
                $doc = $activeDocs->get($docId);
                if (! $doc) {
                    continue;
                }

                $verified[] = [
                    'source_type' => 'document',
                    'faq_id' => null,
                    'document_id' => $doc->id,
                    'chunk_id' => isset($payload['chunk_id']) ? (int) $payload['chunk_id'] : null,
                    'title' => $doc->title,
                    'category' => $doc->category?->name,
                    'category_slug' => $doc->category?->slug,
                    'source_label' => $doc->source_label ?: 'Knowledge Base',
                    'source_url' => $doc->source_url,
                    'score' => round((float) ($item['score'] ?? 0.0), 4),
                    'content' => $content,
                    'excerpt' => Str::limit($content, 200),
                ];
            }

            if (count($verified) >= $topK) {
                break;
            }
        }

        return $verified;
    }

    private function buildGroundedSystemPrompt(): string
    {
        $basePrompt = $this->settingValue('system_prompt', self::DEFAULT_SYSTEM_PROMPT);

        return trim($basePrompt)."\n\n".implode("\n", [
            'ATURAN KETAT JAWABAN:',
            '1. Jawab HANYA berdasarkan KONTEKS RESMI yang disediakan pada pesan pengguna.',
            '2. DILARANG mengarang fakta, jadwal, nominal biaya, nomor kontak, nama, atau kebijakan pondok di luar konteks.',
            '3. Jika konteks hanya menjawab sebagian pertanyaan, sampaikan bagian yang pasti ada di konteks saja, lalu arahkan secara sopan ke sekretariat atau https://bahrulmaghfirohmalang.or.id/ untuk rincian lainnya.',
            '4. Jika informasi ternyata tidak ada pada konteks yang diberikan, sampaikan permohonan maaf dengan sopan bahwa informasi tersebut belum tersedia di basis pengetahuan resmi.',
            '5. Gunakan bahasa Indonesia yang ramah, islami, sopan, jelas, dan ringkas.',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $contexts
     */
    private function buildUserPromptWithContext(string $question, array $contexts): string
    {
        $blocks = [];
        foreach ($contexts as $idx => $ctx) {
            $num = $idx + 1;
            $jenis = $ctx['source_type'] === 'faq' ? 'FAQ Aktif' : 'Dokumen Knowledge Base';
            $header = "[Sumber #{$num} | Jenis: {$jenis} | Kategori: ".($ctx['category'] ?? '-')." | Judul: {$ctx['title']} | Skor: {$ctx['score']}]";
            if (! empty($ctx['source_url'])) {
                $header .= "\nTautan Sumber: {$ctx['source_url']}";
            } elseif (! empty($ctx['source_label'])) {
                $header .= "\nLabel Sumber: {$ctx['source_label']}";
            }
            $blocks[] = $header."\n".$ctx['content'];
        }

        return "KONTEKS RESMI PONDOK PESANTREN BAHRUL MAGHFIROH:\n\n"
            .implode("\n\n---\n\n", $blocks)
            ."\n\nPERTANYAAN PENGGUNA:\n{$question}";
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function recentHistoryMessages(Conversation $conversation, int $limit = 6): array
    {
        $recent = $conversation->messages()
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $history = [];
        foreach ($recent as $msg) {
            $content = trim((string) $msg->content);
            if ($content === '') {
                continue;
            }

            $history[] = [
                'role' => $msg->sender_type === 'assistant' ? 'assistant' : 'user',
                'content' => $content,
            ];
        }

        return $history;
    }

    private function settingValue(string $key, string $default): string
    {
        $value = ChatbotSetting::getValue($key);
        $trimmed = is_string($value) ? trim($value) : '';

        return $trimmed !== '' ? $trimmed : $default;
    }
}
