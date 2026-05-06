console.log('Motorlan Migration JS: File LOADED (top level)');

function motorlanMigrationInit() {
    console.log('Motorlan Migration JS: Init running');

    // Safe check for the localized object
    if (typeof motorlanMigration === 'undefined') {
        console.error('Motorlan Migration: motorlanMigration is not defined. Check wp_localize_script.');
        return;
    }

    // State variables - Main Migration
    let importId = '';
    let offset = 0;
    let totalRows = 0;
    let running = false;

    // State variables - Featured Migration
    let featuredImportId = '';
    let featuredOffset = 0;
    let featuredTotalRows = 0;
    let featuredRunning = false;

    // Elements Helper
    function getEl(id) {
        const el = document.getElementById(id);
        if (!el) console.warn(`Motorlan Migration: Element #${id} not found.`);
        return el;
    }

    // Elements - Buttons
    const btnMain = getEl('motorlan-submit-migration');
    const btnFeatured = getEl('motorlan-submit-featured');

    console.log('Motorlan Migration: btnMain =', btnMain);
    console.log('Motorlan Migration: btnFeatured =', btnFeatured);

    // Elements - Main Migration
    const form = getEl('motorlan-migration-form');
    const fileInput = getEl('motorlan-csv-file');
    const chunkInput = getEl('motorlan-chunk-size');
    const progressFill = document.querySelector('.motorlan-progress-fill');
    const progressText = getEl('motorlan-migration-progress-text');
    const progressLog = getEl('motorlan-migration-log');
    const spinner = getEl('motorlan-migration-status');

    // Elements - Featured Images Migration
    const featuredForm = getEl('motorlan-migration-featured-form');
    const featuredFileInput = getEl('motorlan-csv-file-featured');
    const featuredChunkInput = getEl('motorlan-chunk-size-featured');
    const featuredLangInput = getEl('motorlan-lang-featured');
    const featuredSpinner = getEl('motorlan-migration-featured-status');
    const featuredProgressWrap = getEl('motorlan-migration-progress-featured');
    const featuredProgressFill = document.querySelector('.motorlan-progress-fill-featured');
    const featuredProgressText = getEl('motorlan-migration-progress-text-featured');

    // Endpoints configuration
    const restRoot =
        motorlanMigration.restRoot ||
        (window.wpApiSettings && window.wpApiSettings.root) ||
        '';

    const computedRestEndpoints = restRoot
        ? {
            upload: restRoot.replace(/\/?$/, '/') + 'motorlan/v1/migration/upload',
            chunk: restRoot.replace(/\/?$/, '/') + 'motorlan/v1/migration/chunk',
            featured: restRoot.replace(/\/?$/, '/') + 'motorlan/v1/migration/chunk-featured',
        }
        : null;

    const endpoints = motorlanMigration.endpoint || computedRestEndpoints || {
        upload: motorlanMigration.ajaxUrl,
        chunk: motorlanMigration.ajaxUrl,
        featured: motorlanMigration.ajaxUrl,
    };

    const restNonce =
        motorlanMigration.restNonce ||
        (window.wpApiSettings && window.wpApiSettings.nonce) ||
        motorlanMigration.nonce ||
        '';

    // --- Utilities ---

    function buildHeaders(isRest = true) {
        const headers = new Headers();
        if (isRest && restNonce) {
            headers.append('X-WP-Nonce', restNonce);
        }
        return headers;
    }

    /**
     * Parse the fetch response safely. Checks response.ok and content-type
     * before attempting JSON parse. Returns parsed JSON or throws a
     * descriptive error.
     */
    async function safeParseFetchResponse(response) {
        const contentType = response.headers.get('content-type') || '';

        if (!response.ok) {
            // Try to get an error message from JSON body if possible.
            if (contentType.includes('application/json')) {
                try {
                    const json = await response.json();
                    const msg = json.message || json.data?.message || JSON.stringify(json);
                    throw new Error(`Error ${response.status}: ${msg}`);
                } catch (e) {
                    if (e.message.startsWith('Error ')) throw e;
                }
            }
            // Non-JSON error (HTML error page, empty body, etc.)
            const text = await response.text().catch(() => '');
            throw new Error(
                `Error del servidor (${response.status} ${response.statusText}). ` +
                (text.length < 200 ? text : 'Revisa la consola del navegador y el log de PHP.')
            );
        }

        // Response is OK but might not be JSON.
        if (!contentType.includes('application/json')) {
            const text = await response.text().catch(() => '');
            throw new Error(
                'La respuesta del servidor no es JSON. ' +
                (text.length < 200 ? text : 'Revisa el log de errores de PHP.')
            );
        }

        return response.json();
    }

    function normalizeResponse(json, fallbackMessage) {
        if (!json) throw new Error(fallbackMessage);

        if (Object.prototype.hasOwnProperty.call(json, 'success')) {
            if (json.success) {
                return json.data;
            }
            const ajaxMessage =
                (typeof json.data === 'string' && json.data) ||
                json.data?.message ||
                json.message ||
                fallbackMessage;
            throw new Error(ajaxMessage);
        }
        if (json.code) {
            const restMessage = json.message || fallbackMessage;
            throw new Error(restMessage);
        }
        return json;
    }

    function logMessage(message, level = 'info', isFeatured = false) {
        const li = document.createElement('li');
        li.textContent = message;
        if (level === 'error') {
            li.style.color = '#dc3232';
        }
        const logId = isFeatured ? 'motorlan-migration-log-featured' : 'motorlan-migration-log';
        const target = document.getElementById(logId);
        if (target) {
            target.insertAdjacentElement('afterbegin', li);
        }
    }

    // --- Main Migration Logic ---

    function handleMainStart() {
        console.log('Motorlan Migration: Manually starting main migration');
        if (!fileInput || !fileInput.files.length) {
            alert(motorlanMigration.strings?.fileRequired || 'Selecciona un archivo.');
            return;
        }
        if (running) {
            alert(motorlanMigration.strings?.alreadyRunning || 'Espera a que termine.');
            return;
        }
        running = true;
        offset = 0;
        totalRows = 0;
        importId = '';

        if (progressFill) progressFill.style.width = '0%';
        if (progressText) progressText.textContent = 'Cargando CSV...';
        if (progressLog) progressLog.innerHTML = '';
        if (spinner) spinner.classList.add('is-active');

        uploadCsv(fileInput.files[0]);
    }

    if (btnMain) {
        btnMain.addEventListener('click', function (e) {
            e.preventDefault();
            handleMainStart();
        });
    }

    // Also catch form submit just in case
    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            handleMainStart();
            return false;
        });
    }

    function uploadCsv(file) {
        const data = new FormData();
        data.append('motorlan_csv', file);

        const isRest = endpoints.upload && !endpoints.upload.includes('admin-ajax.php');
        if (!isRest) {
            data.append('action', 'motorlan_import_csv_upload');
            data.append('security', motorlanMigration.nonce);
        }

        const headers = buildHeaders(isRest);

        fetch(endpoints.upload, {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: data,
        })
            .then(response => safeParseFetchResponse(response))
            .then(json => {
                const payload = normalizeResponse(json, 'Error inesperado al subir.');
                importId = payload.import_id;
                totalRows = payload.rows;
                logMessage(`CSV cargado (${totalRows} filas).`);
                const limit = parseInt(chunkInput?.value || 10, 10);
                processChunk(limit);
            })
            .catch(error => {
                console.error('Motorlan Migration: Upload error', error);
                logMessage(error.message || 'Error desconocido al subir el CSV.', 'error');
                if (spinner) spinner.classList.remove('is-active');
                running = false;
            });
    }

    function processChunk(limit) {
        const data = new FormData();
        data.append('import_id', importId);
        data.append('offset', offset);
        data.append('limit', limit);

        const isRest = endpoints.chunk && !endpoints.chunk.includes('admin-ajax.php');
        if (!isRest) {
            data.append('action', 'motorlan_import_csv_chunk');
            data.append('security', motorlanMigration.nonce);
        }

        const headers = buildHeaders(isRest);

        fetch(endpoints.chunk, {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: data,
        })
            .then(response => safeParseFetchResponse(response))
            .then(json => {
                const payload = normalizeResponse(json, 'Error en chunk.');
                const chunk = payload.chunk;
                offset += (chunk.processed || 0);

                if (progressFill && progressText) {
                    const percent = totalRows ? Math.min(100, Math.round((offset / totalRows) * 100)) : 0;
                    progressFill.style.width = `${percent}%`;
                    progressText.textContent = `Procesadas ${offset} de ${totalRows} filas (${percent}%)`;
                }

                if (chunk.errors && chunk.errors.length) {
                    chunk.errors.forEach(err => logMessage(err, 'error'));
                }
                if (chunk.updated) {
                    logMessage(`Actualizadas ${chunk.updated} publicaciones.`);
                }
                if (chunk.created) {
                    logMessage(`Creadas ${chunk.created} publicaciones.`);
                }

                if (payload.finished) {
                    logMessage('Migración completada.');
                    if (spinner) spinner.classList.remove('is-active');
                    running = false;
                    if (progressFill) progressFill.style.width = '100%';
                    if (progressText) progressText.textContent = 'Migración completada';
                    return;
                }

                setTimeout(() => {
                    processChunk(limit);
                }, 200);
            })
            .catch(error => {
                console.error('Motorlan Migration: Chunk error', error);
                logMessage(error.message || 'Error desconocido procesando chunk.', 'error');
                if (spinner) spinner.classList.remove('is-active');
                running = false;
            });
    }

    // --- Featured Images Migration Logic ---

    function handleFeaturedStart() {
        console.log('Motorlan Migration: Manually starting featured migration');
        if (!featuredFileInput || !featuredFileInput.files.length) {
            alert(motorlanMigration.strings?.fileRequired || 'Selecciona un archivo.');
            return;
        }
        if (featuredRunning) {
            alert(motorlanMigration.strings?.alreadyRunning || 'Espera a que termine.');
            return;
        }
        featuredRunning = true;
        featuredOffset = 0;
        featuredTotalRows = 0;
        featuredImportId = '';

        if (featuredProgressWrap) featuredProgressWrap.style.display = 'block';
        if (featuredProgressFill) featuredProgressFill.style.width = '0%';
        if (featuredProgressText) featuredProgressText.textContent = 'Cargando CSV (Destacadas)...';

        const featuredLog = document.getElementById('motorlan-migration-log-featured');
        if (featuredLog) featuredLog.innerHTML = '';
        if (featuredSpinner) featuredSpinner.classList.add('is-active');

        const lang = featuredLangInput?.value || 'es';
        const chunkSize = parseInt(featuredChunkInput?.value || 20, 10);

        uploadFeaturedCsv(featuredFileInput.files[0], lang, chunkSize);
    }

    if (btnFeatured) {
        console.log('Motorlan Migration: Attaching click event to btnFeatured');
        btnFeatured.addEventListener('click', function (e) {
            console.log('Motorlan Migration: Featured button clicked!');
            e.preventDefault();
            handleFeaturedStart();
        });
    } else {
        console.error('Motorlan Migration: btnFeatured not found! Cannot attach event listener.');
    }

    if (featuredForm) {
        featuredForm.addEventListener('submit', function (event) {
            event.preventDefault();
            handleFeaturedStart();
            return false;
        });
    }

    function uploadFeaturedCsv(file, lang, chunkSize) {
        const data = new FormData();
        data.append('motorlan_csv', file);

        const isRest = endpoints.upload && !endpoints.upload.includes('admin-ajax.php');
        const headers = buildHeaders(isRest);

        if (!isRest) {
            data.append('action', 'motorlan_import_csv_upload');
            data.append('security', motorlanMigration.nonce);
        }

        fetch(endpoints.upload, {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: data,
        })
            .then(response => safeParseFetchResponse(response))
            .then(json => {
                const payload = normalizeResponse(json, 'Error inesperado al subir.');
                featuredImportId = payload.import_id;
                featuredTotalRows = payload.rows;
                logMessage(`CSV Destacadas cargado (${featuredTotalRows} filas).`, 'info', true);
                processFeaturedChunk(chunkSize, lang);
            })
            .catch(error => {
                console.error('Motorlan Migration: Featured upload error', error);
                logMessage(error.message || 'Error desconocido al subir el CSV.', 'error', true);
                if (featuredSpinner) featuredSpinner.classList.remove('is-active');
                featuredRunning = false;
            });
    }

    function processFeaturedChunk(limit, lang) {
        const data = new FormData();
        data.append('import_id', featuredImportId);
        data.append('offset', featuredOffset);
        data.append('limit', limit);
        data.append('lang', lang);

        const isRest = endpoints.featured && !endpoints.featured.includes('admin-ajax.php');
        const headers = buildHeaders(isRest);

        if (!isRest) {
            data.append('action', 'motorlan_import_csv_chunk_featured');
            data.append('security', motorlanMigration.nonce);
        }

        fetch(endpoints.featured, {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: data,
        })
            .then(response => safeParseFetchResponse(response))
            .then(json => {
                const payload = normalizeResponse(json, 'Error en chunk.');
                const chunk = payload.chunk;
                featuredOffset += (chunk.processed || 0);

                const total = payload.total || featuredTotalRows;
                if (total) featuredTotalRows = total;

                if (featuredProgressFill && featuredProgressText) {
                    const percent = featuredTotalRows ? Math.min(100, Math.round((featuredOffset / featuredTotalRows) * 100)) : 0;
                    featuredProgressFill.style.width = `${percent}%`;
                    featuredProgressText.textContent = `Procesadas (Destacadas) ${featuredOffset} de ${featuredTotalRows} filas (${percent}%) — Vinculadas: ${chunk.updated || 0}, Saltadas: ${chunk.skipped || 0}`;
                }

                if (chunk.errors && chunk.errors.length) {
                    chunk.errors.forEach(err => logMessage(err, 'error', true));
                }
                if (chunk.updated) {
                    logMessage(`Vinculadas ${chunk.updated} imágenes destacadas.`, 'info', true);
                }
                if (chunk.skipped) {
                    logMessage(`Saltadas ${chunk.skipped} (ya tienen imagen o sin URL).`, 'info', true);
                }

                if (payload.finished) {
                    logMessage('Proceso de destacadas completado.', 'info', true);
                    if (featuredSpinner) featuredSpinner.classList.remove('is-active');
                    featuredRunning = false;
                    if (featuredProgressFill) featuredProgressFill.style.width = '100%';
                    if (featuredProgressText) featuredProgressText.textContent = 'Completado';
                    return;
                }

                setTimeout(() => {
                    processFeaturedChunk(limit, lang);
                }, 200);
            })
            .catch(error => {
                console.error('Motorlan Migration: Featured chunk error', error);
                logMessage(error.message || 'Error desconocido procesando chunk.', 'error', true);
                if (featuredSpinner) featuredSpinner.classList.remove('is-active');
                featuredRunning = false;
            });
    }

    console.log('Motorlan Migration: Initialized successfully');
}

// Run immediately if DOM is already ready, otherwise wait for it.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', motorlanMigrationInit);
} else {
    motorlanMigrationInit();
}
