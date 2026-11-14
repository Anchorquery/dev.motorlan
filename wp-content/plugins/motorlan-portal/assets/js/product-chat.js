// Chat de la ficha de producto ("Consultar precio"): port de ChatModal.vue + useProductChat.
//
// Mismos endpoints (motorlan/v1) y mismas claves de localStorage que la SPA, así que un
// invitado que consultó desde la SPA conserva su sala al pasar al portal y viceversa:
//   ml_guest_rooms, ml_guest_room_tokens, ml_guest_name, ml_guest_email, motorlan_requested_prices
//
// El transporte (leer/enviar/sondear) está aislado en createChatTransport(): en la fase de
// chat se cambia el sondeo por pusher-js sin tocar el componente.

const LS = {
  get(key) { try { return window.localStorage.getItem(key) } catch (e) { return null } },
  set(key, value) { try { window.localStorage.setItem(key, value) } catch (e) { /* sin almacenamiento */ } },
  map(key) {
    try {
      const v = JSON.parse(LS.get(key) || '{}')
      return v && typeof v === 'object' && !Array.isArray(v) ? v : {}
    } catch (e) { return {} }
  },
  list(key) {
    try {
      const v = JSON.parse(LS.get(key) || '[]')
      return Array.isArray(v) ? v : []
    } catch (e) { return [] }
  },
}

const strongId = () => {
  try {
    if (crypto.randomUUID) return `g${crypto.randomUUID().replace(/-/g, '')}`
    const bytes = new Uint8Array(16)
    crypto.getRandomValues(bytes)
    return `g${Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('')}`
  } catch (e) {
    return `g${Date.now().toString(36)}${Math.random().toString(16).slice(2)}`
  }
}

export const guest = {
  roomId(productId) {
    const map = LS.map('ml_guest_rooms')
    const pid = String(productId)
    if (typeof map[pid] === 'string' && /^[A-Za-z0-9_-]{1,100}$/.test(map[pid])) return map[pid]
    map[pid] = strongId()
    LS.set('ml_guest_rooms', JSON.stringify(map))
    return map[pid]
  },
  resetRoomId(productId) {
    const map = LS.map('ml_guest_rooms')
    delete map[String(productId)]
    LS.set('ml_guest_rooms', JSON.stringify(map))
    return guest.roomId(productId)
  },
  rememberRoom(productId, roomKey) {
    const m = String(roomKey).match(/^pub-\d+-viewer-([A-Za-z0-9_-]{1,100})$/)
    if (!m || /^\d+$/.test(m[1])) return
    const map = LS.map('ml_guest_rooms')
    map[String(productId)] = m[1]
    LS.set('ml_guest_rooms', JSON.stringify(map))
  },
  token(roomKey) {
    const t = roomKey ? LS.map('ml_guest_room_tokens')[roomKey] : null
    return typeof t === 'string' && t ? t : null
  },
  setToken(roomKey, token) {
    if (!roomKey || !token) return
    const map = LS.map('ml_guest_room_tokens')
    if (map[roomKey] === token) return
    map[roomKey] = token
    LS.set('ml_guest_room_tokens', JSON.stringify(map))
  },
  name: () => (LS.get('ml_guest_name') || '').trim() || null,
  email: () => (LS.get('ml_guest_email') || '').trim() || null,
  setName: (v) => { if (v && v.trim()) LS.set('ml_guest_name', v.trim()) },
  setEmail: (v) => { if (v && v.trim()) LS.set('ml_guest_email', v.trim()) },
}

const requested = {
  has: (id) => LS.list('motorlan_requested_prices').map(Number).includes(Number(id)),
  add(id) {
    const list = LS.list('motorlan_requested_prices')
    if (!list.map(Number).includes(Number(id))) {
      list.push(Number(id))
      LS.set('motorlan_requested_prices', JSON.stringify(list))
    }
  },
}

// "2026-11-10 10:00:00" (UTC sin zona) → ISO
const isoTime = (value) => {
  let v = String(value || '').trim()
  if (!v) return new Date().toISOString()
  if (v.includes(' ')) v = v.replace(' ', 'T')
  if (!/Z$|[+-]\d{2}:\d{2}$/.test(v)) v += 'Z'
  const t = Date.parse(v)
  return Number.isNaN(t) ? new Date().toISOString() : new Date(t).toISOString()
}

/**
 * Lectura, envío y sondeo de los mensajes de una publicación.
 * Sondeo adaptativo como usePolling: 3 s → ×1.5 sin novedades → máx. 10 s; 429 espera
 * Retry-After; 403/404 lo detienen; con la pestaña oculta no consulta.
 */
