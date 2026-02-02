import { createFetch } from '@vueuse/core'
import { useUserStore } from '@/@core/stores/user'

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

export const useApi = createFetch({
  baseUrl: baseURL,
  fetchOptions: {
    // Include cookies in all requests - this is how WordPress session auth works
    credentials: 'include',
  },
  options: {
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
      const nonce = (window as any)?.wpData?.nonce || (window as any)?.wpData?.rest_nonce
      if (nonce)
        headers.set('X-WP-Nonce', nonce)

      return { options: { ...nextOptions, headers } }
    },

    async onFetchError({ response }) {
      if (response && response.status === 401) {
        // Session expired or user not logged in
        const userStore = useUserStore()
        userStore.logout()

        // Redirect to login
        window.location.href = '/login'
      }
      return {}
    },
  },
})
