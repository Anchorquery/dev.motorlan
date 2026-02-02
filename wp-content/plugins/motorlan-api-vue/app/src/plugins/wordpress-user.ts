import type { App } from 'vue'
import { useUserStore } from '@/@core/stores/user'

declare global {
  interface Window {
    wpData?: {
      user_data?: {
        is_logged_in?: boolean
        user?: {
          id?: number
          email?: string
          display_name?: string
          displayName?: string
          nicename?: string
          is_admin?: boolean
        }
      }
    }
  }
}

/**
 * WordPress User Plugin
 * Initializes user state from WordPress bootstrap data or fetches from API.
 * Uses WordPress session cookies for authentication (no JWT).
 */
export default async function (_app: App) {
  const userStore = useUserStore()
  const bootstrapData = window.wpData?.user_data

  if (bootstrapData?.user && bootstrapData.is_logged_in) {
    // User data available from WordPress bootstrap
    userStore.setFromBootstrap(bootstrapData.user, true)
  }
  else if (!bootstrapData?.is_logged_in) {
    // Not logged in according to bootstrap
    userStore.setFromBootstrap(null, false)
  }
  else {
    // No bootstrap data available - fetch from API
    await userStore.fetchUserSession()
  }
}
