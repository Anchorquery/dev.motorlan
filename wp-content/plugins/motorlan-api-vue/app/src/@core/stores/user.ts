import { defineStore } from 'pinia'
import api from '@/services/api'

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
        const data = await api('/wp-json/motorlan/v1/session')
        if (data) {
          this.isLoggedIn = data.is_logged_in
          this.user = data.user
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