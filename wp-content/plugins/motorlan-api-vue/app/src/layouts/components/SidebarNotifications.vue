<script lang="ts" setup>
import NavBarNotifications from './NavBarNotifications.vue'
</script>

<template>
 
    <div class="d-flex align-center cursor-pointer w-100 h-min">
      <NavBarNotifications />
    </div>
  
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
