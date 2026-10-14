<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { VForm } from 'vuetify/components/VForm'
import { apiErrorMessage, refreshRestNonce, useApi } from '@/composables/useApi'
import { requiredValidator, passwordValidator, confirmedValidator } from '@/@core/utils/validators'
import PasswordRequirements from './PasswordRequirements.vue'

const props = defineProps<{
  modelValue: boolean
  email: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
}>()

const { t } = useI18n()

const step = ref(1)
const form = ref<VForm | null>(null)
const newPassword = ref('')
const confirmPassword = ref('')
const verificationCode = ref('')
const isLoading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const dialogVisible = ref(props.modelValue)

watch(() => props.modelValue, (val) => {
  dialogVisible.value = val
  if (!val) {
    // Reset state when dialog is closed
    step.value = 1
    newPassword.value = ''
    confirmPassword.value = ''
    verificationCode.value = ''
    errorMessage.value = ''
    successMessage.value = ''
  }
})

watch(dialogVisible, (val) => {
  emit('update:modelValue', val)
})

const requestVerificationCode = async () => {
  if (form.value) {
    const { valid } = await form.value.validate()
    if (valid) {
      isLoading.value = true
      errorMessage.value = ''
      try {
        // Se envía también la contraseña nueva para que el backend la valide (también
        // que no contenga el nombre o el email) ANTES de mandar el código
        const req = await useApi('/wp-json/motorlan/v1/profile/change-password-request').post({
          email: props.email,
          password: newPassword.value,
        }).json()
        if (req.error.value || (req.statusCode.value ?? 0) >= 400) {
          errorMessage.value = apiErrorMessage(req.data.value, t('change_password.send_code_error', 'Error al enviar el código de verificación.'))
          return
        }
        step.value = 2
      }
      catch {
        errorMessage.value = t('change_password.send_code_error', 'Error al enviar el código de verificación.')
      }
      finally {
        isLoading.value = false
      }
    }
  }
}

const verifyCodeAndChangePassword = async () => {
  if (form.value) {
    const { valid } = await form.value.validate()
    if (valid) {
      isLoading.value = true
      errorMessage.value = ''
      try {
        // API call to verify code and change password
        const req = await useApi('/wp-json/motorlan/v1/profile/change-password-confirm').post({
          password: newPassword.value,
          code: verificationCode.value,
        }).json()
        if (req.error.value || (req.statusCode.value ?? 0) >= 400) {
          errorMessage.value = apiErrorMessage(req.data.value, t('change_password.invalid_code', 'El código de verificación es incorrecto o ha expirado.'))
          return
        }

        // La cookie nueva (emitida al cambiar la contraseña) invalida el nonce de la
        // página: pedir uno nuevo ya, para que la siguiente acción no falle con 403.
        await refreshRestNonce()

        successMessage.value = t('change_password.success', 'Contraseña cambiada con éxito.')
        step.value = 3 // Success step
      }
      catch {
        errorMessage.value = t('change_password.invalid_code', 'El código de verificación es incorrecto o ha expirado.')
      }
      finally {
        isLoading.value = false
      }
    }
  }
}

const closeDialog = () => {
  dialogVisible.value = false
}
</script>

<script lang="ts">
export default {
  name: 'ChangePasswordDialog',
}
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    max-width="500px"
    persistent
  >
    <VCard class="motor-card-enhanced overflow-visible">
      <VCardTitle class="pa-6 pb-0">
        <span class="text-h5 text-premium-title">{{ t('change_password.title', 'Cambiar contraseña') }}</span>
      </VCardTitle>

      <VCardText class="pa-6">
        <VForm ref="form">
          <!-- Step 1: Enter new password -->
          <div v-if="step === 1">
            <p class="text-body-2 text-muted mb-6">
              {{ t('change_password.intro', 'Elige una contraseña segura que no uses en otros sitios.') }}
            </p>
            <AppTextField
              v-model="newPassword"
              :label="t('change_password.new_password', 'Nueva contraseña')"
              type="password"
              autocomplete="new-password"
              placeholder="············"
              :rules="[requiredValidator, passwordValidator]"
              class="mb-2"
            />
            <PasswordRequirements
              :password="newPassword"
              class="mb-4"
            />
            <AppTextField
              v-model="confirmPassword"
              :label="t('change_password.confirm_password', 'Confirmar nueva contraseña')"
              type="password"
              autocomplete="new-password"
              placeholder="············"
              :rules="[requiredValidator, () => confirmedValidator(confirmPassword, newPassword)]"
            />
          </div>

          <!-- Step 2: Enter verification code -->
          <div v-if="step === 2">
            <div class="d-flex align-center gap-3 mb-6">
              <VAvatar
                color="primary"
                variant="tonal"
                size="48"
              >
                <VIcon
                  icon="tabler-mail-opened"
                  size="24"
                />
              </VAvatar>
              <div>
                <h6 class="text-h6 font-weight-bold">
                  {{ t('change_password.check_email_title', 'Verifica tu correo') }}
                </h6>
                <p class="text-body-2 mb-0">
                  {{ t('change_password.check_email_body', 'Hemos enviado un código a tu email.') }}
                </p>
              </div>
            </div>
            <AppTextField
              v-model="verificationCode"
              :label="t('change_password.code', 'Código de verificación')"
              placeholder="000000"
              autocomplete="one-time-code"
              inputmode="numeric"
              :rules="[requiredValidator]"
            />
          </div>

          <!-- Step 3: Success message -->
          <div
            v-if="step === 3"
            class="text-center py-4"
          >
            <VAvatar
              color="success"
              variant="tonal"
              size="72"
              class="mb-4"
            >
              <VIcon
                icon="tabler-check"
                size="40"
              />
            </VAvatar>
            <h5 class="text-h5 font-weight-bold mb-2">
              {{ t('change_password.done_title', '¡Todo listo!') }}
            </h5>
            <p class="text-body-1 text-success mb-0">
              {{ successMessage }}
            </p>
          </div>

          <VAlert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            class="mt-4"
          >
            {{ errorMessage }}
          </VAlert>
        </VForm>
      </VCardText>

      <VCardActions class="pa-6 pt-0">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          class="rounded-pill"
          @click="closeDialog"
        >
          {{ step === 3 ? t('change_password.close', 'Cerrar') : t('change_password.cancel', 'Cancelar') }}
        </VBtn>
        <VBtn
          v-if="step === 1"
          color="primary"
          variant="elevated"
          class="rounded-pill px-6"
          :loading="isLoading"
          @click="requestVerificationCode"
        >
          {{ t('change_password.send_code', 'Enviar código') }}
        </VBtn>
        <VBtn
          v-if="step === 2"
          color="primary"
          variant="elevated"
          class="rounded-pill px-6"
          :loading="isLoading"
          @click="verifyCodeAndChangePassword"
        >
          {{ t('change_password.submit', 'Cambiar contraseña') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
