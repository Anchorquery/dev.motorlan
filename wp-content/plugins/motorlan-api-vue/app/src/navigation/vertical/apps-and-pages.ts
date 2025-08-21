export default [
  {
    title: 'Motors Shop',
    to: { name: 'tienda' },
    icon: { icon: 'tabler-shopping-cart' },
  },
  { heading: 'Apps & Pages' },
  {
    title: 'Motors',
    icon: { icon: 'tabler-car' },
    children: [
      { title: 'List', to: 'apps-motors-motor-list' },
      { title: 'Add', to: 'apps-motors-motor-add' },
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
    title: 'My Purchases',
    icon: { icon: 'tabler-shopping-cart' },
    children: [
      { title: 'Purchases', to: 'my-purchases-purchases' },
      { title: 'Questions', to: 'my-purchases-questions' },
      { title: 'Reviews', to: 'my-purchases-reviews' },
      { title: 'Favorites', to: 'my-purchases-favorites' },
    ],
  },
]
