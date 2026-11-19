<?php

namespace Tests\Feature;

use App\Contracts\LlmProviderInterface;
use App\Exceptions\LlmProviderException;
use App\Models\Conversation;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\KnowledgeDocument;
use App\Models\Message;
use App\Models\User;
use App\Services\AI\GeminiLlmProvider;
use App\Services\AI\LlmProviderManager;
use App\Services\AI\OpenAiCompatibleLlmProvider;
use App\Services\Knowledge\FaqIndexingService;
use App\Services\Knowledge\KnowledgeIndexingService;
use Database\Seeders\ChatbotSettingSeeder;
use Database\Seeders\FaqCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ChatRagApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_llm_provider_interface_resolves_gemini_2_5_flash_and_sends_expected_payload(): void
    {
        config()->set('ai.llm.driver', 'gemini');
        config()->set('ai.llm.model', 'gemini-2.5-flash');
        config()->set('ai.providers.gemini.api_key', 'TEST_GEMINI_LLM_SECRET_123');

        $provider = app(LlmProviderInterface::class);
        $this->assertInstanceOf(GeminiLlmProvider::class, $provider);
        $this->assertSame('gemini', $provider->driverName());

        $openAiAdapter = app(LlmProviderManager::class)->driver('openai_compatible');
        $this->assertInstanceOf(OpenAiCompatibleLlmProvider::class, $openAiAdapter);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Pendaftaran santri baru dibuka setiap bulan Januari hingga Maret.'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $reply = $provider->generateReply(
            'Anda adalah Asisten Virtual resmi.',
            [
                ['role' => 'user', 'content' => 'Halo'],
                ['role' => 'assistant', 'content' => 'Waalaikumsalam, ada yang bisa dibantu?'],
                ['role' => 'user', 'content' => 'Kapan pendaftaran dibuka?'],
            ]
        );

        $this->assertSame('Pendaftaran santri baru dibuka setiap bulan Januari hingga Maret.', $reply);

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), 'models/gemini-2.5-flash:generateContent')
                && $request->header('x-goog-api-key')[0] === 'TEST_GEMINI_LLM_SECRET_123'
                && ($request['systemInstruction']['parts'][0]['text'] ?? '') === 'Anda adalah Asisten Virtual resmi.'
                && count($request['contents'] ?? []) === 3
                && ($request['contents'][1]['role'] ?? '') === 'model';
        });
    }

    public function test_gemini_llm_provider_handles_rate_limit_timeout_and_redacts_api_key(): void
    {
        config()->set('ai.providers.gemini.api_key', 'SECRET_KEY_NEVER_LEAK_777');
        config()->set('ai.llm.retry_times', 0);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'API failure for SECRET_KEY_NEVER_LEAK_777'],
            ], 500),
        ]);

        $provider = new GeminiLlmProvider;

        try {
            $provider->generateReply('System', [['role' => 'user', 'content' => 'Tes']]);
            $this->fail('Seharusnya melempar LlmProviderException.');
        } catch (LlmProviderException $e) {
            $this->assertStringNotContainsString('SECRET_KEY_NEVER_LEAK_777', $e->getMessage());
            $this->assertStringContainsString('[REDACTED]', $e->getMessage());
        }
    }

    public function test_gemini_llm_provider_handles_connection_timeout(): void
    {
        config()->set('ai.providers.gemini.api_key', 'SECRET_KEY_TIMEOUT_888');
        config()->set('ai.llm.retry_times', 0);

        Http::fake(static function (): void {
            throw new ConnectionException('Connection timed out for SECRET_KEY_TIMEOUT_888');
        });

        $provider = new GeminiLlmProvider;

        $this->expectException(LlmProviderException::class);
        $this->expectExceptionMessage('timeout');

        $provider->generateReply('System', [['role' => 'user', 'content' => 'Tes']]);
    }

    public function test_start_conversation_and_rag_message_using_active_faq_and_knowledge_base(): void
    {
        config()->set('ai.vector.driver', 'mysql');
        config()->set('ai.providers.gemini.api_key', 'TEST_STAGE5_GEMINI_KEY');
        config()->set('ai.rag.similarity_threshold', 0.65);

        $this->seed([FaqCategorySeeder::class, ChatbotSettingSeeder::class]);
        $admin = User::factory()->create();
        $registrationCat = FaqCategory::query()->where('slug', 'pendaftaran')->firstOrFail();
        $facilityCat = FaqCategory::query()->where('slug', 'fasilitas')->firstOrFail();

        $faqVec = $this->normalizedVector(768, [0 => 0.9, 1 => 0.4]);
        $docVec = $this->normalizedVector(768, [0 => 0.85, 1 => 0.5]);
        $queryVec = $this->normalizedVector(768, [0 => 0.9, 1 => 0.42]);

        Http::fake([
            'generativelanguage.googleapis.com/*:embedContent' => Http::sequence()
                ->push(['embedding' => ['values' => $faqVec]], 200)
                ->push(['embedding' => ['values' => $docVec]], 200)
                ->push(['embedding' => ['values' => $queryVec]], 200),
            'generativelanguage.googleapis.com/*:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => 'Pendaftaran santri baru dilakukan melalui sekretariat resmi Pondok Pesantren Bahrul Maghfiroh Kota Malang, dan santri baru memperoleh fasilitas asrama putra maupun putri.',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // 1. Indeks 1 FAQ aktif dan 1 Knowledge Document aktif
        $faq = Faq::query()->create([
            'category_id' => $registrationCat->id,
            'question' => 'Bagaimana cara mendaftar santri baru di Bahrul Maghfiroh?',
            'answer' => 'Pendaftaran santri baru dilakukan melalui sekretariat resmi dan portal pendaftaran pondok.',
            'keywords' => 'pendaftaran, santri baru, psb',
            'source_url' => 'https://bahrulmaghfirohmalang.or.id/pendaftaran',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        app(FaqIndexingService::class)->indexFaq($faq);

        $doc = KnowledgeDocument::query()->create([
            'category_id' => $facilityCat->id,
            'title' => 'Panduan Asrama Santri Baru',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'source_label' => 'Dokumen SIB-BM',
            'raw_content' => 'Asrama santri baru terdiri atas gedung asrama putra dan asrama putri di lingkungan Pondok Pesantren Bahrul Maghfiroh.',
            'is_active' => true,
            'uploaded_by' => $admin->id,
        ]);
        app(KnowledgeIndexingService::class)->indexDocument($doc);

        // 2. Mulai percakapan melalui REST API
        $startRes = $this->postJson('/api/v1/chat/conversations', [
            'origin_domain' => 'https://bahrulmaghfirohmalang.or.id/profil',
            'visitor_id' => 'visitor-browser-001',
        ]);

        $startRes->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.chatbot_name', 'Asisten Virtual Bahrul Maghfiroh')
            ->assertJsonPath('data.origin_domain', 'bahrulmaghfirohmalang.or.id');

        $sessionToken = $startRes->json('data.session_token');
        $this->assertSame(64, strlen($sessionToken));
        $this->assertStringNotContainsString('TEST_STAGE5_GEMINI_KEY', $startRes->getContent());

        // 3. Kirim pesan pertanyaan via REST API (menguji X-Widget-Session header)
        $msgRes = $this->withHeaders([
            'X-Widget-Session' => $sessionToken,
        ])->postJson('/api/v1/chat/messages', [
            'message' => 'Bagaimana pendaftaran santri baru dan apakah ada fasilitas asrama?',
        ]);

        $msgRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.conversation.message_count', 2)
            ->assertJsonPath('data.user_message.sender_type', 'user')
            ->assertJsonPath('data.assistant_message.sender_type', 'assistant')
            ->assertJsonPath('data.assistant_message.is_fallback', false);

        $sources = $msgRes->json('data.assistant_message.matched_sources');
        $this->assertCount(2, $sources);
        $sourceTypes = array_column($sources, 'source_type');
        $this->assertContains('faq', $sourceTypes);
        $this->assertContains('document', $sourceTypes);

        // Verifikasi konteks FAQ dan Knowledge Base benar-benar dikirim ke Gemini 2.5 Flash
        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'models/gemini-2.5-flash:generateContent')) {
                return false;
            }

            $userContent = $request['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($userContent, 'FAQ Aktif')
                && str_contains($userContent, 'Dokumen Knowledge Base')
                && str_contains($userContent, 'sekretariat resmi')
                && str_contains($userContent, 'gedung asrama putra');
        });

        // 4. Verifikasi tersimpan di DB & endpoint GET riwayat
        $conversation = Conversation::query()->where('session_token', $sessionToken)->firstOrFail();
        $this->assertSame(2, $conversation->message_count);
        $this->assertDatabaseCount('messages', 2);

        $historyRes = $this->getJson("/api/v1/chat/conversations/{$sessionToken}");
        $historyRes->assertOk()
            ->assertJsonCount(2, 'data.messages');
    }

    public function test_returns_polite_fallback_without_calling_llm_when_no_context_matches(): void
    {
        config()->set('ai.vector.driver', 'mysql');
        config()->set('ai.providers.gemini.api_key', 'TEST_STAGE5_FALLBACK_KEY');
        config()->set('ai.rag.similarity_threshold', 0.65);

        $this->seed([FaqCategorySeeder::class, ChatbotSettingSeeder::class]);
        $admin = User::factory()->create();
        $cat = FaqCategory::query()->where('slug', 'profil-pondok')->firstOrFail();

        $docVec = $this->normalizedVector(768, [0 => 1.0]);
        $unrelatedQueryVec = $this->normalizedVector(768, [5 => 1.0]); // cosine = 0.0 (< 0.65)

        Http::fake([
            'generativelanguage.googleapis.com/*:embedContent' => Http::sequence()
                ->push(['embedding' => ['values' => $docVec]], 200)
                ->push(['embedding' => ['values' => $unrelatedQueryVec]], 200),
            'generativelanguage.googleapis.com/*:generateContent' => Http::response([], 500),
        ]);

        $doc = KnowledgeDocument::query()->create([
            'category_id' => $cat->id,
            'title' => 'Profil Singkat Pondok',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Pondok Pesantren Bahrul Maghfiroh berlokasi di Kota Malang.',
            'is_active' => true,
            'uploaded_by' => $admin->id,
        ]);
        app(KnowledgeIndexingService::class)->indexDocument($doc);

        $startRes = $this->postJson('/api/v1/chat/conversations');
        $sessionToken = $startRes->json('data.session_token');

        $msgRes = $this->postJson('/api/v1/chat/messages', [
            'session_token' => $sessionToken,
            'message' => 'Berapa harga tiket pesawat ke luar negeri?',
        ]);

        $msgRes->assertOk()
            ->assertJsonPath('data.assistant_message.is_fallback', true)
            ->assertJsonPath('data.assistant_message.matched_sources', []);

        $this->assertStringContainsString(
            'https://bahrulmaghfirohmalang.or.id/',
            $msgRes->json('data.assistant_message.content')
        );

        // Pastikan LLM tidak dipanggil ketika konteks tidak tersedia (anti halusinasi)
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), ':generateContent'));

        $assistantMsg = Message::query()->where('sender_type', 'assistant')->firstOrFail();
        $this->assertTrue($assistantMsg->is_fallback);
    }

    public function test_inactive_faq_and_inactive_category_are_excluded_and_trigger_fallback(): void
    {
        config()->set('ai.vector.driver', 'mysql');
        config()->set('ai.providers.gemini.api_key', 'TEST_STAGE5_ACTIVE_FILTER');

        $this->seed([FaqCategorySeeder::class, ChatbotSettingSeeder::class]);
        $admin = User::factory()->create();
        $cat = FaqCategory::query()->where('slug', 'kegiatan')->firstOrFail();

        $vec = $this->normalizedVector(768, [0 => 1.0]);
        Http::fake([
            'generativelanguage.googleapis.com/*:embedContent' => Http::response([
                'embedding' => ['values' => $vec],
            ], 200),
        ]);

        $faq = Faq::query()->create([
            'category_id' => $cat->id,
            'question' => 'Apakah ada kegiatan ekstrakurikuler?',
            'answer' => 'Kegiatan ekstrakurikuler santri dilaksanakan setiap sore.',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        app(FaqIndexingService::class)->indexFaq($faq);
        $this->assertDatabaseCount('knowledge_chunks', 1);

        // Nonaktifkan FAQ melalui endpoint admin -> vector harus dibersihkan
        $this->actingAs($admin)
            ->patch(route('admin.faqs.toggle-status', $faq))
            ->assertRedirect(route('admin.faqs.index'));

        $this->assertDatabaseCount('knowledge_chunks', 0);

        $sessionToken = $this->postJson('/api/v1/chat/conversations')->json('data.session_token');
        $res = $this->postJson('/api/v1/chat/messages', [
            'session_token' => $sessionToken,
            'message' => 'Apakah ada kegiatan ekstrakurikuler?',
        ]);

        $res->assertOk()
            ->assertJsonPath('data.assistant_message.is_fallback', true)
            ->assertJsonPath('data.assistant_message.matched_sources', []);
    }

    public function test_api_validates_inputs_handles_404_503_and_enforces_rate_limit(): void
    {
        config()->set('ai.providers.gemini.api_key', 'SECRET_NEVER_EXPOSE_999');
        config()->set('ai.embedding.retry_times', 0);

        // 1. Validasi input gagal -> 422 JSON
        $this->postJson('/api/v1/chat/messages', [
            'session_token' => 'invalid-short',
            'message' => 'a',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['session_token', 'message']);

        // 2. Session tidak ditemukan -> 404 JSON
        $this->postJson('/api/v1/chat/messages', [
            'session_token' => str_repeat('a', 64),
            'message' => 'Bagaimana jadwal pendaftaran santri?',
        ])->assertStatus(404)
            ->assertJsonPath('error_code', 'CONVERSATION_NOT_FOUND');

        // 3. Kegagalan provider AI -> 503 aman tanpa membocorkan API key
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'Rejected key SECRET_NEVER_EXPOSE_999'],
            ], 401),
        ]);

        $sessionToken = $this->postJson('/api/v1/chat/conversations')->json('data.session_token');

        $errRes = $this->postJson('/api/v1/chat/messages', [
            'session_token' => $sessionToken,
            'message' => 'Apa saja syarat masuk pondok?',
        ]);

        $errRes->assertStatus(503)
            ->assertJsonPath('error_code', 'AI_SERVICE_UNAVAILABLE');
        $this->assertStringNotContainsString('SECRET_NEVER_EXPOSE_999', $errRes->getContent());

        // Tidak ada pesan yatim tersimpan saat upstream 503 gagal
        $this->assertDatabaseCount('messages', 0);

        // 4. Uji rate limiting pada endpoint startConversation (batas 15/menit per IP)
        $rateTestServer = ['REMOTE_ADDR' => '10.99.88.77'];
        for ($i = 0; $i < 15; $i++) {
            $this->withServerVariables($rateTestServer)
                ->postJson('/api/v1/chat/conversations')
                ->assertCreated();
        }

        $this->withServerVariables($rateTestServer)
            ->postJson('/api/v1/chat/conversations')
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'RATE_LIMIT_EXCEEDED');
    }

    /**
     * @param  array<int, float>  $weights
     * @return list<float>
     */
    private function normalizedVector(int $dimensions, array $weights): array
    {
        $vector = array_fill(0, $dimensions, 0.0);
        foreach ($weights as $index => $value) {
            $vector[$index] = $value;
        }

        $norm = sqrt(array_sum(array_map(static fn (float $v) => $v * $v, $vector)));
        if ($norm > 0.0) {
            $vector = array_map(static fn (float $v) => $v / $norm, $vector);
        }

        return $vector;
    }
}
