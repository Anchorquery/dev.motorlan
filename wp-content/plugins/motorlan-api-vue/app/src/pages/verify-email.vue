<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'

const route = useRoute()
const router = useRouter()

const isVerifying = ref(true)
const isSuccess = ref(false)
const errorMessage = ref<string | null>(null)
const countdown = ref(5)
const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const startCountdown = () => {
  const timer = setInterval(() => {
    countdown.value--
    if (countdown.value <= 0) {
      clearInterval(timer)
      goToLogin()
    }
  }, 1000)
}

const verifyEmail = async (token: string) => {
  isVerifying.value = true
  errorMessage.value = null

  try {
    // Correct usage with full REST path
    const { data, error } = await (useApi('/wp-json/motorlan/v1/verify-email').post({ token }).json() as any)

    if (data.value && data.value.success) {
      isSuccess.value = true
      startCountdown()
    } else {
      errorMessage.value = data.value?.message || error.value?.message || 'Token de verificación inválido o expirado.'
    }
  } catch (err) {
    console.error('Verify email error:', err)
    errorMessage.value = 'Error de conexión. Por favor, intenta de nuevo.'
  } finally {
    isVerifying.value = false
  }
}

onMounted(() => {
  const token = route.query.token as string

  if (token) {
    verifyEmail(token)
  } else {
    isVerifying.value = false
    errorMessage.value = 'Token de verificación no encontrado.'
  }
})

const goToLogin = () => {
  router.push({ name: 'login' })
}

const isResending = ref(false)
const resendSuccess = ref(false)

const resendVerification = async () => {
  isResending.value = true
  resendSuccess.value = false
  
  try {
    const token = route.query.token as string
    // Si tenemos el token, tratamos de sacar el usuario de ahí en el backend, 
    // pero el endpoint /resend-verification prefiere el correo.
    // Como no tenemos el correo aquí, vamos a basarnos en el token o 
    // simplemente dar instrucciones de volver al login para auto-reenvío.
    
    // Mejor: Si fallamos aquí y el usuario quiere reenvío, lo mandamos al login 
    // donde al intentar entrar se le reenviará automáticamente según la nueva lógica.
    // O podemos intentar buscarlo si el token es válido pero expirado.
    
    // Por simplicidad y seguridad (ya que no tengo el email aquí), 
    // voy a usar el token para intentar el reenvío si el backend lo soporta 
    // o simplemente avisar que intente login.
    
    const { data } = await (useApi('/wp-json/motorlan/v1/resend-verification').post({ token }).json() as any)
    
    if (data.value && data.value.success) {
      resendSuccess.value = true
      showToast('Nuevo enlace enviado. Por favor, revisa tu email.')
    } else {
      showToast(data.value?.message || 'No se pudo reenviar el enlace.', 'error')
    }
  } catch (err) {
    showToast('Error al intentar reenviar el enlace.', 'error')
  } finally {
    isResending.value = false
  }
}

const showToast = (message: string, color: string = 'success') => {
  // Simple fallback since useToast might not be available here directly
  // or we can just use the errorMessage ref to show status
  if (color === 'error') {
    errorMessage.value = message
  } else {
    errorMessage.value = null
    isSuccess.value = true // Show success state for the resend
    isVerifying.value = false
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
            Verificación de Email
          </h4>

          <div v-if="isVerifying" class="py-10">
            <VProgressCircular
              indeterminate
              color="primary"
              size="64"
            />
            <p class="mt-4 text-body-1">Verificando tu cuenta, por favor espera...</p>
          </div>

          <div v-else-if="isSuccess">
            <VIcon
              icon="tabler-circle-check"
              size="80"
              color="success"
              class="mb-4"
            />
            <h5 class="text-h5 font-weight-medium mb-2">¡Email Verificado!</h5>
            <p class="text-body-1 text-medium-emphasis mb-2">
              Tu cuenta ha sido activada con éxito. Ya puedes acceder a todas las funcionalidades de Motorlan.
            </p>
            <p class="text-body-1 font-weight-bold mb-6">
              Serás redirigido al login en {{ countdown }} segundos. Usa tus credenciales para ingresar.
            </p>
            <VBtn
              block
              color="primary"
              size="large"
              rounded="lg"
              @click="goToLogin"
            >
              Ir al login ahora
            </VBtn>
          </div>

          <div v-else>
            <VIcon
              icon="tabler-alert-triangle"
              size="80"
              color="error"
              class="mb-4"
            />
            <h5 class="text-h5 font-weight-medium mb-2">Error de Verificación</h5>
            <p class="text-body-1 text-error mb-6">
              {{ errorMessage }}
            </p>
            <p class="text-body-2 text-medium-emphasis mb-6">
              Si el problema persiste, intenta solicitar un nuevo enlace de verificación o contacta con soporte.
            </p>
            <VBtn
              block
              color="primary"
              size="large"
              rounded="lg"
              class="mb-3"
              :loading="isResending"
              @click="resendVerification"
            >
              Solicitar nuevo enlace
            </VBtn>

            <VBtn
              block
              variant="text"
              color="secondary"
              @click="goToLogin"
            >
              Volver al login
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
