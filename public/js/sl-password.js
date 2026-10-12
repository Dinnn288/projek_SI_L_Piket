/**
 * Show/Hide password SI-LPiket (reusable, tanpa backend).
 *
 * - Menangani setiap [data-sl-pw-toggle] secara independen (satu tombol
 *   hanya mengubah input di dalam [data-sl-pw-field] yang sama).
 * - Hanya menukar input type password <-> text; tidak menyimpan,
 *   tidak mengirim, dan tidak mencatat nilai password ke mana pun
 *   (tanpa console/log/localStorage/session).
 * - Auto-enhance: input[type=password] yang belum dibungkus (mis. form
 *   dinamis/modal/partial) otomatis dibungkus dengan tombol yang sama,
 *   sehingga halaman baru tetap ter-cover tanpa script tambahan.
 * - Default selalu tersembunyi (type=password); refresh kembali hidden.
 */
(function () {
    'use strict';

    var EYE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    var EYE_OFF = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    var LABEL_SHOW = 'Tampilkan password';
    var LABEL_HIDE = 'Sembunyikan password';

    function setState(btn, input, show) {
        input.setAttribute('type', show ? 'text' : 'password');
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('aria-label', show ? LABEL_HIDE : LABEL_SHOW);
        btn.setAttribute('title', show ? LABEL_HIDE : LABEL_SHOW);
        btn.innerHTML = show ? EYE_OFF : EYE;
    }

    function bindButton(btn) {
        if (!btn || btn.dataset.slPwBound === '1') return;
        btn.dataset.slPwBound = '1';

        var field = btn.closest('[data-sl-pw-field]');
        var input = field ? field.querySelector('input[type="password"], input[type="text"][data-sl-pw-input]') : null;

        if (!input) return;
        input.dataset.slPwInput = '1';
        if (!input.getAttribute('type')) input.setAttribute('type', 'password');

        // Sinkron tampilan awal dengan tipe aktual (default hidden).
        var initiallyShown = input.getAttribute('type') === 'text';
        setState(btn, input, initiallyShown);

        btn.addEventListener('click', function () {
            var show = input.getAttribute('type') === 'password';
            setState(btn, input, show);
            // Kembalikan fokus ke input agar alur isi form tidak terputus.
            if (document.activeElement !== input) {
                try { input.focus({ preventScroll: true }); } catch (e) { input.focus(); }
            }
        });
    }

    function enhanceInput(input) {
        if (!input || input.dataset.slPwEnhanced === '1') return;
        if (input.closest('[data-sl-pw-field]')) {
            input.dataset.slPwEnhanced = '1';
            return;
        }
        input.dataset.slPwEnhanced = '1';

        var field = document.createElement('div');
        field.className = 'sl-pw-field';
        field.setAttribute('data-sl-pw-field', '');

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'sl-pw-toggle';
        btn.setAttribute('data-sl-pw-toggle', '');
        btn.setAttribute('aria-label', LABEL_SHOW);
        btn.setAttribute('aria-pressed', 'false');
        btn.setAttribute('title', LABEL_SHOW);
        btn.tabIndex = 0;

        input.parentNode.insertBefore(field, input);
        field.appendChild(input);
        field.appendChild(btn);
        bindButton(btn);
    }

    function scan(root) {
        (root || document).querySelectorAll('[data-sl-pw-toggle]').forEach(bindButton);
        (root || document).querySelectorAll('input[type="password"]').forEach(enhanceInput);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { scan(document); });
    } else {
        scan(document);
    }

    // Form dinamis/modal/partial: tangani node password yang muncul belakangan.
    if (window.MutationObserver) {
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    if (node.matches && (node.matches('[data-sl-pw-toggle]') || node.matches('input[type="password"]'))) {
                        scan(node.parentNode || document);
                    } else if (node.querySelectorAll) {
                        scan(node);
                    }
                });
            });
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }
})();
