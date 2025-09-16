<script setup lang="ts">
import { VForm } from 'vuetify/components/VForm'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import AuthProvider from '@/views/pages/authentication/AuthProvider.vue'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

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

const register = async () => {
  if (!form.value.privacyPolicies) {
    genericError.value = 'Debes aceptar la política de privacidad y los términos.'
    
    return
  }
  isSubmitting.value = true
  genericError.value = null
  errors.value = {}

  const { data, error } = await useApi('/wp-json/wp/v2/users/register', {
    method: 'POST',
    body: JSON.stringify({
      username: form.value.username,
      email: form.value.email,
      password: form.value.password,
    }),
  }).json()

  isSubmitting.value = false

  if (error.value) {
    genericError.value = error.value.data?.message || 'Ocurrió un error al registrar la cuenta.'
    
    return
  }

  // Handle success
  if (data.value) {
    // Optionally, you can automatically log in the user here
    // For now, let's redirect to the login page with a success message
    router.push({ name: 'login', query: { registered: 'true' } })
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
          style="padding-inline: 100px;"
        >
          <VImg
            max-width="500"
            :src="imageVariant"
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
      style="background-color: rgb(var(--v-theme-surface));"
    >
      <VCard
        flat
        :max-width="500"
        class="mt-12 mt-sm-0 pa-4"
      >
        <VCardText>
          <h4 class="text-h4 mb-1">
            {{ t('register.title') }}
          </h4>
          <p class="mb-0">
            {{ t('register.subtitle') }}
          </p>
        </VCardText>

        <VCardText>
          <VAlert
            v-if="genericError"
            color="error"
            variant="tonal"
            class="mb-4"
          >
            {{ genericError }}
          </VAlert>
          <VForm
            ref="refVForm"
            @submit.prevent="onSubmit"
          >
            <VRow>
              <!-- Username -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.username"
                  :rules="[requiredValidator]"
                  autofocus
                  :label="t('register.username')"
                  placeholder="Johndoe"
                  :error-messages="errors.username"
                />
              </VCol>

              <!-- email -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.email"
                  :rules="[requiredValidator, emailValidator]"
                  :label="t('register.email')"
                  type="email"
                  placeholder="johndoe@email.com"
                  :error-messages="errors.email"
                />
              </VCol>

              <!-- password -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.password"
                  :rules="[requiredValidator]"
                  :label="t('register.password')"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  autocomplete="password"
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
                    <span class="me-1 text-high-emphasis">{{ t('register.agree_to') }}</span>
                    <a
                      href="javascript:void(0)"
                      class="text-primary"
                    >{{ t('register.privacy_policy') }}</a>
                  </VLabel>
                </div>

                <VBtn
                  block
                  type="submit"
                  :loading="isSubmitting"
                  :disabled="isSubmitting"
                >
                  {{ t('register.sign_up') }}
                </VBtn>
              </VCol>

              <!-- create account -->
              <VCol
                cols="12"
                class="text-center text-base"
              >
                <span class="d-inline-block">{{ t('register.already_account') }}</span>
                <RouterLink
                  class="text-primary ms-1 d-inline-block"
                  :to="{ name: 'login' }"
                >
                  {{ t('register.sign_in_instead') }}
                </RouterLink>
              </VCol>

              <VCol
                cols="12"
                class="d-flex align-center"
              >
                <VDivider />
                <span class="mx-4">{{ t('register.or') }}</span>
                <VDivider />
              </VCol>

              <!-- auth providers -->
              <VCol
                cols="12"
                class="text-center"
              >
                <AuthProvider />
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
</style>
