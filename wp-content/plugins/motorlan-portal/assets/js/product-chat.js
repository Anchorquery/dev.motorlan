// Chat de la ficha de producto ("Consultar precio"): port de ChatModal.vue + useProductChat.
//
// La conversación (leer, sondear, enviar, agrupar) es el núcleo común de chat.js; aquí
// solo va lo propio de la ficha: invitados, enlace del email (?ct=) y "precio pedido".
// Mismas claves de localStorage que la SPA, así que un invitado que consultó desde la SPA
// conserva su sala al pasar al portal y viceversa:
//   ml_guest_rooms, ml_guest_room_tokens, ml_guest_name, ml_guest_email, motorlan_requested_prices

import { chatCore, mix } from './chat.js'

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

/**
 * Alpine: x-data="mlProductChat({ productId, userId, userName, i18n, … })".
 * Abre el diálogo #ml-chat (components/dialog.twig) con openChat().
 */
export default (cfg = {}) => {
  const app = window.motorlanPortal || {}
  const t = (k, fallback) => (cfg.i18n && cfg.i18n[k]) || fallback
  const fetcher = window.mlFetch || ((url, opts) => fetch(url, { credentials: 'same-origin', ...opts }))
  const api = () => String(app.apiBase || '/wp-json/motorlan/v1/').replace(/\/$/, '')

  return mix(chatCore(cfg.i18n || {}), {
    productId: Number(cfg.productId),
    isLogged: Number(cfg.userId) > 0,
    started: false,
    tokenExpired: false,
    requested: false,
    success: false,
    form: { name: '', email: '', message: t('defaultMessage', 'Hola, me interesa saber el precio de la publicación'), website: '', agreed: false },
    providedRoomKey: null,
    roomKey: null,
    roomToken: null,
    viewerName: null,
    panelId: 'ml-chat-messages',

    chatEndpoint() { return `${api()}/products/${this.productId}/messages` },
    chatParams() { return { room_key: this.roomKey, room_token: this.roomToken } },
    onRoomToken(token) {
      this.roomToken = token
      guest.setToken(this.roomKey, token)
    },

    init() {
      this.requested = requested.has(this.productId)
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
        const res = await fetcher(`${api()}/chat-tokens/${encodeURIComponent(ct)}`, { headers: { Accept: 'application/json' } })
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
        this.$nextTick(() => this.poll())
        this.markRead()
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
      if (this.messages.length) {
        this.$nextTick(() => this.poll())
        this.markRead()
      }
    },

    setRoom(key) {
      this.roomKey = key
      this.roomToken = guest.token(key)
    },

    async findExistingUserRoom() {
      try {
        await fetcher(`${api()}/buyer/claim-guest-chats`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' })
        const res = await fetcher(`${api()}/buyer/inquiries`, { headers: { Accept: 'application/json' } })
        const body = await res.json().catch(() => ({}))
        const match = Array.isArray(body.data) ? body.data.find((it) => Number(it && it.product_id) === this.productId) : null
        return match && match.room_key ? String(match.room_key) : null
      } catch (e) { return null }
    },

    // Con sesión, leer la conversación en la ficha también la marca como leída (antes solo
    // en «Consultas enviadas»: el aviso de no leído seguía aunque ya se hubiera leído aquí).
    async markRead() {
      if (!this.isLogged || !this.roomKey || this.locked || !this.messages.length) return
      try {
        await fetcher(`${api()}/products/${this.productId}/rooms`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ room_key: this.roomKey }),
        })
      } catch (e) { /* se marcará en la siguiente apertura */ }
    },
    onIncoming() { if (this.panelVisible()) this.markRead() },

    async sendAs(text, extra = {}) {
      const payload = {}
      const name = extra.name || this.viewerName
      if (name) payload.viewer_name = name
      if (extra.email) payload.guest_email = extra.email
      if (extra.website) payload.website = extra.website
      const ok = await this.send(text, payload)
      if (ok && this.messages.length) this.poll()
      return ok
    },

    async submitGuest() {
      if (!this.formValid) return
      const f = this.form
      guest.setName(f.name)
      guest.setEmail(f.email)
      this.viewerName = f.name.trim()
      this.setRoom(this.roomKey)
      const ok = await this.sendAs(f.message, { email: f.email.trim(), name: f.name.trim(), website: f.website })
      if (!ok || this.locked || !this.messages.length) return
      requested.add(this.productId)
      this.requested = true
      this.success = true
    },

    async submitMessage() {
      if (!this.canSend) return
      if (await this.sendAs(this.text)) this.text = ''
    },

    close() {
      this.stopPoll()
      window.dispatchEvent(new CustomEvent('ml-dialog-close', { detail: 'ml-chat' }))
    },
  })
}
