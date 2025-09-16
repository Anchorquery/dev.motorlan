<script lang="ts" setup>
import navItems from '@/navigation/vertical'
import { themeConfig } from '@themeConfig'
// Components
import NavBarNotifications from '@/layouts/components/NavBarNotifications.vue'
import NavbarShortcuts from '@/layouts/components/NavbarShortcuts.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import NavBarI18n from '@core/components/I18n.vue'
import SidebarNotifications from '@/layouts/components/SidebarNotifications.vue'

// @layouts plugin
import { VerticalNavLayout } from '@layouts'

</script>

<template>
  <VerticalNavLayout :nav-items="navItems">


    <template #before-vertical-nav-items>
      <div class="px-5">
        <SidebarNotifications />
      </div>
    </template>

    <!-- 👉 Pages -->
    <slot />
  </VerticalNavLayout>
</template>
