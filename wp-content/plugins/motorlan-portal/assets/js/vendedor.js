/**
 * Panel del vendedor (fase 4): componentes Alpine de Mis publicaciones, alta/edición,
 * ventas, ofertas recibidas e interesados. Se registran en alpine:init para no tocar el
 * orden de app.js (solo importa este archivo).
 *
 *  mlPubList     listado: selección múltiple y confirmación de acciones (estado, duplicar…)
 *  mlRowMenu     menú «⋮» de una fila (<details>): posición fija para que la tabla no lo corte
 *  mlPubForm     alta (asistente) y edición (pestañas): validación por paso, archivos
 *                (previsualizar, quitar, reducir fotos), límites de subida, borrador local
 *  mlOffers      ofertas recibidas: diálogo de detalle (y ?offer_id= al cargar)
 *  mlInquiries   interesados: abrir conversación, refresco periódico y venta manual
 *  (la valoración es el kit components/rating-dialog.twig: Alpine en línea + POST normal)
 */

const MB = 1024 * 1024
const toast = (text, color = 'success') => window.Alpine && window.Alpine.store('toast').show(text, color)
const fmt = (s, ...args) => {
  let i = 0
  return String(s).replace(/%(\d+\$)?[sd]/g, (m, pos) => {
    const v = pos ? args[parseInt(pos, 10) - 1] : args[i++]
    return v === undefined ? '' : String(v)
  })
}
const size = (bytes) => (bytes >= MB ? `${(bytes / MB).toFixed(bytes >= 10 * MB ? 0 : 1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`)
let seq = 0
const key = (p = 'k') => `${p}${Date.now().toString(36)}${(seq++).toString(36)}`

// ── Listado ──────────────────────────────────────────────────────────────────────────
function mlPubList(i18n = {}) {
  return {
    i18n,
    count: 0,
    sel: { action: '', id: 0, ids: [], status: '', name: '', page: 1, title: '', text: '', confirm: '', btn: '', tone: '' },
    init() {
      this.$el.addEventListener('change', (e) => { if (e.target.matches('[data-ml-pub-check]')) this.recount() })
      document.body.addEventListener('htmx:afterSwap', () => this.$nextTick(() => this.recount()))
    },
    checks() { return Array.from(document.querySelectorAll('[data-ml-pub-check]')) },
    recount() { this.count = this.checks().filter((c) => c.checked).length },
    allCount() { return this.checks().length },
    toggleAll(on) { this.checks().forEach((c) => { c.checked = on }); this.recount() },
    askBulk() {
      const ids = this.checks().filter((c) => c.checked).map((c) => Number(c.value))
      if (ids.length) this.ask({ action: 'bulk-delete', ids })
    },
    ask(d) {
      const t = this.i18n[d.action === 'status' ? `status_${d.status}` : d.action] || {}
      const n = (d.ids || []).length
      this.sel = {
        action: d.action, id: d.id || 0, ids: d.ids || [], status: d.status || '', name: d.name || '', page: d.page || 1,
        title: t.title || '', text: fmt(t.text || '', n), confirm: t.confirm || '', btn: t.btn || 'ml-c-primary', tone: t.tone || '',
      }
      window.dispatchEvent(new CustomEvent('ml-dialog', { detail: 'ml-pub-confirm' }))
    },
    // Exportar con los filtros que hay en pantalla (aunque no se hayan aplicado por URL).
    exportUrl() {
      const form = document.getElementById('ml-pubs-filters')
      const url = new URL(this.$root.querySelector('[data-ml-export]')?.getAttribute('href') || window.location.href, window.location.href)
      if (form) {
        const q = new URLSearchParams(new FormData(form))
        q.delete('page')
        url.search = q.toString()
      }
      url.searchParams.set('export', 'csv')
      return url.toString()
    },
  }
}

