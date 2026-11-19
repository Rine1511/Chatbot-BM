<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard Admin') — Asisten Virtual Bahrul Maghfiroh</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="min-h-screen bg-[#F7F9F8] text-slate-900">
    <div class="flex min-h-screen">
        <!-- Mobile Sidebar Backdrop -->
        <div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-slate-950/50 lg:hidden" aria-hidden="true"></div>

        <!-- Sidebar Navigation -->
        <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-[#0B3B27] text-white transition-transform duration-200 lg:static lg:translate-x-0">
            <div class="flex h-16 items-center justify-between border-b border-white/10 px-5">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 min-w-0">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#C49A2A] text-sm font-bold text-[#07291B]">
                        BM
                    </span>
                    <span class="truncate text-sm font-semibold tracking-tight text-white">
                        Asisten Virtual BM
                    </span>
                </a>
                <button type="button" id="close-sidebar-btn" class="rounded-md p-1.5 text-white/70 hover:bg-white/10 hover:text-white lg:hidden" aria-label="Tutup navigasi">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5" aria-label="Navigasi Admin">
                <p class="px-3 pb-2 text-xs font-medium text-emerald-200/70">Menu Utama</p>

                <a href="{{ route('admin.dashboard') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-white/12 text-white' : 'text-emerald-100/80 hover:bg-white/8 hover:text-white' }}">
                    <svg class="h-4 w-4 shrink-0 text-[#D4AF37]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                    </svg>
                    <span class="truncate">Dashboard</span>
                </a>

                <p class="px-3 pt-5 pb-2 text-xs font-medium text-emerald-200/70">Basis Pengetahuan</p>

                <a href="{{ route('admin.categories.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('admin.categories.*') ? 'bg-white/12 text-white' : 'text-emerald-100/80 hover:bg-white/8 hover:text-white' }}">
                    <svg class="h-4 w-4 shrink-0 text-[#D4AF37]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/>
                    </svg>
                    <span class="truncate">Kategori</span>
                </a>

                <a href="{{ route('admin.faqs.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('admin.faqs.*') ? 'bg-white/12 text-white' : 'text-emerald-100/80 hover:bg-white/8 hover:text-white' }}">
                    <svg class="h-4 w-4 shrink-0 text-[#D4AF37]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9.75a3.75 3.75 0 117.5 0c0 1.657-1.12 2.404-2.25 3.12-.75.475-1.5.948-1.5 1.88m0 3h.008v.008H12v-.008z"/>
                    </svg>
                    <span class="truncate">Manajemen FAQ</span>
                </a>

                <a href="{{ route('admin.knowledge.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('admin.knowledge.*') ? 'bg-white/12 text-white' : 'text-emerald-100/80 hover:bg-white/8 hover:text-white' }}">
                    <svg class="h-4 w-4 shrink-0 text-[#D4AF37]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                    </svg>
                    <span class="truncate">Knowledge Base</span>
                </a>

                <p class="px-3 pt-5 pb-2 text-xs font-medium text-emerald-200/70">Layanan &amp; Sistem</p>

                <a href="{{ route('admin.sibbm.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('admin.sibbm.*') ? 'bg-white/12 text-white' : 'text-emerald-100/80 hover:bg-white/8 hover:text-white' }}">
                    <svg class="h-4 w-4 shrink-0 text-[#D4AF37]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15"/>
                    </svg>
                    <span class="truncate">Integrasi SIB-BM</span>
                </a>

                <a href="{{ route('chat.index') }}" target="_blank" rel="noopener noreferrer"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-emerald-100/80 transition-colors hover:bg-white/8 hover:text-white">
                    <svg class="h-4 w-4 shrink-0 text-[#D4AF37]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM2.25 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 2.25 14.189 2.25 12z"/>
                    </svg>
                    <span class="truncate">Halaman Chat</span>
                </a>

                @php
                    $upcomingLinks = [
                        ['key' => 'conversations', 'label' => 'Riwayat Percakapan', 'stage' => 'Tahap 5'],
                        ['key' => 'settings', 'label' => 'Pengaturan Chatbot', 'stage' => 'Tahap 4'],
                    ];
                @endphp

                @foreach ($upcomingLinks as $item)
                    @php
                        $isActiveModule = request()->routeIs('admin.modules.show') && request()->route('module') === $item['key'];
                    @endphp
                    <a href="{{ route('admin.modules.show', $item['key']) }}"
                       class="flex items-center justify-between gap-2 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ $isActiveModule ? 'bg-white/12 text-white' : 'text-emerald-100/80 hover:bg-white/8 hover:text-white' }}">
                        <span class="truncate">{{ $item['label'] }}</span>
                        <span class="shrink-0 text-[11px] font-mono text-emerald-200/60">{{ $item['stage'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="border-t border-white/10 px-4 py-4">
                <p class="text-xs font-medium text-emerald-100/90 truncate">{{ auth()->user()->name }}</p>
                <p class="mt-0.5 text-xs text-emerald-200/65 truncate">{{ auth()->user()->email }}</p>
            </div>
        </aside>

        <!-- Main Viewport -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Top Header -->
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3 min-w-0">
                    <button type="button" id="open-sidebar-btn" class="rounded-lg border border-slate-200 p-2 text-slate-600 hover:bg-slate-50 lg:hidden" aria-label="Buka navigasi">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <div class="flex items-center gap-2 text-sm min-w-0">
                        <span class="text-slate-400 whitespace-nowrap">Panel Admin</span>
                        <span class="text-slate-300" aria-hidden="true">/</span>
                        <h1 class="truncate font-semibold text-slate-900">@yield('header_title', 'Dashboard')</h1>
                    </div>
                </div>

                <div class="flex items-center gap-4 shrink-0">
                    <span class="hidden text-xs text-slate-500 sm:inline whitespace-nowrap">
                        Pondok Pesantren Bahrul Maghfiroh
                    </span>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 transition-colors hover:border-slate-300 hover:bg-slate-50 whitespace-nowrap shrink-0 cursor-pointer">
                            Keluar
                        </button>
                    </form>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                @if (session('status'))
                    <div class="mb-6 flex items-start justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
                        <ul class="list-disc space-y-1 pl-4">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>

            <footer class="border-t border-slate-200 bg-white px-4 py-3.5 text-xs text-slate-500 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <span>Asisten Virtual Bahrul Maghfiroh — Aplikasi Standalone Chatbot</span>
                    <span>Website resmi: <a href="https://bahrulmaghfirohmalang.or.id/" target="_blank" rel="noopener noreferrer" class="font-medium text-[#0E4B32] hover:underline">bahrulmaghfirohmalang.or.id</a></span>
                </div>
            </footer>
        </div>
    </div>

    <!-- Accessible Confirmation Modal for Deletions -->
    <div id="confirm-delete-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="confirm-delete-title">
        <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-lg">
            <h3 id="confirm-delete-title" class="text-base font-semibold text-slate-900">Konfirmasi Penghapusan</h3>
            <p id="confirm-delete-message" class="mt-2 text-sm text-slate-600">Apakah Anda yakin ingin menghapus data ini?</p>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" id="confirm-delete-cancel" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer">
                    Batal
                </button>
                <button type="button" id="confirm-delete-submit" class="rounded-lg bg-red-600 px-4 py-2 text-xs font-semibold text-white hover:bg-red-700 cursor-pointer">
                    Ya, Hapus
                </button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const sidebar = document.getElementById('admin-sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            const openBtn = document.getElementById('open-sidebar-btn');
            const closeBtn = document.getElementById('close-sidebar-btn');

            function openSidebar() {
                sidebar?.classList.remove('-translate-x-full');
                backdrop?.classList.remove('hidden');
            }

            function closeSidebar() {
                sidebar?.classList.add('-translate-x-full');
                backdrop?.classList.add('hidden');
            }

            openBtn?.addEventListener('click', openSidebar);
            closeBtn?.addEventListener('click', closeSidebar);
            backdrop?.addEventListener('click', closeSidebar);

            // Confirmation modal handler for delete forms
            const modal = document.getElementById('confirm-delete-modal');
            const messageEl = document.getElementById('confirm-delete-message');
            const cancelBtn = document.getElementById('confirm-delete-cancel');
            const submitBtn = document.getElementById('confirm-delete-submit');
            let pendingForm = null;

            document.addEventListener('submit', function (event) {
                const form = event.target;
                if (!(form instanceof HTMLFormElement) || !form.dataset.confirmMessage) {
                    return;
                }
                if (form.dataset.confirmed === 'true') {
                    return;
                }
                event.preventDefault();
                pendingForm = form;
                if (messageEl) {
                    messageEl.textContent = form.dataset.confirmMessage;
                }
                modal?.classList.remove('hidden');
                modal?.classList.add('flex');
            });

            function closeModal() {
                pendingForm = null;
                modal?.classList.add('hidden');
                modal?.classList.remove('flex');
            }

            cancelBtn?.addEventListener('click', closeModal);
            submitBtn?.addEventListener('click', function () {
                if (pendingForm) {
                    pendingForm.dataset.confirmed = 'true';
                    pendingForm.submit();
                }
            });
            modal?.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
