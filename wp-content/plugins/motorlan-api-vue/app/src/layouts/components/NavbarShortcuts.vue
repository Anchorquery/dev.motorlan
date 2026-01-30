<script setup lang="ts">
const shortcuts = [
  {
    icon: 'tabler-file-dollar',
    title: 'Invoice App',
    subtitle: 'Manage Accounts',
    to: { name: 'dashboard-invoice-list' },
  },
  {
    icon: 'tabler-user',
    title: 'Users',
    subtitle: 'Manage Users',
    to: { name: 'dashboard-user-list' },
  },
  {
    icon: 'tabler-users',
    title: 'Role Management',
    subtitle: 'Permission',
    to: { name: 'dashboard-roles' },
  },
  {
    icon: 'tabler-settings',
    title: 'Settings',
    subtitle: 'Account Settings',
    to: { name: 'pages-account-settings-tab', params: { tab: 'account' } },
  },

]
</script>

<template>
  <!-- <Shortcuts :shortcuts="shortcuts" /> -->
</template>
