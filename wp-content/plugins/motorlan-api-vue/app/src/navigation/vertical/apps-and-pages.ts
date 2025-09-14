export default [
  {
    title: 'Motors Shop',
    to: { name: 'store' },
    icon: { icon: 'tabler-shopping-cart' },
  },
  { heading: 'Apps & Pages' },
  {
    title: 'Publicaciones',
    icon: { icon: 'tabler-file-text' },
    children: [
      { title: 'List', to: 'apps-publications-publication-list' },
      { title: 'Add', to: 'apps-publications-publication-add' },
    ],
  },
  {
    title: 'Chat',
    icon: { icon: 'tabler-message-circle-2' },
    to: 'apps-chat',
  },

  {
    title: 'Invoice',
    icon: { icon: 'tabler-file-invoice' },

    children: [
      { title: 'List', to: 'apps-invoice-list' },
      { title: 'Preview', to: { name: 'apps-invoice-preview-id', params: { id: '5036' } } },
      { title: 'Edit', to: { name: 'apps-invoice-edit-id', params: { id: '5036' } } },
      { title: 'Add', to: 'apps-invoice-add' },
    ],
  },
  {
    title: 'Perfil',
    icon: { icon: 'tabler-user' },
    children: [
      { title: 'Cuenta', to: { name: 'apps-user-account' } },
      { title: 'Estadísticas', to: { name: 'apps-user-stats' } },
    ],
  },
  {
    title: 'Compras',
    icon: { icon: 'tabler-user-circle' },
    children: [
      { title: 'Compras', to: 'apps-purchases-purchases' },
      { title: 'Preguntas', to: 'apps-purchases-questions' },
      { title: 'Opiniones', to: 'apps-purchases-opinions' },
      { title: 'Favoritos', to: 'apps-purchases-favorites' },
    ],
  },
]
