@extends('admin.layouts.app')

@section('title', 'Knowledge Base')
@section('header_title', 'Manajemen Knowledge Base')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold tracking-tight text-slate-900">Dokumen Knowledge Base</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Kelola materi teks dan dokumen resmi (.txt, .md, .csv, .pdf, .docx), lalu jalankan proses ekstraksi, chunking, embedding, dan vector indexing.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                @if (($pendingCount ?? 0) > 0)
                    <form method="POST" action="{{ route('admin.knowledge.process-pending') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-[#C49A2A] bg-[#F9F3E0] px-4 py-2.5 text-xs font-semibold text-[#7A5908] transition-colors hover:bg-[#f2e7c3] whitespace-nowrap cursor-pointer">
                            Proses Dokumen Pending ({{ $pendingCount }})
                        </button>
                    </form>
                @endif
                <a href="{{ route('admin.knowledge.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#0B3B27] px-4 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-[#0E4B32] whitespace-nowrap shrink-0">
                    Tambah Dokumen
                </a>
            </div>
        </div>

        <!-- Search & Filters -->
        <form method="GET" action="{{ route('admin.knowledge.index') }}" class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-12 md:items-center">
            <div class="md:col-span-4">
                <label for="q" class="sr-only">Cari dokumen</label>
                <input
                    id="q"
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Cari judul, deskripsi, sumber, atau nama file..."
                    class="block w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
                >
            </div>
            <div class="md:col-span-2">
                <label for="category_id" class="sr-only">Filter kategori</label>
                <select id="category_id" name="category_id" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none">
                    <option value="">Semua Kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ $categoryId === $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="source_type" class="sr-only">Filter tipe sumber</label>
                <select id="source_type" name="source_type" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none">
                    <option value="">Semua Tipe</option>
                    <option value="text" {{ $sourceType === 'text' ? 'selected' : '' }}>Konten Teks</option>
                    <option value="file" {{ $sourceType === 'file' ? 'selected' : '' }}>Unggahan File</option>
                    <option value="sib_bm_sync" {{ $sourceType === 'sib_bm_sync' ? 'selected' : '' }}>Sinkron SIB-BM</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="processing_status" class="sr-only">Filter status proses</label>
                <select id="processing_status" name="processing_status" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none">
                    <option value="">Semua Proses</option>
                    <option value="pending" {{ $processingStatus === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="processing" {{ $processingStatus === 'processing' ? 'selected' : '' }}>Processing</option>
                    <option value="indexed" {{ $processingStatus === 'indexed' ? 'selected' : '' }}>Indexed</option>
                    <option value="failed" {{ $processingStatus === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>
            <div class="flex items-center gap-2 md:col-span-2 md:justify-end">
                <select id="status" name="status" aria-label="Filter status aktif" class="block w-full rounded-lg border border-slate-300 px-2.5 py-2 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none">
                    <option value="">Semua</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </select>
                <button type="submit" class="rounded-lg bg-[#0B3B27] px-3.5 py-2 text-xs font-semibold text-white hover:bg-[#0E4B32] cursor-pointer shrink-0">
                    Filter
                </button>
                @if ($search !== '' || $categoryId > 0 || $sourceType !== '' || $processingStatus !== '' || $status !== '')
                    <a href="{{ route('admin.knowledge.index') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 shrink-0">
                        Reset
                    </a>
                @endif
            </div>
        </form>

        <!-- Knowledge Documents Table -->
        <div class="rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-medium text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Judul & Metadata Sumber</th>
                            <th class="px-4 py-3">Kategori</th>
                            <th class="px-4 py-3">Tipe & Berkas</th>
                            <th class="px-4 py-3 text-right">Chunks</th>
                            <th class="px-4 py-3">Status & Waktu Proses</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($documents as $doc)
                            <tr class="hover:bg-slate-50/70 align-top">
                                <td class="px-6 py-4 max-w-md">
                                    <a href="{{ route('admin.knowledge.show', $doc) }}" class="font-semibold text-slate-900 hover:text-[#0E4B32] hover:underline">
                                        {{ $doc->title }}
                                    </a>
                                    @if ($doc->description)
                                        <p class="mt-1 text-xs text-slate-600 line-clamp-2">{{ $doc->description }}</p>
                                    @endif
                                    @if ($doc->source_label)
                                        <p class="mt-1 text-[11px] text-slate-500">Sumber: {{ $doc->source_label }}</p>
                                    @endif
                                    @if ($doc->processing_status === 'failed' && $doc->error_message)
                                        <p class="mt-1.5 text-[11px] text-red-600 line-clamp-2" title="{{ $doc->error_message }}">
                                            Error: {{ \Illuminate\Support\Str::limit($doc->error_message, 120) }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="font-medium text-slate-800">{{ $doc->category?->name ?? '-' }}</span>
                                    @if ($doc->category && ! $doc->category->is_active)
                                        <p class="mt-0.5 text-[11px] text-amber-700">Kategori nonaktif</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-xs text-slate-600">
                                    <p class="font-medium text-slate-800">
                                        @if ($doc->source_type === \App\Models\KnowledgeDocument::SOURCE_FILE)
                                            File Unggahan
                                        @elseif ($doc->source_type === \App\Models\KnowledgeDocument::SOURCE_SIBBM)
                                            Sinkron SIB-BM
                                        @else
                                            Konten Teks
                                        @endif
                                    </p>
                                    @if ($doc->original_filename)
                                        <p class="mt-0.5 font-mono text-[11px] text-slate-500 truncate max-w-[200px]" title="{{ $doc->original_filename }}">
                                            {{ $doc->original_filename }} ({{ $doc->formattedFileSize() }})
                                        </p>
                                    @else
                                        <p class="mt-0.5 font-mono text-[11px] text-slate-500">
                                            {{ mb_strlen((string) $doc->raw_content) }} karakter
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right font-mono tabular-nums text-slate-800">
                                    {{ $doc->chunks_count }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-xs">
                                    <p class="font-medium {{ $doc->is_active ? 'text-[#0E4B32]' : 'text-slate-400' }}">
                                        {{ $doc->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </p>
                                    @php
                                        $procColor = match ($doc->processing_status) {
                                            'indexed' => 'text-[#0E4B32]',
                                            'processing' => 'text-amber-700',
                                            'failed' => 'text-red-600',
                                            default => 'text-slate-500',
                                        };
                                    @endphp
                                    <p class="mt-0.5 font-mono text-[11px] font-semibold {{ $procColor }}">
                                        {{ $doc->processing_status }}
                                    </p>
                                    @if ($doc->last_indexed_at)
                                        <p class="mt-0.5 font-mono text-[10px] text-slate-400">
                                            {{ $doc->last_indexed_at->format('d/m/Y H:i') }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-3">
                                        @if ($doc->is_active && $doc->category?->is_active && $doc->processing_status !== 'processing')
                                            <form method="POST" action="{{ route('admin.knowledge.index-document', $doc) }}">
                                                @csrf
                                                <input type="hidden" name="redirect" value="index">
                                                <button type="submit" class="text-xs font-semibold text-[#A67C16] hover:underline cursor-pointer">
                                                    {{ $doc->processing_status === 'indexed' ? 'Re-index' : 'Proses' }}
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('admin.knowledge.show', $doc) }}" class="text-xs font-semibold text-slate-700 hover:underline">
                                            Detail
                                        </a>
                                        <a href="{{ route('admin.knowledge.edit', $doc) }}" class="text-xs font-semibold text-[#0E4B32] hover:underline">
                                            Ubah
                                        </a>
                                        <form method="POST"
                                              action="{{ route('admin.knowledge.destroy', $doc) }}"
                                              data-confirm-message="Hapus dokumen &quot;{{ \Illuminate\Support\Str::limit($doc->title, 80) }}&quot; beserta berkas dan chunk terkait?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-semibold text-red-600 hover:underline cursor-pointer">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">
                                    Belum ada dokumen Knowledge Base yang sesuai. Klik tombol <span class="font-semibold text-slate-700">Tambah Dokumen</span> untuk menambahkan referensi teks atau mengunggah berkas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($documents->hasPages())
                <div class="border-t border-slate-200 px-6 py-4">
                    {{ $documents->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
