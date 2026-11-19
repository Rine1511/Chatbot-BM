# Panduan Instalasi & Menjalankan Proyek (PHP, Composer, MySQL & Laragon)

Dokumen ini menjelaskan cara menjalankan proyek **Asisten Virtual Bahrul Maghfiroh** secara mandiri di lingkungan lokal (termasuk **Laragon** di Windows) maupun server produksi PHP/MySQL.

---

## 1. Prasyarat Sistem
- **PHP**: versi `>= 8.2` dengan ekstensi aktif: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `intl`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, `zip`
- **Composer**: versi `>= 2.x`
- **Database**: MySQL `>= 8.0` atau MariaDB `>= 10.6`
- **Node.js & NPM** *(opsional untuk kompilasi ulang asset Tailwind CSS)*: Node `>= 18`

---

## 2. Instalasi di Laragon (Windows)

1. **Letakkan proyek** di folder `C:\laragon\www\bm-chatbot` (atau salin seluruh isi folder proyek ke direktori tersebut).
2. **Aktifkan layanan Laragon**: klik **Start All** (pastikan Apache/Nginx dan MySQL berjalan).
3. **Buat database MySQL** (utama dan database pengujian terpisah):
   - Buka HeidiSQL / phpMyAdmin dari Laragon, lalu buat database baru:
     ```sql
     CREATE DATABASE bm_chatbot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     CREATE DATABASE bm_chatbot_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     ```
4. **Buka Terminal Laragon** (*Menu -> Terminal*) dan arahkan ke folder proyek:
   ```bash
   cd C:\laragon\www\bm-chatbot
   ```
5. **Instal dependensi PHP**:
   ```bash
   composer install
   ```
6. **Salin konfigurasi environment**:
   ```bash
   copy .env.example .env
   php artisan key:generate
   ```
7. **Sesuaikan `.env`**:
   ```env
   APP_NAME="Asisten Virtual Bahrul Maghfiroh"
   APP_URL=http://bm-chatbot.test

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=bm_chatbot
   DB_USERNAME=root
   DB_PASSWORD=

   AI_LLM_DRIVER=gemini
   AI_LLM_MODEL=gemini-2.5-flash
   AI_EMBEDDING_MODEL=gemini-embedding-001
   AI_EMBEDDING_DIMENSIONS=768
   GEMINI_API_KEY=ISI_API_KEY_GEMINI_ANDA

   AI_VECTOR_DRIVER=mysql
   AI_VECTOR_FALLBACK_DRIVER=mysql

   # Isi kata sandi kuat (minimal 12 karakter) sebelum menjalankan seeder:
   ADMIN_SEED_NAME="Administrator Pondok"
   ADMIN_SEED_EMAIL=admin@bahrulmaghfirohmalang.or.id
   ADMIN_SEED_PASSWORD="GantiDenganPasswordKuatAnda#2026"
   ```
   *(Gunakan `AI_VECTOR_DRIVER=qdrant` apabila Qdrant sudah dijalankan sesuai `docs/DEPLOYMENT_QDRANT.md`. Untuk produksi HTTPS, set `APP_ENV=production`, `APP_DEBUG=false`, dan `SESSION_SECURE_COOKIE=true`.)*

8. **Jalankan migrasi dan seeder awal**:
   ```bash
   php artisan migrate --seed
   ```
   Perintah ini akan membuat seluruh tabel database, mengisi **6 kategori wajib** (`Profil Pondok`, `Pendidikan`, `Pendaftaran`, `Kegiatan`, `Fasilitas`, `Informasi Bangunan`), pengaturan awal chatbot, serta akun admin awal.
   *(Catatan keamanan: Jika `ADMIN_SEED_PASSWORD` dikosongkan pada mode `local`, seeder akan membuat kata sandi acak 16 karakter dan menampilkannya satu kali di terminal. Pada mode `production`, seeder akan menolak berjalan bila `ADMIN_SEED_PASSWORD` belum diisi dengan kata sandi kuat.)*

9. **Akses aplikasi**:
   - Via Virtual Host Laragon (reload Laragon jika perlu): `http://bm-chatbot.test/admin/login`
   - Atau via PHP built-in server:
     ```bash
     php artisan serve
     ```
     Lalu buka `http://127.0.0.1:8000/admin/login`.

---

## 3. Pengujian Menggunakan MySQL (Termasuk di Laragon)

> **Peringatan:** Jangan menjalankan `migrate:fresh` pada database produksi atau database yang berisi data penting. Gunakan database pengujian khusus `bm_chatbot_test`.

1. **Pengujian unit & feature suite pada SQLite in-memory** (default `phpunit.xml`):
   ```bash
   php artisan test
   ```
2. **Pengujian penuh terhadap MySQL (`bm_chatbot_test`)**:
   - Di Terminal Laragon (Linux/macOS/Git Bash):
     ```bash
     DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=bm_chatbot_test DB_USERNAME=root DB_PASSWORD= php artisan test
     ```
   - Di PowerShell Windows (Laragon):
     ```powershell
     $env:DB_CONNECTION="mysql"; $env:DB_HOST="127.0.0.1"; $env:DB_PORT="3306"; $env:DB_DATABASE="bm_chatbot_test"; $env:DB_USERNAME="root"; $env:DB_PASSWORD=""; php artisan test
     ```
   - Untuk menguji migrasi + seeder MySQL secara manual pada `bm_chatbot_test`:
     ```bash
     php artisan migrate:fresh --seed --database=mysql
     ```
     *(Pastikan `.env` sementara mengarah ke `bm_chatbot_test`, bukan database produksi.)*
