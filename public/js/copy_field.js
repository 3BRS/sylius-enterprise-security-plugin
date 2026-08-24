(function () {
    'use strict';

    var init = function () {
        document.querySelectorAll('[data-three-brs-copy-text]').forEach(function (el) {
            if (el.dataset.threeBrsCopyBound === '1') {
                return;
            }
            el.dataset.threeBrsCopyBound = '1';

            var copy = function () {
                var text = el.getAttribute('data-three-brs-copy-text') || '';
                var flash = function () {
                    el.classList.add('is-copied');
                    setTimeout(function () { el.classList.remove('is-copied'); }, 1500);
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(flash, function () { /* permission denied; ignore */ });
                    return;
                }

                // Fallback for HTTP / older Safari without async clipboard API.
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'absolute';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); flash(); } catch (e) { /* ignore */ }
                document.body.removeChild(ta);
            };

            el.addEventListener('click', copy);
            el.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    copy();
                }
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
