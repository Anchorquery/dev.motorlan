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
        }
      }
    }
  }
}

export default function (_app: App) {
  const userStore = useUserStore()
  const bootstrapData = window.wpData?.user_data

  if (bootstrapData) {
    userStore.setFromBootstrap(
      bootstrapData.user ?? null,
      Boolean(bootstrapData.is_logged_in)
    )
  }

  if (!bootstrapData?.is_logged_in) {
    userStore.fetchUserSession()
  }
}
