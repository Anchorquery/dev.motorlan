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
const userStore = useUserStore()

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

interface AbilityRule {
  action: string
  subject: string
}

const login = async () => {
  isSubmitting.value = true
  try {
    // Reset errors
    errors.value = { username: undefined, password: undefined }
    genericError.value = null

    // Use new WordPress session-based login endpoint
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
      genericError.value = responseData.message || 'An unknown error occurred. Please try again.'
      return
    }

    const { user, profile } = responseData

    // Update Pinia store with user data
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
    const userAbilities: AbilityRule[] = user.is_admin
      ? [{ action: 'manage', subject: 'all' }]
      : [{ action: 'read', subject: 'all' }]

    ability.update(userAbilities)

    // Check if profile is complete
    const { nombre, apellidos } = profile?.personal_data || {}

    await nextTick(() => {
      showToast('Inicio de sesión exitoso')

      if (!profile?.personal_data || !nombre || !apellidos) {
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
    console.error('Login Error:', err)
    genericError.value = err.message || 'Failed to connect to the server. Please check your connection.'
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

                <div class="d-flex align-center flex-wrap justify-space-between mt-2 mb-6">
                  <RouterLink
                    class="text-primary text-sm"
                    :to="{ name: 'forgot-password' }"
                  >
                    {{ t('login.forgot_password') }}
                  </RouterLink>
                </div>

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
