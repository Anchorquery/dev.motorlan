<script setup lang="ts">
import { ref, watch } from 'vue'
import { useApi } from '@/composables/useApi'
import { requiredValidator, passwordValidator, confirmedValidator } from '@/@core/utils/validators'
import { VForm } from 'vuetify/components/VForm'

const props = defineProps<{
  modelValue: boolean
  email: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
}>()

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
        // API call to request password change and send code
        await useApi('/wp-json/motorlan/v1/profile/change-password-request').post({ email: props.email })
        step.value = 2
      }
      catch (error: any) {
        errorMessage.value = error.data?.message || 'Error al enviar el código de verificación.'
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
        await useApi('/wp-json/motorlan/v1/profile/change-password-confirm').post({
          password: newPassword.value,
          code: verificationCode.value,
        })
        successMessage.value = 'Contraseña cambiada con éxito.'
        step.value = 3 // Success step
      }
      catch (error: any) {
        errorMessage.value = error.data?.message || 'El código de verificación es incorrecto o ha expirado.'
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
        <span class="text-h5 text-premium-title">Cambiar Contraseña</span>
      </VCardTitle>

      <VCardText class="pa-6">
        <VForm ref="form">
          <!-- Step 1: Enter new password -->
          <div v-if="step === 1">
            <p class="text-body-2 text-muted mb-6">
              Elige una contraseña segura que no uses en otros sitios.
            </p>
            <AppTextField
              v-model="newPassword"
              label="Nueva Contraseña"
              type="password"
              placeholder="············"
              :rules="[requiredValidator, passwordValidator]"
              class="mb-4"
            />
            <AppTextField
              v-model="confirmPassword"
              label="Confirmar Nueva Contraseña"
              type="password"
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
                <h6 class="text-h6 font-weight-bold">Verifica tu correo</h6>
                <p class="text-body-2 mb-0">
                  Hemos enviado un código a tu email.
                </p>
              </div>
            </div>
            <AppTextField
              v-model="verificationCode"
              label="Código de Verificación"
              placeholder="000000"
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
            <h5 class="text-h5 font-weight-bold mb-2">¡Todo listo!</h5>
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
          {{ step === 3 ? 'Cerrar' : 'Cancelar' }}
        </VBtn>
        <VBtn
          v-if="step === 1"
          color="primary"
          variant="elevated"
          class="rounded-pill px-6"
          :loading="isLoading"
          @click="requestVerificationCode"
        >
          Enviar Código
        </VBtn>
        <VBtn
          v-if="step === 2"
          color="primary"
          variant="elevated"
          class="rounded-pill px-6"
          :loading="isLoading"
          @click="verifyCodeAndChangePassword"
        >
          Cambiar Contraseña
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>