// ── Menú de fila: <details> con posición fija (la tabla tiene overflow) ──────────────
function mlRowMenu() {
  return {
    close() { this.$el.open = false },
    place() {
      const menu = this.$refs.menu
      if (!this.$el.open || !menu) { if (menu) menu.removeAttribute('style'); return }
      const r = this.$el.querySelector('summary').getBoundingClientRect()
      const w = menu.offsetWidth || 224
      const h = menu.offsetHeight || 200
      const top = r.bottom + 6 + h > window.innerHeight ? Math.max(8, r.top - 6 - h) : r.bottom + 6
      const left = Math.min(Math.max(8, r.right - w), window.innerWidth - w - 8)
      menu.style.cssText = `position: fixed; top: ${top}px; left: ${left}px; inset-inline-end: auto; z-index: 60;`
      const off = () => { this.close(); window.removeEventListener('scroll', off, true); window.removeEventListener('resize', off) }
      window.addEventListener('scroll', off, { capture: true, once: true, passive: true })
      window.addEventListener('resize', off, { once: true })
    },
  }
}

// ── Alta y edición ───────────────────────────────────────────────────────────────────
const FIELDS = ['tipo_ui', 'marca', 'marca_custom', 'tipo_o_referencia', 'potencia', 'velocidad', 'par_nominal', 'voltaje', 'intensidad',
  'descripcion', 'estado_del_articulo', 'pais', 'provincia', 'precio_de_venta', 'consultar', 'stock']
const MOTOR = ['potencia', 'velocidad', 'par_nominal', 'voltaje', 'intensidad']
// Paso del asistente → pestaña del editor.
const TAB_OF = ['specs', 'basic', 'specs', 'media', 'basic', 'docs']
const IMG_TYPES = /^image\/(jpeg|png|gif|webp)$/
const DOC_TYPES = /^(application\/pdf|image\/jpeg|image\/png)$/
const DOC_EXT = /\.(pdf|jpe?g|png)$/i

/** Fotos de más de 1 MB → JPEG de 2000 px como máximo (compressImage de la SPA). */
async function shrink(file) {
  if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size <= MB) return file
  try {
    const bmp = await createImageBitmap(file)
    const scale = Math.min(1, 2000 / Math.max(bmp.width, bmp.height))
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(bmp.width * scale)
    canvas.height = Math.round(bmp.height * scale)
    const ctx = canvas.getContext('2d')
    ctx.fillStyle = '#fff' // PNG con transparencia → fondo blanco, no negro
    ctx.fillRect(0, 0, canvas.width, canvas.height)
    ctx.drawImage(bmp, 0, 0, canvas.width, canvas.height)
    bmp.close && bmp.close()
    const blob = await new Promise((res) => canvas.toBlob(res, 'image/jpeg', 0.85))
    if (!blob || blob.size >= file.size) return file
    return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() })
  } catch (e) {
    return file
  }
}

