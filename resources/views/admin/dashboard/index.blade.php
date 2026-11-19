@extends('admin.layouts.app')

@section('title', 'Dashboard Utama')
@section('header_title', 'Dashboard Utama')

@section('content')
    <div class="space-y-6">
        <!-- Intro Banner -->
        <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold tracking-tight text-slate-900">
                    {{ $systemReadiness['chatbot_name'] }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Ringkasan basis pengetahuan, statistik percakapan, dan kesiapan infrastruktur AI.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <a href="{{ route('admin.faqs.create') }}" class="inline-flex items-center rounded-lg bg-[#0B3B27] px-3.5 py-2 text-xs font-semibold text-white transition-colors hover:bg-[#0E4B32] whitespace-nowrap">
                    Tambah FAQ
                </a>
                <a href="{{ route('admin.knowledge.create') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 whitespace-nowrap">
                    Tambah Knowledge Base
                </a>
            </div>
        </div>

        <!-- Primary Stat Cards -->
        <section aria-label="Statistik Utama">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <p class="text-xs font-medium text-slate-500">Total Percakapan</p>
                    <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-slate-900">
                        {{ number_format($stats['total_conversations']) }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <p class="text-xs font-medium text-slate-500">Total Pertanyaan</p>
                    <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-slate-900">
                        {{ number_format($stats['total_questions']) }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <p class="text-xs font-medium text-slate-500">Total FAQ</p>
                    <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-slate-900">
                        {{ number_format($stats['total_faqs']) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500 tabular-nums">{{ number_format($stats['active_faqs']) }} aktif</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <p class="text-xs font-medium text-slate-500">Dokumen Knowledge</p>
                    <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-slate-900">
                        {{ number_format($stats['total_documents']) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500 tabular-nums">
                        {{ number_format($stats['indexed_documents']) }} terindeks · {{ number_format($stats['pending_documents']) }} pending
                        @if ($stats['failed_documents'] > 0)
                            · <span class="text-red-600 font-medium">{{ number_format($stats['failed_documents']) }} gagal</span>
                        @endif
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <p class="text-xs font-medium text-slate-500">Chunk Terindeks</p>
                    <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-slate-900">
                        {{ number_format($stats['total_chunks']) }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <p class="text-xs font-medium text-slate-500">Kategori Aktif</p>
                    <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-[#0E4B32]">
                        {{ number_format($stats['total_categories']) }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Categories & System Configuration -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Categories Table (2 cols) -->
            <div class="rounded-xl border border-slate-200 bg-white lg:col-span-2">
                <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-4">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Master Kategori Pengetahuan</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Kategori bersama untuk FAQ dan Knowledge Base</p>
                    </div>
                    <a href="{{ route('admin.categories.index') }}" class="text-xs font-semibold text-[#0E4B32] hover:underline whitespace-nowrap shrink-0">
                        Kelola Kategori
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-medium text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Kategori</th>
                                <th class="px-4 py-3 text-right">FAQ</th>
                                <th class="px-4 py-3 text-right">Dokumen</th>
                                <th class="px-6 py-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($categories as $category)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="px-6 py-3.5">
                                        <p class="font-medium text-slate-900">{{ $category->name }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500 line-clamp-1">{{ $category->description }}</p>
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-mono tabular-nums text-slate-700">
                                        {{ $category->faqs_count }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-mono tabular-nums text-slate-700">
                                        {{ $category->knowledge_documents_count }}
                                    </td>
                                    <td class="px-6 py-3.5 text-right text-xs font-medium {{ $category->is_active ? 'text-[#0E4B32]' : 'text-slate-400' }}">
                                        {{ $category->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-sm text-slate-500">
                                        Belum ada kategori terdaftar.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- AI & Integration Configuration Summary -->
            <div class="rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="text-sm font-semibold text-slate-900">Konfigurasi Layanan AI & Data</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Dibaca dari konfigurasi server `.env`</p>
                </div>
                <dl class="divide-y divide-slate-100 px-6 py-2 text-sm">
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-slate-500">Driver LLM</dt>
                        <dd class="font-mono text-xs font-medium text-slate-900">{{ $systemReadiness['llm_driver'] }} · {{ $systemReadiness['llm_model'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-slate-500">Model Embedding</dt>
                        <dd class="font-mono text-xs font-medium text-slate-900">{{ $systemReadiness['embedding_model'] }} ({{ $systemReadiness['embedding_dimensions'] }}d)</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-slate-500">Vector Storage</dt>
                        <dd class="font-mono text-xs font-medium text-slate-900">
                            {{ $systemReadiness['vector_driver'] }}
                            @if ($systemReadiness['vector_driver'] === 'qdrant')
                                · {{ $systemReadiness['qdrant_collection'] }}
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-slate-500">Kunci API Gemini</dt>
                        <dd class="text-xs font-medium {{ $systemReadiness['gemini_configured'] ? 'text-[#0E4B32]' : 'text-amber-700' }}">
                            {{ $systemReadiness['gemini_configured'] ? 'Terkonfigurasi di server' : 'Belum diisi di .env' }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-slate-500">Integrasi SIB-BM</dt>
                        <dd class="text-xs font-medium {{ $systemReadiness['sibbm_enabled'] ? 'text-[#0E4B32]' : 'text-slate-600' }}">
                            {{ $systemReadiness['sibbm_enabled'] ? 'API Aktif' : 'Mode Knowledge Base Lokal' }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Recent Conversations -->
        <div class="rounded-xl border border-slate-200 bg-white">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Aktivitas Percakapan Terbaru</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Sesi interaksi pengunjung melalui widget chatbot</p>
                </div>
            </div>

            @if ($recentConversations->isEmpty())
                <div class="px-6 py-10 text-center">
                    <p class="text-sm font-medium text-slate-700">Belum ada riwayat percakapan tercatat.</p>
                    <p class="mt-1 text-xs text-slate-500">
                        Percakapan pengunjung akan muncul di sini setelah endpoint REST API (Tahap 5) dan Widget Chatbot (Tahap 6) aktif digunakan.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-medium text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Token Sesi</th>
                                <th class="px-4 py-3">Domain Asal</th>
                                <th class="px-4 py-3 text-right">Jumlah Pesan</th>
                                <th class="px-6 py-3 text-right">Pesan Terakhir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentConversations as $conversation)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="px-6 py-3.5 font-mono text-xs text-slate-800">
                                        {{ \Illuminate\Support\Str::limit($conversation->session_token, 16) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-600">
                                        {{ $conversation->origin_domain ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-mono tabular-nums text-slate-700">
                                        {{ $conversation->messages_count }}
                                    </td>
                                    <td class="px-6 py-3.5 text-right font-mono text-xs text-slate-500">
                                        {{ optional($conversation->last_message_at)->format('d/m/Y H:i') ?? '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
