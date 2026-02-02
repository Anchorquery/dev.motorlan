export default [
  { heading: 'Gestión de Ventas', action: 'read', subject: 'all' },
  {
    title: 'Mis Publicaciones',
    icon: { icon: 'tabler-list' },
    to: 'dashboard-publications-publication-list',
    action: 'read',
    subject: 'all',
  },
  {
    title: 'Ventas',
    icon: { icon: 'tabler-currency-euro' },
    to: 'dashboard-publications-sales',
    action: 'read',
    subject: 'all',
  },
  {
    title: 'Ofertas Recibidas',
    icon: { icon: 'tabler-tag' },
    to: 'dashboard-publications-offers-received',
    action: 'read',
    subject: 'all',
  },
  {
    title: 'Preguntas',
    icon: { icon: 'tabler-message-question' },
    to: { name: 'dashboard-questions-list' },
    action: 'read',
    subject: 'all',
  },
  {
    title: 'Interesados',
    icon: { icon: 'tabler-users' },
    to: { name: 'dashboard-inquiries' },
    action: 'read',
    subject: 'all',
  },

  { heading: 'Gestión de Compras', action: 'read', subject: 'all' },
  {
    title: 'Mis Compras',
    icon: { icon: 'tabler-shopping-bag' },
    to: 'dashboard-purchases-purchases',
    action: 'read',
    subject: 'all',
  },
  {
    title: 'Ofertas Enviadas',
    icon: { icon: 'tabler-send' },
    to: 'dashboard-purchases-offers-sent',
    action: 'read',
    subject: 'all',
  },
  {
    title: 'Favoritos',
    icon: { icon: 'tabler-heart' },
    to: { name: 'dashboard-favorites-list' },
    action: 'read',
    subject: 'all',
  },



  { heading: 'Administración', action: 'manage', subject: 'all' },
  {
    title: 'Publicaciones',
    icon: { icon: 'tabler-list' },
    to: { name: 'dashboard-admin-publications' },
    action: 'manage',
    subject: 'all',
  },
  {
    title: 'Aprobaciones',
    icon: { icon: 'tabler-check' },
    to: { name: 'dashboard-admin-approvals' },
    action: 'manage',
    subject: 'all',
  },
]
