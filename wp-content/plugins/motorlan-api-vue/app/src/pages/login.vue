<!-- ❗Errors in the form are set on line 60 -->
<script setup lang="ts">
import { nextTick, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAbility } from '@casl/vue'
import { useRoute, useRouter } from 'vue-router'
import { VForm } from 'vuetify/components/VForm'
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import authV2LoginIllustrationBorderedDark from '@images/pages/auth-v2-login-illustration-bordered-dark.png'
import authV2LoginIllustrationBorderedLight from '@images/pages/auth-v2-login-illustration-bordered-light.png'
import authV2LoginIllustrationDark from '@images/pages/auth-v2-login-illustration-dark.png'
import authV2LoginIllustrationLight from '@images/pages/auth-v2-login-illustration-light.png'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import { useToast } from '@/composables/useToast'
import { requiredValidator } from '@core/utils/validators'
import { useUserStore } from '@/@core/stores/user'

const authThemeImg = useGenerateImageVariant(authV2LoginIllustrationLight, authV2LoginIllustrationDark, authV2LoginIllustrationBorderedLight, authV2LoginIllustrationBorderedDark, true)

const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)

const { t } = useI18n()

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const isPasswordVisible = ref(false)

const route = useRoute()
const router = useRouter()
const ability = useAbility()
const { showToast } = useToast()

const errors = ref<Record<string, string | undefined>>({
  username: undefined,
  password: undefined,
})

const genericError = ref<string | null>(null)

const refVForm = ref<VForm>()
const isSubmitting = ref(false)

const credentials = ref({
  username: '',
  password: '',
})

interface UserData {
  displayName: string
  email: string
  nicename: string
  role: string
}

interface AbilityRule {
  action: string
  subject: string
}

const loginSyncWithWordPress = async () => {
  const wpData = (window as typeof window & { wpData?: { login_endpoint?: string; rest_nonce?: string; nonce?: string } }).wpData
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

    console.log('Logging in with fetch...')
    const loginUrl = '/wp-json/jwt-auth/v1/token'
    
    const response = await fetch(loginUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        username: credentials.value.username,
        password: credentials.value.password,
      }),
    })

    const responseData = await response.json().catch(() => ({}))
    console.log('Login attempt:', { status: response.status, data: responseData })

    if (!response.ok) {
       // Validate structure of error response usually sent by WP JWT Auth
       // It normally sends { code: "...", message: "...", data: { status: 403 } } or similar
       if (responseData.message) {
          genericError.value = responseData.message
       } else if (responseData.code) {
          genericError.value = `Login failed: ${responseData.code}`
       } else {
          genericError.value = 'An unknown error occurred. Please try again.'
       }
       return
    }

    // Safety check for token
    if (!responseData || !responseData.token) {
      console.error('Login successful but no token received', responseData)
      genericError.value = 'No access token received from server.'
      return
    }

    const { token, user_display_name, user_email, user_nicename } = responseData

    // Store the token in a cookie
    useCookie<string>('accessToken').value = token
    localStorage.setItem('accessToken', token)

    // Use native fetch to ensure the new token is used immediately
    console.log('Fetching profile...')
    const profileResponse = await fetch('/wp-json/motorlan/v1/profile', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
    })

    if (!profileResponse.ok) {
      const errorData = await profileResponse.json().catch(() => ({}))
      console.error('Profile fetch failed', profileResponse.status, errorData)
      genericError.value = errorData.message || `Error al obtener el perfil: ${profileResponse.statusText}`
      
      return
    }

    const profileData = await profileResponse.json()
    console.log('Profile data:', profileData)

    // Store user data in a cookie
    useCookie<UserData>('userData').value = {
      displayName: user_display_name,
      email: user_email,
      nicename: user_nicename,
      role: user_nicename, // Add the role to the user data
    }

    await loginSyncWithWordPress()

    const userStore = useUserStore()
    userStore.setFromBootstrap(
      { id: profileData.id ?? 0, email: user_email, display_name: user_display_name },
      true
    )

    // Grant abilities based on role
    const userAbilities: AbilityRule[] = [{ action: 'manage', subject: 'all' }]

    ability.update(userAbilities)
    useCookie<AbilityRule[]>('userAbilityRules').value = userAbilities

    const { nombre, apellidos } = profileData.personal_data || {}

    // Redirect to `to` query if exist or redirect to index route
    // ❗ nextTick is required to wait for DOM updates and later redirect
    await nextTick(() => {
      showToast('Logueo exitoso')
      // Check if personal_data exists and has required fields
      if (!profileData.personal_data || !nombre || !apellidos) {
         // If personal_data is missing entirely, we might want to warn too, or just check the fields if it exists
        showToast('Por favor, completa tu perfil para continuar.', 'warning')
        router.replace({ name: 'dashboard-user-account' })
      }
      else {
        if (route.query.to)
          router.replace(String(route.query.to))
        else
          router.replace({ path: '/dashboard/purchases/purchases' })
      }
    })
  }
  catch (err: any) {
    console.error('Login Error Catch:', err)
    genericError.value = err.data?.message || err.message || 'Failed to connect to the server. Please check your connection or contact support.'
  }
  finally {
    isSubmitting.value = false
  }
}

const onSubmit = () => {
  refVForm.value?.validate()
    .then(({ valid: isValid }) => {
      if (isValid)
        login()
    })
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
            {{ t('login.welcome', { title: themeConfig.app.title }) }}
          </h4>
          <p class="mb-0 text-body-1 text-medium-emphasis">
            {{ t('login.subtitle') }}
          </p>
        </VCardText>

        <VCardText>
          <VAlert
            v-if="genericError"
            color="error"
            variant="tonal"
            class="mb-6 rounded-lg"
            icon="tabler-alert-triangle"
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
                  :label="t('register.email')"
                  placeholder="johndoe@email.com"
                  type="email"
                  autofocus
                  variant="outlined"
                  density="comfortable"
                  :rules="[requiredValidator]"
                  :error-messages="errors.username"
                  class="mb-1"
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
                  variant="outlined"
                  density="comfortable"
                  :error-messages="errors.password"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                  class="mb-1"
                />

                <div class="d-flex align-center flex-wrap justify-space-between mt-2 mb-6" />

                <VBtn
                  block
                  type="submit"
                  size="large"
                  rounded="lg"
                  :loading="isSubmitting"
                  :disabled="isSubmitting"
                  class="font-weight-bold text-uppercase letter-spacing-1 hover-lift"
                >
                  {{ t('login.login_button') }}
                </VBtn>
              </VCol>

              <!-- create account -->
              <VCol
                cols="12"
                class="text-center"
              >
                <span class="text-body-2 text-medium-emphasis">{{ t('login.new_platform') }}</span>
                <RouterLink
                  class="text-primary font-weight-semibold ms-1 text-decoration-none"
                  :to="{ name: 'register' }"
                >
                  {{ t('login.create_account') }}
                </RouterLink>
              </VCol>


            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core/scss/template/pages/page-auth";

.hover-lift {
  transition: transform 0.2s, box-shadow 0.2s;
  
  &:not(:disabled):hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.3);
  }
}
</style>
