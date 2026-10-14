(function () {
    const config = window.motorlanTransferPublicationsConfig || window.motorlanTransferPublications || {};

    const form = document.getElementById('motorlan-transfer-form');
    const statusEl = document.getElementById('motorlan-transfer-status');
    const resultsEl = document.getElementById('motorlan-transfer-results');

    if (!form || !statusEl || !resultsEl) {
        return;
    }

    const sourceInput = document.getElementById('motorlan-transfer-source');
    const targetInput = document.getElementById('motorlan-transfer-target');
    const buttons = form.querySelectorAll('[data-transfer-action]');

    const strings = config.strings || {};

    function setBusy(isBusy) {
        buttons.forEach((button) => {
            button.disabled = isBusy;
        });
        statusEl.classList.toggle('is-loading', isBusy);
        statusEl.textContent = isBusy ? (strings.working || 'Procesando...') : '';
    }

    function setStatus(message, type) {
        statusEl.classList.remove('is-error', 'is-success', 'is-info');
        if (type) {
            statusEl.classList.add(`is-${type}`);
        }
        statusEl.textContent = message || '';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }

    function renderResult(payload, dryRun) {
        const source = escapeHtml(payload.source_email || sourceInput.value);
        const target = escapeHtml(payload.target_email || targetInput.value);
        const publications = Number(payload.publications ?? payload.moved_publications ?? 0);
        const questions = Number(payload.questions ?? payload.updated_questions ?? 0);
        const title = dryRun ? 'Simulación' : 'Resultado';

        resultsEl.innerHTML = `
            <div class="motorlan-transfer-result-card">
                <div class="motorlan-transfer-result-card__title">${title}</div>
                <dl class="motorlan-transfer-stats">
                    <div>
                        <dt>Origen</dt>
                        <dd>${source}</dd>
                    </div>
                    <div>
                        <dt>Destino</dt>
                        <dd>${target}</dd>
                    </div>
                    <div>
                        <dt>Motores</dt>
                        <dd>${publications}</dd>
                    </div>
                    <div>
                        <dt>Preguntas</dt>
                        <dd>${questions}</dd>
                    </div>
                </dl>
                ${Array.isArray(payload.publication_ids) && payload.publication_ids.length
                    ? `<div class="motorlan-transfer-ids"><strong>IDs:</strong> ${payload.publication_ids.join(', ')}</div>`
                    : ''
                }
            </div>
        `;
    }

    async function submitTransfer(dryRun) {
        const sourceEmail = sourceInput.value.trim();
        const targetEmail = targetInput.value.trim();

        if (!sourceEmail) {
            setStatus(strings.sourceRequired || 'Indica el correo de origen.', 'error');
            return;
        }

        if (!targetEmail) {
            setStatus(strings.targetRequired || 'Indica el correo de destino.', 'error');
            return;
        }

        setBusy(true);
        setStatus('');

        try {
            const response = await fetch(config.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.restNonce || '',
                },
                body: JSON.stringify({
                    source_email: sourceEmail,
                    target_email: targetEmail,
                    dry_run: dryRun,
                }),
            });

            const payload = await response.json().catch(() => null);

            if (!response.ok) {
                const message = payload?.message || payload?.data?.message || strings.invalidResponse || 'No se pudo completar la acción.';
                setStatus(message, 'error');
                return;
            }

            renderResult(payload || {}, dryRun);
            setStatus(dryRun ? (strings.dryRunSuccess || 'Simulación completada correctamente.') : (strings.runSuccess || 'Transferencia completada correctamente.'), 'success');
        } catch (error) {
            setStatus(error?.message || strings.invalidResponse || 'No se pudo completar la acción.', 'error');
        } finally {
            setBusy(false);
        }
    }

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const dryRun = button.getAttribute('data-transfer-action') === 'dry-run';
            submitTransfer(dryRun);
        });
    });
})();
