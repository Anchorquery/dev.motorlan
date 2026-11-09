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
  // Preguntas y respuestas: el backend desactivó sus rutas REST (questions-routes.php)
  // y las sustituyó por el chat "Interesados". Emails y notificaciones antiguas aún
  // enlazan aquí.
  {
    path: '/dashboard/publications/questions',
    redirect: { name: 'dashboard-inquiries' },
  },
  {
    path: '/dashboard/publications/publication/questions/:uuid',
    redirect: { name: 'dashboard-inquiries' },
  },
  {
    path: '/dashboard/questions/list',
    redirect: { name: 'dashboard-inquiries' },
  },
  {
    path: '/dashboard/purchases/questions',
    redirect: { name: 'dashboard-purchases-inquiries' },
  },
  {
    path: '/dashboard/user/company',
    redirect: { name: 'dashboard-user-account' },
  },
]

export const routes: RouteRecordRaw[] = []
