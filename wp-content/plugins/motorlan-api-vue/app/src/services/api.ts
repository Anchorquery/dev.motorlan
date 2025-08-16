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

const api = ofetch.create({
  baseURL,
  headers: {
    'Content-Type': 'application/json', 
  },
  onRequest: ({ options }) => {
    const token = getToken()
    if (token) {
      options.headers = {
        ...options.headers,
        Authorization: `Bearer ${token}`,
      }
    }
  },
})

export default api
