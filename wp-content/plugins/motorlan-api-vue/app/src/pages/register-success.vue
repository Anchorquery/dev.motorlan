<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'

const { t } = useI18n()
const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})
</script>

<template>
  <VRow
    no-gutters
    class="auth-wrapper bg-surface"
  >
    <VCol
      cols="12"
      class="auth-card-v2 d-flex flex-column align-center justify-center h-screen"
      style="background: linear-gradient(to bottom right, rgb(var(--v-theme-surface)), rgba(var(--v-theme-primary), 0.05));"
    >
      <VCard
        flat
        :max-width="500"
        class="pa-6 pa-sm-8 elevation-10 rounded-xl text-center"
      >
        <VCardText>
          <VIcon
            icon="tabler-confetti"
            size="80"
            color="primary"
            class="mb-4"
          />
          <h4 class="text-h4 font-weight-bold mb-2">
            ¡Felicidades por registrarte!
          </h4>
          <p class="text-body-1 text-medium-emphasis mb-6">
            Tu cuenta ha sido creada con éxito, pero aún falta un último paso para activarla.
          </p>

          <VAlert
            color="info"
            variant="tonal"
            class="mb-6 rounded-lg text-start"
            icon="tabler-mail"
          >
            <div class="font-weight-bold mb-1">Verifica tu correo electrónico</div>
            <div>
              Hemos enviado un enlace de activación a tu bandeja de entrada. Por favor, revísalo y haz clic en el botón para confirmar tu cuenta.
            </div>
          </VAlert>

          <p class="text-body-2 text-medium-emphasis mb-6">
            Si no recibes el correo en unos minutos, por favor revisa tu carpeta de <strong>spam</strong> o correo no deseado.
          </p>

          <VBtn
            block
            color="primary"
            size="large"
            rounded="lg"
            :to="{ name: 'login' }"
            class="font-weight-bold"
          >
            Volver al login
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core/scss/template/pages/page-auth.scss";
</style>
