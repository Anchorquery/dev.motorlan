import { createFetch } from '@vueuse/core'
import { useUserStore } from '@/@core/stores/user'
import { getI18n } from '@/plugins/i18n'
import { pageUrl } from '@/utils/urls'

/**
 * API composable using WordPress session cookies for authentication.
 * No JWT tokens - relies entirely on WordPress native session management.
 */

const getSiteBaseUrl = () => {
  if (typeof window === 'undefined')
    return ''

  const wpSiteUrl = (window as any)?.wpData?.site_url
  if (typeof wpSiteUrl === 'string' && wpSiteUrl.trim().length)
    return wpSiteUrl.trim()

  return window.location.origin
}

const envBaseUrl = import.meta.env.VITE_API_BASE_URL?.trim() ?? ''
const baseURL = envBaseUrl.length ? envBaseUrl : getSiteBaseUrl()

const tr = (key: string, fallback: string, named: Record<string, unknown> = {}) => {
  try {
    return getI18n().global.t(key, named, fallback) as string
  }
  catch {
    return fallback
  }
}

// --- Nonce REST ---------------------------------------------------------------
// El nonce solo venía en el HTML inicial y caduca a las 12-24 h, mientras la cookie
// dura 14 días: en pestañas abiertas desde el día anterior todo POST daba 403.
let lastNonceRefresh = Date.now()

const getRestNonce = (): string | null => {
  const wpData = (window as any)?.wpData

  return wpData?.nonce || wpData?.rest_nonce || null
}

const setRestNonce = (nonce?: string | null) => {
  const wpData = (window as any)?.wpData
  if (!nonce || !wpData)
    return
  wpData.nonce = nonce
  wpData.rest_nonce = nonce
  lastNonceRefresh = Date.now()
}

/**
 * Resultado de pedir un nonce nuevo:
 * - `ok`: hay sesión y el nonce nuevo vale para ella.
 * - `anonymous`: el servidor no ve sesión (cookie caducada o usuario anónimo). Si el
 *   endpoint de admin-ajax devuelve también un nonce anónimo, viene en `nonce`.
 * - `error`: sin red o respuesta inesperada.
 */
type NonceRefreshResult = { state: 'ok' | 'anonymous' | 'error'; nonce?: string }

let pendingRefresh: Promise<NonceRefreshResult> | null = null

const doRefreshRestNonce = async (): Promise<NonceRefreshResult> => {
  const url = (window as any)?.wpData?.nonce_refresh_url
  if (!url)
    return { state: 'error' }
  try {
    const res = await fetch(url, { credentials: 'include', cache: 'no-store' })
    const json = await res.json().catch(() => null)
    if (json?.success && json.data?.nonce) {
      if (json.data.logged_in === false)
        return { state: 'anonymous', nonce: json.data.nonce }
      setRestNonce(json.data.nonce)

      return { state: 'ok', nonce: json.data.nonce }
    }
    if (res.status === 401 || json?.data?.code === 'not_logged_in')
      return { state: 'anonymous' }

    return { state: 'error' }
  }
  catch {
    // sin red: se reintentará en la próxima visita a la pestaña
    return { state: 'error' }
  }
}

/**
 * Pide un nonce REST nuevo (las llamadas simultáneas comparten la misma petición).
 * Exportado para usarlo justo después de cambiar la contraseña: la cookie nueva
 * invalida el nonce de la página.
 */
export const refreshRestNonce = (): Promise<NonceRefreshResult> => {
  if (!pendingRefresh) {
    pendingRefresh = doRefreshRestNonce().finally(() => {
      pendingRefresh = null
    })
  }

  return pendingRefresh
}

// Al volver a una pestaña tras más de 1 h, renovar el nonce de forma preventiva
if (typeof document !== 'undefined') {
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && Date.now() - lastNonceRefresh > 60 * 60 * 1000 && (window as any)?.wpData?.user_data)
      refreshRestNonce()
  })
}

const believesLoggedIn = () => {
  if ((window as any)?.wpData?.user_data?.is_logged_in)
    return true
  try {
    return useUserStore().getIsLoggedIn
  }
  catch {
    return false
  }
}

