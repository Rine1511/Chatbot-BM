# Panduan Deployment Produksi — Asisten Virtual Bahrul Maghfiroh

Dokumen ini menjadi acuan teknis sebelum mempublikasikan aplikasi standalone **Asisten Virtual Bahrul Maghfiroh** ke server produksi.

> **Penting:** Jangan menimpa `.env` development, jangan menjalankan `migrate:fresh` atau `db:wipe` pada database produksi, dan lakukan backup terlebih dahulu sebelum setiap pembaruan.

---

## 1. Persiapan Lingkungan Server Produksi

### Kebutuhan Minimum
- **PHP `>= 8.2`** (ekstensi: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `intl`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, `zip`)
- **Composer `>= 2.x`**
- **MySQL `>= 8.0` atau MariaDB `>= 10.6`**
- **Web Server**: Nginx atau Apache dengan HTTPS (TLS/SSL) aktif
- **Qdrant** *(opsional, direkomendasikan)* sesuai panduan `docs/DEPLOYMENT_QDRANT.md`, atau gunakan `AI_VECTOR_DRIVER=mysql` bila Qdrant tidak tersedia.

### Konfigurasi `.env` Produksi
Salin `.env.example` menjadi `.env` di server produksi lalu sesuaikan variabel berikut:

```env
APP_NAME="Asisten Virtual Bahrul Maghfiroh"
APP_ENV=production
APP_KEY=base64:... # generate dengan php artisan key:generate
APP_DEBUG=false
APP_URL=https://chatbot.bahrulmaghfirohmalang.or.id
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=30

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bm_chatbot_prod
DB_USERNAME=bm_chatbot_user
DB_PASSWORD="GANTI_DENGAN_PASSWORD_DB_KUAT"

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=database

AI_LLM_DRIVER=gemini
AI_LLM_MODEL=gemini-2.5-flash
AI_EMBEDDING_DRIVER=gemini
AI_EMBEDDING_MODEL=gemini-embedding-001
AI_EMBEDDING_DIMENSIONS=768
GEMINI_API_KEY="ISI_API_KEY_GEMINI_PRODUKSI"

# Gunakan qdrant bila service Qdrant aktif, atau mysql bila single-server MySQL
AI_VECTOR_DRIVER=qdrant
AI_VECTOR_FALLBACK_DRIVER=mysql
QDRANT_HOST=http://127.0.0.1:6333
QDRANT_API_KEY="OPSIONAL_API_KEY_QDRANT"
QDRANT_COLLECTION=bm_knowledge_chunks

# Integrasi SIB-BM
SIBBM_API_ENABLED=true
SIBBM_API_BASE_URL=https://sibbm.bahrulmaghfirohmalang.or.id/api
SIBBM_API_BUILDINGS_ENDPOINT=/buildings
SIBBM_API_KEY="ISI_TOKEN_API_SIBBM"

# Keamanan CORS & Widget Website Utama
ALLOWED_WIDGET_ORIGINS=https://bahrulmaghfirohmalang.or.id,https://www.bahrulmaghfirohmalang.or.id
CHAT_RATE_LIMIT_PER_MINUTE=20

# Akun Admin Awal (WAJIB diisi kata sandi kuat >= 12 karakter sebelum seeding)
ADMIN_SEED_NAME="Administrator Pondok"
ADMIN_SEED_EMAIL=admin@bahrulmaghfirohmalang.or.id
ADMIN_SEED_PASSWORD="GANTI_PASSWORD_ADMIN_KUAT_2026"
```

---

## 2. Langkah Instalasi & Migrasi Awal

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
```

Untuk mengoptimalkan konfigurasi, route, dan view pada produksi:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
*(Saat memperbarui `.env`, jalankan ulang `php artisan config:clear && php artisan config:cache`.)*

---

## 3. Menjalankan Queue Worker & Scheduler

### Queue Worker (Jika `RAG_QUEUE_INDEXING=true` / `QUEUE_CONNECTION=database`)
Gunakan **Supervisor** (`/etc/supervisor/conf.d/bm-chatbot-worker.conf`):

```ini
[program:bm-chatbot-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/bm-chatbot/artisan queue:work database --sleep=3 --tries=3 --backoff=10,30,60 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/bm-chatbot/storage/logs/worker.log
```

### Sinkronisasi Berkala SIB-BM & Indexing FAQ (Cron)
Tambahkan pada crontab `www-data`:
```cron
0 */6 * * * cd /var/www/bm-chatbot && php artisan sibbm:sync >> storage/logs/sibbm-sync.log 2>&1
```

---

## 4. Keamanan, Backup & Pemeliharaan

### Perlindungan API Key & Kredensial
- Seluruh API key (`GEMINI_API_KEY`, `OPENAI_API_KEY`, `QDRANT_API_KEY`, `SIBBM_API_KEY`) disimpan **hanya** di file `.env` server dan tidak pernah dikirim ke Blade, JavaScript widget, atau respons JSON.
- Pesan error upstream disaring melalui `App\Support\SecretRedactor` sebelum disimpan atau dicatat ke log.
- Pastikan permission file `.env` adalah `600` atau `640` dan tidak berada di folder `public/`.

### Backup Berkala
1. **Backup Database MySQL/MariaDB (harian):**
   ```bash
   mysqldump -u bm_chatbot_user -p --single-transaction bm_chatbot_prod > /backup/bm_chatbot_$(date +%F).sql
   ```
2. **Backup Folder Unggahan & Snapshot Qdrant:**
   - Backup `storage/app/` (dokumen pengetahuan terunggah) dan volume `./qdrant_storage` (atau buat snapshot via API Qdrant).

### Logging & Pemeliharaan
- Gunakan `LOG_CHANNEL=daily` dan `LOG_LEVEL=warning` di produksi agar log otomatis terotasi (`LOG_DAILY_DAYS=30`).
- Pantau `storage/logs/laravel.log` secara berkala untuk mendeteksi warning fallback Qdrant, kegagalan indexing, atau batas kuota Gemini API.

---

## 5. Checklist Pemeriksaan Akhir Sebelum Go-Live
- [ ] `APP_ENV=production` dan `APP_DEBUG=false`
- [ ] `SESSION_SECURE_COOKIE=true` (HTTPS aktif)
- [ ] `GEMINI_API_KEY` valid & teruji untuk `gemini-2.5-flash` serta `gemini-embedding-001` (dimensi `768`)
- [ ] `ALLOWED_WIDGET_ORIGINS` sesuai domain resmi website pondok (`https://bahrulmaghfirohmalang.or.id`, `https://www.bahrulmaghfirohmalang.or.id`)
- [ ] `php artisan migrate --force` dan `php artisan db:seed --force` berhasil (6 kategori wajib & admin aktif)
- [ ] Queue worker aktif (bila memakai mode async) dan backup harian database terjadwal
- [ ] Pengujian smoke test `/chat`, `/admin/login`, `/widget/bm-chatbot.js`, dan `/api/v1/chat/conversations` sukses
