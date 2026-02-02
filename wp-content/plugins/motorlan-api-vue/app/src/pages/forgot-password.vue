<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { VForm } from 'vuetify/components/VForm'
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import { requiredValidator, emailValidator } from '@core/utils/validators'
import authV2ForgotPasswordIllustrationDark from '@images/pages/auth-v2-forgot-password-illustration-dark.png'
import authV2ForgotPasswordIllustrationLight from '@images/pages/auth-v2-forgot-password-illustration-light.png'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'

const { t } = useI18n()

const email = ref('')
const isSubmitting = ref(false)
const isSuccess = ref(false)
const errorMessage = ref<string | null>(null)
const refVForm = ref<VForm>()

const authThemeImg = useGenerateImageVariant(authV2ForgotPasswordIllustrationLight, authV2ForgotPasswordIllustrationDark)
const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const sendResetLink = async () => {
  isSubmitting.value = true
  errorMessage.value = null

  try {
    const response = await fetch('/wp-json/motorlan/v1/forgot-password', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ email: email.value }),
    })

    const data = await response.json()

    if (response.ok && data.success) {
      isSuccess.value = true
    }
    else {
      errorMessage.value = data.message || 'Error al enviar el enlace de recuperación.'
    }
  }
  catch (err) {
    console.error('Forgot password error:', err)
    errorMessage.value = 'Error de conexión. Por favor, intenta de nuevo.'
  }
  finally {
    isSubmitting.value = false
  }
}

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid }) => {
    if (valid)
      sendResetLink()
  })
}
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
        class="pa-6 pa-sm-8 elevation-10 rounded-xl"
      >
        <VCardText>
          <h4 class="text-h4 font-weight-bold mb-1">
            ¿Olvidaste tu contraseña?
          </h4>
          <p class="mb-0 text-body-1 text-medium-emphasis">
            Ingresa tu email y te enviaremos instrucciones para restablecer tu contraseña
          </p>
        </VCardText>

        <VCardText>
          <!-- Success message -->
          <VAlert
            v-if="isSuccess"
            color="success"
            variant="tonal"
            class="mb-6 rounded-lg"
            icon="tabler-check"
          >
            <div class="font-weight-medium">
              ¡Enlace enviado!
            </div>
            <div class="text-body-2">
              Si el email existe en nuestro sistema, recibirás un enlace para restablecer tu contraseña.
            </div>
          </VAlert>

          <!-- Error message -->
          <VAlert
            v-if="errorMessage"
            color="error"
            variant="tonal"
            class="mb-6 rounded-lg"
            icon="tabler-alert-triangle"
          >
            {{ errorMessage }}
          </VAlert>

          <VForm
            v-if="!isSuccess"
            ref="refVForm"
            @submit.prevent="onSubmit"
          >
            <VRow>
              <!-- email -->
              <VCol cols="12">
                <VTextField
                  v-model="email"
                  autofocus
                  label="Email"
                  type="email"
                  placeholder="tucorreo@email.com"
                  variant="outlined"
                  density="comfortable"
                  :rules="[requiredValidator, emailValidator]"
                />
              </VCol>

              <!-- Reset link -->
              <VCol cols="12">
                <VBtn
                  block
                  type="submit"
                  size="large"
                  rounded="lg"
                  :loading="isSubmitting"
                  :disabled="isSubmitting"
                  class="font-weight-bold text-uppercase letter-spacing-1 hover-lift"
                >
                  Enviar enlace
                </VBtn>
              </VCol>

              <!-- back to login -->
              <VCol cols="12">
                <RouterLink
                  class="d-flex align-center justify-center text-primary font-weight-semibold text-decoration-none"
                  :to="{ name: 'login' }"
                >
                  <VIcon
                    icon="tabler-chevron-left"
                    size="20"
                    class="me-1 flip-in-rtl"
                  />
                  <span>Volver al login</span>
                </RouterLink>
              </VCol>
            </VRow>
          </VForm>

          <!-- After success, show link to login -->
          <div
            v-else
            class="text-center mt-4"
          >
            <RouterLink
              class="d-flex align-center justify-center text-primary font-weight-semibold text-decoration-none"
              :to="{ name: 'login' }"
            >
              <VIcon
                icon="tabler-chevron-left"
                size="20"
                class="me-1 flip-in-rtl"
              />
              <span>Volver al login</span>
            </RouterLink>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core/scss/template/pages/page-auth.scss";

.hover-lift {
  transition: transform 0.2s, box-shadow 0.2s;

  &:not(:disabled):hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.3);
  }
}
</style>
