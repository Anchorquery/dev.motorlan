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
      const token = getToken()
      const headers = new Headers(options.headers as any)
      const isFormData = options.body instanceof FormData
      if (!isFormData)
        headers.set('Content-Type', 'application/json')
      if (token)
        headers.set('Authorization', `Bearer ${token}`)
      const nonce = (window as any)?.wpData?.nonce
      if (nonce)
        headers.set('X-WP-Nonce', nonce)
      return { options: { ...options, headers } }
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