const isOnLoginPage = () => {
  const current = window.location.pathname.replace(/\/$/, '')
  const login = pageUrl('login').replace(/\/$/, '')

  return current === login || current.endsWith('/login')
}

/**
 * URL del login con ?to= para volver a la página actual tras iniciar sesión.
 */
export const loginUrlWithReturn = () => {
  const login = pageUrl('login')
  if (isOnLoginPage())
    return login
  const here = `${window.location.pathname}${window.location.search}`

  return `${login}${login.includes('?') ? '&' : '?'}to=${encodeURIComponent(here)}`
}

let redirectingToLogin = false

/**
 * Sesión caducada: limpiar el estado y llevar al login (una sola vez).
 */
export const redirectToLoginExpired = () => {
  try {
    useUserStore().logout()
  }
  catch { /* pinia aún no disponible */ }
  if (redirectingToLogin || isOnLoginPage())
    return
  redirectingToLogin = true
  window.location.href = loginUrlWithReturn()
}

const isInvalidNonceResponse = async (response: Response) => {
  if (response.status !== 403)
    return false
  const body = await response.clone().json().catch(() => null)

  return body?.code === 'rest_cookie_invalid_nonce'
}

/**
 * fetch con renovación automática del nonce: si WordPress responde 403
 * rest_cookie_invalid_nonce (el fallo es de autenticación, antes de ejecutar nada,
 * así que repetir es seguro) se pide un nonce nuevo y se repite UNA vez.
 * - Con sesión: se repite con el nonce nuevo (antes la acción fallaba y se mostraba
 *   "Tu sesión se ha renovado. Vuelve a intentarlo.", también justo después de cambiar
 *   la contraseña).
 * - Sin sesión y creyendo estar logueado (cookie caducada): al login con ?to=.
 * - Anónimo: se repite como anónimo (sin nonce o con el nonce anónimo nuevo). Antes
 *   el mensaje se repetía para siempre.
 */
export const fetchWithNonceRetry = async (
  input: RequestInfo | URL,
  init: RequestInit = {},
  opts: { redirectOnExpired?: boolean } = {},
): Promise<Response> => {
  const response = await fetch(input, init)
  if (!(await isInvalidNonceResponse(response)))
    return response

  const result = await refreshRestNonce()
  const headers = new Headers(init.headers as HeadersInit | undefined)

  if (result.state === 'ok' && result.nonce) {
    headers.set('X-WP-Nonce', result.nonce)

    return fetch(input, { ...init, headers })
  }

  if (result.state === 'anonymous') {
    if (believesLoggedIn()) {
      if (opts.redirectOnExpired !== false)
        redirectToLoginExpired()

      return response
    }
    if (result.nonce) {
      setRestNonce(result.nonce)
      headers.set('X-WP-Nonce', result.nonce)
    }
    else {
      headers.delete('X-WP-Nonce')
    }

    return fetch(input, { ...init, headers })
  }

  return response
}

/**
 * Llamada REST sencilla (fuera de useApi) con cookie + X-WP-Nonce + reintento.
 */
export const apiFetch = (path: string, init: RequestInit = {}, opts: { redirectOnExpired?: boolean } = {}) => {
  const headers = new Headers(init.headers as HeadersInit | undefined)
  const nonce = getRestNonce()
  if (nonce && !headers.has('X-WP-Nonce'))
    headers.set('X-WP-Nonce', nonce)
  const url = /^https?:\/\//.test(path) ? path : `${baseURL.replace(/\/$/, '')}${path.startsWith('/') ? '' : '/'}${path}`

  return fetchWithNonceRetry(url, { credentials: 'include', ...init, headers }, opts)
}

/**
 * Mensaje de error de la API traducido por `code` (auth_errors.<code>), con el texto
 * del backend como respaldo y la lista de requisitos de contraseña (data.errors).
 */
export const apiErrorMessage = (data: any, fallback: string) => {
  const payload = data && typeof data === 'object' ? data : {}
  // Parámetros disponibles para las traducciones (p. ej. "{minutes} minutos")
  const named = { minutes: payload.retry_minutes ?? '' }
  const base = payload.code
    ? tr(`auth_errors.${payload.code}`, payload.message || fallback, named)
    : (payload.message || fallback)
  const details: string[] = Array.isArray(payload.errors) ? payload.errors : []

  return details.length ? `${base} ${details.join(' ')}` : base
}

