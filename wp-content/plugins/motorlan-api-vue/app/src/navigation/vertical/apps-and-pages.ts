export default [
  { heading: 'Gestión de Ventas' },
  {
    title: 'Mis Publicaciones',
    icon: { icon: 'tabler-list' },
    to: 'dashboard-publications-publication-list',
  },
  {
    title: 'Ventas',
    icon: { icon: 'tabler-currency-euro' },
    to: 'dashboard-publications-sales',
  },
  {
    title: 'Ofertas Recibidas',
    icon: { icon: 'tabler-tag' },
    to: 'dashboard-publications-offers-received',
  },
  {
    title: 'Preguntas',
    icon: { icon: 'tabler-message-question' },
    to: { name: 'dashboard-questions-list' },
  },
  {
    title: 'Interesados',
    icon: { icon: 'tabler-users' },
    to: { name: 'dashboard-inquiries' },
  },

  { heading: 'Gestión de Compras' },
  {
    title: 'Mis Compras',
    icon: { icon: 'tabler-shopping-bag' },
    to: 'dashboard-purchases-purchases',
  },
  {
    title: 'Ofertas Enviadas',
    icon: { icon: 'tabler-send' },
    to: 'dashboard-purchases-offers-sent',
  },
  {
    title: 'Favoritos',
    icon: { icon: 'tabler-heart' },
    to: { name: 'dashboard-favorites-list' },
  },

  { heading: 'Mi Cuenta' },
  {
    title: 'Perfil',
    icon: { icon: 'tabler-user' },
    to: { name: 'dashboard-user-account' },
  },

  { heading: 'Administración', action: 'manage', subject: 'all' },
  {
    title: 'Aprobaciones',
    icon: { icon: 'tabler-check' },
    to: { name: 'dashboard-admin-approvals' },
    action: 'manage',
    subject: 'all',
  },
]
