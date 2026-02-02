<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { VForm } from 'vuetify/components/VForm'
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import { requiredValidator } from '@core/utils/validators'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'

const route = useRoute()
const router = useRouter()

const password = ref('')
const confirmPassword = ref('')
const isPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)
const isSubmitting = ref(false)
const isSuccess = ref(false)
const errorMessage = ref<string | null>(null)
const isValidLink = ref(true)
const refVForm = ref<VForm>()

const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

// Get key and login from URL query params
const resetKey = ref('')
const resetLogin = ref('')

onMounted(() => {
  resetKey.value = (route.query.key as string) || ''
  resetLogin.value = (route.query.login as string) || ''

  if (!resetKey.value || !resetLogin.value) {
    isValidLink.value = false
    errorMessage.value = 'Enlace de restablecimiento inválido. Por favor, solicita uno nuevo.'
  }
})

const passwordConfirmValidator = (value: string) => {
  if (value !== password.value)
    return 'Las contraseñas no coinciden'
  return true
}

const passwordLengthValidator = (value: string) => {
  if (value.length < 8)
    return 'La contraseña debe tener al menos 8 caracteres'
  return true
}

const resetPassword = async () => {
  isSubmitting.value = true
  errorMessage.value = null

  try {
    const response = await fetch('/wp-json/motorlan/v1/reset-password', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        key: resetKey.value,
        login: resetLogin.value,
        password: password.value,
      }),
    })

    const data = await response.json()

    if (response.ok && data.success) {
      isSuccess.value = true
    }
    else {
      errorMessage.value = data.message || 'Error al restablecer la contraseña.'
    }
  }
  catch (err) {
    console.error('Reset password error:', err)
    errorMessage.value = 'Error de conexión. Por favor, intenta de nuevo.'
  }
  finally {
    isSubmitting.value = false
  }
}

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid }) => {
    if (valid)
      resetPassword()
  })
}

const goToLogin = () => {
  router.push({ name: 'login' })
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
            Restablecer contraseña
          </h4>
          <p class="mb-0 text-body-1 text-medium-emphasis">
            Ingresa tu nueva contraseña
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
              ¡Contraseña actualizada!
            </div>
            <div class="text-body-2">
              Tu contraseña ha sido restablecida correctamente. Ya puedes iniciar sesión.
            </div>
          </VAlert>

          <!-- Invalid link message -->
          <VAlert
            v-if="!isValidLink"
            color="error"
            variant="tonal"
            class="mb-6 rounded-lg"
            icon="tabler-alert-triangle"
          >
            {{ errorMessage }}
          </VAlert>

          <!-- Error message -->
          <VAlert
            v-if="errorMessage && isValidLink && !isSuccess"
            color="error"
            variant="tonal"
            class="mb-6 rounded-lg"
            icon="tabler-alert-triangle"
          >
            {{ errorMessage }}
          </VAlert>

          <VForm
            v-if="isValidLink && !isSuccess"
            ref="refVForm"
            @submit.prevent="onSubmit"
          >
            <VRow>
              <!-- New password -->
              <VCol cols="12">
                <VTextField
                  v-model="password"
                  autofocus
                  label="Nueva contraseña"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  variant="outlined"
                  density="comfortable"
                  :rules="[requiredValidator, passwordLengthValidator]"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />
              </VCol>

              <!-- Confirm password -->
              <VCol cols="12">
                <VTextField
                  v-model="confirmPassword"
                  label="Confirmar contraseña"
                  placeholder="············"
                  :type="isConfirmPasswordVisible ? 'text' : 'password'"
                  variant="outlined"
                  density="comfortable"
                  :rules="[requiredValidator, passwordConfirmValidator]"
                  :append-inner-icon="isConfirmPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
                />
              </VCol>

              <!-- Submit button -->
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
                  Restablecer contraseña
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

          <!-- After success or invalid link, show button to login -->
          <div
            v-if="isSuccess || !isValidLink"
            class="text-center mt-4"
          >
            <VBtn
              color="primary"
              size="large"
              rounded="lg"
              class="font-weight-bold"
              @click="goToLogin"
            >
              Ir al login
            </VBtn>
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
