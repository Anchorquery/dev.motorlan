import { defineStore } from 'pinia'
import { useApi } from '@/composables/useApi'

interface User {
  id: number
  email: string
  display_name: string
  isAdmin: boolean
}

interface UserState {
  isLoggedIn: boolean
  user: User | null
  loading: boolean
}

export const useUserStore = defineStore('user', {
  state: (): UserState => {
    // Initialize from WordPress bootstrap data if available
    const bootstrapData = (typeof window !== 'undefined') ? (window as any).wpData?.user_data : null

    if (bootstrapData?.user) {
      return {
        isLoggedIn: Boolean(bootstrapData.is_logged_in) && Boolean(bootstrapData.user?.id),
        user: {
          id: bootstrapData.user.id ?? 0,
          email: bootstrapData.user.email ?? '',
          display_name: bootstrapData.user.display_name ?? bootstrapData.user.displayName ?? '',
          isAdmin: Boolean(bootstrapData.user.is_admin),
        },
        loading: false,
      }
    }

    return {
      isLoggedIn: false,
      user: null,
      loading: true,
    }
  },

  getters: {
    getUser: (state) => state.user,
    getIsLoggedIn: (state) => state.isLoggedIn,
    isLoading: (state) => state.loading,
    isAdmin: (state) => state.user?.isAdmin ?? false,
  },

  actions: {
    /**
     * Set user data from WordPress bootstrap or login response
     */
    setFromBootstrap(user: Partial<User & { is_admin?: boolean }> | null, isLoggedIn: boolean) {
      this.user = user
        ? {
          id: user.id ?? 0,
          email: user.email ?? '',
          display_name: user.display_name ?? (user as any).displayName ?? '',
          isAdmin: Boolean(user.is_admin ?? (user as any).isAdmin),
        }
        : null
      this.isLoggedIn = Boolean(isLoggedIn) && Boolean(this.user?.id)
      this.loading = false
    },

    /**
     * Fetch current session from server
     * Uses WordPress session cookies automatically
     */
    async fetchUserSession() {
      this.loading = true
      try {
        const { data, error } = await useApi('/wp-json/motorlan/v1/session').get().json()

        if (error.value)
          throw error.value

        const payload: any = data.value

        if (payload && payload.is_logged_in && payload.user) {
          this.user = {
            id: payload.user.id ?? 0,
            email: payload.user.email ?? '',
            display_name: payload.user.display_name ?? '',
            isAdmin: Boolean(payload.user.is_admin),
          }
          this.isLoggedIn = true
        }
        else {
          this.user = null
          this.isLoggedIn = false
        }
      }
      catch (error) {
        console.error('Session check error', error)
        this.user = null
        this.isLoggedIn = false
      }
      finally {
        this.loading = false
      }
    },

    /**
     * Clear user state (called on logout)
     */
    logout() {
      this.user = null
      this.isLoggedIn = false
      this.loading = false
    },
  },
})
