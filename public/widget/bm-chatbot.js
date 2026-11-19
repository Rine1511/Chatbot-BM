(function () {
    'use strict';

    if (window.__BMChatbotWidgetLoaded) {
        return;
    }
    window.__BMChatbotWidgetLoaded = true;

    var scriptEl = document.currentScript || (function () {
        var scripts = document.getElementsByTagName('script');
        for (var i = scripts.length - 1; i >= 0; i--) {
            if (scripts[i].src && scripts[i].src.indexOf('bm-chatbot.js') !== -1) {
                return scripts[i];
            }
        }
        return null;
    })();

    var inferredOrigin = '';
    if (scriptEl && scriptEl.src) {
        try {
            inferredOrigin = new URL(scriptEl.src, window.location.href).origin;
        } catch (e) {
            inferredOrigin = window.location.origin;
        }
    } else {
        inferredOrigin = window.location.origin;
    }

    var rawBase = (scriptEl && scriptEl.getAttribute('data-api-base')) || inferredOrigin;
    var API_BASE = rawBase.replace(/\/+$/, '') + '/api/v1/chat';
    var WIDGET_TITLE = (scriptEl && scriptEl.getAttribute('data-title')) || 'Asisten Virtual Bahrul Maghfiroh';
    var STORAGE_KEY = 'bm_widget_session_token';
    var DEFAULT_WELCOME = "Assalamu'alaikum warahmatullahi wabarakatuh. Selamat datang di layanan informasi Pondok Pesantren Bahrul Maghfiroh Kota Malang. Ada yang dapat kami bantu?";

    var host = document.createElement('div');
    host.id = 'bm-chatbot-widget-host';
    host.style.cssText = 'all: initial; position: fixed; bottom: 0; right: 0; z-index: 2147483000; font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;';

    var shadow = host.attachShadow({ mode: 'open' });

    var style = document.createElement('style');
    style.textContent = [
        ':host { all: initial; font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #0f172a; }',
        '*, *::before, *::after { box-sizing: border-box; }',
        '.bm-root { position: fixed; bottom: 20px; right: 20px; z-index: 2147483000; display: flex; flex-direction: column; align-items: flex-end; gap: 12px; }',
        '.bm-launcher { display: inline-flex; align-items: center; gap: 10px; min-height: 52px; padding: 0 18px; border: 1px solid rgba(212, 175, 55, 0.55); border-radius: 9999px; background: #0B3B27; color: #ffffff; font-size: 14px; font-weight: 600; cursor: pointer; box-shadow: 0 10px 25px rgba(7, 41, 27, 0.28); transition: transform 160ms ease, background-color 160ms ease; }',
        '.bm-launcher:hover { background: #0E4B32; transform: translateY(-1px); }',
        '.bm-launcher:focus-visible { outline: 2px solid #D4AF37; outline-offset: 2px; }',
        '.bm-launcher-icon { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 9999px; background: #C49A2A; color: #07291B; font-size: 12px; font-weight: 700; flex-shrink: 0; }',
        '.bm-panel { width: 380px; max-width: calc(100vw - 24px); height: 550px; max-height: calc(100dvh - 96px); display: none; flex-direction: column; overflow: hidden; border-radius: 16px; border: 1px solid #cbd5e1; background: #ffffff; box-shadow: 0 20px 45px rgba(15, 23, 42, 0.22); }',
        '.bm-panel.is-open { display: flex; }',
        '.bm-header { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 16px; background: #0B3B27; color: #ffffff; border-bottom: 2px solid #C49A2A; }',
        '.bm-header-brand { display: flex; align-items: center; gap: 10px; min-width: 0; }',
        '.bm-badge { width: 32px; height: 32px; border-radius: 8px; background: #C49A2A; color: #07291B; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; }',
        '.bm-title-wrap { min-width: 0; }',
        '.bm-title { margin: 0; font-size: 14px; font-weight: 700; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #ffffff; }',
        '.bm-subtitle { margin: 2px 0 0; font-size: 11px; color: rgba(236, 253, 245, 0.8); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }',
        '.bm-header-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }',
        '.bm-icon-btn { min-width: 32px; height: 32px; padding: 0 8px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.08); color: #ffffff; font-size: 11px; font-weight: 600; cursor: pointer; }',
        '.bm-icon-btn:hover { background: rgba(255, 255, 255, 0.16); }',
        '.bm-alert { display: none; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 14px; background: #fffbeb; border-bottom: 1px solid #fde68a; color: #78350f; font-size: 12px; }',
        '.bm-alert.is-visible { display: flex; }',
        '.bm-alert-close { border: none; background: transparent; color: #78350f; font-weight: 700; font-size: 11px; cursor: pointer; }',
        '.bm-messages { flex: 1; overflow-y: auto; padding: 14px; background: #f8faf9; display: flex; flex-direction: column; gap: 12px; }',
        '.bm-msg-row { display: flex; flex-direction: column; max-width: 86%; }',
        '.bm-msg-row.is-user { align-self: flex-end; align-items: flex-end; }',
        '.bm-msg-row.is-assistant { align-self: flex-start; align-items: flex-start; }',
        '.bm-bubble { padding: 10px 13px; border-radius: 14px; font-size: 13px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }',
        '.bm-msg-row.is-user .bm-bubble { background: #0B3B27; color: #ffffff; border-bottom-right-radius: 4px; }',
        '.bm-msg-row.is-assistant .bm-bubble { background: #ffffff; color: #1e293b; border: 1px solid #e2e8f0; border-bottom-left-radius: 4px; }',
        '.bm-sources { margin-top: 6px; padding-top: 6px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #475569; width: 100%; }',
        '.bm-sources-title { margin: 0 0 3px; font-weight: 600; color: #0E4B32; }',
        '.bm-source-item { margin: 2px 0; }',
        '.bm-source-link { color: #A67C16; font-weight: 600; text-decoration: none; }',
        '.bm-source-link:hover { text-decoration: underline; }',
        '.bm-meta { margin-top: 4px; font-size: 10px; color: #94a3b8; }',
        '.bm-typing { display: none; padding: 8px 14px; background: #f8faf9; border-top: 1px solid #e2e8f0; font-size: 12px; color: #475569; }',
        '.bm-typing.is-visible { display: block; }',
        '.bm-quick-bar { display: flex; gap: 6px; overflow-x: auto; padding: 8px 14px; background: #ffffff; border-top: 1px solid #e2e8f0; }',
        '.bm-quick-chip { flex-shrink: 0; border: 1px solid #cbd5e1; background: #f8fafc; color: #0B3B27; border-radius: 8px; padding: 6px 10px; font-size: 11px; font-weight: 600; cursor: pointer; white-space: nowrap; }',
        '.bm-quick-chip:hover { background: #eef6f1; border-color: #0E4B32; }',
        '.bm-form { display: flex; align-items: flex-end; gap: 8px; padding: 10px 14px 12px; background: #ffffff; border-top: 1px solid #e2e8f0; }',
        '.bm-input { flex: 1; min-height: 40px; max-height: 96px; resize: none; border: 1px solid #cbd5e1; border-radius: 10px; padding: 9px 12px; font-family: inherit; font-size: 13px; color: #0f172a; background: #f8fafc; }',
        '.bm-input:focus { outline: none; border-color: #0E4B32; background: #ffffff; }',
        '.bm-send { min-height: 40px; padding: 0 14px; border: none; border-radius: 10px; background: #0B3B27; color: #ffffff; font-size: 13px; font-weight: 600; cursor: pointer; flex-shrink: 0; }',
        '.bm-send:hover { background: #0E4B32; }',
        '.bm-send:disabled { opacity: 0.55; cursor: not-allowed; }',
        '@media (max-width: 639px) {',
        '  .bm-root { bottom: 12px; right: 12px; left: 12px; }',
        '  .bm-panel { width: 100%; max-width: 100%; height: min(78dvh, 560px); max-height: calc(100dvh - 84px); }',
        '  .bm-launcher-label { display: none; }',
        '  .bm-launcher { width: 54px; height: 54px; padding: 0; justify-content: center; }',
        '}',
        '@media (prefers-reduced-motion: reduce) { .bm-launcher { transition: none; } }'
    ].join('\n');

    var root = document.createElement('div');
    root.className = 'bm-root';

    var panel = document.createElement('section');
    panel.className = 'bm-panel';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', WIDGET_TITLE);

    // Header
    var header = document.createElement('div');
    header.className = 'bm-header';

    var brand = document.createElement('div');
    brand.className = 'bm-header-brand';
    var badge = document.createElement('span');
    badge.className = 'bm-badge';
    badge.textContent = 'BM';
    var titleWrap = document.createElement('div');
    titleWrap.className = 'bm-title-wrap';
    var titleEl = document.createElement('h2');
    titleEl.className = 'bm-title';
    titleEl.textContent = WIDGET_TITLE;
    var subtitleEl = document.createElement('p');
    subtitleEl.className = 'bm-subtitle';
    subtitleEl.textContent = 'Pondok Pesantren Bahrul Maghfiroh';
    titleWrap.appendChild(titleEl);
    titleWrap.appendChild(subtitleEl);
    brand.appendChild(badge);
    brand.appendChild(titleWrap);

    var actions = document.createElement('div');
    actions.className = 'bm-header-actions';
    var resetBtn = document.createElement('button');
    resetBtn.type = 'button';
    resetBtn.className = 'bm-icon-btn';
    resetBtn.textContent = 'Baru';
    resetBtn.title = 'Mulai percakapan baru';
    var closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'bm-icon-btn';
    closeBtn.setAttribute('aria-label', 'Tutup jendela chatbot');
    closeBtn.textContent = 'Tutup';
    actions.appendChild(resetBtn);
    actions.appendChild(closeBtn);

    header.appendChild(brand);
    header.appendChild(actions);

    // Alert bar
    var alertBox = document.createElement('div');
    alertBox.className = 'bm-alert';
    alertBox.setAttribute('role', 'alert');
    var alertMsg = document.createElement('span');
    var alertClose = document.createElement('button');
    alertClose.type = 'button';
    alertClose.className = 'bm-alert-close';
    alertClose.textContent = 'Tutup';
    alertBox.appendChild(alertMsg);
    alertBox.appendChild(alertClose);

    // Messages container
    var messagesEl = document.createElement('div');
    messagesEl.className = 'bm-messages';
    messagesEl.setAttribute('aria-live', 'polite');

    // Typing indicator
    var typingEl = document.createElement('div');
    typingEl.className = 'bm-typing';
    typingEl.textContent = 'Asisten sedang mengetik...';

    // Quick prompt chips
    var quickBar = document.createElement('div');
    quickBar.className = 'bm-quick-bar';
    var quickPrompts = [
        { label: 'Profil Pondok', text: 'Bagaimana profil Pondok Pesantren Bahrul Maghfiroh?' },
        { label: 'Pendaftaran', text: 'Bagaimana cara pendaftaran santri baru?' },
        { label: 'Bangunan SIB-BM', text: 'Apa saja bangunan dan fasilitas di pondok?' }
    ];
    quickPrompts.forEach(function (qp) {
        var chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'bm-quick-chip';
        chip.textContent = qp.label;
        chip.addEventListener('click', function () {
            sendMessage(qp.text);
        });
        quickBar.appendChild(chip);
    });

    // Form
    var form = document.createElement('form');
    form.className = 'bm-form';
    form.setAttribute('novalidate', 'novalidate');
    var input = document.createElement('textarea');
    input.className = 'bm-input';
    input.rows = 1;
    input.maxLength = 1000;
    input.placeholder = 'Tulis pertanyaan Anda...';
    input.setAttribute('aria-label', 'Tulis pertanyaan Anda');
    var sendBtn = document.createElement('button');
    sendBtn.type = 'submit';
    sendBtn.className = 'bm-send';
    sendBtn.textContent = 'Kirim';
    form.appendChild(input);
    form.appendChild(sendBtn);

    panel.appendChild(header);
    panel.appendChild(alertBox);
    panel.appendChild(messagesEl);
    panel.appendChild(typingEl);
    panel.appendChild(quickBar);
    panel.appendChild(form);

    // Floating Launcher Button
    var launcher = document.createElement('button');
    launcher.type = 'button';
    launcher.className = 'bm-launcher';
    launcher.setAttribute('aria-label', 'Buka Asisten Virtual Bahrul Maghfiroh');
    launcher.setAttribute('aria-expanded', 'false');

    var launcherIcon = document.createElement('span');
    launcherIcon.className = 'bm-launcher-icon';
    launcherIcon.textContent = 'BM';
    var launcherLabel = document.createElement('span');
    launcherLabel.className = 'bm-launcher-label';
    launcherLabel.textContent = 'Tanya Asisten BM';
    launcher.appendChild(launcherIcon);
    launcher.appendChild(launcherLabel);

    root.appendChild(panel);
    root.appendChild(launcher);
    shadow.appendChild(style);
    shadow.appendChild(root);

    function mount() {
        if (!document.body) return;
        if (!document.getElementById('bm-chatbot-widget-host')) {
            document.body.appendChild(host);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount);
    } else {
        mount();
    }

    var sessionToken = '';
    try {
        sessionToken = window.localStorage.getItem(STORAGE_KEY) || '';
    } catch (e) {
        sessionToken = '';
    }
    var isOpen = false;
    var isInitialized = false;
    var isBusy = false;
    var welcomeText = DEFAULT_WELCOME;

    function saveToken(token) {
        sessionToken = token || '';
        try {
            if (sessionToken) {
                window.localStorage.setItem(STORAGE_KEY, sessionToken);
            } else {
                window.localStorage.removeItem(STORAGE_KEY);
            }
        } catch (e) {}
    }

    function showAlert(msg) {
        alertMsg.textContent = msg;
        alertBox.classList.add('is-visible');
    }

    function hideAlert() {
        alertBox.classList.remove('is-visible');
    }

    function setBusy(busy) {
        isBusy = busy;
        sendBtn.disabled = busy;
        if (busy) {
            typingEl.classList.add('is-visible');
        } else {
            typingEl.classList.remove('is-visible');
        }
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function formatTime(iso) {
        var d = iso ? new Date(iso) : new Date();
        if (isNaN(d.getTime())) return '';
        return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    function appendMessage(msg) {
        var row = document.createElement('div');
        var isUser = msg.sender_type === 'user';
        row.className = 'bm-msg-row ' + (isUser ? 'is-user' : 'is-assistant');

        var bubble = document.createElement('div');
        bubble.className = 'bm-bubble';
        bubble.textContent = msg.content || '';
        row.appendChild(bubble);

        if (!isUser && Array.isArray(msg.matched_sources) && msg.matched_sources.length > 0) {
            var srcBox = document.createElement('div');
            srcBox.className = 'bm-sources';
            var srcTitle = document.createElement('p');
            srcTitle.className = 'bm-sources-title';
            srcTitle.textContent = 'Referensi:';
            srcBox.appendChild(srcTitle);

            var seen = {};
            msg.matched_sources.forEach(function (src) {
                var label = src.title || src.source_label || 'Sumber Resmi';
                if (seen[label]) return;
                seen[label] = true;

                var item = document.createElement('div');
                item.className = 'bm-source-item';
                item.appendChild(document.createTextNode(label + (src.category ? ' · ' + src.category : '')));

                if (src.source_url && /^https?:\/\//i.test(src.source_url)) {
                    item.appendChild(document.createTextNode(' · '));
                    var a = document.createElement('a');
                    a.className = 'bm-source-link';
                    a.href = src.source_url;
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                    a.textContent = 'Lihat';
                    item.appendChild(a);
                }
                srcBox.appendChild(item);
            });
            row.appendChild(srcBox);
        }

        var meta = document.createElement('span');
        meta.className = 'bm-meta';
        var t = formatTime(msg.created_at);
        meta.textContent = (isUser ? 'Anda' : 'Asisten BM') + (t ? ' · ' + t : '');
        row.appendChild(meta);

        messagesEl.appendChild(row);
        messagesEl.scrollTop = messagesEl.scrollHeight;
        return row;
    }

    function renderWelcome(text) {
        messagesEl.innerHTML = '';
        appendMessage({
            sender_type: 'assistant',
            content: text || welcomeText,
            created_at: new Date().toISOString(),
            matched_sources: []
        });
    }

    function startConversation() {
        hideAlert();
        setBusy(true);
        return fetch(API_BASE + '/conversations', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                origin_domain: window.location.hostname
            })
        })
            .then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (data) {
                    return { ok: res.ok, status: res.status, data: data };
                });
            })
            .then(function (res) {
                if (!res.ok) {
                    showAlert(res.data.message || 'Tidak dapat memulai sesi percakapan.');
                    renderWelcome(welcomeText);
                    return;
                }
                if (res.data && res.data.data) {
                    if (res.data.data.chatbot_name) {
                        titleEl.textContent = res.data.data.chatbot_name;
                    }
                    if (res.data.data.welcome_message) {
                        welcomeText = res.data.data.welcome_message;
                    }
                    saveToken(res.data.data.session_token || '');
                }
                renderWelcome(welcomeText);
            })
            .catch(function () {
                showAlert('Gagal terhubung ke layanan chatbot.');
                renderWelcome(welcomeText);
            })
            .finally(function () {
                setBusy(false);
            });
    }

    function initConversation() {
        if (isInitialized) return;
        isInitialized = true;

        if (!sessionToken || !/^[a-f0-9]{64}$/i.test(sessionToken)) {
            startConversation();
            return;
        }

        fetch(API_BASE + '/conversations/' + encodeURIComponent(sessionToken), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (res) {
                if (res.status === 404 || res.status === 422) {
                    saveToken('');
                    return startConversation();
                }
                return res.json().then(function (payload) {
                    var history = (payload.data && payload.data.messages) || [];
                    messagesEl.innerHTML = '';
                    appendMessage({
                        sender_type: 'assistant',
                        content: welcomeText,
                        created_at: payload.data && payload.data.started_at,
                        matched_sources: []
                    });
                    history.forEach(appendMessage);
                });
            })
            .catch(function () {
                renderWelcome(welcomeText);
                showAlert('Koneksi terganggu saat memuat riwayat percakapan.');
            });
    }

    function sendMessage(rawText) {
        var text = (rawText || '').trim();
        hideAlert();
        if (text.length < 2) {
            showAlert('Mohon tuliskan pertanyaan minimal 2 karakter.');
            input.focus();
            return;
        }
        if (isBusy) return;

        var ensureSession = (sessionToken && /^[a-f0-9]{64}$/i.test(sessionToken))
            ? Promise.resolve()
            : startConversation();

        ensureSession.then(function () {
            if (!sessionToken) return;

            var userRow = appendMessage({
                sender_type: 'user',
                content: text,
                created_at: new Date().toISOString()
            });
            input.value = '';
            setBusy(true);

            fetch(API_BASE + '/messages', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Widget-Session': sessionToken
                },
                body: JSON.stringify({
                    session_token: sessionToken,
                    message: text
                })
            })
                .then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (payload) {
                        return { ok: res.ok, status: res.status, payload: payload };
                    });
                })
                .then(function (res) {
                    if (res.status === 404) {
                        userRow.remove();
                        saveToken('');
                        showAlert('Sesi percakapan berakhir, memulai sesi baru...');
                        return startConversation();
                    }
                    if (!res.ok) {
                        userRow.remove();
                        input.value = text;
                        showAlert(res.payload.message || 'Layanan chatbot sedang sibuk. Silakan coba lagi.');
                        return;
                    }
                    if (res.payload && res.payload.data && res.payload.data.assistant_message) {
                        appendMessage(res.payload.data.assistant_message);
                    }
                })
                .catch(function () {
                    userRow.remove();
                    input.value = text;
                    showAlert('Koneksi ke server chatbot gagal.');
                })
                .finally(function () {
                    setBusy(false);
                });
        });
    }

    function togglePanel(open) {
        isOpen = typeof open === 'boolean' ? open : !isOpen;
        if (isOpen) {
            panel.classList.add('is-open');
            launcher.setAttribute('aria-expanded', 'true');
            initConversation();
            setTimeout(function () { input.focus(); }, 60);
        } else {
            panel.classList.remove('is-open');
            launcher.setAttribute('aria-expanded', 'false');
        }
    }

    launcher.addEventListener('click', function () { togglePanel(); });
    closeBtn.addEventListener('click', function () { togglePanel(false); });
    alertClose.addEventListener('click', hideAlert);
    resetBtn.addEventListener('click', function () {
        if (isBusy) return;
        saveToken('');
        startConversation();
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        sendMessage(input.value);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage(input.value);
        }
    });
})();
