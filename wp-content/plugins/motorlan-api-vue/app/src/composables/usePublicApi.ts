import { createFetch } from '@vueuse/core'

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

export const usePublicApi = createFetch({
  baseUrl: baseURL,
  fetchOptions: {
    credentials: 'omit',
  },
  options: {
    async beforeFetch({ options }) {
      const nextOptions: RequestInit = { ...options }
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

      if (shouldSerializeBody && String(nextOptions.method || 'GET').toUpperCase() !== 'GET')
        headers.set('Content-Type', 'application/json')

      return { options: { ...nextOptions, headers } }
    },

    async onFetchError({ error }) {
      console.error('Public API error:', error)
      return { error }
    },
  },
})
