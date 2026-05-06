import type { RouteNamedMap, _RouterTyped } from 'unplugin-vue-router'
import { useUserStore } from '@/@core/stores/user'
import { watch } from 'vue'

export const setupGuards = (router: _RouterTyped<RouteNamedMap & { [key: string]: any }>) => {
  router.beforeEach(async to => {
    // History mode: Detectar base y redirigir según contexto
    const wpData = (window as unknown as { wpData?: { vue_base?: string, initial_route?: string, user_data?: { is_logged_in: boolean } } }).wpData
    const vueBase = wpData?.vue_base || '/'
    const initialRoute = wpData?.initial_route

    // Si estamos en la raíz, verificar si hay una ruta inicial (ej: login) o redirigir a dashboard
    if (to.path === '/') {
      if (initialRoute) {
        // Eliminar slash inicial si existe para evitar doble slash
        const target = initialRoute.startsWith('/') ? initialRoute : `/${initialRoute}`
        return { path: target, replace: true }
      }

      if (vueBase.includes('mi-cuenta')) {
        return { path: '/dashboard', replace: true }
      }
    }

    // Public routes are accessible by everyone
    if (to.meta.public)
      return

    // Unauthenticated only routes (login, register) should be accessible without waiting for user store
    // This prevents redirect loops when accessing login page while not authenticated
    if (to.meta.unauthenticatedOnly) {
      const userStore = useUserStore()
      const isWpLoggedIn = wpData?.user_data?.is_logged_in

      // Check if user is logged in (via store OR wpData hint)
      if (isWpLoggedIn || (!userStore.isLoading && userStore.getIsLoggedIn)) {
        return { name: 'dashboard-purchases-purchases', replace: true }
      }

      // Allow access to login/register pages without waiting for store
      return undefined
    }

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
