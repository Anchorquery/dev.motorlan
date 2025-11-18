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
import { useApi } from '@/composables/useApi'
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

const loginSyncWithWordPress = async () => {
  const loginUrl = window.wpData?.login_endpoint || '/wp-json/wp/v2/custom/login'
  try {
    const response = await fetch(loginUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.wpData?.rest_nonce || '',
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

    const { data, error } = await useApi('/wp-json/jwt-auth/v1/token')
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
    useCookie('accessToken').value = token
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
    useCookie('userData').value = {
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
    const userAbilities = [{ action: 'manage', subject: 'all' }]

    ability.update(userAbilities)
    useCookie('userAbilityRules').value = userAbilities

    const { nombre, apellidos } = profileData.personal_data

    // Redirect to `to` query if exist or redirect to index route
    // ❗ nextTick is required to wait for DOM updates and later redirect
    await nextTick(() => {
      showToast('Logueo exitoso')
      if (!nombre || !apellidos) {
        showToast('Por favor, completa tu perfil para continuar.', 'warning')
        router.replace({ name: 'apps-user-account' })
      }
      else {
        if (route.query.to)
          router.replace(String(route.query.to))
        else
          router.replace({ path: '/apps/purchases/purchases' })
      }
    })
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
    .then(({ valid: isValid }) => {
      if (isValid)
        login()
    })
}
</script>

<template>
  <RouterLink to="/">
    <div class="auth-logo d-flex align-center gap-x-3">
      <VNodeRenderer :nodes="themeConfig.app.logo" />
      <h1 class="auth-title">
        {{ themeConfig.app.title }}
      </h1>
    </div>
  </RouterLink>

  <VRow
    no-gutters
    class="auth-wrapper bg-surface"
  >
    <VCol
      md="8"
      class="d-none d-md-flex"
    >
      <div class="position-relative bg-background w-100 me-0">
        <div
          class="d-flex align-center justify-center w-100 h-100"
          style="padding-inline: 6.25rem;"
        >
          <VImg
            max-width="613"
            :src="authThemeImg"
            class="auth-illustration mt-16 mb-2"
          />
        </div>

        <img
          class="auth-footer-mask"
          :src="authThemeMask"
          alt="auth-footer-mask"
          height="280"
          width="100"
        >
      </div>
    </VCol>

    <VCol
      cols="12"
      md="4"
      class="auth-card-v2 d-flex align-center justify-center"
    >
      <VCard
        flat
        :max-width="500"
        class="mt-12 mt-sm-0 pa-4"
      >
        <VCardText>
          <h4 class="text-h4 mb-1">
            {{ t('login.welcome', { title: themeConfig.app.title }) }}
          </h4>
          <p class="mb-0">
            {{ t('login.subtitle') }}
          </p>
        </VCardText>

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

                <div class="d-flex align-center flex-wrap justify-space-between my-6" />

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
                >
                  {{ t('login.create_account') }}
                </RouterLink>
              </VCol>
              <VCol
                cols="12"
                class="d-flex align-center"
              >
                <VDivider />
                <span class="mx-4">{{ t('login.or') }}</span>
                <VDivider />
              </VCol>

              <!-- auth providers -->
              <VCol
                cols="12"
                class="text-center"
              />
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core/scss/template/pages/page-auth";
</style>
