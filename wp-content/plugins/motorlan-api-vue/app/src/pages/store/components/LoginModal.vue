<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAbility } from '@casl/vue'
import { useToast } from '@/composables/useToast'
import { requiredValidator } from '@core/utils/validators'
import { useUserStore } from '@/@core/stores/user'

const props = defineProps<{
  isDialogVisible: boolean
}>()

const emit = defineEmits<{
  (e: 'update:isDialogVisible', value: boolean): void
  (e: 'close'): void
}>()

const { t } = useI18n()
const ability = useAbility()
const { showToast } = useToast()

const isPasswordVisible = ref(false)
const errors = ref<Record<string, string | undefined>>({
  username: undefined,
  password: undefined,
})
const genericError = ref<string | null>(null)
const refVForm = ref<any>()
const isSubmitting = ref(false)

const credentials = ref({
  username: '',
  password: '',
})



const login = async () => {
    isSubmitting.value = true
    try {
        // Reset errors
        errors.value = { username: undefined, password: undefined }
        genericError.value = null

        // Use native WordPress session login
    const response = await fetch('/wp-json/motorlan/v1/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      credentials: 'include', // Important: include cookies
      body: JSON.stringify({
        username: credentials.value.username,
        password: credentials.value.password,
        remember: true,
      }),
    })

    const responseData = await response.json().catch(() => ({}))

    if (!response.ok || !responseData.success) {
      genericError.value = responseData.message || 'Error al iniciar sesión. Por favor verifica tus credenciales.'
      return
    }

    const { user, profile } = responseData

    // Update Pinia store with user data
    const userStore = useUserStore()
    userStore.setFromBootstrap(
      {
        id: user.id,
        email: user.email,
        display_name: user.display_name,
        is_admin: user.is_admin,
      },
      true
    )

    // Grant abilities based on role
    const userAbilities = user.is_admin
      ? [{ action: 'manage', subject: 'all' }]
      : [{ action: 'read', subject: 'all' }]

    ability.update(userAbilities)

    showToast('Inicio de sesión exitoso')
    
    // Reload the page to refresh state and ensure all components catch the new session
    window.location.reload()
  }
  catch (err: any) {
    console.error('Login Error:', err)
    genericError.value = err.message || 'Error de conexión. Por favor intenta de nuevo.'
  }
  finally {
    isSubmitting.value = false
  }
}

const onSubmit = () => {
  refVForm.value?.validate()
    .then(({ valid }: { valid: boolean }) => {
      if (valid)
        login()
    })
}

const closeDialog = () => {
  emit('update:isDialogVisible', false)
  emit('close')
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="500"
    @update:model-value="closeDialog"
  >
    <VCard class="pa-4">
      <VCardTitle class="d-flex justify-space-between align-center">
        <span>Iniciar Sesión</span>
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>
      
      <VCardText>
        <VAlert
          v-if="genericError"
          color="error"
          variant="tonal"
          class="mb-4"
        >
          <div v-html="genericError" />
        </VAlert>

        <VForm
          ref="refVForm"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- username -->
            <VCol cols="12">
              <VTextField
                v-model="credentials.username"
                :label="t('login.username')"
                placeholder="johndoe"
                type="text"
                autofocus
                :rules="[requiredValidator]"
                :error-messages="errors.username"
              />
            </VCol>

            <!-- password -->
            <VCol cols="12">
              <VTextField
                v-model="credentials.password"
                :label="t('login.password')"
                placeholder="············"
                :rules="[requiredValidator]"
                :type="isPasswordVisible ? 'text' : 'password'"
                autocomplete="password"
                :error-messages="errors.password"
                :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                @click:append-inner="isPasswordVisible = !isPasswordVisible"
              />
            </VCol>

            <VCol cols="12">
              <VBtn
                block
                type="submit"
                :loading="isSubmitting"
                :disabled="isSubmitting"
              >
                {{ t('login.login_button') }}
              </VBtn>
            </VCol>

            <!-- create account -->
            <VCol
              cols="12"
              class="text-center"
            >
              <span>{{ t('login.new_platform') }}</span>
              <RouterLink
                class="text-primary ms-1"
                :to="{ name: 'register' }"
                @click="closeDialog"
              >
                {{ t('login.create_account') }}
              </RouterLink>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