function mlPubForm(cfg) {
  const vals = cfg.values || {}
  const v = {}
  FIELDS.forEach((k) => { v[k] = k === 'consultar' ? !!vals[k] : (vals[k] ?? '') + '' })
  const firstStep = Number(cfg.step) > 0 ? Number(cfg.step) : 0
  return {
    mode: cfg.mode,
    cfg,
    t: cfg.i18n || {},
    v,
    err: {},
    step: cfg.mode === 'add' ? firstStep : 0,
    tab: cfg.mode === 'edit' ? (Number(cfg.step) >= 0 && cfg.step !== 0 ? TAB_OF[firstStep] : 'basic') : 'basic',
    busy: false,
    leaving: false,
    dirty: false,
    drag: '',
    sizeError: '',
    image: vals.image ? { ...vals.image, key: `i${vals.image.id}` } : null,
    gallery: (vals.gallery || []).map((g) => ({ ...g, key: `g${g.id}` })),
    docs: (vals.docs || []).map((d) => ({ ...d, key: `d${d.id}`, file: null, error: '' })),

    get draftKey() { return `ml-pub-draft-${cfg.userId || 0}` },

    init() {
      this.$el.noValidate = true
      if (this.mode === 'add' && !cfg.hasResult) this.restoreDraft()
      this.$watch('v', () => {
        this.dirty = true
        if (this.mode === 'add') this.saveDraft()
      }, { deep: true })
      window.addEventListener('beforeunload', (e) => {
        if (!this.dirty || this.leaving) return
        e.preventDefault()
        e.returnValue = this.t.leave || ''
      })
    },

    // Borrador local del alta (sessionStorage, como la SPA): sin archivos.
    saveDraft() {
      try { sessionStorage.setItem(this.draftKey, JSON.stringify({ v: this.v, step: this.step })) } catch (e) { /* lleno o bloqueado */ }
    },
    restoreDraft() {
      let d = null
      try { d = JSON.parse(sessionStorage.getItem(this.draftKey) || 'null') } catch (e) { d = null }
      if (!d || !d.v || !Object.values(d.v).some((x) => x !== '' && x !== false && x !== '1' && x !== 'new')) return
      FIELDS.forEach((k) => { if (k in d.v) this.v[k] = d.v[k] })
      this.step = Math.min(Math.max(0, Number(d.step) || 0), 5)
      if (this.step === 3 || this.step === 5) this.step = 3 // los archivos no se guardan
      this.$nextTick(() => toast(this.t.draftRestored, 'info'))
    },
    clearDraft() { try { sessionStorage.removeItem(this.draftKey) } catch (e) { /* nada */ } },
    discard() { this.leaving = true; this.clearDraft() },

    // ── Presentación ──
    isMotor() { return this.v.tipo_ui === 'motor-ac' || this.v.tipo_ui === 'motor-dc' },
    isAC() { return this.v.tipo_ui !== 'motor-dc' },
    brandName() {
      if (Number(this.v.marca) === -1) return (this.v.marca_custom || '').trim()
      return (cfg.brands || {})[this.v.marca] || ''
    },
    preview() {
      const p = []
      const b = this.brandName()
      if (b) p.push(b.toUpperCase())
      if (this.v.tipo_o_referencia.trim()) p.push(this.v.tipo_o_referencia.trim().toUpperCase())
      if (this.isMotor() || this.mode === 'edit') {
        if (this.v.potencia !== '') p.push(`${this.v.potencia} KW`)
        else if (this.v.par_nominal !== '') p.push(`${this.v.par_nominal} NM`)
      }
      return p.join(' ')
    },
    slugChanged() {
      const k = cfg.slugKey || []
      const now = [this.v.marca, this.v.marca_custom, this.v.tipo_o_referencia, this.v.potencia, this.v.par_nominal, this.v.tipo_ui]
      return k.length > 0 && now.some((x, i) => String(x ?? '') !== String(k[i] ?? ''))
    },
    mainThumb() { return this.image ? (this.image.thumb || this.image.url || '') : '' },
    stepLabel() { return fmt(this.t.stepOf || 'Paso %1$d de %2$d', this.step + 1, 6) },

    // ── Navegación (asistente / pestañas) ──
    select(tab) { this.tab = tab },
    isActive(tab) { return this.tab === tab },
    go(i) {
      if (i <= this.step) { this.step = i; this.top(); return }
      for (let s = this.step; s < i; s++) {
        if (!this.check(s)) { this.step = s; return }
      }
      this.step = i
      this.top()
    },
    next() { if (this.check(this.step)) { this.step = Math.min(this.step + 1, 5); this.top() } },
    prev() { this.step = Math.max(0, this.step - 1); this.top() },
    top() {
      this.$nextTick(() => {
        const r = this.$el.getBoundingClientRect()
        if (r.top < 0) window.scrollTo({ top: window.scrollY + r.top - 90, behavior: 'smooth' })
      })
    },
    pickType() {
      if (!this.isMotor()) MOTOR.forEach((k) => { this.v[k] = '' })
      this.err = {}
      if (this.mode === 'add') setTimeout(() => this.next(), 150)
    },

    // ── Validación (reglas de los Step*.vue; la definitiva es la de la API) ──
    rules(n) {
      const e = {}
      const t = this.t
      const v = this.v
      const blank = (x) => String(x ?? '').trim() === ''
      const pos = (k) => { if (!blank(v[k]) && !(Number(v[k]) > 0)) e[k] = t.positive }
      if (n === 0 && !v.tipo_ui) e.tipo_ui = t.typeRequired
      if (n === 1) {
        if (blank(v.marca)) e.marca = t.brandRequired
        else if (Number(v.marca) === -1 && blank(v.marca_custom)) e.marca_custom = t.brandCustom
        if (blank(v.tipo_o_referencia)) e.tipo_o_referencia = t.required
      }
      if (n === 2) {
        if (this.mode === 'edit' && !v.tipo_ui) e.tipo_ui = t.typeRequired
        if (this.isMotor()) {
          if (blank(v.velocidad)) e.velocidad = t.required
          if (this.isAC()) {
            if (blank(v.potencia)) e.potencia = t.required
          } else if (blank(v.potencia) && blank(v.par_nominal)) {
            e.potencia = t.powerOrTorque
            e.par_nominal = t.powerOrTorque
          }
          MOTOR.forEach((k) => { if (!e[k]) pos(k) })
        }
      }
      if (n === 3 && !this.image) e.image = t.mainImage
      if (n === 4) {
        ['descripcion', 'estado_del_articulo', 'pais', 'provincia'].forEach((k) => { if (blank(v[k])) e[k] = t.required })
        if (!blank(v.precio_de_venta) && Number(v.precio_de_venta) < 0) e.precio_de_venta = t.priceNegative
        if (blank(v.stock)) e.stock = t.required
        else if (!Number.isInteger(Number(v.stock))) e.stock = t.stockInteger
        else if (Number(v.stock) <= 0) e.stock = t.stockPositive
      }
      if (n === 5) {
        this.docs.forEach((d) => { d.error = !d.id && !d.file && d.nombre.trim() ? t.required : d.error })
        if (this.docs.some((d) => d.error)) e.docs = t.fixErrors
      }
      return e
    },
    check(n) {
      const e = this.rules(n)
      // Solo se sustituyen los errores de este paso.
      const own = { 0: ['tipo_ui'], 1: ['marca', 'marca_custom', 'tipo_o_referencia'], 2: ['tipo_ui', ...MOTOR], 3: ['image'],
        4: ['descripcion', 'estado_del_articulo', 'pais', 'provincia', 'precio_de_venta', 'stock'], 5: ['docs'] }[n] || []
      const next = { ...this.err }
      own.forEach((k) => { delete next[k] })
      this.err = { ...next, ...e }
      if (Object.keys(e).length) {
        toast(this.t.fixErrors, 'error')
        this.focusError()
        return false
      }
      return true
    },
    focusError() {
      this.$nextTick(() => {
        const el = this.$el.querySelector('[aria-invalid="true"]:not([type="hidden"])')
        if (el && el.offsetParent !== null) el.focus({ preventScroll: false })
      })
    },

    // ── Archivos ──
    limitError(file) {
      const max = (cfg.limits || {}).maxFile || 0
      return max && file.size > max ? fmt(this.t.fileTooBig, file.name, size(max)) : ''
    },
    async addMain(files) {
      const f = files && files[0]
      if (!f) return
      if (!IMG_TYPES.test(f.type)) { toast(this.t.onlyImages, 'error'); return }
      const file = await shrink(f)
      const tooBig = this.limitError(file)
      if (tooBig) { toast(tooBig, 'error'); return }
      if (this.image && this.image.file) URL.revokeObjectURL(this.image.url)
      this.image = { key: key('i'), file, url: URL.createObjectURL(file), name: file.name }
      delete this.err.image
      this.dirty = true
    },
    removeMain() {
      if (this.image && this.image.file) URL.revokeObjectURL(this.image.url)
      this.image = null
      this.dirty = true
    },
    async addGallery(files) {
      const max = cfg.maxGallery || 5
      const list = Array.from(files || [])
      if (list.some((f) => !IMG_TYPES.test(f.type))) toast(this.t.onlyImages, 'error')
      const imgs = list.filter((f) => IMG_TYPES.test(f.type))
      const room = max - this.gallery.length
      if (imgs.length > room) toast(fmt(this.t.maxImages, max), 'warning')
      for (const f of imgs.slice(0, Math.max(0, room))) {
        const file = await shrink(f)
        const tooBig = this.limitError(file)
        if (tooBig) { toast(tooBig, 'error'); continue }
        this.gallery.push({ key: key('g'), file, url: URL.createObjectURL(file), name: file.name })
      }
      this.dirty = true
    },
    removeGallery(i) {
      const g = this.gallery[i]
      if (g && g.file) URL.revokeObjectURL(g.url)
      this.gallery.splice(i, 1)
      this.dirty = true
    },
    addDoc() {
      if (this.docs.length >= (cfg.maxDocs || 5)) return
      this.docs.push({ key: key('d'), id: 0, nombre: '', file: null, error: '' })
    },
    pickDoc(d, files) {
      const f = files && files[0]
      if (!f) return
      d.error = ''
      if (!DOC_TYPES.test(f.type) && !DOC_EXT.test(f.name)) { d.error = this.t.docType; d.file = null; return }
      const tooBig = this.limitError(f)
      if (tooBig) { d.error = tooBig; d.file = null; return }
      d.file = f
      if (!d.nombre.trim()) d.nombre = f.name.replace(/\.[^.]+$/, '')
      this.dirty = true
    },
    removeDoc(i) { this.docs.splice(i, 1); this.dirty = true },

    /** ¿Cabe todo en post_max_size? (la SPA avisaba antes de enviar). */
    sizeCheck() {
      const files = [this.image && this.image.file, ...this.gallery.map((g) => g.file), ...this.docs.map((d) => d.file)].filter(Boolean)
      const max = (cfg.limits || {}).maxPost || 0
      const total = files.reduce((s, f) => s + f.size, 0)
      // Margen para los campos de texto y las cabeceras multipart.
      this.sizeError = max && total > max * 0.95 ? fmt(this.t.totalTooBig, size(max)) : ''
      return !this.sizeError
    },

    // ── Envío ──
    async submit(e) {
      e.preventDefault()
      if (this.busy) return
      const steps = [0, 1, 2, 3, 4, 5]
      for (const s of steps) {
        if (!this.check(s)) {
          if (this.mode === 'add') this.step = s
          else this.tab = TAB_OF[s]
          this.focusError()
          return
        }
      }
      if (!this.sizeCheck()) { toast(this.sizeError, 'error'); return }

      const form = this.$el
      const fd = new FormData(form)
      const submitter = e.submitter
      if (submitter && submitter.name) fd.set(submitter.name, submitter.value)
      if (this.image && this.image.file) fd.append('motor_image', this.image.file, this.image.file.name)
      this.gallery.filter((g) => g.file).forEach((g) => fd.append('motor_gallery[]', g.file, g.file.name))
      this.docs.filter((d) => !d.id && d.file).forEach((d) => {
        fd.append('documentacion_adicional_archivos[]', d.file, d.file.name)
        fd.append('doc_nombre_nuevo[]', d.nombre.trim())
      })

      this.busy = true
      let res = null
      let data = null
      try {
        res = await fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
        data = await res.json().catch(() => null)
      } catch (err) {
        this.busy = false
        toast(this.t.network, 'error')
        return
      }
      if (data && data.ok && data.redirect) {
        this.leaving = true
        this.clearDraft()
        window.location.assign(data.redirect)
        return
      }
      this.busy = false
      const msg = (data && data.message) || (res && res.status === 413 ? fmt(this.t.totalTooBig, size((cfg.limits || {}).maxPost || 0)) : this.t.network)
      toast(msg, 'error')
      if (data && Number(data.step) >= 0) {
        if (this.mode === 'add') this.step = Number(data.step)
        else this.tab = TAB_OF[Number(data.step)]
      }
    },
  }
}

