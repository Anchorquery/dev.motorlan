<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAbility } from '@casl/vue'
import { useToast } from '@/composables/useToast'
import { requiredValidator } from '@core/utils/validators'
import { useUserStore } from '@/@core/stores/user'
import { useApi } from '@/composables/useApi'

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

const loginSyncWithWordPress = async () => {
  const wpData = (window as any).wpData
  const loginUrl = wpData?.login_endpoint || '/wp-json/wp/v2/custom/login'
  try {
    const response = await fetch(loginUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpData?.rest_nonce || wpData?.nonce || '',
      },
      body: JSON.stringify({
        username: credentials.value.username,
        password: credentials.value.password,
      }),
      credentials: 'include',
    })
    if (!response.ok) {
      console.warn('Unable to sync WordPress session', await response.text())
    }
  } catch (error) {
    console.error('Error syncing WordPress session', error)
  }
}

const login = async () => {
  isSubmitting.value = true
  try {
    // Reset errors
    errors.value = { username: undefined, password: undefined }
    genericError.value = null

    const { data, error } = await useApi<any>('/wp-json/jwt-auth/v1/token')
      .post({
        username: credentials.value.username,
        password: credentials.value.password,
      })
      .json()

    if (error.value) {
      const errorData = error.value.data
      if (errorData && errorData.errors) {
        errors.value = errorData.errors
      }
      else {
        genericError.value = errorData?.message || error.value.message || 'An unknown error occurred. Please try again.'
      }

      return
    }

    const { token, user_display_name, user_email, user_nicename } = data.value

    // Store the token in a cookie
    useCookie<string>('accessToken').value = token
    localStorage.setItem('accessToken', token)

    // Use native fetch to ensure the new token is used immediately
    const profileResponse = await fetch('/wp-json/motorlan/v1/profile', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
    })

    if (!profileResponse.ok) {
      const errorData = await profileResponse.json().catch(() => ({}))
      genericError.value = errorData.message || `Error al obtener el perfil: ${profileResponse.statusText}`
      
      return
    }

    const profileData = await profileResponse.json()

    // Store user data in a cookie
    useCookie<any>('userData').value = {
      displayName: user_display_name,
      email: user_email,
      nicename: user_nicename,
      role: user_nicename,
    }

    await loginSyncWithWordPress()

    const userStore = useUserStore()
    userStore.setFromBootstrap(
      { id: profileData.id ?? 0, email: user_email, display_name: user_display_name },
      true
    )

    // Grant abilities based on role
    const userAbilities = [{ action: 'manage', subject: 'all' }]

    ability.update(userAbilities)
    useCookie<any>('userAbilityRules').value = userAbilities

    showToast('Logueo exitoso')
    
    // Reload the page to refresh state
    window.location.reload()
  }
  catch (err: any) {
    console.error(err)
    genericError.value = err.data?.message || 'Failed to connect to the server. Please check your connection or contact support.'
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
