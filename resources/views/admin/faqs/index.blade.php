@extends('admin.layouts.app')

@section('title', 'Manajemen FAQ')
@section('header_title', 'Manajemen FAQ')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold tracking-tight text-slate-900">Daftar FAQ Pondok Pesantren</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Kelola pertanyaan resmi, kata kunci, variasi pertanyaan, serta status aktif FAQ. Hanya FAQ berstatus aktif pada kategori aktif yang digunakan sebagai sumber jawaban chatbot.
                </p>
            </div>
            <a href="{{ route('admin.faqs.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#0B3B27] px-4 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-[#0E4B32] whitespace-nowrap shrink-0">
                Tambah FAQ
            </a>
        </div>

        <!-- Search & Filters -->
        <form method="GET" action="{{ route('admin.faqs.index') }}" class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-12 md:items-center">
            <div class="md:col-span-5">
                <label for="q" class="sr-only">Cari FAQ</label>
                <input
                    id="q"
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Cari pertanyaan, jawaban, kata kunci, atau variasi..."
                    class="block w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
                >
            </div>
            <div class="md:col-span-3">
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
                <label for="status" class="sr-only">Filter status</label>
                <select id="status" name="status" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none">
                    <option value="">Semua Status</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
            <div class="flex items-center gap-2 md:col-span-2 md:justify-end">
                <select id="featured" name="featured" aria-label="Filter prioritas" class="block w-full rounded-lg border border-slate-300 px-2.5 py-2 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none">
                    <option value="">Semua</option>
                    <option value="featured" {{ $featured === 'featured' ? 'selected' : '' }}>Utama</option>
                    <option value="standard" {{ $featured === 'standard' ? 'selected' : '' }}>Standar</option>
                </select>
                <button type="submit" class="rounded-lg bg-[#0B3B27] px-3.5 py-2 text-xs font-semibold text-white hover:bg-[#0E4B32] cursor-pointer shrink-0">
                    Filter
                </button>
                @if ($search !== '' || $categoryId > 0 || $status !== '' || $featured !== '')
                    <a href="{{ route('admin.faqs.index') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 shrink-0">
                        Reset
                    </a>
                @endif
            </div>
        </form>

        <!-- FAQ Table -->
        <div class="rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-medium text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Pertanyaan & Ringkasan Jawaban</th>
                            <th class="px-4 py-3">Kategori</th>
                            <th class="px-4 py-3">Kata Kunci / Variasi</th>
                            <th class="px-4 py-3">Status Chatbot</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($faqs as $faq)
                            <tr class="hover:bg-slate-50/70 align-top">
                                <td class="px-6 py-4 max-w-md">
                                    <div class="flex items-start gap-2">
                                        @if ($faq->is_featured)
                                            <span class="mt-0.5 shrink-0 font-mono text-[11px] font-semibold text-[#A67C16]" title="FAQ Prioritas Utama">
                                                [UTAMA]
                                            </span>
                                        @endif
                                        <p class="font-semibold text-slate-900">{{ $faq->question }}</p>
                                    </div>
                                    <p class="mt-1.5 text-xs leading-relaxed text-slate-600 line-clamp-2">
                                        {{ \Illuminate\Support\Str::limit($faq->answer, 160) }}
                                    </p>
                                    @if ($faq->source_url)
                                        <p class="mt-1.5 text-[11px] text-slate-500 truncate">
                                            Sumber:
                                            <a href="{{ $faq->source_url }}" target="_blank" rel="noopener noreferrer" class="font-medium text-[#0E4B32] hover:underline">
                                                {{ $faq->source_url }}
                                            </a>
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="font-medium text-slate-800">{{ $faq->category?->name ?? '-' }}</span>
                                    @if ($faq->category && ! $faq->category->is_active)
                                        <p class="mt-0.5 text-[11px] text-amber-700">Kategori nonaktif</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-xs text-slate-600 max-w-xs">
                                    @if ($faq->keywords)
                                        <p class="font-medium text-slate-700">{{ \Illuminate\Support\Str::limit($faq->keywords, 80) }}</p>
                                    @else
                                        <p class="text-slate-400">Belum ada kata kunci</p>
                                    @endif
                                    @php
                                        $variationsCount = count($faq->variationsList());
                                    @endphp
                                    <p class="mt-1 font-mono text-[11px] text-slate-500">
                                        {{ $variationsCount }} variasi pertanyaan
                                    </p>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <form method="POST" action="{{ route('admin.faqs.toggle-status', $faq) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-semibold transition-colors cursor-pointer {{ $faq->is_active ? 'border-emerald-200 bg-emerald-50 text-[#0E4B32] hover:bg-emerald-100' : 'border-slate-200 bg-slate-100 text-slate-500 hover:bg-slate-200' }}"
                                                title="Klik untuk mengubah status aktif/nonaktif">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $faq->is_active ? 'bg-[#0E4B32]' : 'bg-slate-400' }}"></span>
                                            {{ $faq->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </form>
                                    <p class="mt-1 font-mono text-[11px] text-slate-400">
                                        Indeks: {{ $faq->embedding_status }}
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-3">
                                        <a href="{{ route('admin.faqs.edit', $faq) }}" class="text-xs font-semibold text-[#0E4B32] hover:underline">
                                            Ubah
                                        </a>
                                        <form method="POST"
                                              action="{{ route('admin.faqs.destroy', $faq) }}"
                                              data-confirm-message="Hapus FAQ &quot;{{ \Illuminate\Support\Str::limit($faq->question, 80) }}&quot;? Tindakan ini tidak dapat dibatalkan.">
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
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">
                                    Belum ada data FAQ yang sesuai. Klik tombol <span class="font-semibold text-slate-700">Tambah FAQ</span> untuk menambahkan pertanyaan resmi pondok.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($faqs->hasPages())
                <div class="border-t border-slate-200 px-6 py-4">
                    {{ $faqs->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
