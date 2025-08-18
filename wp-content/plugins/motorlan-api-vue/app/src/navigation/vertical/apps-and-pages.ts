export default [
  { heading: 'Apps & Pages' },
  {
    title: 'Ecommerce',
    icon: { icon: 'tabler-shopping-cart' },
    children: [
      // {
      //   title: 'Dashboard',
      //   to: 'apps-ecommerce-dashboard',
      // },
      {
        title: 'Product',
        children: [
          { title: 'List', to: 'apps-ecommerce-product-list' },
          { title: 'Add', to: 'apps-ecommerce-product-add' },
          { title: 'Category', to: 'apps-ecommerce-product-category-list' },
        ],
      },
      {
        title: 'Order',
        children: [
          { title: 'List', to: 'apps-ecommerce-order-list' },
          { title: 'Details', to: { name: 'apps-ecommerce-order-details-id', params: { id: '9042' } } },
        ],
      },
      {
        title: 'Customer',
        children: [
          { title: 'List', to: 'apps-ecommerce-customer-list' },
          { title: 'Details', to: { name: 'apps-ecommerce-customer-details-id', params: { id: 478426 } } },
        ],
      },
      {
        title: 'Manage Review',
        to: 'apps-ecommerce-manage-review',
      },
      {
        title: 'Referrals',
        to: 'apps-ecommerce-referrals',
      },
      {
        title: 'Settings',
        to: 'apps-ecommerce-settings',
      },
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
  }
]
