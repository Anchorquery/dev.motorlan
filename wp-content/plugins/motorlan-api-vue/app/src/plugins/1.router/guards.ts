import type { RouteNamedMap, _RouterTyped } from 'unplugin-vue-router'
import { useUserStore } from '@/@core/stores/user'
import { watch } from 'vue'

export const setupGuards = (router: _RouterTyped<RouteNamedMap & { [key: string]: any }>) => {
  router.beforeEach(async to => {
    // History mode: Detectar base y redirigir según contexto
    const vueBase = (window as unknown as { wpData?: { vue_base?: string } }).wpData?.vue_base || '/'

    // Si estamos en /mi-cuenta/ y la ruta es la raíz, redirigir al dashboard
    if (vueBase.includes('mi-cuenta') && to.path === '/') {
      return { path: '/dashboard', replace: true }
    }

    // Public routes are accessible by everyone
    if (to.meta.public)
      return

    const userStore = useUserStore()

    // Wait for store to finish loading if needed
    if (userStore.isLoading) {
      await new Promise<void>(resolve => {
        const unwatch = watch(
          () => userStore.isLoading,
          loading => {
            if (!loading) {
              unwatch()
              resolve()
            }
          },
        )
        // Check immediate in case it changed
        if (!userStore.isLoading) {
          unwatch()
          resolve()
        }
        // Safety timeout
        setTimeout(() => {
          unwatch()
          resolve()
        }, 2000)
      })
    }

    const isLoggedIn = userStore.getIsLoggedIn

    // If route is only for unauthenticated users (login, register, etc.)
    if (to.meta.unauthenticatedOnly) {
      if (isLoggedIn)
        return { name: 'dashboard-purchases-purchases', replace: true }
      else
        return undefined
    }

    // Allow access to account page for logged in users (profile completion)
    if (to.name === 'dashboard-user-account' && isLoggedIn)
      return undefined

    // Redirect to login if not authenticated
    if (!isLoggedIn) {
      return {
        name: 'login',
        query: {
          ...to.query,
          to: to.fullPath !== '/' ? to.path : undefined,
        },
      }
    }
  })
}
