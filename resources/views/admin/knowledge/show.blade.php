@extends('admin.layouts.app')

@section('title', 'Detail Dokumen Knowledge Base')
@section('header_title', 'Detail Dokumen Knowledge Base')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-[#0E4B32]">
                    {{ $document->category?->name ?? 'Tanpa Kategori' }}
                    @if ($document->category && ! $document->category->is_active)
                        <span class="text-amber-700">(Kategori Nonaktif)</span>
                    @endif
                </p>
                <h2 class="mt-1 text-xl font-bold tracking-tight text-slate-900">{{ $document->title }}</h2>
                @if ($document->description)
                    <p class="mt-2 text-sm text-slate-600">{{ $document->description }}</p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                @if ($document->is_active && $document->category?->is_active)
                    <form method="POST" action="{{ route('admin.knowledge.index-document', $document) }}" class="flex items-center gap-2">
                        @csrf
                        <button type="submit"
                                @disabled($document->processing_status === \App\Models\KnowledgeDocument::STATUS_PROCESSING)
                                class="rounded-lg bg-[#C49A2A] px-4 py-2 text-xs font-semibold text-[#07291B] hover:bg-[#D4AF37] disabled:opacity-50 cursor-pointer">
                            {{ $document->processing_status === \App\Models\KnowledgeDocument::STATUS_INDEXED ? 'Re-index Dokumen' : 'Proses & Indeks Sekarang' }}
                        </button>
                    </form>
                @endif
                @if ($document->source_type === \App\Models\KnowledgeDocument::SOURCE_FILE && $document->file_path)
                    <a href="{{ route('admin.knowledge.download', $document) }}" class="rounded-lg border border-[#0E4B32]/30 bg-emerald-50 px-3.5 py-2 text-xs font-semibold text-[#0E4B32] hover:bg-emerald-100">
                        Unduh Berkas
                    </a>
                @endif
                <a href="{{ route('admin.knowledge.edit', $document) }}" class="rounded-lg bg-[#0B3B27] px-4 py-2 text-xs font-semibold text-white hover:bg-[#0E4B32]">
                    Ubah Dokumen
                </a>
                <a href="{{ route('admin.knowledge.index') }}" class="rounded-lg border border-slate-200 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Kembali
                </a>
            </div>
        </div>

        @if (! $document->is_active || ! $document->category?->is_active)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                Dokumen atau kategorinya saat ini berstatus <span class="font-semibold">nonaktif</span> sehingga tidak akan diindeks dan tidak dipakai oleh chatbot.
            </div>
        @endif

        @if ($document->error_message)
            <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-900" role="alert">
                <p class="font-semibold">Pesan Kesalahan Pemrosesan / Indexing</p>
                <p class="mt-1 text-xs leading-relaxed">{{ $document->error_message }}</p>
            </div>
        @endif

        <!-- Metadata Grid -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-medium text-slate-500">Status Dokumen & Proses</p>
                <p class="mt-2 text-base font-bold {{ $document->is_active ? 'text-[#0E4B32]' : 'text-slate-500' }}">
                    {{ $document->is_active ? 'Aktif' : 'Nonaktif' }}
                </p>
                @php
                    $procColor = match ($document->processing_status) {
                        'indexed' => 'text-[#0E4B32]',
                        'processing' => 'text-amber-700',
                        'failed' => 'text-red-600',
                        default => 'text-slate-600',
                    };
                @endphp
                <p class="mt-1 font-mono text-xs font-semibold {{ $procColor }}">
                    Pipeline: {{ $document->processing_status }}
                </p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-medium text-slate-500">Tipe & Sumber</p>
                <p class="mt-2 text-base font-bold text-slate-900">
                    {{ $document->source_type === 'file' ? 'File Unggahan' : ($document->source_type === 'sib_bm_sync' ? 'Sinkron SIB-BM' : 'Konten Teks') }}
                </p>
                <p class="mt-1 text-xs text-slate-500 truncate">{{ $document->source_label ?: 'Belum diberi label sumber' }}</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-medium text-slate-500">Chunks & Vector Embedding</p>
                <p class="mt-2 font-mono text-2xl font-bold tabular-nums text-slate-900">{{ $document->chunks->count() }}</p>
                <p class="mt-1 text-xs text-slate-500">
                    @if ($document->last_indexed_at)
                        Diindeks: {{ $document->last_indexed_at->format('d/m/Y H:i:s') }}
                    @else
                        Belum pernah diindeks
                    @endif
                </p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-medium text-slate-500">Informasi Berkas / Pengunggah</p>
                <p class="mt-2 text-sm font-semibold text-slate-900 truncate">
                    {{ $document->original_filename ?: 'Input Teks Langsung' }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    {{ $document->original_filename ? $document->formattedFileSize().' · ' : '' }}{{ $document->uploader?->name ?? 'Admin' }}
                </p>
            </div>
        </div>

        @if ($document->source_url)
            <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm">
                <p class="text-slate-700">
                    <span class="font-medium">Tautan Referensi:</span>
                    <a href="{{ $document->source_url }}" target="_blank" rel="noopener noreferrer" class="font-medium text-[#0E4B32] hover:underline break-all">
                        {{ $document->source_url }}
                    </a>
                </p>
            </div>
        @endif

        <!-- Content Preview -->
        <div class="rounded-xl border border-slate-200 bg-white">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <h3 class="text-sm font-semibold text-slate-900">Pratinjau Konten Teks Ternormalisasi</h3>
                <span class="font-mono text-xs text-slate-500">
                    {{ $document->raw_content ? mb_strlen($document->raw_content).' karakter' : 'Belum diekstrak' }}
                </span>
            </div>
            <div class="p-6">
                @if ($document->raw_content)
                    <pre class="max-h-80 overflow-y-auto whitespace-pre-wrap rounded-lg border border-slate-200 bg-slate-50 p-4 font-mono text-xs leading-relaxed text-slate-800">{{ $document->raw_content }}</pre>
                @else
                    <p class="text-sm text-slate-500">
                        Berkas biner (.pdf / .docx) tersimpan aman di storage privat server. Klik <span class="font-semibold text-slate-700">Proses &amp; Indeks Sekarang</span> untuk mengekstrak teks, membuat chunks, menghasilkan embedding, dan menyimpannya ke vector store.
                    </p>
                @endif
            </div>
        </div>

        <!-- Extracted Chunks & Embeddings Table -->
        <div class="rounded-xl border border-slate-200 bg-white">
            <div class="flex flex-col gap-1 border-b border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Daftar Potongan Teks (Chunks) &amp; Vector Embedding</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Konfigurasi saat ini: chunk {{ config('ai.rag.chunk_size') }} karakter, overlap {{ config('ai.rag.chunk_overlap') }} karakter · Model {{ config('ai.embedding.model') }} ({{ config('ai.embedding.dimensions') }}d)
                    </p>
                </div>
                <span class="font-mono text-xs font-medium text-slate-600">
                    Total: {{ $document->chunks->count() }} chunks
                </span>
            </div>

            @if ($document->chunks->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-slate-500">
                    Belum ada chunk tersimpan. Klik <span class="font-semibold text-slate-700">Proses &amp; Indeks Sekarang</span> untuk memecah dokumen dan menghasilkan embedding vektor.
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($document->chunks as $chunk)
                        <div class="p-6 space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                                <div class="flex flex-wrap items-center gap-3 font-mono text-slate-600">
                                    <span class="font-semibold text-[#0E4B32]">Chunk #{{ $chunk->chunk_index + 1 }}</span>
                                    <span>~{{ $chunk->token_count }} token</span>
                                    @if ($chunk->embedding)
                                        <span>{{ $chunk->embedding->embedding_model }} ({{ $chunk->embedding->dimensions }}d)</span>
                                    @endif
                                    @if (isset($chunk->metadata['vector_driver']))
                                        <span>Driver: {{ $chunk->metadata['vector_driver'] }}</span>
                                    @endif
                                </div>
                                @if ($chunk->vector_point_id)
                                    <span class="font-mono text-[11px] text-slate-400" title="Point ID Vector Store">
                                        ID: {{ $chunk->vector_point_id }}
                                    </span>
                                @endif
                            </div>
                            <pre class="whitespace-pre-wrap rounded-lg border border-slate-200 bg-slate-50 p-3.5 font-mono text-xs leading-relaxed text-slate-800">{{ $chunk->content }}</pre>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Delete Footer -->
        <div class="flex items-center justify-between rounded-xl border border-red-200/70 bg-white p-5">
            <div>
                <p class="text-sm font-semibold text-slate-900">Hapus Dokumen</p>
                <p class="text-xs text-slate-500">Menghapus dokumen akan ikut menghapus berkas unggahan, seluruh chunk, embedding, dan titik vector terkait secara permanen.</p>
            </div>
            <form method="POST"
                  action="{{ route('admin.knowledge.destroy', $document) }}"
                  data-confirm-message="Hapus dokumen &quot;{{ $document->title }}&quot; secara permanen?">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-xs font-semibold text-red-700 hover:bg-red-100 cursor-pointer">
                    Hapus Dokumen
                </button>
            </form>
        </div>
    </div>
@endsection
