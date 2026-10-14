<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { apiErrorMessage, useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { emailValidator, requiredValidator } from '@core/utils/validators'

const { t } = useI18n()
const { showToast } = useToast()

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const showResend = ref(false)
const resendEmail = ref('')
const isResending = ref(false)
const devVerifyUrl = ref<string | null>(null)

onMounted(() => {
  try {
    const stored = sessionStorage.getItem('motorlan_register_email')
    if (stored) resendEmail.value = stored
    const dev = sessionStorage.getItem('motorlan_dev_verify_url')
    if (dev) devVerifyUrl.value = dev
  }
  catch {}
})

const submitResend = async () => {
  if (!resendEmail.value) return
  isResending.value = true
  const { data, error } = await useApi('/wp-json/motorlan/v1/resend-verification', {
    method: 'POST',
    body: JSON.stringify({ email: resendEmail.value }),
  }).json()
  isResending.value = false

  if (error.value) {
    showToast(apiErrorMessage(data.value, t('verify_email.resend_error', 'No se pudo reenviar el enlace.')), 'error')
    return
  }
  // Respuesta neutra (no revela si la cuenta existe o ya está activa)
  showToast(apiErrorMessage(data.value, t('auth_errors.resend_neutral', 'Si la cuenta existe y no está activada, se ha enviado un nuevo enlace.')), 'success')
  showResend.value = false
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
            {{ t('register_success.title') }}
          </h4>
          <p class="text-body-1 text-medium-emphasis mb-6">
            {{ t('register_success.subtitle') }}
          </p>

          <VAlert
            color="info"
            variant="tonal"
            class="mb-6 rounded-lg text-start"
            icon="tabler-mail"
          >
            <div class="font-weight-bold mb-1">{{ t('register_success.verify_email') }}</div>
            <div>
              {{ t('register_success.verify_email_body') }}
            </div>
          </VAlert>

          <p class="text-body-2 text-medium-emphasis mb-4">
            {{ t('register_success.spam_note') }}
          </p>

          <VAlert
            v-if="devVerifyUrl"
            color="warning"
            variant="tonal"
            class="mb-4 rounded-lg text-start"
            icon="tabler-bug"
          >
            <div class="font-weight-bold mb-1">DEV: enlace de verificación</div>
            <a :href="devVerifyUrl" class="text-warning text-decoration-underline" style="word-break: break-all;">
              {{ devVerifyUrl }}
            </a>
          </VAlert>

          <div v-if="showResend" class="text-start mb-6">
            <VTextField
              v-model="resendEmail"
              :rules="[requiredValidator, emailValidator]"
              :label="t('register.email', 'Correo electrónico')"
              type="email"
              variant="outlined"
              density="comfortable"
              class="mb-3"
            />
            <div class="d-flex gap-2">
              <VBtn
                variant="tonal"
                color="secondary"
                block
                @click="showResend = false"
              >
                {{ t('register_success.cancel', 'Cancelar') }}
              </VBtn>
              <VBtn
                color="primary"
                block
                :loading="isResending"
                :disabled="!resendEmail || isResending"
                @click="submitResend"
              >
                {{ t('register_success.resend_button', 'Reenviar enlace') }}
              </VBtn>
            </div>
          </div>
          <VBtn
            v-else
            variant="text"
            color="primary"
            class="mb-4"
            @click="showResend = true"
          >
            {{ t('register_success.resend_prompt', '¿No recibiste el correo? Reenviar enlace') }}
          </VBtn>

          <VBtn
            block
            color="primary"
            size="large"
            rounded="lg"
            :to="{ name: 'login' }"
            class="font-weight-bold"
          >
            {{ t('register_success.back_to_login') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core/scss/template/pages/page-auth.scss";
</style>
