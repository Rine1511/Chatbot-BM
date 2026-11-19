<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\KnowledgeDocument;
use App\Models\User;
use Database\Seeders\FaqCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_inactive_admin_cannot_access_category_management(): void
    {
        $this->get(route('admin.categories.index'))
            ->assertRedirect(route('admin.login'));

        $this->post(route('admin.categories.store'), ['name' => 'Uji Akses'])
            ->assertRedirect(route('admin.login'));

        $inactive = User::factory()->inactive()->create();

        $this->actingAs($inactive)
            ->get(route('admin.categories.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_list_create_update_and_delete_unused_category_with_sanitization(): void
    {
        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Profil Pondok')
            ->assertSee('Informasi Bangunan');

        // Create with XSS tags and automatic slug
        $storeResponse = $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => '<script>alert(1)</script>Beasiswa Santri',
            'description' => 'Informasi resmi <b>beasiswa</b> pondok.',
            'sort_order' => 10,
            'is_active' => '1',
        ]);

        $storeResponse->assertRedirect(route('admin.categories.index'));
        $storeResponse->assertSessionHas('status');

        $category = FaqCategory::query()->where('slug', 'alert1beasiswa-santri')->first()
            ?? FaqCategory::query()->where('name', 'like', '%Beasiswa Santri%')->firstOrFail();

        $this->assertStringNotContainsString('<script>', $category->name);
        $this->assertStringNotContainsString('<b>', (string) $category->description);
        $this->assertTrue($category->is_active);

        // Update category
        $updateResponse = $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => 'Beasiswa & Keringanan',
            'slug' => 'beasiswa-keringanan',
            'description' => 'Diperbarui oleh admin.',
            'sort_order' => 11,
            'is_active' => '0',
        ]);

        $updateResponse->assertRedirect(route('admin.categories.index'));

        $category->refresh();
        $this->assertSame('Beasiswa & Keringanan', $category->name);
        $this->assertSame('beasiswa-keringanan', $category->slug);
        $this->assertFalse($category->is_active);

        // Delete unused category succeeds
        $deleteResponse = $this->actingAs($admin)->delete(route('admin.categories.destroy', $category));
        $deleteResponse->assertRedirect(route('admin.categories.index'));
        $deleteResponse->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('faq_categories', ['id' => $category->id]);
    }

    public function test_category_counts_are_displayed_and_deletion_is_blocked_when_used_by_faq_or_document(): void
    {
        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();

        $categoryWithFaq = FaqCategory::query()->where('slug', 'pendidikan')->firstOrFail();
        $categoryWithDoc = FaqCategory::query()->where('slug', 'informasi-bangunan')->firstOrFail();

        Faq::query()->create([
            'category_id' => $categoryWithFaq->id,
            'question' => 'Apa saja jenjang pendidikan yang tersedia?',
            'answer' => 'Tersedia jenjang pendidikan diniyah dan formal sesuai informasi resmi.',
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        KnowledgeDocument::query()->create([
            'category_id' => $categoryWithDoc->id,
            'title' => 'Data Gedung Utama SIB-BM',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Informasi gedung dan ruangan terintegrasi dengan data SIB-BM resmi.',
            'processing_status' => KnowledgeDocument::STATUS_PENDING,
            'is_active' => true,
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Pendidikan')
            ->assertSee('Digunakan');

        // Blocked when FAQ exists
        $faqBlockResponse = $this->actingAs($admin)->delete(route('admin.categories.destroy', $categoryWithFaq));
        $faqBlockResponse->assertRedirect(route('admin.categories.index'));
        $faqBlockResponse->assertSessionHasErrors('category');
        $this->assertDatabaseHas('faq_categories', ['id' => $categoryWithFaq->id]);

        // Blocked when KnowledgeDocument exists
        $docBlockResponse = $this->actingAs($admin)->delete(route('admin.categories.destroy', $categoryWithDoc));
        $docBlockResponse->assertRedirect(route('admin.categories.index'));
        $docBlockResponse->assertSessionHasErrors('category');
        $this->assertDatabaseHas('faq_categories', ['id' => $categoryWithDoc->id]);
    }

    public function test_duplicate_category_name_or_slug_fails_validation(): void
    {
        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), [
                'name' => 'Profil Pondok',
                'slug' => 'profil-pondok',
                'sort_order' => 99,
            ]);

        $response->assertRedirect(route('admin.categories.create'));
        $response->assertSessionHasErrors(['name', 'slug']);
    }
}
