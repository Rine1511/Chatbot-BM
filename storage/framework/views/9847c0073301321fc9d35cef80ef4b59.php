<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($chatbotName); ?> — Pondok Pesantren Bahrul Maghfiroh Kota Malang</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(asset('css/admin.css')); ?>">
    <style>
        @keyframes bm-typing-fade {
            0%, 100% { opacity: 0.35; }
            50% { opacity: 1; }
        }
        .bm-typing-dot {
            animation: bm-typing-fade 1.2s ease-in-out infinite;
        }
        .bm-typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .bm-typing-dot:nth-child(3) { animation-delay: 0.4s; }
        @media (prefers-reduced-motion: reduce) {
            .bm-typing-dot { animation: none; opacity: 0.85; }
        }
    </style>
</head>
<body class="min-h-screen bg-[#F7F9F8] text-slate-900">
    <div class="flex min-h-screen flex-col">
        <!-- Header -->
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-[#0B3B27] text-white">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                <a href="<?php echo e(route('chat.index')); ?>" class="flex items-center gap-3 min-w-0">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#C49A2A] text-sm font-bold text-[#07291B]">
                        BM
                    </span>
                    <span class="truncate text-sm font-semibold tracking-tight text-white sm:text-base">
                        <?php echo e($chatbotName); ?>

                    </span>
                </a>

                <nav class="hidden md:flex items-center gap-6 text-sm text-emerald-100/85">
                    <a href="https://bahrulmaghfirohmalang.or.id/" target="_blank" rel="noopener noreferrer" class="hover:text-white hover:underline whitespace-nowrap shrink-0">
                        Website Resmi
                    </a>
                </nav>

                <div class="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        id="bm-new-chat-btn"
                        class="inline-flex min-h-[40px] items-center justify-center rounded-lg border border-[#D4AF37]/50 bg-[#0E4B32] px-3.5 py-2 text-xs font-semibold text-white transition-colors hover:bg-[#146342] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#D4AF37] whitespace-nowrap shrink-0 cursor-pointer"
                    >
                        Percakapan Baru
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Workspace -->
        <main class="mx-auto flex w-full max-w-6xl flex-1 flex-col px-0 py-0 sm:px-6 sm:py-6">
            <div class="grid flex-1 grid-cols-1 overflow-hidden border-slate-200 bg-white sm:rounded-xl sm:border lg:grid-cols-[290px_1fr]">
                <!-- Left Institutional Panel (Desktop) -->
                <aside class="hidden flex-col justify-between border-r border-slate-200 bg-[#FBFDFC] p-6 lg:flex">
                    <div class="space-y-6">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-[#A67C16]">
                                Layanan Informasi Resmi
                            </p>
                            <h1 class="mt-1.5 text-lg font-bold tracking-tight text-[#0B3B27]">
                                Pondok Pesantren Bahrul Maghfiroh
                            </h1>
                            <p class="mt-2 text-xs leading-relaxed text-slate-600">
                                Ajukan pertanyaan seputar profil pondok, pendidikan, pendaftaran santri, kegiatan, fasilitas, dan data bangunan SIB-BM.
                            </p>
                        </div>

                        <div class="border-t border-slate-200 pt-5">
                            <p class="text-xs font-semibold text-slate-800">Topik Pertanyaan Cepat</p>
                            <div class="mt-3 flex flex-col gap-2" id="bm-quick-prompts-desktop">
                                <button type="button" data-prompt="Bagaimana profil singkat Pondok Pesantren Bahrul Maghfiroh?" class="bm-quick-btn rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-left text-xs font-medium text-slate-700 transition-colors hover:border-[#0E4B32]/40 hover:bg-[#F3F8F5] hover:text-[#0B3B27] cursor-pointer">
                                    Profil Pondok Pesantren
                                </button>
                                <button type="button" data-prompt="Bagaimana prosedur pendaftaran santri baru?" class="bm-quick-btn rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-left text-xs font-medium text-slate-700 transition-colors hover:border-[#0E4B32]/40 hover:bg-[#F3F8F5] hover:text-[#0B3B27] cursor-pointer">
                                    Pendaftaran Santri Baru
                                </button>
                                <button type="button" data-prompt="Apa saja fasilitas dan informasi bangunan yang tersedia?" class="bm-quick-btn rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-left text-xs font-medium text-slate-700 transition-colors hover:border-[#0E4B32]/40 hover:bg-[#F3F8F5] hover:text-[#0B3B27] cursor-pointer">
                                    Fasilitas &amp; Bangunan SIB-BM
                                </button>
                                <button type="button" data-prompt="Apa saja jenjang pendidikan dan kegiatan santri?" class="bm-quick-btn rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-left text-xs font-medium text-slate-700 transition-colors hover:border-[#0E4B32]/40 hover:bg-[#F3F8F5] hover:text-[#0B3B27] cursor-pointer">
                                    Pendidikan &amp; Kegiatan
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-4 text-xs text-slate-500">
                        <p>Jawaban disusun berdasarkan FAQ resmi, Knowledge Base, dan data bangunan pondok.</p>
                    </div>
                </aside>

                <!-- Chat Column -->
                <section class="flex h-[calc(100dvh-4rem)] flex-col bg-white sm:h-[calc(100dvh-7.5rem)] sm:min-h-[540px]">
                    <!-- Mobile Quick Prompts Strip -->
                    <div class="flex items-center gap-2 overflow-x-auto border-b border-slate-200 bg-[#F7F9F8] px-4 py-2.5 lg:hidden">
                        <button type="button" data-prompt="Bagaimana profil singkat Pondok Pesantren Bahrul Maghfiroh?" class="bm-quick-btn shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:border-[#0E4B32]/40 cursor-pointer whitespace-nowrap">
                            Profil Pondok
                        </button>
                        <button type="button" data-prompt="Bagaimana prosedur pendaftaran santri baru?" class="bm-quick-btn shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:border-[#0E4B32]/40 cursor-pointer whitespace-nowrap">
                            Pendaftaran
                        </button>
                        <button type="button" data-prompt="Apa saja fasilitas dan informasi bangunan yang tersedia?" class="bm-quick-btn shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:border-[#0E4B32]/40 cursor-pointer whitespace-nowrap">
                            Bangunan SIB-BM
                        </button>
                        <button type="button" data-prompt="Apa saja jenjang pendidikan dan kegiatan santri?" class="bm-quick-btn shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:border-[#0E4B32]/40 cursor-pointer whitespace-nowrap">
                            Pendidikan
                        </button>
                    </div>

                    <!-- Status / Error Alert Banner -->
                    <div id="bm-alert-banner" class="hidden items-center justify-between gap-3 border-b border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-900" role="alert">
                        <span id="bm-alert-text"></span>
                        <button type="button" id="bm-alert-dismiss" class="shrink-0 font-semibold text-amber-900 hover:underline cursor-pointer">
                            Tutup
                        </button>
                    </div>

                    <!-- Messages Feed -->
                    <div
                        id="bm-chat-messages"
                        class="flex-1 space-y-4 overflow-y-auto px-4 py-5 sm:px-6"
                        aria-live="polite"
                        aria-label="Riwayat percakapan"
                    >
                        <!-- Rendered dynamically -->
                    </div>

                    <!-- Typing Indicator -->
                    <div id="bm-typing-indicator" class="hidden border-t border-slate-100 bg-white px-4 py-2.5 sm:px-6">
                        <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-[#F7F9F8] px-3.5 py-2 text-xs text-slate-600">
                            <span>Asisten sedang mengetik</span>
                            <span class="inline-flex items-center gap-1" aria-hidden="true">
                                <span class="bm-typing-dot h-1.5 w-1.5 rounded-full bg-[#0E4B32]"></span>
                                <span class="bm-typing-dot h-1.5 w-1.5 rounded-full bg-[#0E4B32]"></span>
                                <span class="bm-typing-dot h-1.5 w-1.5 rounded-full bg-[#0E4B32]"></span>
                            </span>
                        </div>
                    </div>

                    <!-- Input Composer -->
                    <form id="bm-chat-form" class="border-t border-slate-200 bg-white px-4 py-3.5 sm:px-6" novalidate>
                        <div class="flex items-end gap-2.5">
                            <div class="flex-1">
                                <label for="bm-chat-input" class="sr-only">Tulis pertanyaan Anda</label>
                                <textarea
                                    id="bm-chat-input"
                                    rows="1"
                                    maxlength="1000"
                                    placeholder="Tulis pertanyaan Anda tentang Pondok Pesantren Bahrul Maghfiroh..."
                                    class="block max-h-32 min-h-[44px] w-full resize-none rounded-xl border border-slate-300 bg-[#FBFDFC] px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-[#0E4B32] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
                                ></textarea>
                            </div>
                            <button
                                type="submit"
                                id="bm-send-btn"
                                class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-[#0B3B27] px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#0E4B32] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#C49A2A] disabled:cursor-not-allowed disabled:opacity-55 whitespace-nowrap shrink-0 cursor-pointer"
                            >
                                <span>Kirim</span>
                                <svg class="h-4 w-4 text-[#D4AF37]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                                </svg>
                            </button>
                        </div>
                        <div class="mt-1.5 flex items-center justify-between text-[11px] text-slate-400">
                            <span id="bm-validation-hint">Tekan Enter untuk mengirim, Shift+Enter untuk baris baru.</span>
                            <span class="font-mono tabular-nums" id="bm-char-counter">0/1000</span>
                        </div>
                    </form>
                </section>
            </div>
        </main>
    </div>

    <script>
        (function () {
            const API_BASE = <?php echo json_encode(url('/api/v1/chat'), JSON_UNESCAPED_SLASHES, 512) ?>;
            const DEFAULT_WELCOME = <?php echo json_encode($welcomeMessage, 15, 512) ?>;
            const STORAGE_KEY = 'bm_chat_session_token';

            const messagesEl = document.getElementById('bm-chat-messages');
            const typingEl = document.getElementById('bm-typing-indicator');
            const alertBanner = document.getElementById('bm-alert-banner');
            const alertText = document.getElementById('bm-alert-text');
            const alertDismiss = document.getElementById('bm-alert-dismiss');
            const formEl = document.getElementById('bm-chat-form');
            const inputEl = document.getElementById('bm-chat-input');
            const sendBtn = document.getElementById('bm-send-btn');
            const newChatBtn = document.getElementById('bm-new-chat-btn');
            const charCounter = document.getElementById('bm-char-counter');

            let sessionToken = localStorage.getItem(STORAGE_KEY) || '';
            let isBusy = false;

            function formatTime(isoString) {
                const date = isoString ? new Date(isoString) : new Date();
                if (Number.isNaN(date.getTime())) {
                    return '';
                }
                return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            }

            function showAlert(message) {
                if (!alertBanner || !alertText) return;
                alertText.textContent = message;
                alertBanner.classList.remove('hidden');
                alertBanner.classList.add('flex');
            }

            function hideAlert() {
                if (!alertBanner) return;
                alertBanner.classList.add('hidden');
                alertBanner.classList.remove('flex');
            }

            function setLoading(loading) {
                isBusy = loading;
                sendBtn.disabled = loading;
                if (loading) {
                    typingEl.classList.remove('hidden');
                } else {
                    typingEl.classList.add('hidden');
                }
                scrollToBottom();
            }

            function scrollToBottom() {
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }

            function createMessageNode(msg) {
                const isUser = msg.sender_type === 'user';
                const wrapper = document.createElement('div');
                wrapper.className = isUser ? 'flex justify-end' : 'flex justify-start';

                const container = document.createElement('div');
                container.className = isUser
                    ? 'max-w-[85%] sm:max-w-[72%]'
                    : 'max-w-[90%] sm:max-w-[78%]';

                const bubble = document.createElement('div');
                bubble.className = isUser
                    ? 'rounded-2xl rounded-br-sm bg-[#0B3B27] px-4 py-3 text-sm leading-relaxed text-white whitespace-pre-wrap break-words'
                    : 'rounded-2xl rounded-bl-sm border border-slate-200 bg-[#FBFDFC] px-4 py-3 text-sm leading-relaxed text-slate-800 whitespace-pre-wrap break-words';
                bubble.textContent = msg.content || '';
                container.appendChild(bubble);

                // Source citations (clean unboxed text metadata)
                if (!isUser && Array.isArray(msg.matched_sources) && msg.matched_sources.length > 0) {
                    const sourcesBox = document.createElement('div');
                    sourcesBox.className = 'mt-2 border-t border-slate-200/80 pt-2 text-xs text-slate-500 space-y-1';

                    const heading = document.createElement('p');
                    heading.className = 'font-semibold text-[#0E4B32]';
                    heading.textContent = 'Referensi Pengetahuan:';
                    sourcesBox.appendChild(heading);

                    const seen = new Set();
                    msg.matched_sources.forEach(function (src) {
                        const title = src.title || src.source_label || 'Sumber Resmi';
                        const dedupeKey = `${src.source_type || ''}:${title}`;
                        if (seen.has(dedupeKey)) return;
                        seen.add(dedupeKey);

                        const item = document.createElement('div');
                        item.className = 'flex flex-wrap items-center gap-1.5';

                        const label = document.createElement('span');
                        label.textContent = src.category ? `${title} · ${src.category}` : title;
                        item.appendChild(label);

                        if (src.source_url && /^https?:\/\//i.test(src.source_url)) {
                            const sep = document.createElement('span');
                            sep.setAttribute('aria-hidden', 'true');
                            sep.textContent = '·';
                            item.appendChild(sep);

                            const link = document.createElement('a');
                            link.href = src.source_url;
                            link.target = '_blank';
                            link.rel = 'noopener noreferrer';
                            link.className = 'font-medium text-[#A67C16] hover:underline';
                            link.textContent = 'Buka tautan';
                            item.appendChild(link);
                        }

                        sourcesBox.appendChild(item);
                    });

                    container.appendChild(sourcesBox);
                }

                // Meta row (sender label + timestamp)
                const meta = document.createElement('p');
                meta.className = isUser
                    ? 'mt-1 text-right text-[11px] text-slate-400 font-mono tabular-nums'
                    : 'mt-1 text-left text-[11px] text-slate-400 font-mono tabular-nums';
                const timeLabel = formatTime(msg.created_at);
                meta.textContent = isUser
                    ? `Anda${timeLabel ? ' · ' + timeLabel : ''}`
                    : `Asisten BM${timeLabel ? ' · ' + timeLabel : ''}`;
                container.appendChild(meta);

                wrapper.appendChild(container);
                return wrapper;
            }

            function renderWelcome(welcomeText) {
                messagesEl.innerHTML = '';
                messagesEl.appendChild(createMessageNode({
                    sender_type: 'assistant',
                    content: welcomeText || DEFAULT_WELCOME,
                    created_at: new Date().toISOString(),
                    matched_sources: [],
                }));
                scrollToBottom();
            }

            async function startNewConversation() {
                hideAlert();
                setLoading(true);
                try {
                    const response = await fetch(`${API_BASE}/conversations`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            origin_domain: window.location.hostname,
                        }),
                    });

                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        if (response.status === 429) {
                            showAlert(payload.message || 'Batas memulai percakapan tercapai. Mohon tunggu sebentar.');
                        } else {
                            showAlert(payload.message || 'Tidak dapat memulai sesi percakapan baru.');
                        }
                        renderWelcome(DEFAULT_WELCOME);
                        return;
                    }

                    sessionToken = payload.data?.session_token || '';
                    if (sessionToken) {
                        localStorage.setItem(STORAGE_KEY, sessionToken);
                    }
                    renderWelcome(payload.data?.welcome_message || DEFAULT_WELCOME);
                } catch (err) {
                    showAlert('Koneksi ke server gagal. Periksa jaringan Anda lalu muat ulang halaman.');
                    renderWelcome(DEFAULT_WELCOME);
                } finally {
                    setLoading(false);
                }
            }

            async function loadOrInitConversation() {
                if (!sessionToken || !/^[a-f0-9]{64}$/i.test(sessionToken)) {
                    await startNewConversation();
                    return;
                }

                try {
                    const response = await fetch(`${API_BASE}/conversations/${encodeURIComponent(sessionToken)}`, {
                        headers: { 'Accept': 'application/json' },
                    });

                    if (response.status === 404 || response.status === 422) {
                        localStorage.removeItem(STORAGE_KEY);
                        sessionToken = '';
                        await startNewConversation();
                        return;
                    }

                    if (!response.ok) {
                        renderWelcome(DEFAULT_WELCOME);
                        showAlert('Gagal memuat riwayat percakapan sebelumnya.');
                        return;
                    }

                    const payload = await response.json();
                    const history = payload.data?.messages || [];

                    messagesEl.innerHTML = '';
                    messagesEl.appendChild(createMessageNode({
                        sender_type: 'assistant',
                        content: DEFAULT_WELCOME,
                        created_at: payload.data?.started_at,
                        matched_sources: [],
                    }));

                    history.forEach(function (msg) {
                        messagesEl.appendChild(createMessageNode(msg));
                    });
                    scrollToBottom();
                } catch (err) {
                    renderWelcome(DEFAULT_WELCOME);
                    showAlert('Koneksi ke server terganggu saat memuat riwayat percakapan.');
                }
            }

            async function submitMessage(rawText) {
                const text = (rawText || '').trim();
                hideAlert();

                if (text.length < 2) {
                    showAlert('Mohon tuliskan pertanyaan minimal 2 karakter sebelum mengirim.');
                    inputEl.focus();
                    return;
                }

                if (isBusy) return;

                if (!sessionToken || !/^[a-f0-9]{64}$/i.test(sessionToken)) {
                    await startNewConversation();
                    if (!sessionToken) return;
                }

                // Tampilkan pesan pengguna secara optimistik setelah lolos validasi
                const tempUserNode = createMessageNode({
                    sender_type: 'user',
                    content: text,
                    created_at: new Date().toISOString(),
                });
                messagesEl.appendChild(tempUserNode);
                inputEl.value = '';
                charCounter.textContent = '0/1000';
                inputEl.style.height = 'auto';
                setLoading(true);

                try {
                    const response = await fetch(`${API_BASE}/messages`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Widget-Session': sessionToken,
                        },
                        body: JSON.stringify({
                            session_token: sessionToken,
                            message: text,
                        }),
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (response.status === 404) {
                        localStorage.removeItem(STORAGE_KEY);
                        sessionToken = '';
                        tempUserNode.remove();
                        showAlert('Sesi percakapan berakhir. Memulai sesi baru...');
                        await startNewConversation();
                        return;
                    }

                    if (!response.ok) {
                        tempUserNode.remove();
                        inputEl.value = text;
                        charCounter.textContent = `${text.length}/1000`;

                        if (response.status === 429) {
                            showAlert(payload.message || 'Batas penggunaan API tercapai. Tunggu sebentar sebelum mengirim pesan lagi.');
                        } else if (response.status === 422) {
                            const firstErr = payload.errors ? Object.values(payload.errors).flat()[0] : null;
                            showAlert(firstErr || payload.message || 'Input pesan tidak valid.');
                        } else {
                            showAlert(payload.message || 'Layanan asisten virtual sedang sibuk. Silakan coba sesaat lagi.');
                        }
                        return;
                    }

                    const assistantMsg = payload.data?.assistant_message;
                    if (assistantMsg) {
                        messagesEl.appendChild(createMessageNode(assistantMsg));
                    }
                    scrollToBottom();
                } catch (err) {
                    tempUserNode.remove();
                    inputEl.value = text;
                    charCounter.textContent = `${text.length}/1000`;
                    showAlert('Koneksi ke server gagal. Periksa jaringan internet Anda lalu kirim ulang.');
                } finally {
                    setLoading(false);
                }
            }

            formEl.addEventListener('submit', function (e) {
                e.preventDefault();
                submitMessage(inputEl.value);
            });

            inputEl.addEventListener('input', function () {
                const len = inputEl.value.length;
                charCounter.textContent = `${len}/1000`;
                inputEl.style.height = 'auto';
                inputEl.style.height = Math.min(inputEl.scrollHeight, 128) + 'px';
            });

            inputEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    submitMessage(inputEl.value);
                }
            });

            document.querySelectorAll('.bm-quick-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const prompt = btn.getAttribute('data-prompt') || '';
                    if (prompt) {
                        submitMessage(prompt);
                    }
                });
            });

            newChatBtn.addEventListener('click', function () {
                if (isBusy) return;
                localStorage.removeItem(STORAGE_KEY);
                sessionToken = '';
                startNewConversation();
            });

            alertDismiss.addEventListener('click', hideAlert);

            loadOrInitConversation();
        })();
    </script>
</body>
</html>
<?php /**PATH /app/applet/resources/views/chat/index.blade.php ENDPATH**/ ?>