export function createChatTransport({ apiBase, productId, fetcher }) {
  const base = `${apiBase.replace(/\/$/, '')}/products/${productId}/messages`
  const qs = (params) => {
    const p = new URLSearchParams()
    Object.entries(params).forEach(([k, v]) => { if (v) p.set(k, v) })
    const s = p.toString()
    return s ? `${base}?${s}` : base
  }
  const json = async (res) => res.json().catch(() => ({}))

  let timer = null
  let running = false
  let interval = 3000
  let since = null

  return {
    async list(room) {
      const res = await fetcher(qs({ room_key: room.key, room_token: room.token }), { headers: { Accept: 'application/json' } })
      return { status: res.status, ok: res.ok, body: await json(res) }
    },
    async send(payload) {
      const res = await fetcher(base, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(payload) })
      return { status: res.status, ok: res.ok, body: await json(res) }
    },
    sync(ts) { since = ts || null },
    start(room, { onData, onStop, isVisible }) {
      if (running) return
      running = true
      interval = 3000
      const tick = async () => {
        timer = null
        if (!running) return
        if (document.visibilityState === 'hidden' || (isVisible && !isVisible())) {
          if (isVisible && !isVisible()) { running = false; return }
          timer = setTimeout(tick, interval)
          return
        }
        try {
          const res = await fetcher(qs({ room_key: room.key, room_token: room.token(), since_timestamp: since }), { headers: { Accept: 'application/json' } })
          if (res.status === 429) {
            const ra = Number(res.headers.get('Retry-After') || 0)
            interval = ra > 0 ? Math.min(ra * 1000 + 1000, 300000) : 60000
          } else if (res.status === 403 || res.status === 404) {
            running = false
            onStop(res.status)
            return
          } else if (res.ok) {
            const body = await json(res)
            const items = Array.isArray(body.data) ? body.data : []
            interval = items.length ? 3000 : Math.min(interval * 1.5, 10000)
            onData(items, body.meta || null)
            if (body.meta && body.meta.server_timestamp) since = body.meta.server_timestamp
          }
        } catch (e) { /* red: reintento en el siguiente ciclo */ }
        if (running) timer = setTimeout(tick, interval)
      }
      timer = setTimeout(tick, interval)
    },
    stop() {
      running = false
      if (timer) clearTimeout(timer)
      timer = null
    },
    get running() { return running },
  }
}

/**
 * Alpine: x-data="mlProductChat({ productId, userId, userName, i18n, … })".
 * Abre el diálogo #ml-chat (components/dialog.twig) con openChat().
 */
