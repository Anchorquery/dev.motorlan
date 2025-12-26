document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('motorlan-migration-form');
    if (!form) {
        return;
    }

    const fileInput = document.getElementById('motorlan-csv-file');
    const chunkInput = document.getElementById('motorlan-chunk-size');
    const progressFill = document.querySelector('.motorlan-progress-fill');
    const progressText = document.getElementById('motorlan-migration-progress-text');
    const progressLog = document.getElementById('motorlan-migration-log');
    const spinner = document.getElementById('motorlan-migration-status');
    const restRoot =
        motorlanMigration.restRoot ||
        (window.wpApiSettings && window.wpApiSettings.root) ||
        '';
    const computedRestEndpoints = restRoot
        ? {
              upload: restRoot.replace(/\/?$/, '/') + 'motorlan/v1/migration/upload',
              chunk: restRoot.replace(/\/?$/, '/') + 'motorlan/v1/migration/chunk',
          }
        : null;
    const endpoints = motorlanMigration.endpoint || computedRestEndpoints || {
        upload: motorlanMigration.ajaxUrl,
        chunk: motorlanMigration.ajaxUrl,
    };
    const restNonce =
        motorlanMigration.restNonce ||
        (window.wpApiSettings && window.wpApiSettings.nonce) ||
        motorlanMigration.nonce ||
        '';
    let importId = '';
    let offset = 0;
    let totalRows = 0;
    let running = false;

    function buildHeaders(isRest = true) {
        const headers = new Headers();
        if (isRest && restNonce) {
            headers.append('X-WP-Nonce', restNonce);
        }
        return headers;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!fileInput.files.length) {
            alert(motorlanMigration.strings.fileRequired);
            return;
        }
        if (running) {
            alert(motorlanMigration.strings.alreadyRunning);
            return;
        }
        running = true;
        offset = 0;
        totalRows = 0;
        importId = '';
        progressFill.style.width = '0%';
        progressText.textContent = 'Cargando CSV...';
        progressLog.innerHTML = '';
        spinner.classList.add('is-active');
        uploadCsv(fileInput.files[0]);
    });

    function uploadCsv(file) {
        const data = new FormData();
        data.append('motorlan_csv', file);

        const isRest = !endpoints.upload.includes('admin-ajax.php');
        if ( ! isRest ) {
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
            .then(response => response.json())
            .then(json => {
                const payload = normalizeResponse(json, 'Error inesperado.');
                importId = payload.import_id;
                totalRows = payload.rows;
                logMessage(`CSV cargado (${totalRows} filas).`);
                const limit = parseInt(chunkInput.value, 10) || motorlanMigration.chunkLimit;
                processChunk(limit);
            })
            .catch(error => {
                logMessage(error.message, 'error');
                spinner.classList.remove('is-active');
                running = false;
            });
    }
    
    function processChunk(limit) {
        const data = new FormData();
        data.append('import_id', importId);
        data.append('offset', offset);
        data.append('limit', limit);

        const isRest = !endpoints.chunk.includes('admin-ajax.php');
        if ( ! isRest ) {
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
            .then(response => response.json())
            .then(json => {
                const payload = normalizeResponse(json, 'Error en chunk.');
                const chunk = payload.chunk;
                offset += chunk.processed;
                const percent = totalRows ? Math.min(100, Math.round((offset / totalRows) * 100)) : 0;
                progressFill.style.width = `${percent}%`;
                progressText.textContent = `Procesadas ${offset} de ${totalRows} filas (${percent}%)`;

                if (chunk.processed && chunk.errors && chunk.errors.length) {
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
                    spinner.classList.remove('is-active');
                    running = false;
                    progressFill.style.width = '100%';
                    progressText.textContent = 'Migración completada';
                    return;
                }

                setTimeout(() => {
                    processChunk(limit);
                }, 200);
            })
            .catch(error => {
                logMessage(error.message, 'error');
                spinner.classList.remove('is-active');
                running = false;
            });
    }

    function normalizeResponse(json, fallbackMessage) {
        if (json && Object.prototype.hasOwnProperty.call(json, 'success')) {
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
        if (json && json.code) {
            const restMessage = json.message || fallbackMessage;
            throw new Error(restMessage);
        }
        return json;
    }

    function logMessage(message, level = 'info') {
        const li = document.createElement('li');
        li.textContent = message;
        if (level === 'error') {
            li.style.color = '#dc3232';
        }
        progressLog.insertAdjacentElement('afterbegin', li);
    }
});