// ── Ofertas recibidas ────────────────────────────────────────────────────────────────
const dialog = (id, open = true) => window.dispatchEvent(new CustomEvent(open ? 'ml-dialog' : 'ml-dialog-close', { detail: id }))
const dropQuery = (...names) => {
  const url = new URL(window.location.href)
  if (!names.some((n) => url.searchParams.has(n))) return
  names.forEach((n) => url.searchParams.delete(n))
  window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash)
}
const anyDialogOpen = () => Array.from(document.querySelectorAll('#ml-overlays .ml-dialog')).some((el) => el.offsetParent !== null)

function mlOffers(cfg = {}) {
  return {
    o: null,
    init() {
      if (cfg.open) this.$nextTick(() => this.show(cfg.open))
      else if (cfg.missing) toast(cfg.missingText || 'No se encontró la oferta.', 'warning')
      dropQuery('offer_id')
    },
    show(r) {
      this.o = r
      dialog('ml-offer-detail')
    },
  }
}

// ── Interesados ──────────────────────────────────────────────────────────────────────
const REFRESH_MS = 60000

function mlInquiries(cfg = {}) {
  const t = cfg.i18n || {}
  return {
    sale: { productId: 0, roomKey: '', title: '', name: '', amount: '', busy: false, error: '' },
    timer: null,
    init() {
      if (cfg.open) this.$nextTick(() => this.openRoom(cfg.open))
      else if (cfg.missing) toast(t.roomMissing, 'warning')
      dropQuery('room_key')
      this.timer = setInterval(() => {
        if (document.visibilityState === 'visible' && !anyDialogOpen()) this.refresh()
      }, REFRESH_MS)
    },
    destroy() { clearInterval(this.timer) },
    openRoom(r) {
      window.dispatchEvent(new CustomEvent('ml-chat-open', { detail: {
        id: 'ml-inq-chat', kind: 'product', productId: r.product_id, roomKey: r.room_key,
        title: r.title, image: r.image, otherName: r.user,
      } }))
    },
    // Recarga la bandeja (no leídos, conversaciones nuevas) en la página y búsqueda actuales.
    refresh() {
      const form = document.getElementById('ml-inq-filters')
      if (!form || !window.htmx || document.querySelector('#ml-inq.htmx-request')) return
      const q = new URLSearchParams(new FormData(form))
      const page = document.querySelector('[data-ml-inq-page]')?.dataset.mlInqPage
      if (page && page !== '1') q.set('page', page)
      window.htmx.ajax('GET', `${cfg.url}${cfg.url.includes('?') ? '&' : '?'}${q}`, { target: '#ml-inq', swap: 'outerHTML' })
    },
    askSale(d) {
      this.sale = { productId: Number(d.productId) || 0, roomKey: d.roomKey || '', title: d.title || '', name: d.name || '', amount: '', busy: false, error: '' }
      dialog('ml-inq-sale')
    },
    async sell() {
      const raw = String(this.sale.amount || '').trim()
      // Formato español: «1.500,50» → 1500.50 (el servidor lo vuelve a validar).
      const n = Number(raw.replace(/[\s€]/g, '').replace(/\.(?=\d{3}(?:\D|$))/g, '').replace(',', '.'))
      if (raw && !(Number.isFinite(n) && n > 0)) { this.sale.error = t.amountInvalid; return }
      this.sale.error = ''
      this.sale.busy = true
      const body = new FormData()
      body.set('_mlnonce', cfg.nonce)
      body.set('action', 'sale')
      body.set('product_id', String(this.sale.productId))
      body.set('room_key', this.sale.roomKey)
      if (raw) body.set('amount', raw)
      let data = null
      try {
        const res = await fetch(cfg.url, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } })
        data = await res.json().catch(() => null)
      } catch (e) {
        data = { ok: false, message: t.network }
      }
      this.sale.busy = false
      if (!data || !data.ok) { this.sale.error = (data && data.message) || t.saleError; return }
      dialog('ml-inq-sale', false)
      dialog('ml-inq-chat', false)
      toast(data.message)
      this.refresh()
      if (data.uuid) {
        window.dispatchEvent(new CustomEvent('ml-rate-set', { detail: { id: 'ml-inq-rate', uuid: data.uuid, name: this.sale.name } }))
        dialog('ml-inq-rate')
      }
    },
  }
}

document.addEventListener('alpine:init', () => {
  const A = window.Alpine
  A.data('mlPubList', mlPubList)
  A.data('mlRowMenu', mlRowMenu)
  A.data('mlPubForm', mlPubForm)
  A.data('mlOffers', mlOffers)
  A.data('mlInquiries', mlInquiries)
})

export { mlPubList, mlRowMenu, mlPubForm, mlOffers, mlInquiries }
