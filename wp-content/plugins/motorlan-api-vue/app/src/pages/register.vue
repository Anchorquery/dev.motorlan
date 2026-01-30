<script setup lang="ts">
import { ref } from 'vue'
import { VForm } from 'vuetify/components/VForm'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'
import AuthProvider from '@/views/pages/authentication/AuthProvider.vue'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import { emailValidator, requiredValidator } from '@core/utils/validators'

import authV2RegisterIllustrationBorderedDark from '@images/pages/auth-v2-register-illustration-bordered-dark.png'
import authV2RegisterIllustrationBorderedLight from '@images/pages/auth-v2-register-illustration-bordered-light.png'
import authV2RegisterIllustrationDark from '@images/pages/auth-v2-register-illustration-dark.png'
import authV2RegisterIllustrationLight from '@images/pages/auth-v2-register-illustration-light.png'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'

const imageVariant = useGenerateImageVariant(authV2RegisterIllustrationLight,
  authV2RegisterIllustrationDark,
  authV2RegisterIllustrationBorderedLight,
  authV2RegisterIllustrationBorderedDark, true)

const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)

const { t } = useI18n()
const router = useRouter()

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const form = ref({
  first_name: '',
  last_name: '',
  username: '',
  email: '',
  password: '',
  privacyPolicies: false,
})

const isPasswordVisible = ref(false)
const isSubmitting = ref(false)
const refVForm = ref<VForm>()
const errors = ref<Record<string, string | undefined>>({})
const genericError = ref<string | null>(null)
const isCheckingUsername = ref(false)
const usernameError = ref<string | null>(null)
const showSuccessNotification = ref(false)



const register = async () => {
  if (!form.value.privacyPolicies) {
    genericError.value = 'Debes aceptar la politica de privacidad y los terminos.'
    
    return
  }
  isSubmitting.value = true
  genericError.value = null
  errors.value = {}

  const { data, error } = await useApi('/wp-json/motorlan/v1/register', {
    method: 'POST',
    body: JSON.stringify({
      first_name: form.value.first_name,
      last_name: form.value.last_name,
      username: form.value.email,
      email: form.value.email,
      password: form.value.password,
    }),
  }).json()

  isSubmitting.value = false

  if (error.value) {
    const errorMessage = error.value.data?.message || 'Ocurrio un error al registrar la cuenta.'
    const loweredMessage = errorMessage.toLowerCase()
    
    // Asignar errores específicos si es posible, o mostrar genérico
    if (loweredMessage.includes('email')) {
      errors.value.email = errorMessage
    }
    else {
      genericError.value = errorMessage
    }
    
    return
  }

  if (data.value) {
    showSuccessNotification.value = true
    setTimeout(() => {
      router.push({ name: 'login' })
    }, 4000)
  }
}

const onSubmit = () => {
  refVForm.value?.validate()
    .then(({ valid: isValid }) => {
      if (isValid)
        register()
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
            {{ t('register.title') }}
          </h4>
          <p class="mb-0 text-body-1 text-medium-emphasis">
            {{ t('register.subtitle') }}
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
            {{ genericError }}
          </VAlert>
          <VForm
            ref="refVForm"
            @submit.prevent="onSubmit"
          >
            <VRow>

              <!-- First Name -->
              <VCol cols="12" md="6">
                <VTextField
                  v-model="form.first_name"
                  :rules="[requiredValidator]"
                  autofocus
                  :label="t('register.first_name')"
                  placeholder="John"
                  variant="outlined"
                  density="comfortable"
                  :error-messages="errors.first_name"
                />
              </VCol>

              <!-- Last Name -->
              <VCol cols="12" md="6">
                <VTextField
                  v-model="form.last_name"
                  :rules="[requiredValidator]"
                  :label="t('register.last_name')"
                  placeholder="Doe"
                  variant="outlined"
                  density="comfortable"
                  :error-messages="errors.last_name"
                />
              </VCol>

              <!-- email -->
              <VCol cols="12">
                <VTextField
                  v-model="form.email"
                  :rules="[requiredValidator, emailValidator]"
                  :label="t('register.email')"
                  type="email"
                  placeholder="johndoe@email.com"
                  variant="outlined"
                  density="comfortable"
                  :error-messages="errors.email"
                />
              </VCol>

              <!-- password -->
              <VCol cols="12">
                <VTextField
                  v-model="form.password"
                  :rules="[requiredValidator]"
                  :label="t('register.password')"
                  placeholder="************"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  autocomplete="password"
                  variant="outlined"
                  density="comfortable"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :error-messages="errors.password"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />

                <div class="d-flex align-center my-6">
                  <VCheckbox
                    id="privacy-policy"
                    v-model="form.privacyPolicies"
                    :rules="[requiredValidator]"
                    inline
                  />
                  <VLabel
                    for="privacy-policy"
                    style="opacity: 1;"
                  >
                    <span class="me-1 text-medium-emphasis">{{ t('register.agree_to') }}</span>
                    <a
                      href="javascript:void(0)"
                      class="text-primary font-weight-medium text-decoration-none"
                    >{{ t('register.privacy_policy') }}</a>
                  </VLabel>
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
                  {{ t('register.sign_up') }}
                </VBtn>
              </VCol>

              <!-- create account -->
              <VCol
                cols="12"
                class="text-center text-base"
              >
                <span class="d-inline-block text-body-2 text-medium-emphasis">{{ t('register.already_account') }}</span>
                <RouterLink
                  class="text-primary font-weight-semibold ms-1 d-inline-block text-decoration-none"
                  :to="{ name: 'login' }"
                >
                  {{ t('register.sign_in_instead') }}
                </RouterLink>
              </VCol>


            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
  <VSnackbar
    v-model="showSuccessNotification"
    color="success"
    :timeout="4000"
  >
    Registro exitoso. Hemos enviado un correo de bienvenida. Revisa tu bandeja de entrada.
  </VSnackbar>
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
