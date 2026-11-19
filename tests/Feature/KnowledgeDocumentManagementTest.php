<?php

namespace Tests\Feature;

use App\Models\FaqCategory;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\User;
use Database\Seeders\FaqCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KnowledgeDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_knowledge_base_or_download_files(): void
    {
        $this->seed(FaqCategorySeeder::class);
        $cat = FaqCategory::query()->firstOrFail();
        $doc = KnowledgeDocument::query()->create([
            'category_id' => $cat->id,
            'title' => 'Dokumen Uji Privat',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Konten teks dokumen uji untuk memastikan tamu tidak dapat mengakses.',
        ]);

        $this->get(route('admin.knowledge.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.knowledge.show', $doc))->assertRedirect(route('admin.login'));
        $this->get(route('admin.knowledge.download', $doc))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_crud_text_knowledge_document(): void
    {
        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $profileCat = FaqCategory::query()->where('slug', 'profil-pondok')->firstOrFail();

        $createResponse = $this->actingAs($admin)->post(route('admin.knowledge.store'), [
            'category_id' => $profileCat->id,
            'title' => 'Profil Singkat Pondok Pesantren',
            'description' => 'Ringkasan sejarah dan visi misi pondok.',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'source_label' => 'Halaman Profil Resmi',
            'source_url' => 'https://bahrulmaghfirohmalang.or.id/profil',
            'raw_content' => 'Pondok Pesantren Bahrul Maghfiroh Kota Malang menyelenggarakan pendidikan dan pembinaan santri.',
            'is_active' => '1',
        ]);

        $document = KnowledgeDocument::query()->firstOrFail();
        $createResponse->assertRedirect(route('admin.knowledge.show', $document));
        $this->assertSame(KnowledgeDocument::STATUS_PENDING, $document->processing_status);
        $this->assertSame(0, $document->chunk_count);

        $this->actingAs($admin)
            ->get(route('admin.knowledge.show', $document))
            ->assertOk()
            ->assertSee('Profil Singkat Pondok Pesantren')
            ->assertSee('Halaman Profil Resmi')
            ->assertSee('Belum pernah diindeks')
            ->assertSee('Proses & Indeks Sekarang');

        // Edit document
        $this->actingAs($admin)
            ->put(route('admin.knowledge.update', $document), [
                'category_id' => $profileCat->id,
                'title' => 'Profil & Sejarah Pondok Pesantren',
                'description' => 'Pembaruan metadata profil.',
                'source_type' => KnowledgeDocument::SOURCE_TEXT,
                'source_label' => 'Profil Resmi 2026',
                'raw_content' => 'Teks pembaruan profil lengkap untuk diproses menjadi chunk pada Tahap 4.',
                'is_active' => '0',
            ])
            ->assertRedirect(route('admin.knowledge.show', $document));

        $document->refresh();
        $this->assertSame('Profil & Sejarah Pondok Pesantren', $document->title);
        $this->assertFalse($document->is_active);
    }

    public function test_admin_can_upload_download_switch_mode_and_delete_document_with_chunk_cascade(): void
    {
        Storage::fake('local');
        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $buildingCat = FaqCategory::query()->where('slug', 'informasi-bangunan')->firstOrFail();

        $txtFile = UploadedFile::fake()->createWithContent(
            'daftar-gedung.txt',
            "Gedung A: Ruang Kelas Utama\nGedung B: Asrama Santri Putra"
        );

        $storeResponse = $this->actingAs($admin)->post(route('admin.knowledge.store'), [
            'category_id' => $buildingCat->id,
            'title' => 'Ringkasan Daftar Gedung SIB-BM',
            'source_type' => KnowledgeDocument::SOURCE_FILE,
            'source_label' => 'Ekspor SIB-BM',
            'document_file' => $txtFile,
            'is_active' => '1',
        ]);

        $document = KnowledgeDocument::query()->firstOrFail();
        $storeResponse->assertRedirect(route('admin.knowledge.show', $document));
        $this->assertNotNull($document->file_path);
        $this->assertSame('daftar-gedung.txt', $document->original_filename);
        $this->assertStringContainsString('Gedung A: Ruang Kelas Utama', (string) $document->raw_content);
        Storage::disk('local')->assertExists($document->file_path);

        // Admin download succeeds
        $this->actingAs($admin)
            ->get(route('admin.knowledge.download', $document))
            ->assertOk();

        // Replace with a PDF upload and ensure old file is removed
        $oldPath = $document->file_path;
        $pdfFile = UploadedFile::fake()->create('panduan-gedung.pdf', 512, 'application/pdf');

        $this->actingAs($admin)
            ->put(route('admin.knowledge.update', $document), [
                'category_id' => $buildingCat->id,
                'title' => 'Panduan Gedung PDF',
                'source_type' => KnowledgeDocument::SOURCE_FILE,
                'source_label' => 'PDF Resmi SIB-BM',
                'document_file' => $pdfFile,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.knowledge.show', $document));

        $document->refresh();
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertSame('panduan-gedung.pdf', $document->original_filename);

        // Attach a placeholder chunk (Stage 4 readiness) and verify deletion cascades & removes file
        KnowledgeChunk::query()->create([
            'document_id' => $document->id,
            'chunk_index' => 0,
            'content' => 'Potongan konten uji Tahap 4.',
            'content_hash' => hash('sha256', 'Potongan konten uji Tahap 4.'),
            'token_estimate' => 6,
            'embedding_status' => 'pending',
        ]);

        $newFilePath = $document->file_path;

        $this->actingAs($admin)
            ->delete(route('admin.knowledge.destroy', $document))
            ->assertRedirect(route('admin.knowledge.index'));

        $this->assertDatabaseMissing('knowledge_documents', ['id' => $document->id]);
        $this->assertDatabaseCount('knowledge_chunks', 0);
        Storage::disk('local')->assertMissing($newFilePath);
    }

    public function test_upload_validation_rejects_disallowed_extensions_and_oversized_files(): void
    {
        Storage::fake('local');
        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $cat = FaqCategory::query()->firstOrFail();

        // Executable / PHP upload must be rejected
        $phpFile = UploadedFile::fake()->createWithContent('malicious.php', '<?php echo "bad";');

        $this->actingAs($admin)
            ->from(route('admin.knowledge.create'))
            ->post(route('admin.knowledge.store'), [
                'category_id' => $cat->id,
                'title' => 'Percobaan File Berbahaya',
                'source_type' => KnowledgeDocument::SOURCE_FILE,
                'document_file' => $phpFile,
            ])
            ->assertRedirect(route('admin.knowledge.create'))
            ->assertSessionHasErrors('document_file');

        // File > 10 MB (10240 KB) must be rejected
        $oversizedPdf = UploadedFile::fake()->create('terlalu-besar.pdf', 11000, 'application/pdf');

        $this->actingAs($admin)
            ->from(route('admin.knowledge.create'))
            ->post(route('admin.knowledge.store'), [
                'category_id' => $cat->id,
                'title' => 'File Terlalu Besar',
                'source_type' => KnowledgeDocument::SOURCE_FILE,
                'document_file' => $oversizedPdf,
            ])
            ->assertRedirect(route('admin.knowledge.create'))
            ->assertSessionHasErrors('document_file');

        $this->assertDatabaseCount('knowledge_documents', 0);
    }

    public function test_search_and_filter_work_on_knowledge_base_index(): void
    {
        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $cat = FaqCategory::query()->where('slug', 'pendidikan')->firstOrFail();

        KnowledgeDocument::query()->create([
            'category_id' => $cat->id,
            'title' => 'Kurikulum Madrasah Diniyah',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Materi resmi kurikulum madrasah diniyah pondok pesantren.',
            'processing_status' => KnowledgeDocument::STATUS_PENDING,
            'is_active' => true,
            'uploaded_by' => $admin->id,
        ]);

        KnowledgeDocument::query()->create([
            'category_id' => $cat->id,
            'title' => 'Jadwal Ekstrakurikuler Santri',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Jadwal kegiatan ekstrakurikuler mingguan santri putra dan putri.',
            'processing_status' => KnowledgeDocument::STATUS_INDEXED,
            'is_active' => false,
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.knowledge.index', [
                'q' => 'Kurikulum',
                'category_id' => $cat->id,
                'processing_status' => 'pending',
                'status' => 'active',
            ]))
            ->assertOk()
            ->assertSee('Kurikulum Madrasah Diniyah')
            ->assertDontSee('Jadwal Ekstrakurikuler Santri');
    }
}
