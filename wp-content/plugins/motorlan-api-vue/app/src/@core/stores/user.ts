import { defineStore } from 'pinia'
import { useApi } from '@/composables/useApi'

interface User {
  id: number
  email: string
  display_name: string
}

interface UserState {
  isLoggedIn: boolean
  user: User | null
  loading: boolean
}

export const useUserStore = defineStore('user', {
  state: (): UserState => ({
    isLoggedIn: false,
    user: null,
    loading: true,
  }),
  getters: {
    getUser: (state) => state.user,
    getIsLoggedIn: (state) => state.isLoggedIn,
    isLoading: (state) => state.loading,
  },
  actions: {
    async fetchUserSession() {
      this.loading = true
      try {
        const { data, error } = await useApi('/wp-json/motorlan/v1/session').get().json()
        if (error.value)
          throw error.value

        if (data.value) {
          this.isLoggedIn = data.value.is_logged_in
          this.user = data.value.user
        }
      } catch (error) {
        console.error('Error fetching user session:', error)
        this.isLoggedIn = false
        this.user = null
      } finally {
        this.loading = false
      }
    },
    logout() {
      this.user = null
      this.isLoggedIn = false
    },
  },
})