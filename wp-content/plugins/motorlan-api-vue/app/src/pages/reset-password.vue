<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { VForm } from 'vuetify/components/VForm'
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import { passwordValidator, requiredValidator } from '@core/utils/validators'
import { apiErrorMessage } from '@/composables/useApi'
import PasswordRequirements from '@/views/apps/user/view/PasswordRequirements.vue'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'

const { t } = useI18n()

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

// Get key and login from URL
const resetKey = ref('')
const resetLogin = ref('')

/**
 * Lee un parámetro del fragmento (#key=…&login=…, adonde el servidor mueve los
 * tokens para que no lleguen a Google Analytics) o, en enlaces antiguos o en local,
 * de la query.
 */
const readAuthParam = (name: string): string => {
  const hash = window.location.hash.replace(/^#/, '')
  if (hash) {
    const value = new URLSearchParams(hash).get(name)
    if (value)
      return value
  }

  return (route.query[name] as string) || ''
}

onMounted(() => {
  resetKey.value = readAuthParam('key')
  resetLogin.value = readAuthParam('login')

  // No dejar la clave ni el email en la URL (historial, capturas, analítica)
  if (window.location.search || window.location.hash)
    window.history.replaceState(window.history.state, '', window.location.pathname)

  if (!resetKey.value || !resetLogin.value) {
    isValidLink.value = false
    errorMessage.value = t('reset_password.invalid_link', 'Enlace de restablecimiento inválido. Por favor, solicita uno nuevo.')
  }
})

const passwordConfirmValidator = (value: string) => {
  if (value !== password.value)
    return t('reset_password.mismatch', 'Las contraseñas no coinciden.')

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

    const data = await response.json().catch(() => ({}))

    if (response.ok && data.success) {
      isSuccess.value = true
    }
    else if (data.code === 'invalid_key' || data.code === 'expired_key') {
      // El enlace ya no vale: no tiene sentido seguir mostrando el formulario
      isValidLink.value = false
      errorMessage.value = apiErrorMessage(data, t('reset_password.invalid_link', 'Enlace de restablecimiento inválido. Por favor, solicita uno nuevo.'))
    }
    else {
      // Incluye la lista de requisitos que faltan (data.errors)
      errorMessage.value = apiErrorMessage(data, t('reset_password.error', 'No se pudo restablecer la contraseña.'))
    }
  }
  catch (err) {
    console.error('Reset password error:', err)
    errorMessage.value = t('login.connection_error', 'No se pudo conectar con el servidor. Revisa tu conexión.')
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

const goToForgotPassword = () => {
  router.push({ name: 'forgot-password' })
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
            {{ t('reset_password.title', 'Restablecer contraseña') }}
          </h4>
          <p class="mb-0 text-body-1 text-medium-emphasis">
            {{ t('reset_password.subtitle', 'Introduce tu nueva contraseña.') }}
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
              {{ t('reset_password.success_title', '¡Contraseña actualizada!') }}
            </div>
            <div class="text-body-2">
              {{ t('reset_password.success_body', 'Tu contraseña ha sido restablecida correctamente. Ya puedes iniciar sesión.') }}
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
                  :label="t('reset_password.new_password')"
                  :placeholder="t('reset_password.password_placeholder')"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  variant="outlined"
                  density="comfortable"
                  :rules="[requiredValidator, passwordValidator]"
                  autocomplete="new-password"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />
              </VCol>

              <VCol
                cols="12"
                class="pt-0"
              >
                <PasswordRequirements :password="password" />
              </VCol>

              <!-- Confirm password -->
              <VCol cols="12">
                <VTextField
                  v-model="confirmPassword"
                  :label="t('reset_password.confirm_password')"
                  :placeholder="t('reset_password.password_placeholder')"
                  :type="isConfirmPasswordVisible ? 'text' : 'password'"
                  variant="outlined"
                  density="comfortable"
                  :rules="[requiredValidator, passwordConfirmValidator]"
                  autocomplete="new-password"
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
                  {{ t('reset_password.submit_button') }}
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
                  <span>{{ t('reset_password.back_to_login') }}</span>
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
              v-if="!isSuccess"
              color="primary"
              variant="tonal"
              size="large"
              rounded="lg"
              class="font-weight-bold me-2 mb-2"
              @click="goToForgotPassword"
            >
              {{ t('reset_password.request_new_link', 'Solicitar un enlace nuevo') }}
            </VBtn>
            <VBtn
              color="primary"
              size="large"
              rounded="lg"
              class="font-weight-bold mb-2"
              @click="goToLogin"
            >
              {{ t('reset_password.go_to_login', 'Ir al login') }}
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
