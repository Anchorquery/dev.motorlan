// Chat del portal: núcleo común + componente del panel.
//
//  - chatCore(): estado y lógica de una conversación (leer, sondear, enviar, agrupar por
//    día). Port de usePolling + usePurchaseChat + useProductChat de la SPA. Lo usan
//    mlChat (panel: compras y consultas) y mlProductChat (ficha, con invitados).
//  - mlChat(cfg): conversación en un diálogo (components/chat-dialog.twig). Dos tipos:
//      purchase → /purchases/{uuid}/messages           (comprador, vendedor o admin)
//      product  → /products/{id}/messages + room_key   (consultas; marca leído con
//                 POST /products/{id}/rooms)
//    Abrir desde cualquier sitio, también cambiando de conversación (bandejas):
//      $dispatch('ml-chat-open', { id: 'ml-chat', productId: 12, roomKey: 'pub-12-viewer-5', title, image, otherName })
//    Emite en window: ml-chat-read {roomKey, productId} y ml-chat-message {count}.
//
// Tiempo real = sondeo (la SPA quitó pusher-js): 3 s, ×1.5 sin novedades hasta 10 s; con
// 429 espera Retry-After; 403/404 lo paran; pestaña oculta o diálogo cerrado = sin consultas.

const app = () => window.motorlanPortal || {}
const apiBase = () => String(app().apiBase || '/wp-json/motorlan/v1/').replace(/\/$/, '')
const fetcher = (url, opts) => (window.mlFetch ? window.mlFetch(url, opts) : fetch(url, { credentials: 'same-origin', ...opts }))
const lang = () => document.documentElement.lang || 'es'

/** Copia propiedades con sus descriptores (los getters siguen siendo getters). */
export const mix = (...sources) => {
  const out = {}
  for (const src of sources) Object.defineProperties(out, Object.getOwnPropertyDescriptors(src))
  return out
}

/** "2026-11-10 10:00:00" (UTC sin zona) → ISO. */
export const isoTime = (value) => {
  let v = String(value || '').trim()
  if (!v) return new Date().toISOString()
  if (v.includes(' ')) v = v.replace(' ', 'T')
  if (!/Z$|[+-]\d{2}:\d{2}$/.test(v)) v += 'Z'
  const ts = Date.parse(v)
  return Number.isNaN(ts) ? new Date().toISOString() : new Date(ts).toISOString()
}

/** Iniciales (la SPA del vendedor ponía el nombre entero: part.toUpperCase() en vez de part[0]). */
export const initials = (name) => String(name || '').split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0].toUpperCase()).join('') || 'U'

/**
 * Núcleo de una conversación. El consumidor define:
 *   chatEndpoint()  URL de los mensajes
 *   chatParams()    parámetros de sala (GET y POST): { room_key, room_token } o {}
 *   panelId         id del contenedor de los mensajes (scroll y "¿está visible?")
 * y, si quiere, onIncoming(items) y onRoomToken(token).
 */
