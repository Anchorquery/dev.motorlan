import '../css/app.css'
import htmx from 'htmx.org'
import Alpine from 'alpinejs'
import focus from '@alpinejs/focus'
import { fetchRest, renewNonce, isStaleText, STALE_HEADER } from './nonce.js'
import mlStore from './store.js'
import mlProductChat from './product-chat.js'
import { mlChat } from './chat.js'
import mlNotifications from './notifications.js'
import { mlAvatar, mlChangePassword, mlEmailConfirm } from './cuenta.js'
import { mlAuth, mlPassword, mlAuthToken } from './auth.js'
// Panel del vendedor (fase 4): sus componentes se registran solos en alpine:init.
import './vendedor.js'
import './comprador.js' // fase 3: se registra solo en alpine:init (mlRating, mlOffers)

const cfg = window.motorlanPortal || {}
const t = (key, fallback) => (cfg.i18n && cfg.i18n[key]) || fallback

window.htmx = htmx
window.mlFetch = fetchRest

// ── HTMX ────────────────────────────────────────────────────────────────────────────────
// Parciales servidos por la REST (motorlan-portal/v1): sesión = cookie + X-WP-Nonce.
htmx.config.historyCacheSize = 0 // las vistas dependen de la sesión: sin copias en localStorage
htmx.config.defaultSettleDelay = 0

document.body.addEventListener('htmx:configRequest', (e) => {
  if (cfg.nonce) e.detail.headers['X-WP-Nonce'] = cfg.nonce
  else delete e.detail.headers['X-WP-Nonce']
})

// Nonce caducado: no se pinta la respuesta (403, o la versión de invitado), se pide un
// nonce nuevo y se repite la misma petición una sola vez.
document.body.addEventListener('htmx:beforeSwap', (e) => {
  const xhr = e.detail.xhr
  const rc = e.detail.requestConfig
  if (!xhr || !rc) return
  const stale = xhr.getResponseHeader(STALE_HEADER) === '1' || isStaleText(xhr.status, xhr.responseText)
  if (!stale) return
  e.detail.shouldSwap = false
  e.detail.isError = false
  const elt = rc.elt
  if (!elt || elt.__mlRetried) return
  elt.__mlRetried = true
  renewNonce().then(() => {
    const values = rc.formData ? Object.fromEntries(rc.formData) : rc.parameters
    htmx.ajax(rc.verb.toUpperCase(), rc.path, { source: elt, target: e.detail.target, values })
      .finally(() => { elt.__mlRetried = false })
  })
})

document.body.addEventListener('htmx:responseError', (e) => {
  const status = e.detail.xhr ? e.detail.xhr.status : 0
  Alpine.store('toast').show(status === 401 || status === 403 ? t('expired', 'Tu sesión ha caducado.') : t('error', 'Ha ocurrido un error'), 'error')
})
document.body.addEventListener('htmx:sendError', () => Alpine.store('toast').show(t('network', 'Error de red'), 'error'))

// El servidor puede lanzar un aviso con la cabecera HX-Trigger: {"ml:toast": {"text": "…", "color": "success"}}
document.body.addEventListener('ml:toast', (e) => Alpine.store('toast').show(e.detail.text || e.detail.value, e.detail.color))
document.addEventListener('ml:session-expired', () => Alpine.store('toast').show(t('expired', 'Tu sesión ha caducado.'), 'warning'))

// ── Sesión ──────────────────────────────────────────────────────────────────────────────
const api = String(cfg.apiBase || '/wp-json/motorlan/v1/').replace(/\/$/, '')
const loginWithReturn = () => {
  const login = cfg.loginUrl || '/mi-cuenta/login'
  return `${login}${login.includes('?') ? '&' : '?'}to=${encodeURIComponent(window.location.pathname + window.location.search)}`
}

// Cerrar sesión (UserProfile.vue): POST /logout con nonce (sin él WordPress no destruye el
// token de sesión) y al acceso con recarga completa.
window.mlLogout = async () => {
  try { await fetchRest(`${api}/logout`, { method: 'POST', headers: { Accept: 'application/json' } }) } catch (e) { /* se sale igualmente */ }
  window.location.href = cfg.loginUrl || '/'
}

