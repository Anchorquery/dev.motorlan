<script lang="ts" setup>
import navItems from '@/navigation/vertical'
import { themeConfig } from '@themeConfig'
import { useRouter } from 'vue-router'

// Components
import NavBarNotifications from '@/layouts/components/NavBarNotifications.vue'
import NavbarShortcuts from '@/layouts/components/NavbarShortcuts.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import NavBarI18n from '@core/components/I18n.vue'
import SidebarNotifications from '@/layouts/components/SidebarNotifications.vue'

// @layouts plugin
import { VerticalNavLayout } from '@layouts'

const router = useRouter()

const logout = () => {
  // Remove "userData" from cookie
  useCookie('userData').value = null
  useCookie('accessToken').value = null
  useCookie('userAbilityRules').value = null

  // Redirect to login page
  router.push('/login')
}
</script>

<template>
  <VerticalNavLayout :nav-items="navItems">


    <template #before-vertical-nav-items>
      <ul
        class="d-flex flex-column align-center justify-center pa-0"
        style="list-style: none;"
      >
        <SidebarNotifications />
        <VBtn
          icon
          variant="text"
          @click="logout"
        >
          <VIcon icon="tabler-logout" />
        </VBtn>
      </ul>
    </template>

    <!-- 👉 Pages -->
    <slot />
  </VerticalNavLayout>
</template>
