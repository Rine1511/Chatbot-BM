<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login_when_accessing_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_login_page_renders_successfully(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertOk();
        $response->assertSee('Panel Admin Asisten Virtual');
        $response->assertSee('Masuk ke Dashboard');
    }

    public function test_active_admin_can_login_view_dashboard_and_logout_with_session_regeneration(): void
    {
        $this->seed(DatabaseSeeder::class);

        $password = 'StrongTestPass#9876';
        $admin = User::factory()->superAdmin()->create([
            'email' => 'audit-admin@bahrulmaghfirohmalang.or.id',
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $this->assertSame('bcrypt', Hash::info($admin->password)['algoName']);

        $initialSessionId = $this->app['session']->getId();

        $loginResponse = $this->post(route('admin.login.attempt'), [
            'email' => $admin->email,
            'password' => $password,
        ]);

        $loginResponse->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertNotSame($initialSessionId, $this->app['session']->getId());

        $dashboardResponse = $this->get(route('admin.dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Asisten Virtual Bahrul Maghfiroh');
        $dashboardResponse->assertSee('Profil Pondok');
        $dashboardResponse->assertSee('Informasi Bangunan');

        $moduleResponse = $this->get(route('admin.modules.show', 'settings'));
        $moduleResponse->assertOk();
        $moduleResponse->assertSee('Pengaturan Chatbot');

        $faqIndexResponse = $this->get(route('admin.faqs.index'));
        $faqIndexResponse->assertOk();
        $faqIndexResponse->assertSee('Daftar FAQ Pondok Pesantren');

        $admin->refresh();
        $this->assertNotNull($admin->last_login_at);

        $logoutResponse = $this->post(route('admin.logout'));
        $logoutResponse->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_login_fails_with_invalid_credentials_and_triggers_rate_limiter(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::factory()->create([
            'email' => 'ratelimit-admin@bahrulmaghfirohmalang.or.id',
            'password' => Hash::make('ValidStrongPass#123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->from(route('admin.login'))->post(route('admin.login.attempt'), [
                'email' => $admin->email,
                'password' => 'WrongPassword123',
            ]);
            $response->assertRedirect(route('admin.login'));
            $response->assertSessionHasErrors('email');
        }

        $throttledResponse = $this->from(route('admin.login'))->post(route('admin.login.attempt'), [
            'email' => $admin->email,
            'password' => 'ValidStrongPass#123',
        ]);

        $throttledResponse->assertRedirect(route('admin.login'));
        $throttledResponse->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Terlalu banyak percobaan login',
            session('errors')->first('email')
        );
        $this->assertGuest();
    }

    public function test_inactive_admin_cannot_login_or_access_dashboard(): void
    {
        $inactiveAdmin = User::factory()->inactive()->create([
            'password' => Hash::make('RahasiaKuat#12345'),
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->from(route('admin.login'))->post(route('admin.login.attempt'), [
            'email' => $inactiveAdmin->email,
            'password' => 'RahasiaKuat#12345',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_re_running_admin_seeder_does_not_overwrite_existing_admin_password(): void
    {
        $this->seed(AdminUserSeeder::class);

        $seededAdmin = User::query()->firstOrFail();
        $customPassword = 'UpdatedAdminPassword#2026';
        $seededAdmin->forceFill(['password' => Hash::make($customPassword)])->save();

        $this->seed(AdminUserSeeder::class);

        $seededAdmin->refresh();
        $this->assertTrue(Hash::check($customPassword, $seededAdmin->password));
    }

    public function test_dashboard_does_not_expose_api_keys_in_html(): void
    {
        config()->set('ai.providers.gemini.api_key', 'SECRET_GEMINI_TOKEN_TEST_999');
        config()->set('ai.providers.openai_compatible.api_key', 'SECRET_OPENAI_TOKEN_TEST_999');
        config()->set('ai.vector.qdrant.api_key', 'SECRET_QDRANT_TOKEN_TEST_999');

        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertDontSee('SECRET_GEMINI_TOKEN_TEST_999');
        $response->assertDontSee('SECRET_OPENAI_TOKEN_TEST_999');
        $response->assertDontSee('SECRET_QDRANT_TOKEN_TEST_999');
        $response->assertSee('Terkonfigurasi di server');
    }
}
