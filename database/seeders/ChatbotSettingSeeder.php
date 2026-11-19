<?php

namespace Database\Seeders;

use App\Models\ChatbotSetting;
use Illuminate\Database\Seeder;

class ChatbotSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'setting_key' => 'chatbot_name',
                'setting_value' => 'Asisten Virtual Bahrul Maghfiroh',
                'setting_group' => 'general',
            ],
            [
                'setting_key' => 'welcome_message',
                'setting_value' => "Assalamu'alaikum warahmatullahi wabarakatuh. Selamat datang di layanan informasi Pondok Pesantren Bahrul Maghfiroh Kota Malang. Ada yang dapat kami bantu terkait profil pondok, pendidikan, pendaftaran, kegiatan, fasilitas, atau informasi bangunan?",
                'setting_group' => 'general',
            ],
            [
                'setting_key' => 'system_prompt',
                'setting_value' => 'Anda adalah Asisten Virtual resmi Pondok Pesantren Bahrul Maghfiroh Kota Malang. Jawab pertanyaan pengunjung hanya berdasarkan konteks knowledge base resmi dan data SIB-BM yang diberikan. Jangan mengarang fakta, nama, jadwal, biaya, atau kebijakan pondok. Gunakan bahasa Indonesia yang sopan, natural, ringkas, mudah dibaca, dan sertakan tautan sumber resmi apabila tersedia.',
                'setting_group' => 'prompt',
            ],
            [
                'setting_key' => 'fallback_message',
                'setting_value' => 'Mohon maaf, informasi spesifik mengenai hal tersebut belum tersedia pada basis pengetahuan resmi kami. Silakan menghubungi pihak sekretariat atau kontak resmi Pondok Pesantren Bahrul Maghfiroh melalui website https://bahrulmaghfirohmalang.or.id/ untuk informasi lebih lanjut.',
                'setting_group' => 'prompt',
            ],
            [
                'setting_key' => 'allowed_origins',
                'setting_value' => 'https://bahrulmaghfirohmalang.or.id,https://www.bahrulmaghfirohmalang.or.id',
                'setting_group' => 'integration',
            ],
            [
                'setting_key' => 'ai_llm_driver',
                'setting_value' => (string) config('ai.llm.driver', 'gemini'),
                'setting_group' => 'ai',
            ],
            [
                'setting_key' => 'ai_llm_model',
                'setting_value' => (string) config('ai.llm.model', 'gemini-2.5-flash'),
                'setting_group' => 'ai',
            ],
            [
                'setting_key' => 'ai_embedding_model',
                'setting_value' => (string) config('ai.embedding.model', 'gemini-embedding-001'),
                'setting_group' => 'ai',
            ],
            [
                'setting_key' => 'ai_vector_driver',
                'setting_value' => (string) config('ai.vector.driver', 'qdrant'),
                'setting_group' => 'ai',
            ],
        ];

        foreach ($defaults as $item) {
            ChatbotSetting::query()->updateOrCreate(
                ['setting_key' => $item['setting_key']],
                [
                    'setting_value' => $item['setting_value'],
                    'setting_group' => $item['setting_group'],
                    'is_encrypted' => false,
                ]
            );
        }
    }
}
