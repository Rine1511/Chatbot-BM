<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    private const DISALLOWED_PASSWORDS = [
        'password',
        'admin123',
        '12345678',
        'AdminBM#2026!',
        'change_me',
        'changeme',
    ];

    public function run(): void
    {
        $email = trim((string) env('ADMIN_SEED_EMAIL', 'admin@bahrulmaghfirohmalang.or.id'));
        $name = trim((string) env('ADMIN_SEED_NAME', 'Administrator Pondok'));

        $existingUser = User::query()->where('email', $email)->first();

        // Jangan menimpa kata sandi akun admin yang sudah ada saat seeder dijalankan ulang.
        if ($existingUser) {
            return;
        }

        $password = trim((string) env('ADMIN_SEED_PASSWORD', ''));

        if ($password === '') {
            if (app()->isProduction()) {
                throw new RuntimeException(
                    'ADMIN_SEED_PASSWORD wajib diisi pada .env dengan kata sandi kuat (minimal 12 karakter) sebelum menjalankan seeder di produksi.'
                );
            }

            $password = Str::password(16);
            $this->command?->warn("ADMIN_SEED_PASSWORD kosong. Kata sandi acak sementara untuk {$email}: {$password}");
        }

        if (mb_strlen($password) < 12 || in_array(mb_strtolower($password), array_map('mb_strtolower', self::DISALLOWED_PASSWORDS), true)) {
            throw new RuntimeException(
                'ADMIN_SEED_PASSWORD tidak memenuhi standar keamanan (minimal 12 karakter dan bukan kata sandi default/lemah).'
            );
        }

        User::query()->create([
            'name' => $name !== '' ? $name : 'Administrator Pondok',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
