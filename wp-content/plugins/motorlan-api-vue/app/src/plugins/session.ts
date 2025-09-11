import { useUserStore } from '@/@core/stores/user'
import type { App } from 'vue'

export default function (app: App) {
  // Se asume que Pinia ya está registrado antes que este plugin.
  // La función registerPlugins carga los plugins en orden alfabético.
  const userStore = useUserStore()
  userStore.fetchUserSession()
}