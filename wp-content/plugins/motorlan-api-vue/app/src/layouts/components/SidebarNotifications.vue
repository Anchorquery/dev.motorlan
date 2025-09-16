<script lang="ts" setup>
import NavBarNotifications from './NavBarNotifications.vue'
</script>

<template>
 
    <NavBarNotifications />
  
</template>

<style lang="scss">
.nav-link .v-btn {
  color: inherit;
  
  .v-badge__badge {
    background: rgb(var(--v-theme-error));
    color: rgb(var(--v-theme-on-error));
  }
}
</style>