export const chatCore = (i18n = {}) => {
  const t = (k, fallback) => i18n[k] || fallback
  let timer = null
  let interval = 3000
  let since = null

  return {
    messages: [],
    text: '',
    loading: false,
    loaded: false,
    locked: false,
    sending: false,
    polling: false,
    loadError: '',
    sendError: '',
    lastStatus: null,
    currentUserId: null,
    viewerRole: null,
    panelId: 'ml-chat-messages',

    chatUrl(extra = {}) {
      const p = new URLSearchParams()
      Object.entries({ ...this.chatParams(), ...extra }).forEach(([k, v]) => { if (v) p.set(k, v) })
      const qs = p.toString()
      return qs ? `${this.chatEndpoint()}?${qs}` : this.chatEndpoint()
    },
    chatParams() { return {} },

    get canSend() { return !this.locked && !this.sending && this.text.trim().length > 0 },
    get groups() {
      const fmt = new Intl.DateTimeFormat(lang(), { day: 'numeric', month: 'long' })
      const buckets = new Map()
      for (const m of this.messages) {
        // Día LOCAL (con slice(0, 10) sobre ISO UTC, de 00:00 a 02:00 caía en el anterior).
        const key = new Date(m.created_at).toLocaleDateString('sv-SE')
        if (!buckets.has(key)) buckets.set(key, [])
        buckets.get(key).push(m)
      }
      return [...buckets.entries()].sort((a, b) => a[0].localeCompare(b[0])).map(([key, items]) => {
        const label = fmt.format(new Date(items[0].created_at))
        return { key, label: label.charAt(0).toUpperCase() + label.slice(1), items }
      })
    },
    time(iso) { return new Intl.DateTimeFormat(lang(), { hour: '2-digit', minute: '2-digit' }).format(new Date(iso)) },
    initials(name) { return initials(name) },
    isMine(m) {
      if (this.currentUserId) return Number(m.user_id) === Number(this.currentUserId)
      return Boolean(m.is_current_user)
    },
    panelVisible() {
      const el = document.getElementById(this.panelId)
      return !!(el && el.offsetParent !== null)
    },
    scrollBottom() {
      this.$nextTick(() => { const el = document.getElementById(this.panelId); if (el) el.scrollTop = el.scrollHeight })
    },

    apply(items, replace = false) {
      const map = new Map(replace ? [] : this.messages.map((m) => [m.id, m]))
      ;(items || []).forEach((it, i) => {
        const m = {
          id: String(it.id ?? `msg-${i}`),
          message: String(it.message ?? ''),
          created_at: isoTime(it.created_at),
          user_id: Number(it.user_id) || 0,
          display_name: String(it.display_name || ''),
          avatar: typeof it.avatar === 'string' && it.avatar ? it.avatar : null,
          sender_role: typeof it.sender_role === 'string' ? it.sender_role : '',
          is_current_user: Boolean(it.is_current_user),
          guest_email: typeof it.guest_email === 'string' ? it.guest_email : null,
        }
        map.set(m.id, m)
      })
      this.messages = [...map.values()].sort((a, b) => Date.parse(a.created_at) - Date.parse(b.created_at))
      this.scrollBottom()
    },

    meta(meta, moveCursor = true) {
      if (!meta) return
      if (typeof meta.current_user_id === 'number') this.currentUserId = meta.current_user_id
      if (typeof meta.viewer_role === 'string') this.viewerRole = meta.viewer_role
      if (typeof meta.room_token === 'string' && meta.room_token && this.onRoomToken) this.onRoomToken(meta.room_token)
      // El POST no mueve el cursor: se perderían los mensajes llegados entre el último sondeo y el envío.
      if (moveCursor && meta.server_timestamp) since = meta.server_timestamp
    },

    errorFor(status, body, sending = false) {
      const isNonce = body && body.code === 'rest_cookie_invalid_nonce'
      if (status === 403 && !isNonce) {
        this.locked = true
        this.stopPoll()
        return sending ? t('forbiddenSend', 'No tienes permisos para enviar mensajes en este chat.') : t('forbiddenView', 'No tienes permisos para ver estos mensajes.')
      }
      if (status === 404 && !sending) {
        this.locked = true
        this.stopPoll()
        return t('notFound', 'No pudimos encontrar este chat.')
      }
      if (sending) return (body && body.message) || t('sendError', 'No se pudo enviar el mensaje.')
      return t('loadError', 'No se pudieron cargar los mensajes.')
    },

    async load() {
      this.loading = true
      this.loadError = ''
      this.lastStatus = null
      since = null
      try {
        const res = await fetcher(this.chatUrl(), { headers: { Accept: 'application/json' } })
        const body = await res.json().catch(() => ({}))
        if (!res.ok) {
          this.lastStatus = res.status
          this.loadError = this.errorFor(res.status, body)
          return false
        }
        this.apply(Array.isArray(body.data) ? body.data : [], true)
        this.meta(body.meta)
        this.locked = false
        return true
      } catch (e) {
        this.loadError = t('loadError', 'No se pudieron cargar los mensajes.')
        return false
      } finally {
        this.loaded = true
        this.loading = false
      }
    },

    poll() {
      if (this.locked || this.polling) return
      this.polling = true
      interval = 3000
      const tick = async () => {
        timer = null
        if (!this.polling) return
        if (!this.panelVisible()) { this.polling = false; return } // diálogo cerrado: se reanuda al abrir
        if (document.visibilityState === 'hidden') { timer = setTimeout(tick, interval); return }
        try {
          const res = await fetcher(this.chatUrl({ since_timestamp: since }), { headers: { Accept: 'application/json' } })
          if (res.status === 429) {
            const ra = Number(res.headers.get('Retry-After') || 0)
            interval = ra > 0 ? Math.min(ra * 1000 + 1000, 300000) : 60000
          } else if (res.status === 403 || res.status === 404) {
            const body = await res.json().catch(() => ({}))
            if (body.code !== 'rest_cookie_invalid_nonce') {
              this.loadError = this.errorFor(res.status, body)
              return
            }
          } else if (res.ok) {
            const body = await res.json().catch(() => ({}))
            const items = Array.isArray(body.data) ? body.data : []
            interval = items.length ? 3000 : Math.min(interval * 1.5, 10000)
            this.meta(body.meta || null)
            if (items.length) {
              const before = this.messages.length
              this.apply(items)
              if (this.messages.length > before && this.onIncoming) this.onIncoming(items)
            }
          }
        } catch (e) { /* red: se reintenta en el siguiente ciclo */ }
        if (this.polling) timer = setTimeout(tick, interval)
      }
      timer = setTimeout(tick, interval)
    },

    stopPoll() {
      this.polling = false
      if (timer) clearTimeout(timer)
      timer = null
    },

    async send(text, extra = {}) {
      const message = String(text || '').trim()
      if (!message || this.sending || this.locked) return false
      this.sending = true
      this.sendError = ''
      try {
        const res = await fetcher(this.chatEndpoint(), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ message, ...this.chatParams(), ...extra }),
        })
        const body = await res.json().catch(() => ({}))
        if (!res.ok) {
          this.sendError = this.errorFor(res.status, body, true)
          return false
        }
        if (body.data) this.apply([body.data])
        this.meta(body.meta, false)
        this.locked = false
        return true
      } catch (e) {
        this.sendError = t('sendError', 'No se pudo enviar el mensaje.')
        return false
      } finally {
        this.sending = false
      }
    },

    async submitMessage() {
      if (!this.canSend) return
      if (await this.send(this.text)) {
        this.text = ''
        this.poll()
      }
    },
  }
}

