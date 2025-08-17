export default [
  {
    title: 'Dashboards',
    icon: { icon: 'tabler-smart-home' },
    children: [
      {
        title: 'Ecommerce',
        to: 'dashboards-ecommerce',
      }
    ],
    badgeContent: '5',
    badgeClass: 'bg-error',
  },
  {
    title: 'Front Pages',
    icon: { icon: 'tabler-files' },
    children: [
      {
        title: 'Landing',
        to: 'front-pages-landing-page',
        target: '_blank',
      },
      {
        title: 'Pricing',
        to: 'front-pages-pricing',
        target: '_blank',
      },
      {
        title: 'Payment',
        to: 'front-pages-payment',
        target: '_blank',
      },
      {
        title: 'Checkout',
        to: 'front-pages-checkout',
        target: '_blank',
      },
      {
        title: 'Help Center',
        to: 'front-pages-help-center',
        target: '_blank',
      },
    ],
  },
]
