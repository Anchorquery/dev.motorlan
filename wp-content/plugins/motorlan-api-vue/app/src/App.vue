<script setup lang="ts">
import { useTheme } from 'vuetify'
import ScrollToTop from '@core/components/ScrollToTop.vue'
import initCore from '@core/initCore'
import { initConfigStore, useConfigStore } from '@core/stores/config'
import { hexToRgb } from '@core/utils/colorConverter'
import { useToast } from '@/composables/useToast'
import { useUserStore } from '@/@core/stores/user'
import { onMounted, onUnmounted } from 'vue'

const { global } = useTheme()
const { isToastVisible, toastMessage, toastColor } = useToast()
const userStore = useUserStore()

// ℹ️ Sync current theme with initial loader theme
initCore()
initConfigStore()

const configStore = useConfigStore()

// Handle browser back/forward navigation (bfcache)
const handlePageShow = async (event: PageTransitionEvent) => {
  // If page is restored from bfcache, verify session is still valid
  if (event.persisted && userStore.getIsLoggedIn) {
    try {
      const response = await fetch('/wp-json/motorlan/v1/session', {
        credentials: 'include',
      })
      const data = await response.json()

      if (!data.is_logged_in) {
        userStore.logout()
        window.location.href = '/login'
      }
    }
    catch (e) {
      // Network error - assume session is still valid
      console.error('Session check failed:', e)
    }
  }
}

onMounted(() => {
  window.addEventListener('pageshow', handlePageShow)
})

onUnmounted(() => {
  window.removeEventListener('pageshow', handlePageShow)
})
</script>

<template>
  <VLocaleProvider :rtl="configStore.isAppRTL">
    <!-- ℹ️ This is required to set the background color of active nav link based on currently active global theme's primary -->
    <VApp :style="`--v-global-theme-primary: ${hexToRgb(global.current.value.colors.primary)}`">
      <RouterView />
      <ScrollToTop />
      <VSnackbar
        v-model="isToastVisible"
        :color="toastColor"
        location="top end"
        variant="tonal"
      >
        {{ toastMessage }}
      </VSnackbar>
    </VApp>
  </VLocaleProvider>
</template>
