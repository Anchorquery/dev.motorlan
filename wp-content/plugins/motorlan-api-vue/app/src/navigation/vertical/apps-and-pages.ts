export default [
  {
    title: 'Motors Shop',
    to: { name: 'tienda' },
    icon: { icon: 'tabler-shopping-cart' },
  },
  { heading: 'Apps & Pages' },
  {
    title: 'Publicaciones',
    icon: { icon: 'tabler-file-text' },
    children: [
      { title: 'List', to: 'apps-publicaciones-publicacion-list' },
      { title: 'Add', to: 'apps-publicaciones-publicacion-add' },
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
    title: 'User',
    icon: { icon: 'tabler-user' },
    children: [
      { title: 'List', to: 'apps-user-list' },
      { title: 'View', to: { name: 'apps-user-view-id', params: { id: 21 } } },
    ],
  },
  {
    title: 'Compras',
    icon: { icon: 'tabler-user-circle' },
    children: [
      { title: 'Compras', to: 'apps-my-account-purchases' },
      { title: 'Preguntas', to: 'apps-my-account-questions' },
      { title: 'Opiniones', to: 'apps-my-account-opinions' },
      { title: 'Favoritos', to: 'apps-my-account-favorites' },
    ],
  },
]
