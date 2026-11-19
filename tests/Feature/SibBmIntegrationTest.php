<?php

namespace Tests\Feature;

use App\Exceptions\SibBmException;
use App\Models\FaqCategory;
use App\Models\KnowledgeDocument;
use App\Models\SibBmBuildingCache;
use App\Models\User;
use App\Services\SibBm\SibBmClient;
use App\Services\SibBm\SibBmSyncService;
use Database\Seeders\ChatbotSettingSeeder;
use Database\Seeders\FaqCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SibBmIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sibbm_client_fetches_from_rest_api_and_redacts_api_key_on_error(): void
    {
        config()->set('sibbm.enabled', true);
        config()->set('sibbm.base_url', 'https://sibbm.bahrulmaghfirohmalang.or.id/api');
        config()->set('sibbm.buildings_endpoint', '/buildings');
        config()->set('sibbm.api_key', 'SECRET_SIBBM_TOKEN_123');

        Http::fake([
            'https://sibbm.bahrulmaghfirohmalang.or.id/api/buildings' => Http::sequence()
                ->push([
                    'data' => [
                        [
                            'external_id' => 'BM-GD-01',
                            'name' => 'Gedung Pusat Al-Azhar',
                            'category' => 'Pendidikan',
                            'location' => 'Komplek Utara Pondok',
                            'function_desc' => 'Ruang kelas madrasah dan kantor pengasuh',
                            'description' => 'Gedung tiga lantai untuk kegiatan belajar mengajar.',
                            'latitude' => -7.934561,
                            'longitude' => 112.601234,
                            'land_area' => 1450.5,
                            'building_area' => 980.0,
                            'capacity' => 450,
                            'facilities' => ['AC', 'Proyektor', 'Mushola'],
                            'detail_url' => 'https://sibbm.bahrulmaghfirohmalang.or.id/gedung/BM-GD-01',
                        ],
                    ],
                ], 200)
                ->push(['message' => 'Unauthorized SECRET_SIBBM_TOKEN_123'], 401),
        ]);

        $client = new SibBmClient;
        $result = $client->fetchBuildings('api');

        $this->assertSame('api', $result['source']);
        $this->assertCount(1, $result['buildings']);
        $this->assertSame('Gedung Pusat Al-Azhar', $result['buildings'][0]['name']);
        $this->assertSame(450, $result['buildings'][0]['capacity']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://sibbm.bahrulmaghfirohmalang.or.id/api/buildings'
                && $request->hasHeader('Authorization', 'Bearer SECRET_SIBBM_TOKEN_123');
        });

        try {
            $client->fetchBuildings('api');
            $this->fail('Seharusnya melempar SibBmException.');
        } catch (SibBmException $e) {
            $this->assertStringNotContainsString('SECRET_SIBBM_TOKEN_123', $e->getMessage());
            $this->assertStringContainsString('[REDACTED]', $e->getMessage());
        }
    }

    public function test_sibbm_temporary_local_json_integration_syncs_idempotently_without_duplicates(): void
    {
        config()->set('sibbm.enabled', false);
        config()->set('ai.vector.driver', 'mysql');
        config()->set('ai.providers.gemini.api_key', 'TEST_SIBBM_GEMINI_KEY');

        $this->seed([FaqCategorySeeder::class, ChatbotSettingSeeder::class]);

        $tempPath = storage_path('app/test_sibbm_snapshot.json');
        file_put_contents($tempPath, json_encode([
            [
                'external_id' => 'GD-ASRAMA-01',
                'name' => 'Asrama Putra Al-Manshur',
                'category' => 'Asrama',
                'location' => 'Sisi Timur Pondok',
                'function_desc' => 'Hunian santri putra tingkat Tsanawiyah',
                'description' => 'Terdiri atas 18 kamar santri dan aula kajian malam.',
                'land_area' => 900,
                'building_area' => 720,
                'capacity' => 240,
                'facilities' => ['Kamar Tidur', 'Kamar Mandi', 'Aula'],
                'detail_url' => 'https://sibbm.bahrulmaghfirohmalang.or.id/gedung/GD-ASRAMA-01',
            ],
        ], JSON_THROW_ON_ERROR));

        $vec = $this->normalizedVector(768, [0 => 1.0]);
        Http::fake([
            'generativelanguage.googleapis.com/*:embedContent' => Http::response([
                'embedding' => ['values' => $vec],
            ], 200),
        ]);

        try {
            $syncService = app(SibBmSyncService::class);

            // 1. Sinkronisasi pertama
            $first = $syncService->syncFromSource('auto', null, true, false, $tempPath);
            $this->assertSame('local_json', $first['source']);
            $this->assertSame(1, $first['created']);
            $this->assertSame(1, $first['indexed']);
            $this->assertDatabaseCount('sib_bm_buildings_cache', 1);
            $this->assertDatabaseCount('knowledge_documents', 1);

            // 2. Sinkronisasi ulang data yang sama -> unchanged, tidak menduplikasi
            $second = $syncService->syncFromSource('auto', null, true, false, $tempPath);
            $this->assertSame(0, $second['created']);
            $this->assertSame(0, $second['updated']);
            $this->assertSame(1, $second['unchanged']);
            $this->assertDatabaseCount('sib_bm_buildings_cache', 1);
            $this->assertDatabaseCount('knowledge_documents', 1);

            // 3. Data bangunan berubah di SIB-BM -> memperbarui tanpa duplikasi & re-index
            file_put_contents($tempPath, json_encode([
                [
                    'external_id' => 'GD-ASRAMA-01',
                    'name' => 'Asrama Putra Al-Manshur',
                    'category' => 'Asrama',
                    'location' => 'Sisi Timur Pondok',
                    'function_desc' => 'Hunian santri putra tingkat Tsanawiyah & Aliyah',
                    'description' => 'Telah direnovasi menjadi 24 kamar santri.',
                    'land_area' => 950,
                    'building_area' => 840,
                    'capacity' => 300,
                    'facilities' => ['Kamar Tidur', 'Kamar Mandi', 'Aula', 'Wi-Fi'],
                    'detail_url' => 'https://sibbm.bahrulmaghfirohmalang.or.id/gedung/GD-ASRAMA-01',
                ],
            ], JSON_THROW_ON_ERROR));

            $third = $syncService->syncFromSource('auto', null, true, false, $tempPath);
            $this->assertSame(0, $third['created']);
            $this->assertSame(1, $third['updated']);
            $this->assertSame(1, $third['indexed']);
            $this->assertDatabaseCount('sib_bm_buildings_cache', 1);
            $this->assertDatabaseCount('knowledge_documents', 1);

            $cache = SibBmBuildingCache::query()->firstOrFail();
            $this->assertSame(300, $cache->capacity);
            $doc = KnowledgeDocument::query()->firstOrFail();
            $this->assertStringContainsString('300 orang', (string) $doc->raw_content);
            $this->assertStringContainsString('Sisi Timur Pondok', (string) $doc->raw_content);
        } finally {
            @unlink($tempPath);
        }
    }

    public function test_chatbot_rag_answers_building_query_from_sibbm_and_falls_back_when_absent(): void
    {
        config()->set('ai.vector.driver', 'mysql');
        config()->set('ai.providers.gemini.api_key', 'TEST_SIBBM_RAG_KEY');
        config()->set('ai.rag.similarity_threshold', 0.65);

        $this->seed([FaqCategorySeeder::class, ChatbotSettingSeeder::class]);
        $admin = User::factory()->create();

        $buildingVec = $this->normalizedVector(768, [0 => 1.0]);
        $matchQueryVec = $this->normalizedVector(768, [0 => 1.0]);
        $missQueryVec = $this->normalizedVector(768, [3 => 1.0]);

        Http::fake([
            'generativelanguage.googleapis.com/*:embedContent' => Http::sequence()
                ->push(['embedding' => ['values' => $buildingVec]], 200)
                ->push(['embedding' => ['values' => $matchQueryVec]], 200)
                ->push(['embedding' => ['values' => $missQueryVec]], 200),
            'generativelanguage.googleapis.com/*:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => 'Masjid Jami Bahrul Maghfiroh memiliki luas bangunan 1.200 m2 dan berkapasitas 1.500 jamaah untuk kegiatan ibadah dan kajian santri.',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Impor melalui endpoint Admin SIB-BM
        $jsonPayload = json_encode([
            [
                'external_id' => 'BM-MSJ-01',
                'name' => 'Masjid Jami Bahrul Maghfiroh',
                'category' => 'Ibadah',
                'location' => 'Area Tengah Kompleks Pondok',
                'function_desc' => 'Pusat shalat berjamaah dan kajian kitab',
                'land_area' => 1800,
                'building_area' => 1200,
                'capacity' => 1500,
                'facilities' => ['Sound System', 'Tempat Wudhu Luas', 'Perpustakaan Mini'],
                'detail_url' => 'https://sibbm.bahrulmaghfirohmalang.or.id/gedung/BM-MSJ-01',
            ],
        ], JSON_THROW_ON_ERROR);

        $this->actingAs($admin)
            ->post(route('admin.sibbm.import'), ['buildings_json' => $jsonPayload])
            ->assertRedirect(route('admin.sibbm.index'));

        $this->actingAs($admin)
            ->get(route('admin.sibbm.index'))
            ->assertOk()
            ->assertSee('Masjid Jami Bahrul Maghfiroh');

        // Pertanyaan tentang bangunan yang tersedia di SIB-BM
        $sessionToken = $this->postJson('/api/v1/chat/conversations')->json('data.session_token');

        $okRes = $this->postJson('/api/v1/chat/messages', [
            'session_token' => $sessionToken,
            'message' => 'Berapa kapasitas dan luas bangunan Masjid Jami Bahrul Maghfiroh?',
        ]);

        $okRes->assertOk()
            ->assertJsonPath('data.assistant_message.is_fallback', false)
            ->assertJsonPath('data.assistant_message.matched_sources.0.source_url', 'https://sibbm.bahrulmaghfirohmalang.or.id/gedung/BM-MSJ-01');

        // Pertanyaan tentang gedung yang tidak ada di data SIB-BM -> fallback sopan
        $missRes = $this->postJson('/api/v1/chat/messages', [
            'session_token' => $sessionToken,
            'message' => 'Bagaimana spesifikasi Gedung Observatorium Antariksa?',
        ]);

        $missRes->assertOk()
            ->assertJsonPath('data.assistant_message.is_fallback', true)
            ->assertJsonPath('data.assistant_message.matched_sources', []);
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
