import { createFetch } from '@vueuse/core'
import { parse } from 'cookie-es'

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
const TOKEN_HEADER = 'X-Motorlan-New-Access-Token'
const TOKEN_EXPIRES_HEADER = 'X-Motorlan-New-Access-Token-Expires'

const getToken = () => {
  if (typeof document === 'undefined') return null
  const cookies = parse(document.cookie)
  return cookies.accessToken || null
}

const clearCookie = (name: string) => {
  document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`
}

const updateStoredToken = (token: string, expires?: string) => {
  if (typeof document === 'undefined' || !token) return

  const expiration = expires ? new Date(expires) : new Date(Date.now() + 7 * 24 * 60 * 60 * 1000)
  const secure = (typeof window !== 'undefined' && window.location.protocol === 'https:') ? ' Secure;' : ''
  document.cookie = `accessToken=${token}; path=/; expires=${expiration.toUTCString()};${secure}`

  if (typeof localStorage !== 'undefined') {
    localStorage.setItem('accessToken', token)
  }
}

export const useApi = createFetch({
  baseUrl: baseURL,
  fetchOptions: {
    credentials: 'include',
  },
  options: {
    async beforeFetch({ options }) {
      const nextOptions: RequestInit = { ...options }
      const token = getToken()
      const headers = new Headers(nextOptions.headers as any)
      const body = nextOptions.body as any
      const isFormData = body instanceof FormData
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

      if (!isFormData)
        headers.set('Content-Type', 'application/json')
      if (token)
        headers.set('Authorization', `Bearer ${token}`)
      const nonce = (window as any)?.wpData?.nonce || (window as any)?.wpData?.rest_nonce
      if (nonce)
        headers.set('X-WP-Nonce', nonce)
      return { options: { ...nextOptions, headers } }
    },
    async onFetchError({ response }) {
      if (response && response.status === 401) {
        // Si estamos en un entorno con WordPress y el bootstrap dice que estamos logueados,
        // no redirigimos inmediatamente, podría ser un fallo puntual del JWT que el backend puede resolver.
        const isWpLoggedIn = (window as any)?.wpData?.user_data?.is_logged_in

        if (isWpLoggedIn) {
          console.warn('API returned 401 but WordPress session is active. Avoiding redirect.')
          return {}
        }

        clearCookie('userData')
        clearCookie('accessToken')
        clearCookie('userAbilityRules')
        window.location.href = '/login'
      }
      return {}
    },
    async afterFetch({ response }) {
      const newToken = response?.headers?.get(TOKEN_HEADER)
      const expires = response?.headers?.get(TOKEN_EXPIRES_HEADER)
      if (newToken) {
        updateStoredToken(newToken, expires || undefined)
      }
      return { response }
    },
  },
})
