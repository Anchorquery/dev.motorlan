import type { RouteRecordRaw } from 'vue-router'

// Redirecciones de URLs antiguas (páginas eliminadas) por si quedan enlaces guardados
export const redirects: RouteRecordRaw[] = [
  {
    path: '/dashboard/chat',
    redirect: '/dashboard/inquiries',
  },
  {
    path: '/dashboard/purchases',
    redirect: { name: 'dashboard-purchases-purchases' },
  },
  {
    path: '/dashboard/purchases/list',
    redirect: { name: 'dashboard-purchases-purchases' },
  },
  {
    path: '/dashboard/purchases/favorites',
    redirect: { name: 'dashboard-favorites-list' },
  },
  {
    path: '/dashboard/purchases/chat/:uuid',
    redirect: to => ({ path: `/dashboard/purchases/${to.params.uuid}`, query: { openChat: 'true' } }),
  },
  {
    path: '/dashboard/user/company',
    redirect: { name: 'dashboard-user-account' },
  },
]

export const routes: RouteRecordRaw[] = []