/**
 * `await useApi(...)` NO rechaza en respuestas 4xx/5xx (comportamiento de VueUse useFetch):
 * solo rellena `error`. Usar tras cualquier mutación para que los fallos del backend
 * lleguen al catch con el mensaje real (WP_Error -> data.message).
 */
export const assertApiOk = <T extends { error: { value: any }; data: { value: any }; statusCode?: { value: number | null } }>(req: T): T => {
  const status = req.statusCode?.value ?? null
  if (req.error.value || (status !== null && status >= 400)) {
    let data = req.data.value
    if (typeof data === 'string') {
      try { data = JSON.parse(data) }
      catch { /* respuesta no JSON (p. ej. HTML de un fatal PHP) */ }
    }
    const backendMsg = data && typeof data === 'object' ? data.message : null
    const err = new Error(backendMsg || req.error.value?.message || `Error en la petición (${status ?? 'red'})`) as Error & { status?: number | null; data?: any }
    err.status = status
    err.data = data
    throw err
  }

  return req
}

export const useApi = createFetch({
  baseUrl: baseURL,
  fetchOptions: {
    // Include cookies in all requests - this is how WordPress session auth works
    credentials: 'include',
  },
  options: {
    updateDataOnError: true,

    // Renueva el nonce y repite la petición si WordPress lo rechaza por caducado
    fetch: (input: RequestInfo | URL, init?: RequestInit) => fetchWithNonceRetry(input, init ?? {}),

    async beforeFetch({ options }) {
      const nextOptions: RequestInit = { ...options }
      const headers = new Headers(nextOptions.headers as any)
      const body = nextOptions.body as any
      const isFormData = body instanceof FormData

      // Serialize body if needed
      const shouldSerializeBody =
        body !== undefined
        && body !== null
        && !isFormData
        && typeof body === 'object'
        && !(body instanceof Blob)
        && !(body instanceof ArrayBuffer)
        && !(typeof ReadableStream !== 'undefined' && body instanceof ReadableStream)
        && !(body instanceof URLSearchParams)
        && !ArrayBuffer.isView(body)

      if (shouldSerializeBody)
        nextOptions.body = JSON.stringify(body)

      // Set content type for non-FormData requests
      if (!isFormData)
        headers.set('Content-Type', 'application/json')

      // Add WordPress nonce for CSRF protection
      const nonce = getRestNonce()
      if (nonce)
        headers.set('X-WP-Nonce', nonce)

      return { options: { ...nextOptions, headers } }
    },

    // WordPress devuelve un nonce fresco en X-WP-Nonce en las respuestas autenticadas.
    async afterFetch(ctx) {
      setRestNonce(ctx.response?.headers?.get('X-WP-Nonce'))

      return ctx
    },

    // IMPORTANTE: devolver el contexto. Cuando useApi() se llama sin objeto de opciones
    // de VueUse, este callback se usa tal cual (sin combineCallbacks) y su retorno
    // reemplaza { error, data }: devolver {} borraba error.value/data.value en todos
    // los 4xx/5xx y ninguna pantalla podía detectar el fallo.
    async onFetchError(ctx) {
      const { response } = ctx

      // Nonce caducado y el reintento automático tampoco funcionó (sin red, etc.)
      if (response && response.status === 403) {
        let body: any = ctx.data
        if (typeof body === 'string') {
          try { body = JSON.parse(body) }
          catch { body = null }
        }
        if (body && body.code === 'rest_cookie_invalid_nonce') {
          if (ctx.data && typeof ctx.data === 'object')
            ctx.data = { ...ctx.data, message: tr('auth_errors.rest_cookie_invalid_nonce', 'No se pudo validar tu sesión. Recarga la página e inténtalo de nuevo.') }

          return ctx
        }
      }

      if (response && response.status === 401) {
        // Session expired or user not logged in: al login, volviendo luego aquí
        redirectToLoginExpired()
      }

      return ctx
    },
  },
})
