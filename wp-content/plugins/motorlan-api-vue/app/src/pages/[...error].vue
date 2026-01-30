<script setup lang="ts">
import miscMaskDark from '@images/pages/misc-mask-dark.png'
import miscMaskLight from '@images/pages/misc-mask-light.png'
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'

const authThemeMask = useGenerateImageVariant(miscMaskLight, miscMaskDark)

definePage({
  alias: '/pages/misc/not-found/:error(.*)',
  meta: {
    layout: 'blank',
    public: true,
  },
})
</script>

<template>
  <div class="misc-wrapper">
    <div class="misc-center-content text-center mb-12">
      <!-- Premium 404 Typography -->
      <h1 class="text-h1 font-weight-bold text-primary mb-2" style="font-size: 8rem; line-height: 1;">
        404
      </h1>
      <h4 class="text-h4 font-weight-medium mb-2 text-high-emphasis">
        Página no encontrada
      </h4>
      <p class="text-body-1 mb-6 text-medium-emphasis" style="max-width: 400px; margin: 0 auto;">
        Lo sentimos, no pudimos encontrar la página que buscas. Puede que haya sido movida o eliminada.
      </p>

      <VBtn
        to="/"
        prepend-icon="tabler-arrow-left"
        size="large"
        rounded="pill"
        elevation="2"
      >
        Volver al Inicio
      </VBtn>
    </div>

    <!-- Background Mask -->
    <img
      class="misc-footer-img d-none d-md-block"
      :src="authThemeMask"
      alt="misc-footer-img"
      height="320"
    >
  </div>
</template>

<style lang="scss">
@use "@core/scss/template/pages/misc.scss";

.misc-center-content {
  z-index: 1;
}
</style>
