# Panduan Integrasi REST API & Pemasangan Embed Widget

Aplikasi **Asisten Virtual Bahrul Maghfiroh** bersifat standalone sehingga website utama pondok (`https://bahrulmaghfirohmalang.or.id/`) cukup memuat satu skrip JavaScript tanpa mengubah struktur backend website utama.

---

## 1. Pemasangan Embed Widget pada Website Utama

Tambahkan satu baris tag `<script>` berikut tepat sebelum penutup `</body>` pada template website resmi Pondok Pesantren Bahrul Maghfiroh:

```html
<script
  src="https://chatbot.bahrulmaghfirohmalang.or.id/widget/bm-chatbot.js"
  data-api-base="https://chatbot.bahrulmaghfirohmalang.or.id"
  data-title="Asisten Virtual Bahrul Maghfiroh"
  defer
></script>
```

### Karakteristik Widget
- **CSS Terisolasi (Shadow DOM):** Seluruh tampilan widget dirender di dalam *Shadow DOM* sehingga gaya CSS website utama (WordPress, Bootstrap, Elementor, Tailwind, dll.) tidak saling bertabrakan.
- **Responsif Desktop & Mobile:** Tombol mengambang muncul di pojok kanan bawah dan jendela percakapan otomatis menyesuaikan layar ponsel.
- **Kontinuitas Sesi:** `session_token` disimpan di `localStorage` pengunjung (`bm_widget_session_token`) agar riwayat percakapan tetap berlanjut saat berpindah halaman.
- **Keamanan Origin & CORS:** Permintaan dari domain selain yang terdaftar pada `ALLOWED_WIDGET_ORIGINS` (`.env`) dan `allowed_origins` (`chatbot_settings`) akan ditolak dengan HTTP `403`.

---

## 2. Spesifikasi REST API v1

Base URL: `https://chatbot.bahrulmaghfirohmalang.or.id/api/v1/chat`

### A. Memulai Percakapan Baru
- **Endpoint:** `POST /api/v1/chat/conversations`
- **Rate Limit:** 15 permintaan/menit per IP
- **Request JSON (opsional):**
  ```json
  {
    "origin_domain": "bahrulmaghfirohmalang.or.id",
    "visitor_id": "opsional-id-anonim"
  }
  ```
- **Response `201 Created`:**
  ```json
  {
    "status": "success",
    "data": {
      "conversation_id": 1,
      "session_token": "64-karakter-hex",
      "chatbot_name": "Asisten Virtual Bahrul Maghfiroh",
      "welcome_message": "Assalamu'alaikum warahmatullahi wabarakatuh...",
      "origin_domain": "bahrulmaghfirohmalang.or.id",
      "started_at": "2026-11-18T10:00:00+07:00"
    }
  }
  ```

### B. Mengirim Pesan & Mendapatkan Jawaban RAG
- **Endpoint:** `POST /api/v1/chat/messages`
- **Header Opsional:** `X-Widget-Session: <session_token>`
- **Rate Limit:** `CHAT_RATE_LIMIT_PER_MINUTE` (default 20/menit per sesi+IP, 120/jam per IP)
- **Request JSON:**
  ```json
  {
    "session_token": "64-karakter-hex",
    "message": "Bagaimana prosedur pendaftaran santri baru?",
    "category_slug": null
  }
  ```
- **Response `200 OK`:**
  ```json
  {
    "status": "success",
    "data": {
      "conversation": {
        "id": 1,
        "session_token": "64-karakter-hex",
        "message_count": 2,
        "last_message_at": "2026-11-18T10:00:05+07:00"
      },
      "user_message": {
        "id": 1,
        "sender_type": "user",
        "content": "Bagaimana prosedur pendaftaran santri baru?"
      },
      "assistant_message": {
        "id": 2,
        "sender_type": "assistant",
        "content": "...",
        "is_fallback": false,
        "matched_sources": [
          {
            "source_type": "faq",
            "title": "Prosedur Pendaftaran Santri Baru",
            "category": "Pendaftaran",
            "source_url": "https://bahrulmaghfirohmalang.or.id/pendaftaran",
            "score": 0.9124
          }
        ],
        "response_time_ms": 420
      }
    }
  }
  ```

### C. Membaca Riwayat Percakapan
- **Endpoint:** `GET /api/v1/chat/conversations/{sessionToken}`
- **Rate Limit:** 60 permintaan/menit per IP

### Kode Error Standar API
| HTTP Status | `error_code` | Keterangan |
| :--- | :--- | :--- |
| `403` | `ORIGIN_NOT_ALLOWED` | Header `Origin` tidak termasuk dalam daftar domain yang diizinkan |
| `404` | `CONVERSATION_NOT_FOUND` | `session_token` tidak ditemukan di database |
| `422` | `ValidationException` / `INVALID_SESSION_TOKEN` | Input tidak valid (pesan kosong, `< 2` karakter, token salah format) |
| `429` | `RATE_LIMIT_EXCEEDED` | Melebihi batas permintaan per menit/jam (cek header `Retry-After`) |
| `503` | `AI_SERVICE_UNAVAILABLE` | Gangguan sementara pada provider LLM/Embedding/Vector Store |

---

## 3. Integrasi Data SIB-BM
- **Mode REST API:** Atur `SIBBM_API_ENABLED=true`, `SIBBM_API_BASE_URL`, `SIBBM_API_BUILDINGS_ENDPOINT=/buildings`, dan `SIBBM_API_KEY` di `.env`.
- **Mode Sementara (Jika API SIB-BM belum aktif):** Letakkan file JSON daftar bangunan di `storage/app/sibbm_buildings.json` (atau path `SIBBM_LOCAL_JSON_PATH`) atau gunakan menu **Integrasi SIB-BM** di Panel Admin (`/admin/sibbm`).
- **Sinkronisasi CLI:**
  ```bash
  php artisan sibbm:sync
  ```
  Data otomatis diperbarui secara idempoten berdasarkan `external_id` (`source_label = sibbm:<external_id>`) pada tabel `sib_bm_buildings_cache` dan `knowledge_documents` tanpa membuat duplikasi.
