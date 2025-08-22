<script setup lang="ts">
import { useTheme } from 'vuetify'
import ScrollToTop from '@core/components/ScrollToTop.vue'
import initCore from '@core/initCore'
import { initConfigStore, useConfigStore } from '@core/stores/config'
import { hexToRgb } from '@core/utils/colorConverter'
import { useToast } from '@/composables/useToast'

const { global } = useTheme()
const { isToastVisible, toastMessage, toastColor } = useToast()

// ℹ️ Sync current theme with initial loader theme
initCore()
initConfigStore()

const configStore = useConfigStore()
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
