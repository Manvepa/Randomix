/**
 * Gestiona el indicador de carga de los formularios del panel.
 * Muestra un mensaje según la acción y evita envíos duplicados.
 */
(() => {
    'use strict';


    // Define los mensajes asociados a las acciones reales de Randomix.
    const loadingMessages = {
        upload_excel: {
            title: 'Cargando archivo Excel',
            description: 'Estamos guardando el archivo y preparando su validación.'
        },
        validate_sheet: {
            title: 'Analizando hoja de Excel',
            description: 'Estamos comprobando los encabezados y la estructura de los datos.'
        },
        execute_selection: {
            title: 'Realizando selección aleatoria',
            description: 'Estamos procesando los registros y preparando los resultados.'
        },
        upload_comparison_files: {
            title: 'Cargando los archivos de comparación',
            description: 'Estamos guardando Excel A y Excel B y validando sus estructuras. Por favor, espera.'
        },
        execute_comparison: {
            title: 'Comparando los archivos Excel',
            description: 'Estamos buscando coincidencias entre los documentos y calculando los registros restantes.'
        }
    };

    // Construye el indicador una sola vez para reutilizarlo en la página.
    function createLoadingOverlay() {
        const overlay = document.createElement('div');

        overlay.className = 'loading-overlay';
        overlay.hidden = true;
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        overlay.setAttribute('aria-atomic', 'true');

        overlay.innerHTML = `
            <div class="loading-overlay__panel">
                <div class="loading-overlay__spinner" aria-hidden="true"></div>
                <h2 class="loading-overlay__title"></h2>
                <p class="loading-overlay__description"></p>
                <div class="loading-overlay__progress" aria-hidden="true">
                    <span></span>
                </div>
                <p class="loading-overlay__footnote">
                    Por favor, espera. No cierres ni recargues esta página.
                </p>
            </div>
        `;

        document.body.appendChild(overlay);

        return overlay;
    }

    // Inicializa el indicador y escucha los envíos de formularios.
    function initializeLoadingOverlay() {
        const overlay = createLoadingOverlay();
        const title = overlay.querySelector('.loading-overlay__title');
        const description = overlay.querySelector(
            '.loading-overlay__description'
        );

        // Obtiene los campos que participan en el cálculo del sorteo.
        const beneficiaryInput = document.getElementById(
            'beneficiary_quantity'
        );
        const alternateInput = document.getElementById(
            'alternate_quantity'
        );
        const totalInput = document.getElementById('total_quantity');

        // Actualiza el total cada vez que cambia alguna cantidad.
        function updateSelectionTotal() {
            if (
                !(beneficiaryInput instanceof HTMLInputElement)
                || !(alternateInput instanceof HTMLInputElement)
                || !(totalInput instanceof HTMLInputElement)
            ) {
                return;
            }

            const beneficiaries = Number(beneficiaryInput.value);
            const alternates = Number(alternateInput.value);

            // Evita mostrar resultados incorrectos ante valores inválidos.
            if (
                beneficiaryInput.value === ''
                || alternateInput.value === ''
                || !Number.isSafeInteger(beneficiaries)
                || !Number.isSafeInteger(alternates)
                || beneficiaries < 0
                || alternates < 0
            ) {
                totalInput.value = '';
                return;
            }

            const total = beneficiaries + alternates;

            // Evita desbordamientos al sumar cantidades muy grandes.
            totalInput.value = Number.isSafeInteger(total)
                ? String(total)
                : '';
        }

        // Escucha cambios al escribir o modificar los controles numéricos.
        beneficiaryInput?.addEventListener('input', updateSelectionTotal);
        alternateInput?.addEventListener('input', updateSelectionTotal);

        // Calcula el total inicial cuando se carga la página.
        updateSelectionTotal();


        document.addEventListener('submit', (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            const actionInput = form.querySelector('input[name="action"]');
            const action = actionInput?.value;
            const message = loadingMessages[action];

            // Ignora los formularios que no realizan estas operaciones.
            if (!message) {
                return;
            }

            // Evita procesar dos veces el mismo formulario.
            if (form.dataset.loading === 'true') {
                event.preventDefault();
                return;
            }

            // El evento submit solo ocurre después de superar la validación
            // nativa del navegador, incluidos los campos obligatorios.
            event.preventDefault();

            form.dataset.loading = 'true';

            title.textContent = message.title;
            description.textContent = message.description;

            overlay.hidden = false;
            document.body.classList.add('is-loading');

            // Deshabilita el botón para evitar clics repetidos.
            const submitButton = event.submitter instanceof HTMLElement
                ? event.submitter
                : form.querySelector('button[type="submit"], input[type="submit"]');

            if (submitButton) {
                submitButton.disabled = true;
            }

            // Permite que el navegador pinte el indicador antes de enviar.
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    HTMLFormElement.prototype.submit.call(form);
                });
            });
        });
    }

    // Espera a que el documento esté disponible antes de inicializar.
    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initializeLoadingOverlay,
            { once: true }
        );
    } else {
        initializeLoadingOverlay();
    }
})();
