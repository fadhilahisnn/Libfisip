/**
 * Accessibility / Screen Reader Widget
 * Ruang Baca FISIP UNAIR
 * Features: Text-to-Speech, Font Size, Contrast, Dyslexia Font, Highlight Links, Pause Animations, Reset
 */

(function () {
    'use strict';

    const STORAGE_KEY = 'rbc_accessibility';
    const SYNTH = window.speechSynthesis;
    let isSpeaking = false;
    let isPanelOpen = false;
    let voices = [];

    // ─────────────────────────────────────────
    // ResponsiveVoice loader (natural Indonesian TTS via Google)
    // ─────────────────────────────────────────
    function loadResponsiveVoice(callback) {
        if (window.responsiveVoice) { callback(); return; }
        const script = document.createElement('script');
        script.src = 'https://code.responsivevoice.org/responsivevoice.js?key=FREE';
        script.onload = callback;
        script.onerror = () => console.warn('ResponsiveVoice gagal dimuat.');
        document.head.appendChild(script);
    }

    // Persisted state
    let prefs = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');

    // ─────────────────────────────────────────
    // Apply saved preferences on page load
    // ─────────────────────────────────────────
    function applyPrefs() {
        if (prefs.fontSize)     setFontSize(prefs.fontSize, false);
        if (prefs.contrast)     document.body.classList.add('acc-high-contrast');
        if (prefs.dyslexia)     document.body.classList.add('acc-dyslexia');
        if (prefs.highlight)    document.body.classList.add('acc-highlight-links');
        if (prefs.noAnims)      document.body.classList.add('acc-no-anims');
        if (prefs.largeCursor)  document.body.classList.add('acc-large-cursor');
    }

    function savePrefs() {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
    }

    // ─────────────────────────────────────────
    // Core feature functions
    // ─────────────────────────────────────────
    let currentFontScale = prefs.fontSize || 100;

    function setFontSize(scale, save = true) {
        currentFontScale = scale;
        document.documentElement.style.fontSize = (scale / 100) + 'rem';
        if (save) { prefs.fontSize = scale; savePrefs(); }
        updateFontDisplay();
    }

    function changeFontSize(delta) {
        const newScale = Math.min(180, Math.max(70, currentFontScale + delta));
        setFontSize(newScale);
    }

    function toggleContrast() {
        prefs.contrast = !prefs.contrast;
        document.body.classList.toggle('acc-high-contrast', prefs.contrast);
        savePrefs();
        updateButtons();
    }

    function toggleDyslexia() {
        prefs.dyslexia = !prefs.dyslexia;
        document.body.classList.toggle('acc-dyslexia', prefs.dyslexia);
        savePrefs();
        updateButtons();
    }

    function toggleHighlightLinks() {
        prefs.highlight = !prefs.highlight;
        document.body.classList.toggle('acc-highlight-links', prefs.highlight);
        savePrefs();
        updateButtons();
    }

    function toggleNoAnims() {
        prefs.noAnims = !prefs.noAnims;
        document.body.classList.toggle('acc-no-anims', prefs.noAnims);
        savePrefs();
        updateButtons();
    }

    function toggleLargeCursor() {
        prefs.largeCursor = !prefs.largeCursor;
        document.body.classList.toggle('acc-large-cursor', prefs.largeCursor);
        savePrefs();
        updateButtons();
    }

    function resetAll() {
        if (SYNTH) SYNTH.cancel();
        isSpeaking = false;
        prefs = {};
        savePrefs();
        document.body.classList.remove('acc-high-contrast','acc-dyslexia','acc-highlight-links','acc-no-anims','acc-large-cursor');
        setFontSize(100, false);
        updateButtons();
        updateFontDisplay();
        announce('Semua pengaturan aksesibilitas telah direset.');
    }

    // ─────────────────────────────────────────
    // Text-to-Speech
    // ─────────────────────────────────────────
    function readPage() {
        try {
            if (isSpeaking) {
                stopSpeaking();
                return;
            }

            // Sembunyikan widget sementara agar teksnya tidak ikut terambil
            const w1 = document.getElementById('acc-widget');
            const w2 = document.getElementById('chatbot-widget');
            const header = document.querySelector('header');
            const footer = document.querySelector('footer');
            
            if (w1) w1.style.display = 'none';
            if (w2) w2.style.display = 'none';
            if (header) header.style.display = 'none';
            if (footer) footer.style.display = 'none';

            // Ambil teks bersih yang terlihat di layar
            let text = document.body.innerText || document.body.textContent;
            
            if (w1) w1.style.display = '';
            if (w2) w2.style.display = '';
            if (header) header.style.display = '';
            if (footer) footer.style.display = '';

            text = text.replace(/\s+/g, ' ').trim();
            
            if (!text) { 
                alert('Tidak ada teks untuk dibacakan pada halaman ini.'); 
                return; 
            }

            // Batasi teks agar memori tidak habis
            const words = text.split(' ');
            if (words.length > 1500) text = words.slice(0, 1500).join(' ') + '...';

            speakText(text, () => {
                isSpeaking = false;
                updateSpeakBtn(false);
            });
        } catch (error) {
            console.error("readPage error:", error);
            alert("Terjadi kesalahan saat membaca halaman: " + error.message);
        }
    }

    function readSelected() {
        try {
            const sel = window.getSelection()?.toString().trim();
            if (!sel) { 
                alert('Silakan blok/seleksi teks terlebih dahulu dengan mouse, lalu klik tombol ini.'); 
                return; 
            }
            stopSpeaking();
            speakText(sel, () => {});
        } catch (error) {
            console.error("readSelected error:", error);
            alert("Terjadi kesalahan saat membaca teks: " + error.message);
        }
    }

    // Universal speak function
    function speakText(text, onEnd) {
        isSpeaking = true;
        updateSpeakBtn(true);

        if (window.responsiveVoice) {
            // ✅ ResponsiveVoice: natural Indonesian voice from Google TTS
            responsiveVoice.speak(text, 'Indonesian Female', {
                rate: 0.9,
                pitch: 1,
                volume: 1,
                onend: () => { isSpeaking = false; updateSpeakBtn(false); if (onEnd) onEnd(); },
                onerror: () => { isSpeaking = false; updateSpeakBtn(false); }
            });
        } else if (SYNTH) {
            // ⚠️ Fallback: Web Speech API (aksen bergantung OS)
            const utt = new SpeechSynthesisUtterance(text);
            utt.lang = 'id-ID';
            utt.rate = 0.88;
            utt.pitch = 1.0;
            utt.onend = () => { isSpeaking = false; updateSpeakBtn(false); if (onEnd) onEnd(); };
            utt.onerror = () => { isSpeaking = false; updateSpeakBtn(false); };
            SYNTH.speak(utt);
        } else {
            alert('Browser Anda tidak mendukung Text-to-Speech.');
            isSpeaking = false;
            updateSpeakBtn(false);
        }
    }

    function stopSpeaking() {
        if (window.responsiveVoice) responsiveVoice.cancel();
        if (SYNTH) SYNTH.cancel();
        isSpeaking = false;
        updateSpeakBtn(false);
    }

    // ─────────────────────────────────────────
    // Live region for announcements
    // ─────────────────────────────────────────
    function announce(msg) {
        const el = document.getElementById('acc-live-region');
        if (el) { el.textContent = ''; setTimeout(() => { el.textContent = msg; }, 50); }
    }

    // ─────────────────────────────────────────
    // Build Widget HTML
    // ─────────────────────────────────────────
    function createWidget() {
        const css = `
        /* ===== Accessibility Widget Styles ===== */
        #acc-widget { position: fixed; left: 16px; bottom: 24px; z-index: 99999; font-family: 'Inter', sans-serif; }

        #acc-trigger {
            width: 52px; height: 52px; border-radius: 50%;
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            border: none; cursor: pointer; box-shadow: 0 4px 20px rgba(30,64,175,0.5);
            display: flex; align-items: center; justify-content: center;
            transition: transform 0.2s, box-shadow 0.2s;
            color: white; font-size: 1.4rem; position: relative;
        }
        #acc-trigger:hover { transform: scale(1.1); box-shadow: 0 6px 25px rgba(30,64,175,0.65); }
        #acc-trigger:focus { outline: 3px solid #facc15; outline-offset: 3px; }

        #acc-panel {
            position: absolute; bottom: 64px; left: 0;
            width: 300px; background: #fff; border-radius: 16px;
            box-shadow: 0 16px 60px rgba(0,0,0,0.25); overflow: hidden;
            transform: translateY(20px) scale(0.95); opacity: 0;
            pointer-events: none; transition: all 0.25s cubic-bezier(.4,0,.2,1);
        }
        #acc-panel.open { transform: translateY(0) scale(1); opacity: 1; pointer-events: all; }

        .acc-panel-header {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white; padding: 14px 16px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .acc-panel-title { font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .acc-panel-close { background: rgba(255,255,255,0.2); border: none; color: white;
            width: 28px; height: 28px; border-radius: 50%; cursor: pointer; font-size: 1rem;
            display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
        .acc-panel-close:hover { background: rgba(255,255,255,0.35); }

        .acc-section { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; }
        .acc-section-title { font-size: 0.7rem; font-weight: 700; color: #64748b; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 8px; }

        .acc-btn-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .acc-btn {
            padding: 10px 8px; border-radius: 10px; border: 1.5px solid #e2e8f0;
            background: #f8fafc; cursor: pointer; font-size: 0.8rem; font-weight: 600;
            color: #334155; transition: all 0.2s; display: flex; align-items: center;
            gap: 6px; flex-direction: column; text-align: center;
        }
        .acc-btn:hover { border-color: #3b82f6; background: #eff6ff; color: #1d4ed8; }
        .acc-btn:focus { outline: 3px solid #facc15; outline-offset: 2px; }
        .acc-btn.active { background: #1e40af; color: white; border-color: #1e40af; }
        .acc-btn.active:hover { background: #1d4ed8; }
        .acc-btn-icon { font-size: 1.2rem; line-height: 1; }
        .acc-btn-label { font-size: 0.72rem; line-height: 1.2; }

        .acc-font-row { display: flex; align-items: center; gap: 10px; justify-content: space-between; }
        .acc-font-ctrl { width: 34px; height: 34px; border-radius: 8px; border: 1.5px solid #e2e8f0;
            background: #f8fafc; cursor: pointer; font-size: 1.1rem; font-weight: 700;
            color: #334155; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .acc-font-ctrl:hover { border-color: #3b82f6; background: #eff6ff; color: #1d4ed8; }
        .acc-font-ctrl:focus { outline: 3px solid #facc15; }
        #acc-font-display { font-size: 0.85rem; font-weight: 700; color: #334155; min-width: 40px; text-align: center; }

        .acc-speak-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .acc-speak-btn {
            padding: 10px; border-radius: 10px; border: 1.5px solid #e2e8f0;
            background: #f8fafc; cursor: pointer; font-size: 0.78rem; font-weight: 600;
            color: #334155; transition: all 0.2s; display: flex; flex-direction: column;
            align-items: center; gap: 4px;
        }
        .acc-speak-btn:hover { border-color: #3b82f6; background: #eff6ff; color: #1d4ed8; }
        .acc-speak-btn:focus { outline: 3px solid #facc15; }
        #acc-btn-speak.speaking { background: #dc2626; color: white; border-color: #dc2626; }

        .acc-reset-btn {
            width: 100%; padding: 10px; border: none; border-radius: 0 0 16px 16px;
            background: #fef2f2; color: #dc2626; font-size: 0.82rem; font-weight: 700;
            cursor: pointer; transition: background 0.2s;
        }
        .acc-reset-btn:hover { background: #fee2e2; }
        .acc-reset-btn:focus { outline: 3px solid #facc15; }

        /* ===== Body-level accessibility overrides ===== */
        body.acc-high-contrast {
            filter: contrast(1.5) !important;
            --text-main: #000 !important;
            --bg-color: #fff !important;
        }
        body.acc-high-contrast * {
            background-color: initial;
            color: #000 !important;
            border-color: #000 !important;
            text-shadow: none !important;
        }
        body.acc-high-contrast a { color: #0000ee !important; text-decoration: underline !important; }
        body.acc-high-contrast button, body.acc-high-contrast input, body.acc-high-contrast select, body.acc-high-contrast textarea {
            border: 2px solid #000 !important;
        }

        body.acc-dyslexia, body.acc-dyslexia * {
            font-family: 'OpenDyslexic', 'Comic Sans MS', 'Trebuchet MS', cursive !important;
            letter-spacing: 0.05em !important;
            word-spacing: 0.1em !important;
            line-height: 1.8 !important;
        }
        body.acc-highlight-links a {
            background: #fef08a !important;
            color: #1e3a8a !important;
            text-decoration: underline !important;
            padding: 0 3px !important;
            border-radius: 3px !important;
            font-weight: 700 !important;
        }
        body.acc-no-anims *, body.acc-no-anims *::before, body.acc-no-anims *::after {
            animation: none !important;
            transition: none !important;
            scroll-behavior: auto !important;
        }
        body.acc-large-cursor, body.acc-large-cursor * {
            cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 32 32'%3E%3Cpath d='M8,2 L8,26 L14,20 L19,30 L22,28.5 L17,18.5 L25,18.5 Z' fill='black' stroke='white' stroke-width='2'/%3E%3C/svg%3E") 4 2, auto !important;
        }

        /* Dark mode support */
        [data-theme='dark'] #acc-panel { background: #1e293b; }
        [data-theme='dark'] .acc-section { border-bottom-color: #334155; }
        [data-theme='dark'] .acc-section-title { color: #94a3b8; }
        [data-theme='dark'] .acc-btn { background: #0f172a; border-color: #334155; color: #e2e8f0; }
        [data-theme='dark'] .acc-btn:hover { background: #1e3a8a; border-color: #3b82f6; color: #fff; }
        [data-theme='dark'] .acc-font-ctrl { background: #0f172a; border-color: #334155; color: #e2e8f0; }
        [data-theme='dark'] #acc-font-display { color: #e2e8f0; }
        [data-theme='dark'] .acc-speak-btn { background: #0f172a; border-color: #334155; color: #e2e8f0; }
        [data-theme='dark'] .acc-reset-btn { background: #450a0a; color: #fca5a5; }
        [data-theme='dark'] .acc-reset-btn:hover { background: #7f1d1d; }
        `;

        const styleEl = document.createElement('style');
        styleEl.textContent = css;
        document.head.appendChild(styleEl);

        const html = `
        <div id="acc-widget" role="region" aria-label="Panel Aksesibilitas">
            <!-- Live region for screen reader announcements -->
            <div id="acc-live-region" aria-live="polite" aria-atomic="true"
                style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;"></div>

            <!-- Trigger button -->
            <button id="acc-trigger" aria-label="Buka panel aksesibilitas"
                    aria-expanded="false" aria-controls="acc-panel" title="Aksesibilitas">
                ♿
            </button>

            <!-- Panel -->
            <div id="acc-panel" role="dialog" aria-label="Pengaturan Aksesibilitas" aria-modal="false">
                <div class="acc-panel-header">
                    <div class="acc-panel-title">♿ Aksesibilitas</div>
                    <button class="acc-panel-close" id="acc-close-btn" aria-label="Tutup panel">✕</button>
                </div>

                <!-- Font Size -->
                <div class="acc-section">
                    <div class="acc-section-title">Ukuran Teks</div>
                    <div class="acc-font-row">
                        <button class="acc-font-ctrl" id="acc-font-dec" aria-label="Perkecil teks" title="Perkecil">A−</button>
                        <span id="acc-font-display" aria-live="polite" aria-label="Ukuran teks saat ini">100%</span>
                        <button class="acc-font-ctrl" id="acc-font-inc" aria-label="Perbesar teks" title="Perbesar">A+</button>
                    </div>
                </div>

                <!-- Visual options -->
                <div class="acc-section">
                    <div class="acc-section-title">Tampilan Visual</div>
                    <div class="acc-btn-grid">
                        <button class="acc-btn" id="acc-btn-contrast" aria-pressed="false" title="Kontras Tinggi">
                            <span class="acc-btn-icon">◑</span>
                            <span class="acc-btn-label">Kontras Tinggi</span>
                        </button>
                        <button class="acc-btn" id="acc-btn-dyslexia" aria-pressed="false" title="Font Disleksia">
                            <span class="acc-btn-icon">𝐃</span>
                            <span class="acc-btn-label">Font Disleksia</span>
                        </button>
                        <button class="acc-btn" id="acc-btn-highlight" aria-pressed="false" title="Sorot Tautan">
                            <span class="acc-btn-icon">🔗</span>
                            <span class="acc-btn-label">Sorot Tautan</span>
                        </button>
                        <button class="acc-btn" id="acc-btn-noanims" aria-pressed="false" title="Hentikan Animasi">
                            <span class="acc-btn-icon">⏸</span>
                            <span class="acc-btn-label">Hentikan Animasi</span>
                        </button>
                        <button class="acc-btn" id="acc-btn-cursor" aria-pressed="false" title="Kursor Besar" style="grid-column: 1/-1;">
                            <span class="acc-btn-icon">🖱️</span>
                            <span class="acc-btn-label">Kursor Lebih Besar</span>
                        </button>
                    </div>
                </div>

                <!-- Text-to-Speech -->
                <div class="acc-section">
                    <div class="acc-section-title">Pembaca Teks (Text-to-Speech)</div>
                    <div class="acc-speak-row">
                        <button class="acc-speak-btn" id="acc-btn-speak" aria-label="Bacakan seluruh halaman" title="Bacakan Halaman">
                            <span style="font-size:1.3rem;">🔊</span>
                            <span>Bacakan Halaman</span>
                        </button>
                        <button class="acc-speak-btn" id="acc-btn-speak-sel" aria-label="Bacakan teks yang dipilih" title="Bacakan Seleksi">
                            <span style="font-size:1.3rem;">✍️</span>
                            <span>Bacakan Seleksi</span>
                        </button>
                    </div>
                </div>

                <!-- Reset -->
                <button class="acc-reset-btn" id="acc-btn-reset" aria-label="Reset semua pengaturan aksesibilitas">
                    🔄 Reset Semua Pengaturan
                </button>
            </div>
        </div>`;

        document.body.insertAdjacentHTML('beforeend', html);
    }

    // ─────────────────────────────────────────
    // UI Update helpers
    // ─────────────────────────────────────────
    function updateFontDisplay() {
        const el = document.getElementById('acc-font-display');
        if (el) el.textContent = currentFontScale + '%';
    }

    function updateSpeakBtn(speaking) {
        const btn = document.getElementById('acc-btn-speak');
        if (!btn) return;
        if (speaking) {
            btn.classList.add('speaking');
            btn.setAttribute('aria-label', 'Hentikan pembacaan');
            btn.querySelector('span:first-child').textContent = '⏹';
            btn.querySelector('span:last-child').textContent = 'Hentikan';
        } else {
            btn.classList.remove('speaking');
            btn.setAttribute('aria-label', 'Bacakan seluruh halaman');
            btn.querySelector('span:first-child').textContent = '🔊';
            btn.querySelector('span:last-child').textContent = 'Bacakan Halaman';
        }
    }

    function updateButtons() {
        const map = {
            'acc-btn-contrast': prefs.contrast,
            'acc-btn-dyslexia': prefs.dyslexia,
            'acc-btn-highlight': prefs.highlight,
            'acc-btn-noanims': prefs.noAnims,
            'acc-btn-cursor': prefs.largeCursor,
        };
        for (const [id, active] of Object.entries(map)) {
            const btn = document.getElementById(id);
            if (!btn) continue;
            btn.classList.toggle('active', !!active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        }
    }

    // ─────────────────────────────────────────
    // Bind events
    // ─────────────────────────────────────────
    function bindEvents() {
        document.getElementById('acc-trigger').addEventListener('click', togglePanel);
        document.getElementById('acc-close-btn').addEventListener('click', closePanel);
        document.getElementById('acc-font-inc').addEventListener('click', () => changeFontSize(10));
        document.getElementById('acc-font-dec').addEventListener('click', () => changeFontSize(-10));
        document.getElementById('acc-btn-contrast').addEventListener('click', toggleContrast);
        document.getElementById('acc-btn-dyslexia').addEventListener('click', toggleDyslexia);
        document.getElementById('acc-btn-highlight').addEventListener('click', toggleHighlightLinks);
        document.getElementById('acc-btn-noanims').addEventListener('click', toggleNoAnims);
        document.getElementById('acc-btn-cursor').addEventListener('click', toggleLargeCursor);
        document.getElementById('acc-btn-speak').addEventListener('click', readPage);
        document.getElementById('acc-btn-speak-sel').addEventListener('click', readSelected);
        document.getElementById('acc-btn-reset').addEventListener('click', resetAll);

        // Close on Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && isPanelOpen) closePanel();
        });

        // Trap focus inside panel when open
        document.getElementById('acc-panel').addEventListener('keydown', trapFocus);
    }

    function trapFocus(e) {
        if (e.key !== 'Tab') return;
        const panel = document.getElementById('acc-panel');
        const focusable = panel.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }

    function togglePanel() {
        isPanelOpen = !isPanelOpen;
        const panel = document.getElementById('acc-panel');
        const trigger = document.getElementById('acc-trigger');
        panel.classList.toggle('open', isPanelOpen);
        trigger.setAttribute('aria-expanded', isPanelOpen ? 'true' : 'false');
        if (isPanelOpen) {
            announce('Panel aksesibilitas dibuka.');
            setTimeout(() => document.getElementById('acc-close-btn').focus(), 250);
        }
    }

    function closePanel() {
        isPanelOpen = false;
        document.getElementById('acc-panel').classList.remove('open');
        document.getElementById('acc-trigger').setAttribute('aria-expanded', 'false');
        document.getElementById('acc-trigger').focus();
        announce('Panel aksesibilitas ditutup.');
    }

    // ─────────────────────────────────────────
    // Init
    // ─────────────────────────────────────────
    function init() {
        createWidget();
        bindEvents();
        applyPrefs();
        updateButtons();
        updateFontDisplay();
        
        // Load ResponsiveVoice untuk suara Indonesia yang natural
        loadResponsiveVoice(() => {
            console.log('ResponsiveVoice siap: suara Indonesia tersedia.');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
