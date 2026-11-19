@extends('admin.layouts.app')

@section('title', 'Manajemen Kategori')
@section('header_title', 'Manajemen Kategori')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold tracking-tight text-slate-900">Kategori Pengetahuan</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Master kategori bersama untuk FAQ dan dokumen Knowledge Base. Kategori yang masih memiliki data tidak dapat dihapus.
                </p>
            </div>
            <a href="{{ route('admin.categories.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#0B3B27] px-4 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-[#0E4B32] whitespace-nowrap shrink-0">
                Tambah Kategori
            </a>
        </div>

        <!-- Search & Filter -->
        <form method="GET" action="{{ route('admin.categories.index') }}" class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center">
            <div class="flex-1">
                <label for="q" class="sr-only">Cari kategori</label>
                <input
                    id="q"
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Cari nama, slug, atau deskripsi kategori..."
                    class="block w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
                >
            </div>
            <div class="w-full sm:w-44">
                <label for="status" class="sr-only">Filter status</label>
                <select id="status" name="status" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none">
                    <option value="">Semua Status</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="submit" class="rounded-lg bg-[#0B3B27] px-4 py-2 text-xs font-semibold text-white hover:bg-[#0E4B32] cursor-pointer">
                    Filter
                </button>
                @if ($search !== '' || $status !== '')
                    <a href="{{ route('admin.categories.index') }}" class="rounded-lg border border-slate-200 px-3.5 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
                        Reset
                    </a>
                @endif
            </div>
        </form>

        <!-- Categories Table -->
        <div class="rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-medium text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Urutan</th>
                            <th class="px-4 py-3">Nama & Slug</th>
                            <th class="px-4 py-3 text-right">Jumlah FAQ</th>
                            <th class="px-4 py-3 text-right">Jumlah Dokumen</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($categories as $category)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-6 py-3.5 font-mono text-xs tabular-nums text-slate-500">
                                    {{ $category->sort_order }}
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="font-semibold text-slate-900">{{ $category->name }}</p>
                                    <p class="mt-0.5 font-mono text-xs text-slate-500">{{ $category->slug }}</p>
                                    @if ($category->description)
                                        <p class="mt-1 text-xs text-slate-500 line-clamp-2">{{ $category->description }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono tabular-nums text-slate-800">
                                    {{ $category->faqs_count }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono tabular-nums text-slate-800">
                                    {{ $category->knowledge_documents_count }}
                                </td>
                                <td class="px-4 py-3.5 text-xs font-medium {{ $category->is_active ? 'text-[#0E4B32]' : 'text-slate-400' }}">
                                    {{ $category->is_active ? 'Aktif' : 'Nonaktif' }}
                                </td>
                                <td class="px-6 py-3.5 text-right">
                                    <div class="inline-flex items-center justify-end gap-3">
                                        <a href="{{ route('admin.categories.edit', $category) }}" class="text-xs font-semibold text-[#0E4B32] hover:underline">
                                            Ubah
                                        </a>
                                        @if ($category->faqs_count === 0 && $category->knowledge_documents_count === 0)
                                            <form method="POST"
                                                  action="{{ route('admin.categories.destroy', $category) }}"
                                                  data-confirm-message="Hapus kategori &quot;{{ $category->name }}&quot;? Tindakan ini tidak dapat dibatalkan.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs font-semibold text-red-600 hover:underline cursor-pointer">
                                                    Hapus
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-xs text-slate-400" title="Kategori masih memiliki FAQ atau dokumen">
                                                Digunakan
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">
                                    Tidak ada kategori yang sesuai dengan filter saat ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($categories->hasPages())
                <div class="border-t border-slate-200 px-6 py-4">
                    {{ $categories->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
