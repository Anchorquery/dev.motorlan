// Nonce REST de la página (sesión de WordPress con cookie). Caduca a las 12-24 h, o deja de
// valer si se entra o sale en otra pestaña: WordPress responde 403 rest_cookie_invalid_nonce
// a todo. Las lecturas del portal las atiende el servidor como invitado y lo avisa con
// X-Motorlan-Nonce-Stale (Security\StaleNonce); en cualquiera de los dos casos se pide un
// nonce nuevo (una sola petición a la vez) y se repite la petición una vez.

const cfg = window.motorlanPortal || {}
let pending = null

export const STALE_HEADER = 'X-Motorlan-Nonce-Stale'

// motorlan_rest_nonce (motorlan-api-vue) responde {success, data: {nonce, logged_in}};
// rest-nonce (WordPress) responde el nonce en texto, o 0 sin sesión.
export const renewNonce = () => {
  if (!pending) {
    pending = fetch(cfg.nonceUrl || '/wp-admin/admin-ajax.php?action=rest-nonce', { credentials: 'same-origin' })
      .then((r) => (r.ok ? r.text() : ''))
      .catch(() => '')
      .then((text) => {
        let fresh = String(text || '').trim()
        let logged = null
        try {
          const json = JSON.parse(fresh)
          fresh = json && json.data && json.data.nonce ? String(json.data.nonce) : ''
          logged = json && json.data ? !!json.data.logged_in : null
        } catch (e) { /* texto plano */ }
        cfg.nonce = /^[a-f0-9]{10}$/i.test(fresh) ? fresh : ''
        if (logged === false && cfg.isLogged) {
          // La sesión terminó en el servidor: lo que siga fallará; se avisa a la página.
          document.dispatchEvent(new CustomEvent('ml:session-expired'))
        }
        pending = null
        return cfg.nonce
      })
  }
  return pending
}

export const isStaleText = (status, text) =>
  status === 403 && String(text || '').includes('rest_cookie_invalid_nonce')

export const isStale = async (res) => {
  if (!res) return false
  if (res.headers && res.headers.get(STALE_HEADER) === '1') return true
  if (res.status !== 403) return false
  const data = await res.clone().json().catch(() => ({}))
  return !!data && data.code === 'rest_cookie_invalid_nonce'
}

// Cabeceras con el nonce actual. Sin nonce, sin cabecera: vacía, WordPress responde 403.
export const nonceHeaders = (headers = {}) => {
  const out = { ...headers }
  delete out['X-WP-Nonce']
  if (cfg.nonce) out['X-WP-Nonce'] = cfg.nonce
  return out
}

// fetch a la REST con el nonce y, si ya no vale, uno nuevo y otro intento.
export const fetchRest = async (url, opts = {}) => {
  const send = () => fetch(url, { credentials: 'same-origin', ...opts, headers: nonceHeaders(opts.headers) })
  const res = await send()
  if (!(await isStale(res))) return res
  await renewNonce()
  return send()
}