export default (cfg = {}) => {
  const app = window.motorlanPortal || {}
  const t = (k, fallback) => (cfg.i18n && cfg.i18n[k]) || fallback
  const fetcher = window.mlFetch || ((url, opts) => fetch(url, { credentials: 'same-origin', ...opts }))

  return {
    productId: Number(cfg.productId),
    isLogged: Number(cfg.userId) > 0,
    started: false,
    tokenExpired: false,
    requested: false,
    success: false,
    loading: false,
    loaded: false,
    locked: false,
    sending: false,
    loadError: '',
    lastStatus: null,
    sendError: '',
    messages: [],
    text: '',
    form: { name: '', email: '', message: t('defaultMessage', 'Hola, me interesa saber el precio de la publicación'), website: '', agreed: false },
    providedRoomKey: null,
    roomKey: null,
    roomToken: null,
    currentUserId: null,
    viewerName: null,
    transport: null,

    init() {
      this.requested = requested.has(this.productId)
      this.transport = createChatTransport({ apiBase: app.apiBase || '/wp-json/motorlan/v1/', productId: this.productId, fetcher })
      const q = new URLSearchParams(window.location.search)
      const roomKey = (q.get('room_key') || '').trim()
      if (roomKey) this.providedRoomKey = roomKey
      const ct = q.get('ct') || ''
      if (ct) {
        this.exchangeToken(ct)
      } else if (q.get('open_chat') === '1' || roomKey) {
        this.$nextTick(() => this.openChat())
      }
    },

    // Enlace del email (?ct=): token de un solo uso → sala y token de invitado.
    async exchangeToken(ct) {
      try {
        const res = await fetcher(`${(app.apiBase || '/wp-json/motorlan/v1/').replace(/\/$/, '')}/chat-tokens/${encodeURIComponent(ct)}`, { headers: { Accept: 'application/json' } })
        const body = await res.json().catch(() => ({}))
        if (res.status === 410 || res.status === 404 || (body && body.code === 'invalid_token')) {
          this.tokenExpired = true
          return
        }
        if (body && body.room_key) {
          this.providedRoomKey = String(body.room_key)
          if (body.room_token) guest.setToken(this.providedRoomKey, String(body.room_token))
          if (body.guest_email) {
            guest.setEmail(String(body.guest_email))
            if (!guest.name()) guest.setName(String(body.guest_email).split('@')[0])
          }
          this.openChat()
          return
        }
      } catch (e) { /* sin red: se puede abrir el chat a mano */ }
      if (new URLSearchParams(window.location.search).get('open_chat') === '1') this.openChat()
    },

    openChat() {
      window.dispatchEvent(new CustomEvent('ml-dialog', { detail: 'ml-chat' }))
      if (!this.started) {
        this.started = true
        this.start()
      } else if (this.messages.length && !this.locked) {
        this.poll()
      }
    },

    get showGuestForm() {
      if (this.isLogged || this.success) return false
      if (this.providedRoomKey) return false
      if (!this.loaded) return !this.requested
      return this.messages.length === 0
    },
    get formValid() {
      const f = this.form
      return f.name.trim() && /.+@.+\..+/.test(f.email.trim()) && f.message.trim() && f.agreed
    },
    get canSend() { return !this.locked && !this.sending && this.text.trim().length > 0 },

    get groups() {
      const fmt = new Intl.DateTimeFormat(document.documentElement.lang || 'es', { day: 'numeric', month: 'long' })
      const buckets = new Map()
      for (const m of this.messages) {
        const key = new Date(m.created_at).toLocaleDateString('sv-SE')
        if (!buckets.has(key)) buckets.set(key, [])
        buckets.get(key).push(m)
      }
      return [...buckets.entries()].sort((a, b) => a[0].localeCompare(b[0])).map(([key, items]) => {
        const label = fmt.format(new Date(items[0].created_at))
        return { key, label: label.charAt(0).toUpperCase() + label.slice(1), items }
      })
    },
    time(iso) {
      return new Intl.DateTimeFormat(document.documentElement.lang || 'es', { hour: '2-digit', minute: '2-digit' }).format(new Date(iso))
    },
    isMine(m) {
      if (this.currentUserId) return Number(m.user_id) === Number(this.currentUserId)
      const email = guest.email()
      return Boolean(m.is_current_user || (email && m.guest_email === email))
    },

    async start() {
      let viewerId
      if (!this.isLogged) {
        viewerId = guest.roomId(this.productId)
        this.form.name = guest.name() || ''
        this.form.email = guest.email() || ''
        if (this.providedRoomKey) guest.rememberRoom(this.productId, this.providedRoomKey)
        this.viewerName = guest.name()
      } else {
        viewerId = String(cfg.userId)
        this.viewerName = cfg.userName || null
      }
      let existing = null
      if (this.isLogged && !this.providedRoomKey) existing = await this.findExistingUserRoom()
      this.setRoom(this.providedRoomKey || existing || `pub-${this.productId}-viewer-${viewerId}`)
      await this.load()
      // Invitado cuya sala guardada ya no se puede abrir (token perdido): sala nueva.
      if (!this.isLogged && !this.providedRoomKey && this.locked && this.lastStatus === 403) {
        this.setRoom(`pub-${this.productId}-viewer-${guest.resetRoomId(this.productId)}`)
        this.locked = false
        await this.load()
      }
      if (this.messages.length) this.poll()
    },

    setRoom(key) {
      this.roomKey = key
      this.roomToken = guest.token(key)
    },

    async findExistingUserRoom() {
      const api = (app.apiBase || '/wp-json/motorlan/v1/').replace(/\/$/, '')
      try {
        await fetcher(`${api}/buyer/claim-guest-chats`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' })
        const res = await fetcher(`${api}/buyer/inquiries`, { headers: { Accept: 'application/json' } })
        const body = await res.json().catch(() => ({}))
        const match = Array.isArray(body.data) ? body.data.find((it) => Number(it && it.product_id) === this.productId) : null
        return match && match.room_key ? String(match.room_key) : null
      } catch (e) { return null }
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
          is_current_user: Boolean(it.is_current_user),
          guest_email: typeof it.guest_email === 'string' ? it.guest_email : null,
        }
        map.set(m.id, m)
      })
      this.messages = [...map.values()].sort((a, b) => Date.parse(a.created_at) - Date.parse(b.created_at))
      this.$nextTick(() => { const el = document.getElementById('ml-chat-messages'); if (el) el.scrollTop = el.scrollHeight })
    },

    meta(meta, moveCursor = true) {
      if (!meta) return
      if (typeof meta.current_user_id === 'number') this.currentUserId = meta.current_user_id
      if (typeof meta.room_token === 'string' && meta.room_token) {
        this.roomToken = meta.room_token
        guest.setToken(this.roomKey, meta.room_token)
      }
      if (moveCursor && meta.server_timestamp) this.transport.sync(meta.server_timestamp)
    },

    async load() {
      this.loading = true
      this.loadError = ''
      this.lastStatus = null
      this.transport.sync(null)
      try {
        const r = await this.transport.list({ key: this.roomKey, token: this.roomToken })
        if (!r.ok) {
          this.lastStatus = r.status
          if (r.status === 403 && r.body.code !== 'rest_cookie_invalid_nonce') {
            this.loadError = t('forbiddenView', 'No tienes permisos para ver estos mensajes.')
            this.locked = true
          } else if (r.status === 404) {
            this.loadError = t('notFound', 'No pudimos encontrar este chat.')
            this.locked = true
          } else {
            this.loadError = t('loadError', 'No se pudieron cargar los mensajes.')
          }
          return
        }
        this.apply(Array.isArray(r.body.data) ? r.body.data : [], true)
        this.meta(r.body.meta)
        this.locked = false
      } catch (e) {
        this.loadError = t('loadError', 'No se pudieron cargar los mensajes.')
      } finally {
        this.loaded = true
        this.loading = false
      }
    },

    poll() {
      if (this.locked || this.transport.running) return
      const panel = () => document.getElementById('ml-chat-messages')
      this.transport.start({ key: this.roomKey, token: () => this.roomToken }, {
        onData: (items, meta) => { this.meta(meta); if (items.length) this.apply(items) },
        onStop: (status) => {
          this.locked = true
          this.loadError = status === 404 ? t('notFound', 'No pudimos encontrar este chat.') : t('forbiddenView', 'No tienes permisos para ver estos mensajes.')
        },
        // Diálogo cerrado = sin sondeo; se reanuda al volver a abrirlo.
        isVisible: () => { const el = panel(); return !!(el && el.offsetParent !== null) },
      })
    },

    async send(text, extra = {}) {
      const message = String(text || '').trim()
      if (!message || this.sending || this.locked) return false
      this.sending = true
      this.sendError = ''
      try {
        const payload = { message, room_key: this.roomKey }
        const name = extra.name || this.viewerName
        if (name) payload.viewer_name = name
        if (extra.email) payload.guest_email = extra.email
        if (this.roomToken) payload.room_token = this.roomToken
        if (extra.website) payload.website = extra.website
        const r = await this.transport.send(payload)
        if (!r.ok) {
          if (r.status === 403 && r.body.code !== 'rest_cookie_invalid_nonce') {
            this.sendError = t('forbiddenSend', 'No tienes permisos para enviar mensajes en este chat.')
            this.locked = true
            this.transport.stop()
          } else {
            this.sendError = (r.body && r.body.message) || t('sendError', 'No se pudo enviar el mensaje.')
          }
          return false
        }
        if (r.body.data) this.apply([r.body.data])
        // No mover el cursor del sondeo con la respuesta del POST (se perderían mensajes).
        this.meta(r.body.meta, false)
        this.locked = false
        if (this.messages.length) this.poll()
        return true
      } catch (e) {
        this.sendError = t('sendError', 'No se pudo enviar el mensaje.')
        return false
      } finally {
        this.sending = false
      }
    },

    async submitGuest() {
      if (!this.formValid) return
      const f = this.form
      guest.setName(f.name)
      guest.setEmail(f.email)
      this.viewerName = f.name.trim()
      this.setRoom(this.roomKey)
      const ok = await this.send(f.message, { email: f.email.trim(), name: f.name.trim(), website: f.website })
      if (!ok || this.locked || !this.messages.length) return
      requested.add(this.productId)
      this.requested = true
      this.success = true
    },

    async submitMessage() {
      if (!this.canSend) return
      if (await this.send(this.text)) this.text = ''
    },

    close() {
      this.transport.stop()
      window.dispatchEvent(new CustomEvent('ml-dialog-close', { detail: 'ml-chat' }))
    },
  }
}
