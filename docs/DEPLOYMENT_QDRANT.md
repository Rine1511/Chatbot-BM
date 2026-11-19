# Panduan Deployment Vector Storage (Qdrant & Alternatif MySQL)

Aplikasi **Asisten Virtual Bahrul Maghfiroh** menggunakan pola *Adapter* (`App\Contracts\VectorStoreInterface`) sehingga penyimpanan vektor tidak terkunci pada satu penyedia.

---

## 1. Target Utama: Qdrant (Direkomendasikan untuk Produksi)

**Qdrant** adalah *vector database* berperforma tinggi (indeks HNSW) yang mendukung *metadata filtering* (misalnya filter berdasarkan `category_id`, `category_slug`, `document_id`, atau `source_type`) dan berkomunikasi melalui HTTP REST API tanpa memerlukan ekstensi PHP khusus.

### Kebutuhan Minimum Server Qdrant
- **OS**: Linux (Ubuntu/Debian) atau Windows (WSL2 / Docker Desktop)
- **RAM**: Minimal 512 MB khusus untuk Qdrant (direkomendasikan 1–2 GB untuk puluhan ribu *chunk*)
- **Storage**: Minimal 2 GB ruang disk untuk indeks vektor & *snapshots*
- **Port**: `6333` (HTTP REST API) dan `6334` (gRPC, opsional). **Pastikan port `6333` hanya dapat diakses dari localhost/jaringan internal server Laravel (jangan dibuka ke publik).**

### Menjalankan Qdrant dengan Docker Compose
Buat file `docker-compose.qdrant.yml` di server produksi:

```yaml
services:
  qdrant:
    image: qdrant/qdrant:v1.12.5
    container_name: bm_chatbot_qdrant
    restart: unless-stopped
    ports:
      - "127.0.0.1:6333:6333"
    volumes:
      - ./qdrant_storage:/qdrant/storage
    environment:
      # Opsional: aktifkan API Key jika diakses antar-server
      QDRANT__SERVICE__API_KEY: "${QDRANT_API_KEY:-}"
```

Jalankan dengan perintah:
```bash
docker compose -f docker-compose.qdrant.yml up -d
```

Verifikasi Qdrant berjalan:
```bash
curl -s http://127.0.0.1:6333/healthz
```

### Konfigurasi `.env` Laravel untuk Qdrant
Dimensi collection Qdrant (`config('ai.vector.qdrant.vector_size')`) terikat langsung pada `AI_EMBEDDING_DIMENSIONS` sehingga selalu sinkron dengan parameter `outputDimensionality` model `gemini-embedding-001`.

```env
AI_EMBEDDING_MODEL=gemini-embedding-001
AI_EMBEDDING_DIMENSIONS=768

AI_VECTOR_DRIVER=qdrant
AI_VECTOR_FALLBACK_DRIVER=mysql
QDRANT_HOST=http://127.0.0.1:6333
QDRANT_API_KEY=
QDRANT_COLLECTION=bm_knowledge_chunks
QDRANT_DISTANCE=Cosine
QDRANT_TIMEOUT=10
```

### Struktur Payload Metadata Collection Qdrant
Setiap *point* pada collection `bm_knowledge_chunks` membawa metadata standar:
- `document_id`: ID dokumen sumber (atau `null` jika berasal dari FAQ)
- `faq_id`: ID FAQ sumber (atau `null` jika berasal dari dokumen)
- `chunk_id`: ID unik pada tabel `knowledge_chunks`
- `category_id`: ID kategori pada `faq_categories`
- `category_slug`: Slug kategori (`profil-pondok`, `pendidikan`, dst.)
- `source_type`: Sumber (`faq`, `text`, `file`, `sib_bm_sync`)

---

## 2. Alternatif Jika Server Tidak Mendukung Qdrant / Docker

Jika server produksi (misalnya *shared hosting*, cPanel, atau Laragon lokal tanpa Docker) tidak mendukung instalasi Qdrant, aplikasi menyediakan driver cadangan **`mysql`**.

### Cara Kerja Driver MySQL (`MySqlVectorDriver`)
- Vektor *embedding* disimpan pada kolom JSON di tabel `knowledge_embeddings` (berelasi `1:1` dengan `knowledge_chunks`).
- Pencarian kemiripan dilakukan dengan *cosine similarity* di sisi PHP setelah memfilter kandidat berdasarkan kategori/status aktif melalui SQL.
- Cocok untuk skala knowledge base pesantren hingga ~5.000–10.000 *chunk* tanpa layanan tambahan.
- Ketika `AI_VECTOR_DRIVER=qdrant` dikonfigurasi namun layanan Qdrant belum aktif, aplikasi utama dan Dashboard Admin tetap berjalan normal tanpa kegagalan boot.

### Konfigurasi `.env` Laravel untuk Mode MySQL Fallback
```env
AI_VECTOR_DRIVER=mysql
```

---

## 3. Migrasi Antar Driver
Karena seluruh teks potongan dan metadata tersimpan rapi di tabel `knowledge_chunks` pada MySQL, tim IT pondok dapat berpindah antara driver `mysql` dan `qdrant` kapan saja cukup dengan:
1. Mengubah `AI_VECTOR_DRIVER` di `.env`.
2. Menjalankan perintah *re-index* ulang melalui Dashboard Admin atau CLI Artisan (diaktifkan pada Tahap 4).
