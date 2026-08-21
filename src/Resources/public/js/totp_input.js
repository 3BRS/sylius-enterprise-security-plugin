/**
 * Formats a TOTP code as the user types: digits only, grouped as `123-456`,
 * capped at six. The separator is cosmetic — it is stripped again on submit so
 * the server receives the plain six digits it expects.
 *
 * Applies to any input carrying `data-totp-code`, which is why the 2FA setup
 * pages and the challenge page all get it from this one file.
 */
(function () {
    document.querySelectorAll('[data-totp-code]').forEach(function (input) {
        input.addEventListener('input', function () {
            var digits = input.value.replace(/\D/g, '').slice(0, 6);
            input.value = digits.length > 3 ? digits.slice(0, 3) + '-' + digits.slice(3) : digits;
        });

        var form = input.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                input.value = input.value.replace(/-/g, '');
            });
        }
    });
})();
