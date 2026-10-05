/**
 * Formats a TOTP code as the user types: digits only, grouped as `123-456`,
 * capped at six. A grey mask over the field shows the positions still to fill
 * (`12X-XXX`), so the dash is visible from the start. The form submits the code
 * as shown; the server removes the separator.
 *
 * Applies to any input carrying `data-totp-code`, which is why the 2FA setup
 * pages and the challenge page all get it from this one file.
 */
(function () {
    var MASK = 'XXX-XXX';

    function copyFont(from, to) {
        ['fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'letterSpacing'].forEach(function (property) {
            to.style[property] = from[property];
        });
    }

    function format(value) {
        var digits = value.replace(/\D/g, '').slice(0, 6);

        return digits.length > 3 ? digits.slice(0, 3) + '-' + digits.slice(3) : digits;
    }

    function textWidth(input, text) {
        var probe = document.createElement('span');
        var style = window.getComputedStyle(input);
        probe.style.cssText = 'position:absolute;visibility:hidden;white-space:pre;';
        copyFont(style, probe);
        probe.textContent = text;
        document.body.appendChild(probe);
        var width = probe.getBoundingClientRect().width;
        document.body.removeChild(probe);

        return width;
    }

    document.querySelectorAll('[data-totp-code]').forEach(function (input) {
        var style = window.getComputedStyle(input);

        // The field and the mask both start the text at the same offset, so their characters line
        // up; the offset centres the full mask in the field.
        var wrapper = document.createElement('div');
        wrapper.style.position = 'relative';
        wrapper.style.width = style.width;
        wrapper.style.marginLeft = 'auto';
        wrapper.style.marginRight = 'auto';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        input.style.width = '100%';
        input.style.margin = '0';
        input.style.setProperty('text-align', 'left', 'important');
        input.setAttribute('placeholder', '');

        var mask = document.createElement('div');
        mask.setAttribute('aria-hidden', 'true');
        mask.style.cssText = 'position:absolute;top:0;bottom:0;left:0;right:0;display:flex;align-items:center;'
            + 'pointer-events:none;white-space:pre;overflow:hidden;color:var(--bs-secondary-color,#6c757d);';
        copyFont(style, mask);
        var filled = document.createElement('span');
        filled.style.visibility = 'hidden';
        var remaining = document.createElement('span');
        mask.appendChild(filled);
        mask.appendChild(remaining);
        wrapper.appendChild(mask);

        function position() {
            var border = parseFloat(style.borderLeftWidth) || 0;
            var offset = Math.max(0, (input.clientWidth - textWidth(input, MASK)) / 2);
            input.style.paddingLeft = offset + 'px';
            mask.style.paddingLeft = (offset + border) + 'px';
        }

        function render() {
            filled.textContent = input.value;
            remaining.textContent = MASK.slice(input.value.length);
        }

        input.addEventListener('input', function () {
            input.value = format(input.value);
            render();
        });
        window.addEventListener('resize', position);

        input.value = format(input.value);
        position();
        render();
    });
})();
