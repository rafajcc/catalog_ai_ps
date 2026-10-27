/**
 * Catálogo IA - Validación y protección de la Clave API en el navegador.
 *
 * Mejora progresiva: la validación real se hace siempre en el servidor.
 * El campo se sirve como password; si el navegador soporta
 * `-webkit-text-security` se convierte en texto para poder mostrar la máscara
 * en reposo y ocultar lo que se escribe (sin activar el aviso de formulario
 * no seguro del navegador).
 */
(function () {
    'use strict';

    var defaults = {
        urlRequired: 'La URL del servicio es obligatoria.',
        urlInvalid: 'Introduzca una URL válida que utilice HTTPS.',
        keyRequired: 'La Clave API es obligatoria.'
    };

    function getMessages() {
        var messages = window.catalogIaPsMessages;

        if (!messages || typeof messages !== 'object') {
            return defaults;
        }

        return {
            urlRequired: messages.urlRequired || defaults.urlRequired,
            urlInvalid: messages.urlInvalid || defaults.urlInvalid,
            keyRequired: messages.keyRequired || defaults.keyRequired
        };
    }

    function isHttpsUrl(value) {
        if (typeof window.URL !== 'function') {
            return /^https:\/\/[^\s/$.?#].[^\s]*$/i.test(value);
        }

        try {
            var parsed = new window.URL(value);

            return parsed.protocol === 'https:' && parsed.hostname !== '';
        } catch (error) {
            return false;
        }
    }

    function supportsTextSecurity() {
        var probe = document.createElement('span');

        if (typeof probe.style.webkitTextSecurity !== 'undefined') {
            probe.style.webkitTextSecurity = 'disc';

            if (probe.style.webkitTextSecurity === 'disc') {
                return true;
            }
        }

        if (typeof probe.style.textSecurity !== 'undefined') {
            probe.style.textSecurity = 'disc';

            return probe.style.textSecurity === 'disc';
        }

        return false;
    }

    function showMessage(input) {
        if (typeof input.reportValidity === 'function') {
            input.reportValidity();
        } else {
            input.focus();
        }
    }

    function selectAll(input) {
        if (typeof input.select === 'function') {
            try {
                input.select();
            } catch (error) {
                // Algunos navegadores no permiten seleccionar en ciertos tipos.
            }
        }
    }

    function init() {
        var urlInput = document.querySelector('input[name="CATALOG_IA_PS_URL"]');
        var keyInput = document.querySelector('input[name="CATALOG_IA_PS_API_KEY"]');
        var form = urlInput ? urlInput.form : null;

        if (!urlInput || !keyInput || !form) {
            return;
        }

        var messages = getMessages();
        var maskedKey = (keyInput.getAttribute('data-catalog-ia-ps-mask') || keyInput.value || '').trim();
        var canConceal = supportsTextSecurity();

        if (canConceal) {
            // Se oculta igualmente con CSS; así se ve la máscara en reposo.
            keyInput.type = 'text';
        }

        function concealKey() {
            if (canConceal) {
                keyInput.classList.add('is-concealed');
            }
        }

        function revealKey() {
            if (canConceal) {
                keyInput.classList.remove('is-concealed');
            }
        }

        function validateUrl() {
            var value = urlInput.value.trim();
            urlInput.value = value;

            if (value === '') {
                urlInput.setCustomValidity(messages.urlRequired);

                return false;
            }

            if (!isHttpsUrl(value)) {
                urlInput.setCustomValidity(messages.urlInvalid);

                return false;
            }

            urlInput.setCustomValidity('');

            return true;
        }

        function validateKey() {
            var value = keyInput.value.trim();
            keyInput.value = value;

            if (value === '') {
                keyInput.setCustomValidity(messages.keyRequired);

                return false;
            }

            keyInput.setCustomValidity('');

            return true;
        }

        /**
         * Si el usuario vacía el campo habiendo una clave en pantalla, se
         * restaura su máscara para poder reutilizarla.
         */
        function restoreMask() {
            if (keyInput.value.trim() !== '' || maskedKey === '') {
                return;
            }

            keyInput.value = maskedKey;
            keyInput.setCustomValidity('');
        }

        urlInput.addEventListener('input', validateUrl);
        urlInput.addEventListener('blur', validateUrl);

        keyInput.addEventListener('focus', function () {
            concealKey();

            if (keyInput.value.trim() === maskedKey && maskedKey !== '') {
                // Selecciona la máscara para que escribir sustituya el valor.
                selectAll(keyInput);
            }
        });

        keyInput.addEventListener('input', function () {
            if (keyInput.value.trim() === maskedKey) {
                revealKey();
            } else {
                concealKey();
            }

            validateKey();
        });

        keyInput.addEventListener('blur', function () {
            restoreMask();

            if (keyInput.value.trim() === maskedKey) {
                revealKey();
            }

            validateKey();
        });

        form.addEventListener('submit', function (event) {
            restoreMask();

            var urlValid = validateUrl();
            var keyValid = validateKey();

            if (urlValid && keyValid) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            showMessage(urlValid ? keyInput : urlInput);
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
