import { createFetch } from '@vueuse/core'
import { parse } from 'cookie-es'

const baseURL = import.meta.env.VITE_API_BASE_URL

const getToken = () => {
  if (typeof document === 'undefined') return null
  const cookies = parse(document.cookie)
  return cookies.accessToken || null
}

const clearCookie = (name: string) => {
  document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`
}

export const useApi = createFetch({
  baseUrl: baseURL,
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
      const nonce = (window as any)?.wpData?.nonce
      if (nonce)
        headers.set('X-WP-Nonce', nonce)
      return { options: { ...nextOptions, headers } }
    },
    async onFetchError({ response }) {
      if (response && response.status === 401) {
        clearCookie('userData')
        clearCookie('accessToken')
        clearCookie('userAbilityRules')
        window.location.href = '/login'
      }
      return {}
    },
  },
})
