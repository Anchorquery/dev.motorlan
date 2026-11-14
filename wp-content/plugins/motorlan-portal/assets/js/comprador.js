// Panel del comprador (fase 3): valorar una compra y acciones de las ofertas enviadas.
// Se registra solo (alpine:init), así app.js solo necesita importarlo.
//
//  - mlRating(cfg): diálogo «Valorar» (RatingModal.vue). El formulario hace POST (HTMX) a
//    la URL de la pantalla con action=review; el servidor responde sin cuerpo y con el
//    evento ml:review {ok, message} en HX-Trigger. Si va bien, cierra, emite
//    ml-review-done {uuid} y recarga la tabla (cfg.refresh = selector del formulario de
//    filtros). Sin JS las estrellas son radios y el formulario funciona igual (303).
//  - mlOffers(): ofertas enviadas. Un único formulario oculto (#ml-offer-form) para
//    confirmar y retirar: `busy` impide un segundo envío (doble clic) mientras el primero
//    no ha terminado; además la API bloquea la doble confirmación.

const dialog = (name, id) => window.dispatchEvent(new CustomEvent(name, { detail: id }))

const mlRating = (cfg = {}) => ({
  dialogId: cfg.dialog || 'ml-rating',
  refresh: cfg.refresh || '',
  uuid: '',
  name: '',
  target: 'seller',
  rating: 0,
  hover: 0,
  comment: '',
  error: '',
  busy: false,

  // detail: { uuid, name, target }
  start(d = {}) {
    this.uuid = d.uuid || ''
    this.name = d.name || ''
    this.target = d.target === 'buyer' ? 'buyer' : 'seller'
    this.rating = 0
    this.hover = 0
    this.comment = ''
    this.error = ''
    this.busy = false
    dialog('ml-dialog', this.dialogId)
  },

  get shown() { return this.hover || this.rating },
  get valid() { return this.rating > 0 && this.comment.trim().length > 0 && this.comment.length <= 1000 },

  done(e) {
    this.busy = false
    const d = (e && e.detail) || {}
    if (!d.ok) {
      this.error = d.message || (cfg.i18n && cfg.i18n.error) || 'Error al enviar la valoración'
      return
    }
    dialog('ml-dialog-close', this.dialogId)
    window.dispatchEvent(new CustomEvent('ml-review-done', { detail: { uuid: this.uuid } }))
    const form = this.refresh ? document.querySelector(this.refresh) : null
    if (form && window.htmx) window.htmx.trigger(form, 'submit')
  },
})

const mlOffers = () => ({
  sel: null,
  busy: false,

  show(o) {
    this.sel = o
    dialog('ml-dialog', 'ml-offer-detail')
  },

  askWithdraw(o) {
    this.sel = o
    dialog('ml-dialog-close', 'ml-offer-detail')
    dialog('ml-dialog', 'ml-offer-withdraw')
  },

  send(action, id) {
    if (this.busy || !id) return
    const form = this.$refs.form
    if (!form || !window.htmx) return
    this.busy = true
    form.querySelector('[name="action"]').value = action
    form.querySelector('[name="id"]').value = String(id)
    window.htmx.trigger(form, 'submit')
  },

  // htmx:after-request del formulario. Confirmar con éxito navega (HX-Redirect): se deja
  // `busy` para que no se pueda volver a pulsar mientras carga el detalle.
  after(e) {
    const xhr = e.detail && e.detail.xhr
    if (xhr && xhr.getResponseHeader('HX-Redirect')) return
    this.busy = false
    dialog('ml-dialog-close', 'ml-offer-detail')
    dialog('ml-dialog-close', 'ml-offer-withdraw')
  },
})

document.addEventListener('alpine:init', () => {
  window.Alpine.data('mlRating', mlRating)
  window.Alpine.data('mlOffers', mlOffers)
})
