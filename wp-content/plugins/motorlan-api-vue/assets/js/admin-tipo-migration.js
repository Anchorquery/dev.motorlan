(function () {
  const cfg = window.motorlanTipoMigration || {}

  const el = id => document.getElementById(id)
  const previewBtn = el('motorlan-tipo-run-preview')
  const applyBtn = el('motorlan-tipo-run-apply')
  const perPageInput = el('motorlan-tipo-per-page')
  const statusEl = el('motorlan-tipo-status')
  const progressFill = el('motorlan-tipo-progress-fill')
  const summaryEl = el('motorlan-tipo-summary')
  const resultsEl = el('motorlan-tipo-results')
  const resultsBody = el('motorlan-tipo-results-body')
  const resultsSubtitle = el('motorlan-tipo-results-subtitle')

  const state = {
    preview: null,
    applying: false,
    loading: false,
  }

  const familyLabel = (family, alimentacion) => {
    if (family === 'motor')
      return alimentacion === 'dc' ? 'Motor DC' : 'Motor AC'
    if (family === 'regulador')
      return 'Regulador'
    if (family === 'otro-repuesto')
      return 'Otros'
    return 'Sin tipo'
  }

  const escapeHtml = value => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;')

  const setStatus = message => {
    if (statusEl)
      statusEl.textContent = message
  }

  const setProgress = percent => {
    if (progressFill)
      progressFill.style.width = `${Math.max(0, Math.min(100, percent))}%`
  }

  const setLoading = loading => {
    state.loading = loading
    if (previewBtn)
      previewBtn.disabled = loading
    if (applyBtn)
      applyBtn.disabled = loading || !state.preview || !(state.preview.items || []).some(item => item.action === 'would_update')
  }

  const resetResults = () => {
    if (summaryEl) {
      summaryEl.innerHTML = ''
      summaryEl.hidden = true
    }
    if (resultsEl)
      resultsEl.hidden = true
    if (resultsBody)
      resultsBody.innerHTML = ''
    if (resultsSubtitle)
      resultsSubtitle.textContent = ''
  }

  const buildSummaryCard = (title, value, tone = '') => {
    const card = document.createElement('div')
    card.className = `motorlan-summary__card ${tone}`.trim()
    card.innerHTML = `<div class="motorlan-summary__label">${escapeHtml(title)}</div><div class="motorlan-summary__value">${escapeHtml(value)}</div>`
    return card
  }

  const renderSummary = preview => {
    if (!summaryEl)
      return

    summaryEl.innerHTML = ''
    summaryEl.hidden = false

    const stats = preview.stats || {}
    summaryEl.append(
      buildSummaryCard('Escaneadas', stats.scanned ?? 0),
      buildSummaryCard('Se actualizarán', stats.updated ?? 0, 'is-update'),
      buildSummaryCard('Ya coinciden', stats.matched ?? 0, 'is-match'),
      buildSummaryCard('Sin categoría', stats.skipped_no_category ?? 0, 'is-skip'),
      buildSummaryCard('Sin resolver', stats.skipped_unresolved ?? 0, 'is-skip'),
      buildSummaryCard('Se limpiará AC/DC', stats.cleared_supply ?? 0, 'is-clean'),
    )
  }

  const renderRows = items => {
    if (!resultsBody)
      return

    resultsBody.innerHTML = ''

    items.forEach(item => {
      const tr = document.createElement('tr')
      const legacy = (item.category || []).map(term => term.name || term.slug).filter(Boolean).join(' > ') || '—'
      const currentLabel = familyLabel(item.current?.family, item.current?.alimentacion)
      const targetLabel = item.target?.family
        ? familyLabel(item.target.family, item.target.alimentacion)
        : '—'

      const statusClass =
        item.action === 'would_update' || item.action === 'updated'
          ? 'motorlan-pill--update'
          : item.action === 'already_matching'
            ? 'motorlan-pill--match'
            : 'motorlan-pill--skip'

      const statusLabel =
        item.action === 'would_update'
          ? 'Se actualizará'
          : item.action === 'updated'
            ? 'Actualizado'
            : item.action === 'already_matching'
              ? 'Ya coincide'
              : item.action === 'no_category'
                ? 'Sin categoría'
                : 'Sin resolver'

      tr.innerHTML = `
        <td>${escapeHtml(item.post_id)}</td>
        <td>
          <strong>${escapeHtml(item.title || 'Sin título')}</strong>
        </td>
        <td>${escapeHtml(legacy)}</td>
        <td>${escapeHtml(currentLabel)}</td>
        <td>${escapeHtml(targetLabel)}</td>
        <td><span class="motorlan-pill ${statusClass}">${escapeHtml(statusLabel)}</span></td>
      `
      resultsBody.appendChild(tr)
    })
  }

  const renderPreview = preview => {
    state.preview = preview
    renderSummary(preview)
    renderRows(preview.items || [])

    if (resultsEl)
      resultsEl.hidden = false

    if (resultsSubtitle) {
      const stats = preview.stats || {}
      resultsSubtitle.textContent = `${preview.dry_run ? 'Dry-run' : 'Aplicación'} completado. ${stats.updated || 0} publicaciones requieren cambio.`
    }

    if (applyBtn) {
      applyBtn.disabled = state.loading || !(preview.items || []).some(item => item.action === 'would_update')
    }
  }

  const parseJson = async response => {
    const contentType = response.headers.get('content-type') || ''

    if (!response.ok) {
      if (contentType.includes('application/json')) {
        const json = await response.json()
        throw new Error(json?.message || json?.data?.message || cfg.strings?.networkError || 'Error del servidor')
      }

      throw new Error(cfg.strings?.networkError || 'Error del servidor')
    }

    if (!contentType.includes('application/json'))
      throw new Error(cfg.strings?.networkError || 'Respuesta inválida')

    return response.json()
  }

  const fetchBatch = async (page, perPage, dryRun) => {
    const url = new URL(cfg.endpoint)
    url.searchParams.set('page', String(page))
    url.searchParams.set('per_page', String(perPage))
    url.searchParams.set('dry_run', dryRun ? '1' : '0')

    const response = await fetch(url.toString(), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': cfg.restNonce || '',
      },
    })

    return parseJson(response)
  }

  const collectAll = async dryRun => {
    const perPage = Math.min(200, Math.max(1, Number(perPageInput?.value || 100)))
    let page = 1
    let totalPages = 1
    const aggregate = {
      dry_run: dryRun,
      stats: {
        scanned: 0,
        updated: 0,
        matched: 0,
        skipped_no_category: 0,
        skipped_unresolved: 0,
        cleared_supply: 0,
      },
      items: [],
    }

    resetResults()
    setLoading(true)
    setStatus(cfg.strings?.loading || 'Analizando...')
    setProgress(0)

    while (page <= totalPages) {
      const json = await fetchBatch(page, perPage, dryRun)
      const data = json?.data || json

      if (page === 1)
        totalPages = Number(data?.totalPages || 1)

      const stats = data?.stats || {}
      aggregate.stats.scanned += Number(stats.scanned || 0)
      aggregate.stats.updated += Number(stats.updated || 0)
      aggregate.stats.matched += Number(stats.matched || 0)
      aggregate.stats.skipped_no_category += Number(stats.skipped_no_category || 0)
      aggregate.stats.skipped_unresolved += Number(stats.skipped_unresolved || 0)
      aggregate.stats.cleared_supply += Number(stats.cleared_supply || 0)

      aggregate.items.push(...(data?.items || []))

      setProgress(Math.round((page / totalPages) * 100))
      setStatus(`Procesando bloque ${page} de ${totalPages}...`)

      page += 1
    }

    return aggregate
  }

  const handlePreview = async () => {
    try {
      const preview = await collectAll(true)
      renderPreview(preview)
      setStatus(cfg.strings?.donePreview || 'Dry-run completado')
    }
    catch (error) {
      setStatus(error.message || cfg.strings?.networkError || 'Error')
    }
    finally {
      setLoading(false)
      setProgress(100)
    }
  }

  const handleApply = async () => {
    if (!state.preview) {
      setStatus(cfg.strings?.noPreview || 'Ejecuta primero dry-run')
      return
    }

    const pending = (state.preview.items || []).filter(item => item.action === 'would_update')
    if (!pending.length) {
      setStatus(cfg.strings?.noChanges || 'No hay cambios pendientes')
      return
    }

    if (!window.confirm(cfg.strings?.applyConfirm || '¿Continuar?'))
      return

    try {
      const applied = await collectAll(false)
      renderPreview(applied)
      setStatus(cfg.strings?.doneApply || 'Migración aplicada')
    }
    catch (error) {
      setStatus(error.message || cfg.strings?.networkError || 'Error')
    }
    finally {
      setLoading(false)
      setProgress(100)
    }
  }

  if (previewBtn)
    previewBtn.addEventListener('click', handlePreview)

  if (applyBtn)
    applyBtn.addEventListener('click', handleApply)
})()
