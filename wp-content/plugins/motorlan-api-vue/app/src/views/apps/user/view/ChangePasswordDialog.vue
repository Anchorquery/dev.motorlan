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
  <VDialog v-model="dialogVisible" max-width="500px" persistent>
    <VCard>
      <VCardTitle>Cambiar Contraseña</VCardTitle>
      <VCardText>
        <VForm ref="form">
          <!-- Step 1: Enter new password -->
          <div v-if="step === 1">
            <VTextField
              v-model="newPassword"
              label="Nueva Contraseña"
              type="password"
              :rules="[requiredValidator, passwordValidator]"
              class="mb-4"
            />
            <VTextField
              v-model="confirmPassword"
              label="Confirmar Nueva Contraseña"
              type="password"
              :rules="[requiredValidator, () => confirmedValidator(confirmPassword, newPassword)]"
            />
          </div>

          <!-- Step 2: Enter verification code -->
          <div v-if="step === 2">
            <p class="mb-4">
              Hemos enviado un código de verificación a tu correo electrónico. Por favor, introdúcelo a continuación.
            </p>
            <VTextField
              v-model="verificationCode"
              label="Código de Verificación"
              :rules="[requiredValidator]"
            />
          </div>

          <!-- Step 3: Success message -->
          <div v-if="step === 3">
            <p class="text-success">{{ successMessage }}</p>
          </div>

          <VAlert v-if="errorMessage" type="error" class="mt-4">
            {{ errorMessage }}
          </VAlert>
        </VForm>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn color="secondary" @click="closeDialog">
          {{ step === 3 ? 'Cerrar' : 'Cancelar' }}
        </VBtn>
        <VBtn v-if="step === 1" color="primary" :loading="isLoading" @click="requestVerificationCode">
          Enviar Código
        </VBtn>
        <VBtn v-if="step === 2" color="primary" :loading="isLoading" @click="verifyCodeAndChangePassword">
          Cambiar Contraseña
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>