// Atrás/adelante con la página en bfcache: si la sesión ya no vale (se cerró en otra
// pestaña), al acceso en vez de enseñar datos de la sesión anterior (App.vue de la SPA).
window.addEventListener('pageshow', async (e) => {
  if (!e.persisted || !cfg.isLogged) return
  try {
    const res = await fetchRest(`${api}/session`, { headers: { Accept: 'application/json' } })
    const data = await res.json()
    if (!data || !data.is_logged_in) window.location.href = loginWithReturn()
  } catch (err) { /* sin red: se mantiene la página */ }
})

// ── Alpine ──────────────────────────────────────────────────────────────────────────────

// Aviso flotante (VSnackbar de la SPA).
Alpine.store('toast', {
  open: false,
  text: '',
  color: 'secondary',
  timer: null,
  show(text, color = 'secondary', ms = 4000) {
    this.text = text
    this.color = color
    this.open = true
    clearTimeout(this.timer)
    this.timer = setTimeout(() => { this.open = false }, ms)
  },
})

const store = {
  get(key) { try { return window.localStorage.getItem(key) } catch (e) { return null } },
  set(key, value) { try { window.localStorage.setItem(key, value) } catch (e) { /* sin almacenamiento */ } },
}

// Layout del panel: menú lateral colapsable (se recuerda), off-canvas en < 1280 px y
// barra superior con sombra al hacer scroll (navbar flotante de Vuexy).
Alpine.data('appShell', () => ({
  collapsed: store.get('ml-nav-collapsed') === '1',
  hovered: false,
  mobileOpen: false,
  scrolled: false,
  init() {
    const onScroll = () => { this.scrolled = window.scrollY > 0 }
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    this.$watch('mobileOpen', (open) => { document.documentElement.classList.toggle('overflow-hidden', open) })
  },
  get mini() { return this.collapsed && !this.hovered },
  toggleCollapsed() {
    this.collapsed = !this.collapsed
    store.set('ml-nav-collapsed', this.collapsed ? '1' : '0')
  },
}))

// Diálogo (VDialog): x-data="mlDialog()" + @click="show()" / x-show="open".
Alpine.data('mlDialog', (initial = false) => ({
  open: initial,
  // x-trap.noscroll bloquea el scroll de la página mientras está abierto.
  show() { this.open = true },
  close() { this.open = false },
}))

// Pestañas (VTabs): x-data="mlTabs('general')".
Alpine.data('mlTabs', (initial) => ({
  tab: initial,
  select(name) { this.tab = name },
  isActive(name) { return this.tab === name },
}))

// Campo de archivo (VFileInput): muestra los nombres elegidos.
Alpine.data('mlFile', () => ({
  names: [],
  pick(e) { this.names = Array.from(e.target.files || []).map((f) => f.name) },
  clear() { this.names = []; this.$refs.input.value = '' },
}))

// Stepper (AppStepper): x-data="mlStepper(3)".
Alpine.data('mlStepper', (count, initial = 0) => ({
  step: initial,
  count,
  next() { if (this.step < this.count - 1) this.step++ },
  prev() { if (this.step > 0) this.step-- },
  go(i) { this.step = i },
}))

// Pantallas públicas (fase 1): tienda, chat de la ficha y acceso.
Alpine.data('mlStore', mlStore)
Alpine.data('mlProductChat', mlProductChat)
Alpine.data('mlAuth', mlAuth)
Alpine.data('mlPassword', mlPassword)
Alpine.data('mlAuthToken', mlAuthToken)

// Panel (fase 2): campana, chat del kit y Mi perfil.
Alpine.data('mlNotifications', mlNotifications)
Alpine.data('mlChat', mlChat)
Alpine.data('mlAvatar', mlAvatar)
Alpine.data('mlChangePassword', mlChangePassword)
Alpine.data('mlEmailConfirm', mlEmailConfirm)

Alpine.plugin(focus) // x-trap: foco dentro de los diálogos
window.Alpine = Alpine
Alpine.start()
