<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { apiErrorMessage, useApi } from '@/composables/useApi'
import { useUserStore } from '@/@core/stores/user'

const { t } = useI18n()

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

/**
 * Esta página atiende dos enlaces de email:
 * - verify-email#token=…        → activar la cuenta tras el registro
 * - verify-email#email_change=… → confirmar el cambio de email hecho en el perfil
 * (el servidor mueve los tokens de la query al fragmento para que no lleguen a GA)
 */
type ViewState = 'verifying' | 'verified' | 'already_verified' | 'email_changed' | 'resent' | 'error'

const state = ref<ViewState>('verifying')
const errorMessage = ref<string | null>(null)
const errorCode = ref<string | null>(null)
const mode = ref<'verify' | 'email_change'>('verify')
const token = ref('')
const countdown = ref(5)
let timer: ReturnType<typeof setInterval> | null = null

definePage({
  meta: {
    layout: 'blank',
    // Pública: antes era unauthenticatedOnly y, con otra sesión abierta (un admin, un
    // PC compartido), el guard mandaba al dashboard sin verificar nada. Además el
    // cambio de email se confirma normalmente con la sesión abierta.
    public: true,
  },
})

const readAuthParam = (name: string): string => {
  const hash = window.location.hash.replace(/^#/, '')
  if (hash) {
    const value = new URLSearchParams(hash).get(name)
    if (value)
      return value
  }

  return (route.query[name] as string) || ''
}

const goToLogin = () => {
  router.push({ name: 'login' })
}

const goToProfile = () => {
  router.push({ name: 'dashboard-user-profile' })
}

const startCountdown = () => {
  timer = setInterval(() => {
    countdown.value--
    if (countdown.value <= 0) {
      if (timer)
        clearInterval(timer)
      timer = null
      goToLogin()
    }
  }, 1000)
}

onBeforeUnmount(() => {
  if (timer)
    clearInterval(timer)
})

const setError = (data: any, fallback: string) => {
  errorCode.value = data?.code || null
  errorMessage.value = apiErrorMessage(data, fallback)
  state.value = 'error'
}

const verifyEmail = async () => {
  state.value = 'verifying'
  errorMessage.value = null
  try {
    const { data } = await (useApi('/wp-json/motorlan/v1/verify-email').post({ token: token.value }).json() as any)
    const payload = data.value || {}
    if (payload.success) {
      if (payload.code === 'already_verified') {
        state.value = 'already_verified'
      }
      else {
        state.value = 'verified'
        if (!userStore.getIsLoggedIn)
          startCountdown()
      }
    }
    else {
      setError(payload, t('verify_email.invalid_token', 'Token de verificación inválido o expirado.'))
    }
  }
  catch (err) {
    console.error('Verify email error:', err)
    setError(null, t('login.connection_error', 'No se pudo conectar con el servidor. Revisa tu conexión.'))
  }
}

const confirmEmailChange = async () => {
  state.value = 'verifying'
  errorMessage.value = null
  try {
    const { data } = await (useApi('/wp-json/motorlan/v1/profile/confirm-email-change').post({ token: token.value }).json() as any)
    const payload = data.value || {}
    if (payload.success) {
      state.value = 'email_changed'
      if (userStore.user && payload.email)
        userStore.user.email = payload.email
    }
    else {
      setError(payload, t('verify_email.email_change_invalid', 'El enlace para confirmar el email no es válido o ya se ha usado.'))
    }
  }
  catch (err) {
    console.error('Confirm email change error:', err)
    setError(null, t('login.connection_error', 'No se pudo conectar con el servidor. Revisa tu conexión.'))
  }
}

onMounted(() => {
  const emailChangeToken = readAuthParam('email_change')
  const verifyToken = readAuthParam('token')

  // No dejar el token en la URL (historial, capturas, analítica)
  if (window.location.search || window.location.hash)
    window.history.replaceState(window.history.state, '', window.location.pathname)

  if (emailChangeToken) {
    mode.value = 'email_change'
    token.value = emailChangeToken
    confirmEmailChange()
  }
  else if (verifyToken) {
    token.value = verifyToken
    verifyEmail()
  }
  else {
    setError(null, t('verify_email.token_missing', 'Token de verificación no encontrado.'))
  }
})

const isResending = ref(false)

// El reenvío solo tiene sentido para la verificación de la cuenta con un token que el
// backend reconozca (caducado o válido); el token sirve para identificar la cuenta.
const canResend = computed(() => mode.value === 'verify' && !!token.value && errorCode.value !== 'invalid_token')

const resendVerification = async () => {
  isResending.value = true
  try {
    const { data } = await (useApi('/wp-json/motorlan/v1/resend-verification').post({ token: token.value }).json() as any)
    const payload = data.value || {}

    if (payload.success && payload.code === 'already_verified') {
      state.value = 'already_verified'
      errorMessage.value = null
    }
    else if (payload.success) {
      // Antes se mostraba "¡Email Verificado!" aunque no se hubiera enviado nada
      state.value = 'resent'
      errorMessage.value = null
    }
    else {
      setError(payload, t('verify_email.resend_error', 'No se pudo reenviar el enlace.'))
    }
  }
  catch {
    setError(null, t('verify_email.resend_error', 'No se pudo reenviar el enlace.'))
  }
  finally {
    isResending.value = false
  }
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
        <VCardText class="text-center">
          <h4 class="text-h4 font-weight-bold mb-4">
            {{ mode === 'email_change' ? t('verify_email.email_change_title', 'Confirmación de email') : t('verify_email.title', 'Verificación de email') }}
          </h4>

          <div
            v-if="state === 'verifying'"
            class="py-10"
          >
            <VProgressCircular
              indeterminate
              color="primary"
              size="64"
            />
            <p class="mt-4 text-body-1">
              {{ t('verify_email.verifying', 'Comprobando el enlace, por favor espera...') }}
            </p>
          </div>

          <div v-else-if="state === 'verified' || state === 'already_verified'">
            <VIcon
              icon="tabler-circle-check"
              size="80"
              color="success"
              class="mb-4"
            />
            <h5 class="text-h5 font-weight-medium mb-2">
              {{ t('verify_email.success_title', '¡Email verificado!') }}
            </h5>
            <p class="text-body-1 text-medium-emphasis mb-2">
              {{ state === 'already_verified'
                ? t('verify_email.already_verified', 'Tu cuenta ya estaba verificada. Ya puedes iniciar sesión.')
                : t('verify_email.success_body', 'Tu cuenta ha sido activada con éxito. Ya puedes acceder a todas las funcionalidades de Motorlan.') }}
            </p>
            <p
              v-if="state === 'verified' && !userStore.getIsLoggedIn"
              class="text-body-1 font-weight-bold mb-6"
            >
              {{ t('verify_email.redirect_countdown', { seconds: countdown }, 'Serás redirigido al login en {seconds} segundos. Usa tus credenciales para entrar.') }}
            </p>
            <VBtn
              block
              color="primary"
              size="large"
              rounded="lg"
              class="mt-4"
              @click="goToLogin"
            >
              {{ userStore.getIsLoggedIn ? t('verify_email.continue', 'Continuar') : t('verify_email.go_to_login', 'Ir al login ahora') }}
            </VBtn>
          </div>

          <div v-else-if="state === 'email_changed'">
            <VIcon
              icon="tabler-mail-check"
              size="80"
              color="success"
              class="mb-4"
            />
            <h5 class="text-h5 font-weight-medium mb-2">
              {{ t('verify_email.email_changed_title', '¡Email actualizado!') }}
            </h5>
            <p class="text-body-1 text-medium-emphasis mb-6">
              {{ t('verify_email.email_changed_body', 'A partir de ahora usa tu nuevo email para iniciar sesión y recibir los avisos de Motorlan.') }}
            </p>
            <VBtn
              block
              color="primary"
              size="large"
              rounded="lg"
              @click="userStore.getIsLoggedIn ? goToProfile() : goToLogin()"
            >
              {{ userStore.getIsLoggedIn ? t('verify_email.go_to_profile', 'Ir a mi perfil') : t('verify_email.go_to_login', 'Ir al login ahora') }}
            </VBtn>
          </div>

          <div v-else-if="state === 'resent'">
            <VIcon
              icon="tabler-mail-forward"
              size="80"
              color="primary"
              class="mb-4"
            />
            <h5 class="text-h5 font-weight-medium mb-2">
              {{ t('verify_email.resent_title', 'Te hemos enviado un nuevo enlace') }}
            </h5>
            <p class="text-body-1 text-medium-emphasis mb-6">
              {{ t('verify_email.resent_body', 'Revisa tu correo (también la carpeta de spam) y pulsa el enlace para activar tu cuenta. El enlace caduca en 24 horas.') }}
            </p>
            <VBtn
              block
              variant="text"
              color="secondary"
              @click="goToLogin"
            >
              {{ t('verify_email.back_to_login', 'Volver al login') }}
            </VBtn>
          </div>

          <div v-else>
            <VIcon
              icon="tabler-alert-triangle"
              size="80"
              color="error"
              class="mb-4"
            />
            <h5 class="text-h5 font-weight-medium mb-2">
              {{ t('dashboard_lists.verification_error', 'Error de verificación') }}
            </h5>
            <p class="text-body-1 text-error mb-6">
              {{ errorMessage }}
            </p>
            <template v-if="mode === 'verify'">
              <p class="text-body-2 text-medium-emphasis mb-6">
                {{ canResend
                  ? t('verify_email.help_resend', 'Puedes solicitar un nuevo enlace de verificación. Si el problema persiste, contacta con soporte.')
                  : t('verify_email.help_login', 'Inicia sesión con tu email y contraseña: si la cuenta no está activada, te enviaremos un nuevo enlace automáticamente.') }}
              </p>
              <VBtn
                v-if="canResend"
                block
                color="primary"
                size="large"
                rounded="lg"
                class="mb-3"
                :loading="isResending"
                @click="resendVerification"
              >
                {{ t('verify_email.request_new_link', 'Solicitar nuevo enlace') }}
              </VBtn>
            </template>
            <p
              v-else
              class="text-body-2 text-medium-emphasis mb-6"
            >
              {{ t('verify_email.email_change_help', 'Puedes volver a cambiar el email desde tu perfil para recibir un enlace nuevo.') }}
            </p>

            <VBtn
              v-if="mode === 'email_change' && userStore.getIsLoggedIn"
              block
              color="primary"
              size="large"
              rounded="lg"
              class="mb-3"
              @click="goToProfile"
            >
              {{ t('verify_email.go_to_profile', 'Ir a mi perfil') }}
            </VBtn>

            <VBtn
              block
              variant="text"
              color="secondary"
              @click="goToLogin"
            >
              {{ t('verify_email.back_to_login', 'Volver al login') }}
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core/scss/template/pages/page-auth.scss";
</style>
