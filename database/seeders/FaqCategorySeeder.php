<?php

namespace Database\Seeders;

use App\Models\FaqCategory;
use Illuminate\Database\Seeder;

class FaqCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Profil Pondok',
                'slug' => 'profil-pondok',
                'description' => 'Sejarah, visi dan misi, profil lembaga, pengasuh atau pimpinan, serta informasi umum Pondok Pesantren Bahrul Maghfiroh.',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Pendidikan',
                'slug' => 'pendidikan',
                'description' => 'Jenjang pendidikan, program pendidikan, kegiatan pembelajaran, dan informasi lembaga pendidikan.',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Pendaftaran',
                'slug' => 'pendaftaran',
                'description' => 'Prosedur pendaftaran, persyaratan, jadwal penerimaan santri baru, dan informasi biaya resmi apabila tersedia.',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Kegiatan',
                'slug' => 'kegiatan',
                'description' => 'Kegiatan santri harian, kegiatan keagamaan, program unggulan, dan aktivitas pondok.',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Fasilitas',
                'slug' => 'fasilitas',
                'description' => 'Sarana dan prasarana, fasilitas pendidikan, serta fasilitas umum lingkungan pondok.',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Informasi Bangunan',
                'slug' => 'informasi-bangunan',
                'description' => 'Data bangunan, fungsi, lokasi, kapasitas, dan fasilitas gedung yang terintegrasi dengan SIB-BM.',
                'sort_order' => 6,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            FaqCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
