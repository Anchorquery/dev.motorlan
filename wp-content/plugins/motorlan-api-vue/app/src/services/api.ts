import { ofetch } from 'ofetch'
import { parse } from 'cookie-es'

const baseURL = import.meta.env.VITE_API_BASE_URL

// Function to get the token from storage
const getToken = () => {
  // As this isn't a composable, we can't use `useCookie` here.
  // Instead, we'll parse the cookie directly from the document.
  if (typeof document === 'undefined')
    return null

  const cookies = parse(document.cookie)

  return cookies.accessToken || null
}

// Helper function to clear a cookie by name
const clearCookie = (name: string) => {
  document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`
}

const api = ofetch.create({
  baseURL,
  headers: {
    'Content-Type': 'application/json',
  },
  onRequest: ({ options }) => {
    const token = getToken()

    // Create a new Headers object from the existing headers
    const headers = new Headers(options.headers)

    if (token)
      headers.set('Authorization', `Bearer ${token}`)

    options.headers = headers
  },
  onResponseError: ({ response }) => {
    if (response.status === 401) {
      // Clear all user-related cookies
      clearCookie('userData')
      clearCookie('accessToken')
      clearCookie('userAbilityRules')

      // We are using hard navigation here as router instance is not available here
      // You can also use create a plugin to navigate to login page
      // e.g. create a new plugin that exposes a function to navigate to login page
      // and then call that function here
      window.location.href = '/login'
    }
  },
})

export default api
