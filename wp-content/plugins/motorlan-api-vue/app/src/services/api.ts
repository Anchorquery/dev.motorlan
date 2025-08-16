import { ofetch } from 'ofetch'

import { useCookie } from '#app'

const baseURL = import.meta.env.VITE_API_BASE_URL

// Function to get the token from storage
const getToken = () => {
  const token = useCookie('accessToken')

  return token.value
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
