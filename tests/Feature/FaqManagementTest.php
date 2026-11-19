<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\User;
use Database\Seeders\FaqCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_faq_endpoints(): void
    {
        $this->get(route('admin.faqs.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.faqs.store'), [])->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_crud_faq_search_filter_and_toggle_status(): void
    {
        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $registrationCat = FaqCategory::query()->where('slug', 'pendaftaran')->firstOrFail();
        $facilityCat = FaqCategory::query()->where('slug', 'fasilitas')->firstOrFail();

        // Create FAQ with keywords and question variations
        $createResponse = $this->actingAs($admin)->post(route('admin.faqs.store'), [
            'category_id' => $registrationCat->id,
            'question' => 'Bagaimana prosedur pendaftaran santri baru?',
            'answer' => 'Pengunjung dapat mengisi formulir pendaftaran melalui sekretariat resmi atau tautan resmi pondok.',
            'keywords' => 'pendaftaran, PSB, santri baru',
            'question_variations' => "Cara daftar santri\nAlur pendaftaran online",
            'source_url' => 'https://bahrulmaghfirohmalang.or.id/pendaftaran',
            'is_featured' => '1',
            'is_active' => '1',
        ]);

        $createResponse->assertRedirect(route('admin.faqs.index'));
        $faq = Faq::query()->firstOrFail();
        $this->assertSame(Faq::EMBEDDING_PENDING, $faq->embedding_status);
        $this->assertSame($admin->id, $faq->created_by);
        $this->assertCount(2, $faq->variationsList());

        // Second FAQ for filter testing
        Faq::query()->create([
            'category_id' => $facilityCat->id,
            'question' => 'Apakah tersedia fasilitas asrama?',
            'answer' => 'Informasi asrama tercantum pada halaman fasilitas resmi.',
            'keywords' => 'asrama, gedung',
            'is_featured' => false,
            'is_active' => false,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        // Search by variation/keyword
        $this->actingAs($admin)
            ->get(route('admin.faqs.index', ['q' => 'Alur pendaftaran']))
            ->assertOk()
            ->assertSee('Bagaimana prosedur pendaftaran santri baru?')
            ->assertDontSee('Apakah tersedia fasilitas asrama?');

        // Filter by category and status
        $this->actingAs($admin)
            ->get(route('admin.faqs.index', ['category_id' => $facilityCat->id, 'status' => 'inactive']))
            ->assertOk()
            ->assertSee('Apakah tersedia fasilitas asrama?')
            ->assertDontSee('Bagaimana prosedur pendaftaran santri baru?');

        // Update FAQ
        $this->actingAs($admin)->put(route('admin.faqs.update', $faq), [
            'category_id' => $registrationCat->id,
            'question' => 'Bagaimana prosedur pendaftaran santri baru tahun ini?',
            'answer' => 'Silakan mengikuti panduan pendaftaran resmi yang dipublikasikan oleh panitia.',
            'keywords' => 'PSB, daftar ulang',
            'question_variations' => "Cara daftar ulang\nPanduan PSB",
            'source_url' => 'https://bahrulmaghfirohmalang.or.id/psb',
            'is_featured' => '0',
            'is_active' => '1',
        ])->assertRedirect(route('admin.faqs.index'));

        $faq->refresh();
        $this->assertFalse($faq->is_featured);
        $this->assertSame('PSB, daftar ulang', $faq->keywords);

        // Toggle status active -> inactive
        $this->actingAs($admin)
            ->patch(route('admin.faqs.toggle-status', $faq))
            ->assertRedirect(route('admin.faqs.index'));

        $faq->refresh();
        $this->assertFalse($faq->is_active);

        // Delete FAQ
        $this->actingAs($admin)
            ->delete(route('admin.faqs.destroy', $faq))
            ->assertRedirect(route('admin.faqs.index'));

        $this->assertDatabaseMissing('faqs', ['id' => $faq->id]);
    }

    public function test_inactive_faqs_and_faqs_in_inactive_categories_are_excluded_from_chatbot_scope(): void
    {
        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();

        $activeCategory = FaqCategory::query()->where('slug', 'profil-pondok')->firstOrFail();
        $inactiveCategory = FaqCategory::query()->where('slug', 'kegiatan')->firstOrFail();
        $inactiveCategory->update(['is_active' => false]);

        $eligibleFaq = Faq::query()->create([
            'category_id' => $activeCategory->id,
            'question' => 'Di mana alamat Pondok Pesantren Bahrul Maghfiroh?',
            'answer' => 'Pondok Pesantren Bahrul Maghfiroh berlokasi di Kota Malang.',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $inactiveFaq = Faq::query()->create([
            'category_id' => $activeCategory->id,
            'question' => 'FAQ nonaktif yang tidak boleh muncul',
            'answer' => 'Jawaban nonaktif.',
            'is_active' => false,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $faqInInactiveCategory = Faq::query()->create([
            'category_id' => $inactiveCategory->id,
            'question' => 'FAQ dalam kategori nonaktif',
            'answer' => 'Tidak boleh dipakai chatbot.',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $chatbotIds = Faq::query()->forChatbot()->pluck('id')->all();

        $this->assertContains($eligibleFaq->id, $chatbotIds);
        $this->assertNotContains($inactiveFaq->id, $chatbotIds);
        $this->assertNotContains($faqInInactiveCategory->id, $chatbotIds);
    }

    public function test_faq_validation_rejects_invalid_url_and_short_inputs(): void
    {
        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $cat = FaqCategory::query()->firstOrFail();

        $response = $this->actingAs($admin)
            ->from(route('admin.faqs.create'))
            ->post(route('admin.faqs.store'), [
                'category_id' => $cat->id,
                'question' => 'Hai',
                'answer' => 'Ya',
                'source_url' => 'javascript:alert(1)',
            ]);

        $response->assertRedirect(route('admin.faqs.create'));
        $response->assertSessionHasErrors(['question', 'answer', 'source_url']);
    }
}
