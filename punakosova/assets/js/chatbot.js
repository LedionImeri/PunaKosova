/**
 * PunaBot - logjika e dritares së bisedës në frontend.
 * API key NUK ndodhet këtu: frontend-i flet vetëm me chatbot/chatbot_api.php.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-punabot]');
    if (!root) return;

    const endpoint = root.dataset.endpoint;
    const baseUrl = document.querySelector('meta[name="base-url"]')?.content || '';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const win = root.querySelector('.pb-window');
    const list = root.querySelector('[data-pb-messages]');
    const form = root.querySelector('[data-pb-form]');
    const textarea = root.querySelector('[data-pb-text]');
    const sendBtn = root.querySelector('[data-pb-send]');
    const suggestions = root.querySelector('[data-pb-suggestions]');
    const modeLabel = root.querySelector('[data-pb-mode]');
    const launcher = root.querySelector('.pb-launcher');
    const modeDot = root.querySelector('.pb-dot');
    let historyLoaded = false;
    let busy = false;
    let aiProvider = 'AI';

    // ---------- Ndihmëse ----------
    const escapeHtml = (s) => String(s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    /** Formatim i sigurt: escape -> **bold**, [link](/brendshëm), lista "- ". */
    function format(text) {
        const lines = escapeHtml(text).split(/\n/);
        let html = '';
        let inList = false;
        lines.forEach((line) => {
            let l = line
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (m, label, href) => {
                    const decoded = href.replace(/&amp;/g, '&');
                    // Lejo vetëm linke të brendshme të platformës
                    if (decoded.startsWith(baseUrl + '/') && !decoded.startsWith('//')) {
                        return '<a href="' + href + '">' + label + '</a>';
                    }
                    return label;
                });
            const item = l.match(/^\s*[-•]\s+(.*)$/);
            if (item) {
                if (!inList) { html += '<ul>'; inList = true; }
                html += '<li>' + item[1] + '</li>';
            } else {
                if (inList) { html += '</ul>'; inList = false; }
                html += l.trim() === '' ? '<br>' : l + '<br>';
            }
        });
        if (inList) html += '</ul>';
        return html.replace(/(<br>)+$/, '');
    }

    function scrollDown() {
        list.scrollTop = list.scrollHeight;
    }

    function time() {
        return new Date().toLocaleTimeString('sq-AL', { hour: '2-digit', minute: '2-digit' });
    }

    /**
     * Treguesi në header: "AI aktiv" shfaqet VETËM kur serveri e ka verifikuar AI-në
     * (ai_status = 'active') ose kur përgjigjja e fundit erdhi realisht nga AI.
     */
    function setMode(state, provider) {
        if (provider) aiProvider = provider;
        if (!modeLabel || !state) return;
        const labels = {
            active: 'AI aktiv · ' + aiProvider + ' · Shqip / English',
            unavailable: 'AI jo aktiv · Modaliteti bazë',
            unverified: aiProvider + ' · duke u verifikuar…',
            off: 'Modaliteti bazë · Shqip / English',
        };
        modeLabel.textContent = labels[state] || labels.off;
        if (modeDot) {
            modeDot.classList.toggle('pb-dot-warn', state === 'unavailable' || state === 'unverified');
            modeDot.classList.toggle('pb-dot-off', state === 'off');
        }
    }

    /** Etiketa nën përgjigje: dallon përgjigjet e AI-së nga ato rezervë (modaliteti bazë). */
    function sourceBadge(source, provider, model) {
        if (source !== 'ai' && source !== 'fallback') return null;
        const b = document.createElement('span');
        b.className = 'pb-src ' + (source === 'ai' ? 'pb-src-ai' : 'pb-src-basic');
        if (source === 'ai') {
            b.textContent = '✨ ' + (provider ? provider + ' AI' : 'AI') + (model ? ' · ' + model : '');
            b.title = 'Përgjigje e gjeneruar nga ' + (provider || 'AI') + (model ? ' (' + model + ')' : '');
        } else {
            b.textContent = '⚙️ Modaliteti bazë';
            b.title = 'Përgjigje rezervë pa AI (rregulla + kërkim në databazë)';
        }
        return b;
    }

    function addMessage(sender, text, opts = {}) {
        const wrap = document.createElement('div');
        wrap.className = 'pb-msg ' + (sender === 'user' ? 'pb-user' : 'pb-bot') + (opts.error ? ' pb-error' : '');
        const bubble = document.createElement('div');
        bubble.className = 'pb-bubble';
        bubble.innerHTML = sender === 'user' ? escapeHtml(text).replace(/\n/g, '<br>') : format(text);
        wrap.appendChild(bubble);

        if (opts.notice) {
            const n = document.createElement('div');
            n.className = 'pb-notice';
            n.textContent = opts.notice;
            wrap.appendChild(n);
        }
        if (Array.isArray(opts.jobs) && opts.jobs.length) {
            const jobs = document.createElement('div');
            jobs.className = 'pb-jobs';
            opts.jobs.forEach((j) => {
                const a = document.createElement('a');
                a.className = 'pb-job';
                a.href = j.url;
                a.innerHTML = '<strong>' + escapeHtml(j.title) + '</strong>'
                    + '<span>🏢 ' + escapeHtml(j.company) + ' · 📍 ' + escapeHtml(j.city) + '</span>'
                    + '<span>' + escapeHtml(j.type) + ' · Afati: ' + escapeHtml(j.deadline) + '</span>'
                    + '<em>' + escapeHtml(j.salary) + '</em>';
                jobs.appendChild(a);
            });
            wrap.appendChild(jobs);
        }
        const badge = sender === 'user' || opts.error ? null : sourceBadge(opts.source, opts.provider, opts.model);
        if (!opts.noTime || badge) {
            const meta = document.createElement('div');
            meta.className = 'pb-meta';
            meta.textContent = opts.noTime ? '' : time();
            if (badge) {
                if (!opts.noTime) meta.appendChild(document.createTextNode(' · '));
                meta.appendChild(badge);
            }
            wrap.appendChild(meta);
        }
        list.appendChild(wrap);
        scrollDown();
        return wrap;
    }

    function showTyping() {
        const t = document.createElement('div');
        t.className = 'pb-msg pb-bot pb-typing';
        t.innerHTML = '<div class="pb-bubble"><span></span><span></span><span></span></div>';
        list.appendChild(t);
        scrollDown();
        return t;
    }

    async function api(payload) {
        const res = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        let data;
        try {
            data = await res.json();
        } catch (e) {
            throw new Error('Serveri ktheu një përgjigje të pavlefshme. (HTTP ' + res.status + ')');
        }
        if (!data.ok) throw new Error(data.error || 'Ndodhi një gabim.');
        return data;
    }

    // ---------- Hapja / mbyllja ----------
    function toggle(open) {
        const willOpen = typeof open === 'boolean' ? open : win.hidden;
        win.hidden = !willOpen;
        root.classList.toggle('open', willOpen);
        launcher.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        if (willOpen) {
            textarea.focus();
            if (!historyLoaded) loadHistory();
        }
    }
    root.querySelectorAll('[data-pb-toggle]').forEach((b) => b.addEventListener('click', () => toggle()));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !win.hidden) toggle(false); });

    async function loadHistory() {
        historyLoaded = true;
        try {
            const data = await api({ action: 'history' });
            setMode(data.ai_status || (data.ai ? 'active' : 'off'), data.ai_provider);
            if (data.history.length) {
                const sep = document.createElement('div');
                sep.className = 'pb-meta';
                sep.style.textAlign = 'center';
                sep.textContent = '— Biseda e mëparshme —';
                list.appendChild(sep);
                data.history.forEach((m) => addMessage(m.sender, m.message, { noTime: true, source: m.sender === 'bot' ? m.source : null }));
            }
        } catch (e) {
            // Historia nuk është kritike; vazhdo pa të
        }
    }

    // ---------- Dërgimi i mesazhit ----------
    async function send(text) {
        text = text.trim();
        if (!text || busy) return;
        if (text.length > 500) {
            addMessage('bot', 'Mesazhi është shumë i gjatë (maks. 500 karaktere).', { error: true });
            return;
        }
        busy = true;
        sendBtn.disabled = true;
        addMessage('user', text);
        textarea.value = '';
        autoResize();
        if (suggestions) suggestions.innerHTML = '';
        const typing = showTyping();
        try {
            const data = await api({ action: 'message', message: text });
            typing.remove();
            addMessage('bot', data.reply, {
                jobs: data.jobs, notice: data.notice,
                source: data.source, provider: data.ai_provider, model: data.ai_model,
            });
            setMode(data.ai_status, data.ai_provider);
        } catch (e) {
            typing.remove();
            const msg = navigator.onLine === false
                ? 'Nuk ka lidhje me internetin. Kontrolloni lidhjen dhe provoni përsëri.'
                : e.message;
            addMessage('bot', '⚠️ ' + msg, { error: true });
        } finally {
            busy = false;
            sendBtn.disabled = false;
            textarea.focus();
        }
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        send(textarea.value);
    });
    textarea.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            send(textarea.value);
        }
    });
    function autoResize() {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 110) + 'px';
    }
    textarea.addEventListener('input', autoResize);

    root.querySelectorAll('[data-pb-suggest]').forEach((chip) => {
        chip.addEventListener('click', () => send(chip.textContent));
    });

    // ---------- Pastro bisedën ----------
    root.querySelector('[data-pb-clear]')?.addEventListener('click', async () => {
        if (!window.confirm('Të pastrohet biseda?')) return;
        try {
            await api({ action: 'clear' });
            list.querySelectorAll('.pb-msg, .pb-meta').forEach((el, i) => { if (i > 0) el.remove(); });
            addMessage('bot', 'Biseda u pastrua. Si mund t\'ju ndihmoj?', { noTime: true });
        } catch (e) {
            addMessage('bot', '⚠️ ' + e.message, { error: true });
        }
    });
})();
