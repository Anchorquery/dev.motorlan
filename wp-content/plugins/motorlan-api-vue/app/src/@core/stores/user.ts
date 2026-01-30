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
    async fetchUserSession() {
      this.loading = true
      try {
        const { data, error } = await useApi('/wp-json/motorlan/v1/session').get().json()
        if (error.value)
          throw error.value

        const payload: any = (data.value && (data.value as any).data) ? (data.value as any).data : data.value
        if (payload) {
          const candidateUser = payload.user ?? payload
          const normalizedUser = candidateUser
            && typeof candidateUser === 'object'
            && ('id' in candidateUser || 'email' in candidateUser || 'display_name' in candidateUser)
            ? {
              id: candidateUser.id ?? 0,
              email: candidateUser.email ?? '',
              display_name: candidateUser.display_name ?? candidateUser.displayName ?? '',
              isAdmin: Boolean(candidateUser.is_admin ?? candidateUser.isAdmin),
            }
            : null

          if (normalizedUser) {
            this.user = normalizedUser as User
            this.isLoggedIn = Boolean(payload.is_logged_in ?? payload.isLoggedIn ?? normalizedUser?.id)
          } else if (payload.is_logged_in === false || payload.isLoggedIn === false) {
            // Solo si explícitamente nos dicen que no está logueado
            this.isLoggedIn = false
            this.user = null
          }
        }
      } catch (error) {
        console.error('Error fetching user session:', error)
        // No limpiamos el estado inmediatamente en caso de error de red o de la API
        // si ya teníamos datos previos (p.ej. de WordPress Bootstrap)
        if (!this.user) {
          this.isLoggedIn = false
        }
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