/**
 * Alpine: x-data="mlChat({{ ml_json(chat)|e('html_attr') }})" (lo pone chat-dialog.twig).
 * cfg (Support\Chat): kind, uuid | productId + roomKey, dialogId, title, image, otherName,
 * autoOpen, markRead, i18n.
 */
export const mlChat = (cfg = {}) => mix(chatCore(cfg.i18n || {}), {
  kind: cfg.kind === 'product' ? 'product' : 'purchase',
  uuid: cfg.uuid || '',
  productId: Number(cfg.productId) || 0,
  roomKey: cfg.roomKey || '',
  dialogId: cfg.dialogId || 'ml-chat',
  title: cfg.title || '',
  image: cfg.image || '',
  otherName: cfg.otherName || '',
  markReadOn: cfg.markRead !== false,
  panelId: `${cfg.dialogId || 'ml-chat'}-messages`,
  i18n: cfg.i18n || {},
  readTimer: null,

  init() {
    if (cfg.autoOpen) this.$nextTick(() => this.open())
    // Al volver a la pestaña con el diálogo abierto, reanudar el sondeo.
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible' && this.panelVisible()) this.poll()
    })
  },

  chatEndpoint() {
    return this.kind === 'product'
      ? `${apiBase()}/products/${this.productId}/messages`
      : `${apiBase()}/purchases/${encodeURIComponent(this.uuid)}/messages`
  },
  chatParams() { return this.kind === 'product' ? { room_key: this.roomKey } : {} },

  // viewer_role lo da la API en meta (buyer / seller; admin en compras ajenas).
  get heading() {
    if (this.viewerRole === 'seller') return this.i18n.withBuyer || 'Chat con el comprador'
    return this.i18n.withSeller || 'Chat con el vendedor'
  },

  // Abre el diálogo; con detail (evento ml-chat-open) puede cambiar de conversación.
  async open(detail = null) {
    if (detail && typeof detail === 'object') {
      if (detail.id && detail.id !== this.dialogId) return
      const switching = (detail.uuid && detail.uuid !== this.uuid) || (detail.roomKey && detail.roomKey !== this.roomKey) || (detail.productId && Number(detail.productId) !== this.productId)
      ;['title', 'image', 'otherName'].forEach((k) => { if (detail[k] !== undefined) this[k] = detail[k] || '' })
      if (switching) {
        this.stopPoll()
        if (detail.kind) this.kind = detail.kind === 'product' ? 'product' : 'purchase'
        if (detail.uuid) this.uuid = detail.uuid
        if (detail.productId) this.productId = Number(detail.productId)
        if (detail.roomKey) this.roomKey = detail.roomKey
        this.messages = []
        this.loaded = false
        this.locked = false
        this.sendError = ''
        this.text = ''
      }
    } else if (detail && detail !== this.dialogId) {
      return
    }
    window.dispatchEvent(new CustomEvent('ml-dialog', { detail: this.dialogId }))
    if (!this.loaded || this.loadError) await this.load()
    await this.$nextTick()
    this.scrollBottom()
    this.poll()
    this.markRead()
  },

  close() {
    this.stopPoll()
    window.dispatchEvent(new CustomEvent('ml-dialog-close', { detail: this.dialogId }))
  },

  onIncoming(items) {
    window.dispatchEvent(new CustomEvent('ml-chat-message', { detail: { count: items.length, roomKey: this.roomKey, uuid: this.uuid } }))
    if (document.visibilityState === 'visible' && this.panelVisible()) {
      clearTimeout(this.readTimer)
      this.readTimer = setTimeout(() => this.markRead(), 800)
    }
  },

  // Consultas: marcar la sala como leída (BuyerChatModal / SellerChatModal).
  async markRead() {
    if (!this.markReadOn || this.kind !== 'product' || !this.roomKey || this.locked) return
    try {
      const res = await fetcher(`${apiBase()}/products/${this.productId}/rooms`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ room_key: this.roomKey }),
      })
      if (res.ok) window.dispatchEvent(new CustomEvent('ml-chat-read', { detail: { roomKey: this.roomKey, productId: this.productId } }))
    } catch (e) { /* sin red: se marcará en la siguiente apertura */ }
  },
})
