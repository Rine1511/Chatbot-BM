# Panduan Operasional Admin — Asisten Virtual Bahrul Maghfiroh

Dokumen ini memandu tim pengelola/admin Pondok Pesantren Bahrul Maghfiroh dalam mengelola basis pengetahuan chatbot melalui Panel Admin (`/admin/login`).

---

## 1. Manajemen Kategori (`/admin/categories`)
- Aplikasi menyediakan 6 kategori utama: **Profil Pondok**, **Pendidikan**, **Pendaftaran**, **Kegiatan**, **Fasilitas**, dan **Informasi Bangunan**.
- Kategori digunakan bersama oleh **FAQ** dan **Knowledge Base**.
- **Aturan penting:**
  - Kategori yang masih memiliki FAQ atau dokumen tidak dapat dihapus demi menjaga integritas referensi.
  - Menonaktifkan kategori akan **otomatis membersihkan vektor** dokumen dan FAQ di kategori tersebut sehingga tidak lagi digunakan oleh chatbot hingga diaktifkan dan diindeks ulang.

---

## 2. Manajemen FAQ (`/admin/faqs`)
- Gunakan untuk pasangan tanya-jawab resmi beserta kata kunci, variasi pertanyaan, tautan sumber (`https://bahrulmaghfirohmalang.or.id/...`), serta status aktif/nonaktif.
- Saat `GEMINI_API_KEY` aktif di server, FAQ aktif otomatis diindeks ke penyimpanan vektor (`embedding_status = indexed`).
- Untuk mengindeks ulang seluruh FAQ yang berstatus `pending` atau `failed` dari terminal:
  ```bash
  php artisan rag:index-faqs
  ```
- Menonaktifkan atau menghapus FAQ langsung membersihkan titik vektornya agar informasi lama tidak dijawab chatbot.

---

## 3. Manajemen Knowledge Base & Indexing (`/admin/knowledge`)
- Mendukung input teks langsung serta unggahan berkas (`.txt`, `.md`, `.csv`, `.pdf`, `.docx`, maks. 10 MB).
- Setiap dokumen dapat diindeks melalui tombol **Proses & Indeks Sekarang** pada halaman detail dokumen, atau diproses massal melalui tombol **Proses Dokumen Pending**.
- Jika konten dokumen diubah namun proses indexing ulang gagal, sistem otomatis membersihkan potongan (chunk) lama agar chatbot tidak memberikan informasi kedaluwarsa.

---

## 4. Sinkronisasi Data Bangunan SIB-BM (`/admin/sibbm`)
- Buka menu **Integrasi SIB-BM** untuk melihat daftar gedung tersinkronisasi (`sib_bm_buildings_cache`) serta status indeks dokumennya.
- Klik **Sinkronkan Data SIB-BM** untuk menarik pembaruan dari REST API SIB-BM (atau snapshot JSON lokal).
- Jika REST API SIB-BM belum tersedia, gunakan formulir **Impor Snapshot JSON Bangunan** pada halaman yang sama. Data gedung yang sudah ada akan diperbarui tanpa duplikasi.

---

## 5. Riwayat Percakapan & Halaman Uji Pengunjung
- Halaman chat pengunjung standalone dapat diakses pada URL `/chat` (tanpa login) untuk menguji pengalaman tanya-jawab sebelum widget dipasang di website utama.
- Ringkasan statistik percakapan dan pertanyaan terbaru tampil langsung di **Dashboard Admin** (`/admin/dashboard`). Seluruh pesan tersimpan di tabel `conversations` dan `messages` lengkap dengan `matched_sources`, status `is_fallback`, serta waktu respons (`response_time_ms`).
