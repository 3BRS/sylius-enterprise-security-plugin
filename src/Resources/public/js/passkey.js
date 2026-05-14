(function () {
    'use strict';

    if (typeof window === 'undefined') {
        return;
    }

    var SUPPORTED = typeof window.PublicKeyCredential !== 'undefined';

    function base64UrlDecode(input) {
        input = String(input).replace(/-/g, '+').replace(/_/g, '/');
        while (input.length % 4) {
            input += '=';
        }
        var binary = atob(input);
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    }

    function base64UrlEncode(buffer) {
        var bytes = new Uint8Array(buffer);
        var binary = '';
        for (var i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    }

    function decodeOptionsForCreate(options) {
        options.challenge = base64UrlDecode(options.challenge);
        options.user.id = base64UrlDecode(options.user.id);
        if (Array.isArray(options.excludeCredentials)) {
            options.excludeCredentials = options.excludeCredentials.map(function (credential) {
                credential.id = base64UrlDecode(credential.id);
                return credential;
            });
        }
        return options;
    }

    function decodeOptionsForGet(options) {
        options.challenge = base64UrlDecode(options.challenge);
        if (Array.isArray(options.allowCredentials)) {
            options.allowCredentials = options.allowCredentials.map(function (credential) {
                credential.id = base64UrlDecode(credential.id);
                return credential;
            });
        }
        return options;
    }

    function encodeAttestationCredential(credential) {
        return JSON.stringify({
            id: credential.id,
            type: credential.type,
            rawId: base64UrlEncode(credential.rawId),
            response: {
                clientDataJSON: base64UrlEncode(credential.response.clientDataJSON),
                attestationObject: base64UrlEncode(credential.response.attestationObject),
                transports: typeof credential.response.getTransports === 'function'
                    ? credential.response.getTransports()
                    : []
            },
            clientExtensionResults: typeof credential.getClientExtensionResults === 'function'
                ? credential.getClientExtensionResults()
                : {}
        });
    }

    function encodeAssertionCredential(credential) {
        return JSON.stringify({
            id: credential.id,
            type: credential.type,
            rawId: base64UrlEncode(credential.rawId),
            response: {
                clientDataJSON: base64UrlEncode(credential.response.clientDataJSON),
                authenticatorData: base64UrlEncode(credential.response.authenticatorData),
                signature: base64UrlEncode(credential.response.signature),
                userHandle: credential.response.userHandle ? base64UrlEncode(credential.response.userHandle) : null
            },
            clientExtensionResults: typeof credential.getClientExtensionResults === 'function'
                ? credential.getClientExtensionResults()
                : {}
        });
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: body || ''
        });
    }

    function isUserCancellation(error) {
        return error && (error.name === 'NotAllowedError' || error.name === 'AbortError');
    }

    function showError(button, error, cancelledAttr, errorAttr) {
        var message;
        if (isUserCancellation(error)) {
            message = button.getAttribute(cancelledAttr) || 'Operation cancelled.';
        } else {
            message = button.getAttribute(errorAttr) || 'Operation failed.';
        }
        window.alert(message);
    }

    async function registerPasskey(button) {
        var optionsUrl = button.getAttribute('data-options-url');
        var verifyUrl = button.getAttribute('data-verify-url');
        var listUrl = button.getAttribute('data-list-url');
        var labelInput = document.getElementById('three_brs_passkey_label_input');
        var label = labelInput ? labelInput.value : '';

        button.disabled = true;
        try {
            var optionsResponse = await postJson(optionsUrl, '');
            if (!optionsResponse.ok) {
                throw new Error('Failed to fetch registration options');
            }
            var options = decodeOptionsForCreate(await optionsResponse.json());

            var credential = await navigator.credentials.create({ publicKey: options });
            if (!credential) {
                throw new Error('No credential returned by browser');
            }

            var verifyResponse = await postJson(verifyUrl, JSON.stringify({
                label: label,
                credential: encodeAttestationCredential(credential)
            }));
            if (!verifyResponse.ok) {
                throw new Error('Server rejected the registration');
            }

            window.location.href = listUrl;
        } catch (error) {
            button.disabled = false;
            showError(button, error, 'data-cancelled-message', 'data-error-message');
        }
    }

    async function loginWithPasskey(button) {
        var optionsUrl = button.getAttribute('data-options-url');
        var verifyUrl = button.getAttribute('data-verify-url');
        var fallbackUrl = button.getAttribute('data-fallback-url') || '/';

        button.disabled = true;
        try {
            var optionsResponse = await postJson(optionsUrl, '');
            if (!optionsResponse.ok) {
                throw new Error('Failed to fetch login options');
            }
            var options = decodeOptionsForGet(await optionsResponse.json());

            var credential = await navigator.credentials.get({ publicKey: options });
            if (!credential) {
                throw new Error('No credential returned by browser');
            }

            var verifyResponse = await postJson(verifyUrl, JSON.stringify({
                credential: encodeAssertionCredential(credential)
            }));
            if (!verifyResponse.ok) {
                throw new Error('Server rejected the login');
            }

            var result = await verifyResponse.json();
            window.location.href = result.redirect || fallbackUrl;
        } catch (error) {
            button.disabled = false;
            showError(button, error, 'data-cancelled-message', 'data-error-message');
        }
    }

    function init() {
        var registerButton = document.getElementById('three_brs_passkey_register_button');
        var loginButton = document.getElementById('three_brs_passkey_login_button');
        var unsupportedNotice = document.getElementById('three_brs_passkey_unsupported_notice');

        if (!SUPPORTED) {
            if (registerButton) registerButton.disabled = true;
            if (loginButton) loginButton.disabled = true;
            if (unsupportedNotice) unsupportedNotice.classList.remove('d-none');
            return;
        }

        if (registerButton) {
            registerButton.addEventListener('click', function () { registerPasskey(registerButton); });
        }
        if (loginButton) {
            loginButton.addEventListener('click', function () { loginWithPasskey(loginButton); });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
