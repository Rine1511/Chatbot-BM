@extends('admin.layouts.app')

@section('title', 'Integrasi Data SIB-BM')
@section('header_title', 'Integrasi SIB-BM')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-bold tracking-tight text-slate-900">Sistem Informasi Bangunan Bahrul Maghfiroh (SIB-BM)</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Sinkronisasi data bangunan resmi SIB-BM ke Knowledge Base kategori <strong>Informasi Bangunan</strong> tanpa duplikasi untuk kebutuhan jawaban RAG.
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-600">
                    <span>Mode API SIB-BM: <strong class="{{ $apiEnabled ? 'text-[#0E4B32]' : 'text-amber-700' }}">{{ $apiEnabled ? 'Aktif' : 'Belum Diaktifkan' }}</strong></span>
                    <span aria-hidden="true">·</span>
                    <span>Snapshot JSON Lokal: <strong>{{ $hasLocalSnapshot ? 'Tersedia' : 'Belum Ada' }}</strong></span>
                    <span aria-hidden="true">·</span>
                    <span class="font-mono tabular-nums">Dokumen SIB-BM: {{ $indexedDocsCount }}/{{ $syncedDocsCount }} terindeks</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <form method="POST" action="{{ route('admin.sibbm.sync') }}">
                    @csrf
                    <input type="hidden" name="source" value="auto">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#0B3B27] px-4 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-[#0E4B32] whitespace-nowrap cursor-pointer">
                        Sinkronkan Data SIB-BM
                    </button>
                </form>
            </div>
        </div>

        <!-- Temporary JSON Import Form (When SIB-BM REST API is not yet live) -->
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h3 class="text-sm font-bold text-slate-900">Integrasi Sementara / Impor Snapshot JSON Bangunan</h3>
            <p class="mt-1 text-xs text-slate-500">
                Gunakan formulir ini bila REST API SIB-BM belum tersedia. Data yang sama (<code class="font-mono">external_id</code>) akan diperbarui secara idempoten tanpa membuat duplikasi.
            </p>
            <form method="POST" action="{{ route('admin.sibbm.import') }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label for="buildings_json" class="sr-only">JSON Data Bangunan SIB-BM</label>
                    <textarea
                        id="buildings_json"
                        name="buildings_json"
                        rows="5"
                        placeholder='[{"external_id":"GD-01","name":"Gedung Utama","category":"Pendidikan","function_desc":"Ruang Kelas & Sekretariat","land_area":1200,"building_area":850,"capacity":400,"facilities":["AC","Proyektor","Wi-Fi"]}]'
                        class="block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 font-mono text-xs text-slate-800 placeholder-slate-400 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
                    >{{ old('buildings_json') }}</textarea>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer">
                        Impor &amp; Sinkronkan JSON
                    </button>
                </div>
            </form>
        </div>

        <!-- Cached Buildings Table -->
        <div class="rounded-xl border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-sm font-bold text-slate-900">Daftar Bangunan Tersinkronisasi ({{ $buildings->total() }})</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-medium text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Kode &amp; Nama Gedung</th>
                            <th class="px-4 py-3">Kategori &amp; Fungsi</th>
                            <th class="px-4 py-3">Dimensi &amp; Kapasitas</th>
                            <th class="px-4 py-3">Fasilitas</th>
                            <th class="px-6 py-3 text-right">Sinkronisasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($buildings as $building)
                            <tr class="hover:bg-slate-50/70 align-top">
                                <td class="px-6 py-4">
                                    <p class="font-mono text-xs text-[#A67C16]">{{ $building->external_id }}</p>
                                    <p class="font-semibold text-slate-900">{{ $building->name }}</p>
                                </td>
                                <td class="px-4 py-4 text-xs text-slate-600">
                                    <p class="font-medium text-slate-800">{{ $building->category ?? '-' }}</p>
                                    <p class="mt-0.5 text-slate-500">{{ $building->function_desc ?? '-' }}</p>
                                </td>
                                <td class="px-4 py-4 font-mono text-xs text-slate-600 tabular-nums">
                                    <p>Tanah: {{ $building->land_area !== null ? number_format($building->land_area, 0, ',', '.').' m²' : '-' }}</p>
                                    <p>Bangunan: {{ $building->building_area !== null ? number_format($building->building_area, 0, ',', '.').' m²' : '-' }}</p>
                                    <p>Kapasitas: {{ $building->capacity !== null ? $building->capacity.' org' : '-' }}</p>
                                </td>
                                <td class="px-4 py-4 text-xs text-slate-600 max-w-xs">
                                    {{ ! empty($building->facilities) ? implode(', ', $building->facilities) : '-' }}
                                </td>
                                <td class="px-6 py-4 text-right font-mono text-xs text-slate-500 whitespace-nowrap">
                                    {{ $building->synced_at?->format('d/m/Y H:i') ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">
                                    Belum ada data bangunan SIB-BM yang disinkronkan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($buildings->hasPages())
                <div class="border-t border-slate-200 px-6 py-4">
                    {{ $buildings->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
