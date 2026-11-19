<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\Knowledge\FaqIndexingService;
use App\Services\Knowledge\KnowledgeIndexingService;
use App\Services\SibBm\SibBmSyncService;
use Database\Seeders\ChatbotSettingSeeder;
use Database\Seeders\FaqCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Stage6To9FullSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage6_visitor_chat_ui_renders_responsively_without_exposing_secrets(): void
    {
        config()->set('ai.providers.gemini.api_key', 'SECRET_UI_NEVER_LEAK_111');
        config()->set('sibbm.api_key', 'SECRET_SIBBM_NEVER_LEAK_111');

        $this->seed([FaqCategorySeeder::class, ChatbotSettingSeeder::class]);

        $response = $this->get(route('chat.index'));
        $response->assertOk()
            ->assertSee('Asisten Virtual Bahrul Maghfiroh')
            ->assertSee('Percakapan Baru')
            ->assertSee('/api/v1/chat')
            ->assertSee('bm-typing-indicator')
            ->assertDontSee('SECRET_UI_NEVER_LEAK_111')
            ->assertDontSee('SECRET_SIBBM_NEVER_LEAK_111');
    }

    public function test_stage8_embed_widget_asset_exists_isolated_and_origin_cors_enforced(): void
    {
        $widgetPath = public_path('widget/bm-chatbot.js');
        $this->assertFileExists($widgetPath);

        $widgetCode = (string) file_get_contents($widgetPath);
        $this->assertStringContainsString('attachShadow', $widgetCode);
        $this->assertStringContainsString('/api/v1/chat', $widgetCode);
        $this->assertStringContainsString('X-Widget-Session', $widgetCode);
        $this->assertStringContainsString('bm_widget_session_token', $widgetCode);
        $this->assertStringNotContainsString('GEMINI_API_KEY', $widgetCode);

        $this->seed([ChatbotSettingSeeder::class]);

        // 1. Origin resmi pondok diizinkan & header CORS dikembalikan
        $allowedRes = $this->withHeaders([
            'Origin' => 'https://bahrulmaghfirohmalang.or.id',
        ])->postJson('/api/v1/chat/conversations', [
            'origin_domain' => 'bahrulmaghfirohmalang.or.id',
        ]);

        $allowedRes->assertCreated()
            ->assertHeader('Access-Control-Allow-Origin', 'https://bahrulmaghfirohmalang.or.id');

        // 2. Preflight OPTIONS dari origin resmi
        $preflightRes = $this->withHeaders([
            'Origin' => 'https://bahrulmaghfirohmalang.or.id',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, X-Widget-Session',
        ])->options('/api/v1/chat/messages');

        $preflightRes->assertStatus(204)
            ->assertHeader('Access-Control-Allow-Origin', 'https://bahrulmaghfirohmalang.or.id');

        // 3. Origin tidak dikenal ditolak oleh middleware widget.origin (403)
        $blockedRes = $this->withHeaders([
            'Origin' => 'https://domain-asing-tidak-resmi.example',
        ])->postJson('/api/v1/chat/conversations');

        $blockedRes->assertStatus(403)
            ->assertJsonPath('error_code', 'ORIGIN_NOT_ALLOWED');
    }

    public function test_stage9_end_to_end_multi_category_rag_session_sources_and_fallback(): void
    {
        config()->set('ai.vector.driver', 'mysql');
        config()->set('ai.providers.gemini.api_key', 'TEST_STAGE9_E2E_KEY');
        config()->set('ai.rag.similarity_threshold', 0.65);

        $this->seed([FaqCategorySeeder::class, ChatbotSettingSeeder::class]);
        $admin = User::factory()->create();

        // Siapkan vektor berbeda untuk tiap topik utama agar retrieval akurat
        $vecProfil = $this->normalizedVector(768, [0 => 1.0]);
        $vecPendidikan = $this->normalizedVector(768, [1 => 1.0]);
        $vecKegiatan = $this->normalizedVector(768, [2 => 1.0]);
        $vecFasilitas = $this->normalizedVector(768, [3 => 1.0]);
        $vecBangunan = $this->normalizedVector(768, [4 => 1.0]);
        $vecOutOfScope = $this->normalizedVector(768, [10 => 1.0]);

        Http::fake([
            'generativelanguage.googleapis.com/*:embedContent' => Http::sequence()
                // 5 indexing calls (1 FAQ + 3 Docs + 1 SIB-BM)
                ->push(['embedding' => ['values' => $vecProfil]], 200)
                ->push(['embedding' => ['values' => $vecPendidikan]], 200)
                ->push(['embedding' => ['values' => $vecKegiatan]], 200)
                ->push(['embedding' => ['values' => $vecFasilitas]], 200)
                ->push(['embedding' => ['values' => $vecBangunan]], 200)
                // 3 query calls (Pendidikan, Bangunan SIB-BM, Out-of-scope)
                ->push(['embedding' => ['values' => $vecPendidikan]], 200)
                ->push(['embedding' => ['values' => $vecBangunan]], 200)
                ->push(['embedding' => ['values' => $vecOutOfScope]], 200),
            'generativelanguage.googleapis.com/*:generateContent' => Http::sequence()
                ->push([
                    'candidates' => [['content' => ['parts' => [['text' => 'Jenjang pendidikan di Bahrul Maghfiroh meliputi SMP, SMA, dan Madrasah Diniyah.']]]]],
                ], 200)
                ->push([
                    'candidates' => [['content' => ['parts' => [['text' => 'Aula Utama Bahrul Maghfiroh berkapasitas 800 orang dengan luas bangunan 950 m2.']]]]],
                ], 200),
        ]);

        // 1. FAQ Profil Pondok
        $faqProfil = Faq::query()->create([
            'category_id' => FaqCategory::where('slug', 'profil-pondok')->firstOrFail()->id,
            'question' => 'Di mana alamat Pondok Pesantren Bahrul Maghfiroh?',
            'answer' => 'Berlokasi di Jalan Joyo Agung, Kota Malang, Jawa Timur.',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        app(FaqIndexingService::class)->indexFaq($faqProfil);

        // 2. Dokumen Pendidikan, Kegiatan, Fasilitas
        $kbSvc = app(KnowledgeIndexingService::class);
        foreach ([
            ['pendidikan', 'Kurikulum Pendidikan Pondok', 'Tersedia SMP, SMA, dan Madrasah Diniyah terpadu.'],
            ['kegiatan', 'Jadwal Harian Santri', 'Shalat berjamaah, ngaji kitab kuning, dan tahfidz Al-Quran.'],
            ['fasilitas', 'Fasilitas Kesehatan & Koperasi', 'Tersedia klinik kesehatan santri dan koperasi pesantren.'],
        ] as [$slug, $title, $content]) {
            $doc = KnowledgeDocument::query()->create([
                'category_id' => FaqCategory::where('slug', $slug)->firstOrFail()->id,
                'title' => $title,
                'source_type' => KnowledgeDocument::SOURCE_TEXT,
                'raw_content' => $content,
                'is_active' => true,
                'uploaded_by' => $admin->id,
            ]);
            $kbSvc->indexDocument($doc);
        }

        // 3. Data Bangunan SIB-BM
        app(SibBmSyncService::class)->syncBuildings([
            [
                'external_id' => 'BM-AULA-01',
                'name' => 'Aula Utama Bahrul Maghfiroh',
                'category' => 'Pertemuan',
                'function_desc' => 'Aula pengajian akbar dan wisuda santri',
                'building_area' => 950,
                'capacity' => 800,
                'facilities' => ['Panggung', 'Audio', 'AC'],
                'detail_url' => 'https://sibbm.bahrulmaghfirohmalang.or.id/gedung/BM-AULA-01',
            ],
        ], $admin->id, true);

        // Simulasi alur widget dari website utama pondok
        $startRes = $this->withHeaders([
            'Origin' => 'https://bahrulmaghfirohmalang.or.id',
        ])->postJson('/api/v1/chat/conversations', [
            'origin_domain' => 'bahrulmaghfirohmalang.or.id',
        ])->assertCreated();

        $sessionToken = $startRes->json('data.session_token');

        // Query 1: Pendidikan
        $res1 = $this->withHeaders([
            'Origin' => 'https://bahrulmaghfirohmalang.or.id',
            'X-Widget-Session' => $sessionToken,
        ])->postJson('/api/v1/chat/messages', [
            'message' => 'Apa saja jenjang pendidikan yang ada di pondok?',
        ]);
        $res1->assertOk()
            ->assertJsonPath('data.assistant_message.is_fallback', false)
            ->assertJsonPath('data.assistant_message.matched_sources.0.category_slug', 'pendidikan');

        // Query 2: Bangunan SIB-BM
        $res2 = $this->withHeaders([
            'Origin' => 'https://bahrulmaghfirohmalang.or.id',
            'X-Widget-Session' => $sessionToken,
        ])->postJson('/api/v1/chat/messages', [
            'message' => 'Berapa kapasitas Aula Utama Bahrul Maghfiroh?',
        ]);
        $res2->assertOk()
            ->assertJsonPath('data.assistant_message.is_fallback', false)
            ->assertJsonPath('data.assistant_message.matched_sources.0.category_slug', 'informasi-bangunan');

        // Query 3: Pertanyaan di luar Knowledge Base -> Fallback sopan
        $res3 = $this->withHeaders([
            'Origin' => 'https://bahrulmaghfirohmalang.or.id',
            'X-Widget-Session' => $sessionToken,
        ])->postJson('/api/v1/chat/messages', [
            'message' => 'Bagaimana resep masakan pizza Italia?',
        ]);
        $res3->assertOk()
            ->assertJsonPath('data.assistant_message.is_fallback', true)
            ->assertJsonPath('data.assistant_message.matched_sources', []);

        // Verifikasi kontinuitas riwayat & penyimpanan database (3 user + 3 assistant = 6 pesan)
        $historyRes = $this->withHeaders([
            'Origin' => 'https://bahrulmaghfirohmalang.or.id',
        ])->getJson("/api/v1/chat/conversations/{$sessionToken}");

        $historyRes->assertOk()
            ->assertJsonPath('data.message_count', 6)
            ->assertJsonCount(6, 'data.messages');
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
