// Campana del panel (NavBarNotifications.vue + Notifications.vue + stores/notification.ts).
//
// GET  /notifications?per_page=N        lista (más recientes primero)
// GET  /notifications/unread-count      contador real (la lista no las trae todas)
// POST /notifications/read   {notification_ids}
// POST /notifications/unread {notification_ids}
// DELETE /notifications/{id}
//
// Refresco cada 60 s con la pestaña visible y al volver a ella (si hace > 30 s). Al pulsar
// una notificación: se marca leída y se navega a data.vue_path (relativo a /mi-cuenta/, en
// el idioma actual) o a data.url, con offer_id en la query si viene. En el portal toda
// navegación es de página completa, así que no hace falta distinguir SPA / fuera de la SPA.
// «Ver todas» de la SPA no hacía nada: aquí «Cargar más» (hasta 50, el máximo de la API).

const PAGE = 20
const MAX = 50
const REFRESH_MS = 60000

export default (cfg = {}) => {
  const app = window.motorlanPortal || {}
  const api = String(app.apiBase || '/wp-json/motorlan/v1/').replace(/\/$/, '')
  const fetcher = window.mlFetch || ((url, opts) => fetch(url, { credentials: 'same-origin', ...opts }))
  const lang = () => document.documentElement.lang || 'es'
  const json = (method, body) => ({ method, headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) })
  let timer = null
  let lastFetch = 0

  return {
    open: false,
    items: [],
    unread: 0,
    loading: false,
    loaded: false,
    error: false,
    hasMore: false,
    now: Date.now(),

    init() {
      this.refresh()
      timer = setInterval(() => this.refresh(), REFRESH_MS)
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && Date.now() - lastFetch > 30000) this.refresh()
      })
      // Un chat leído o una pantalla que cambie el estado puede pedir refresco.
      window.addEventListener('ml-notifications-refresh', () => this.refresh())
    },
    destroy() { clearInterval(timer) },

    toggle() {
      this.open = !this.open
      if (this.open) {
        this.now = Date.now()
        if (Date.now() - lastFetch > 15000) this.refresh()
      }
    },

    get anyUnread() { return this.unread > 0 || this.items.some((n) => !n.isSeen) },
    get unseenInList() { return this.items.filter((n) => !n.isSeen).length },

    async refresh(size = null) {
      this.now = Date.now()
      if (document.visibilityState === 'hidden' && this.loaded) return
      lastFetch = Date.now()
      const perPage = size || Math.min(MAX, Math.max(PAGE, this.items.length))
      this.loading = true
      try {
        const [list, count] = await Promise.all([
          fetcher(`${api}/notifications?per_page=${perPage}`, { headers: { Accept: 'application/json' } }),
          fetcher(`${api}/notifications/unread-count`, { headers: { Accept: 'application/json' } }),
        ])
        if (list.ok) {
          const data = await list.json().catch(() => [])
          this.items = Array.isArray(data) ? data.map((n) => this.normalize(n)) : []
          this.hasMore = this.items.length >= perPage && perPage < MAX
          this.error = false
        } else if (!this.loaded) {
          this.error = true
        }
        if (count.ok) {
          const c = await count.json().catch(() => ({}))
          this.unread = Number(c && c.count) || 0
        }
      } catch (e) {
        if (!this.loaded) this.error = true
      } finally {
        this.loading = false
        this.loaded = true
      }
    },

    loadMore() { return this.refresh(Math.min(MAX, this.items.length + PAGE)) },

    normalize(n) {
      const data = n && typeof n.data === 'object' && n.data ? n.data : {}
      return {
        id: Number(n.id),
        title: String(n.title || ''),
        subtitle: String(n.subtitle || ''),
        time: String(n.time || ''),
        created_at: n.created_at || null,
        isSeen: Boolean(n.isSeen),
        color: /^[a-z]+$/.test(String(n.color || '')) ? n.color : 'primary',
        image: typeof data.product_image === 'string' && data.product_image ? data.product_image : (typeof n.img === 'string' ? n.img : ''),
        data,
      }
    },

    // «hace 5 minutos» en el idioma de la página (Intl); el texto del servidor de respaldo.
    relative(n) {
      const ts = Date.parse(n.created_at || '')
      if (Number.isNaN(ts)) return n.time
      try {
        const rtf = new Intl.RelativeTimeFormat(lang(), { numeric: 'auto' })
        const diff = Math.round((ts - this.now) / 1000)
        const abs = Math.abs(diff)
        if (abs < 60) return rtf.format(diff, 'second')
        if (abs < 3600) return rtf.format(Math.round(diff / 60), 'minute')
        if (abs < 86400) return rtf.format(Math.round(diff / 3600), 'hour')
        if (abs < 86400 * 30) return rtf.format(Math.round(diff / 86400), 'day')
        return new Intl.DateTimeFormat(lang(), { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(ts))
      } catch (e) { return n.time }
    },

    async mark(ids, read = true) {
      ids = ids.filter((id) => this.items.some((n) => n.id === id && n.isSeen !== read))
      if (!ids.length) return
      try {
        const res = await fetcher(`${api}/notifications/${read ? 'read' : 'unread'}`, json('POST', { notification_ids: ids }))
        if (!res.ok) throw new Error(String(res.status))
        this.items.forEach((n) => {
          if (ids.includes(n.id) && n.isSeen !== read) {
            n.isSeen = read
            this.unread = Math.max(0, this.unread + (read ? -1 : 1))
          }
        })
      } catch (e) { this.toast() }
    },
    toggleRead(n) { return this.mark([n.id], !n.isSeen) },
    // Como la SPA: si queda alguna sin leer, todas leídas; si no, todas sin leer.
    markAll() { return this.mark(this.items.map((n) => n.id), this.items.some((n) => !n.isSeen)) },

    async remove(n) {
      try {
        const res = await fetcher(`${api}/notifications/${n.id}`, { method: 'DELETE', headers: { Accept: 'application/json' } })
        if (!res.ok) throw new Error(String(res.status))
        this.items = this.items.filter((x) => x.id !== n.id)
        if (!n.isSeen) this.unread = Math.max(0, this.unread - 1)
      } catch (e) { this.toast() }
    },

    toast() {
      if (window.Alpine) window.Alpine.store('toast').show(cfg.i18n && cfg.i18n.error ? cfg.i18n.error : 'Ha ocurrido un error', 'error')
    },

    // Destino: vue_path (relativo a la base de la cuenta) o url; null si no lleva a ningún sitio.
    target(n) {
      const d = n.data || {}
      let url = null
      try {
        if (typeof d.vue_path === 'string' && d.vue_path) {
          const base = String(cfg.accountUrl || app.accountUrl || '/mi-cuenta/').replace(/\/?$/, '/')
          url = new URL(d.vue_path.replace(/^\/+/, ''), new URL(base, window.location.origin))
        } else if (typeof d.url === 'string' && d.url) {
          url = new URL(d.url, window.location.origin)
        }
      } catch (e) { return null }
      if (!url || !/^https?:$/.test(url.protocol)) return null
      if (d.offer_id) url.searchParams.set('offer_id', String(d.offer_id))
      return url.toString()
    },

    async go(n) {
      const href = this.target(n)
      // Se espera a marcarla: al cambiar de página el navegador cancelaría la petición.
      if (!n.isSeen) await this.mark([n.id], true)
      if (href) window.location.href = href
    },
  }
}
