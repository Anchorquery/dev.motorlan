<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { VForm } from 'vuetify/components/VForm'
import { useI18n } from 'vue-i18n'
import { apiErrorMessage, useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { emailValidator, requiredValidator } from '@core/utils/validators'
import { accountUrl } from '@/utils/urls'

const { t } = useI18n()
const { showToast } = useToast()

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

type FormState = {
  first_name: string
  last_name: string
  email: string
  password: string
  password_confirm: string
  privacyPolicies: boolean
}

const form = ref<FormState>({
  first_name: '',
  last_name: '',
  email: '',
  password: '',
  password_confirm: '',
  privacyPolicies: false,
})

// Política de privacidad (antes el enlace era javascript:void(0): había que aceptarla
// sin poder leerla). wpData.privacy_url = get_privacy_policy_url() traducida por WPML.
const privacyUrl = (window as any)?.wpData?.privacy_url || '/politica-de-privacidad/'

const step = ref(1)
const isPasswordVisible = ref(false)
const isPasswordConfirmVisible = ref(false)
const isSubmitting = ref(false)
const refStep1 = ref<VForm>()
const refStep2 = ref<VForm>()
const errors = ref<Record<string, string | undefined>>({})
const genericError = ref<string | null>(null)

const passwordChecks = computed(() => {
  const p = form.value.password
  return {
    length: p.length >= 8,
    upper: /[A-Z]/.test(p),
    lower: /[a-z]/.test(p),
    digit: /[0-9]/.test(p),
    special: /[^A-Za-z0-9]/.test(p),
  }
})

const passwordScore = computed(() => {
  const c = passwordChecks.value
  return [c.length, c.upper, c.lower, c.digit, c.special].filter(Boolean).length
})

const passwordStrengthColor = computed(() => {
  const s = passwordScore.value
  if (s <= 2) return 'error'
  if (s === 3) return 'warning'
  if (s === 4) return 'info'
  return 'success'
})

const isPasswordValid = computed(() => passwordScore.value === 5)

const passwordsMatch = computed(() =>
  form.value.password.length > 0 && form.value.password === form.value.password_confirm,
)

const passwordConfirmRule = (v: string) =>
  v === form.value.password || t('register.passwords_mismatch')

watch(() => form.value.password, () => {
  errors.value.password = undefined
})
watch(() => form.value.password_confirm, () => {
  errors.value.password_confirm = undefined
})
watch(() => form.value.email, () => {
  errors.value.email = undefined
})

const goNext = async () => {
  genericError.value = null
  if (step.value === 1) {
    const { valid } = await (refStep1.value?.validate() ?? Promise.resolve({ valid: false }))
    if (valid) step.value = 2
    return
  }
  if (step.value === 2) {
    const { valid } = await (refStep2.value?.validate() ?? Promise.resolve({ valid: false }))
    if (!valid) return
    if (!isPasswordValid.value) {
      genericError.value = t('register.password_requirements_title')
      return
    }
    if (!passwordsMatch.value) {
      errors.value.password_confirm = t('register.passwords_mismatch')
      return
    }
    // El backend rechaza contraseñas que contienen el nombre o el email: avisar aquí
    // en lugar de descubrirlo al enviar el paso 3
    const lowerPass = form.value.password.toLowerCase()
    const personal = [form.value.first_name.trim(), form.value.email.trim()]
      .filter(v => v.length >= 3)
      .map(v => v.toLowerCase())
    if (personal.some(v => lowerPass.includes(v))) {
      errors.value.password = t('password_rules.personal_data', 'La contraseña no debe contener tu nombre ni tu email.')
      return
    }
    step.value = 3
  }
}

const goBack = () => {
  if (step.value > 1) step.value--
}

const register = async () => {
  if (!form.value.privacyPolicies) {
    genericError.value = t('register.must_accept_terms')
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
      // Idioma de la web: los emails (verificación, avisos) se enviarán en este idioma
      lang: (window as any)?.wpData?.language || 'es',
    }),
  }).json()

  if (error.value) {
    isSubmitting.value = false

    const payload: any = data.value || {}
    const baseMessage = payload.message || t('register.generic_error')
    const field = payload.field as string | undefined

    // Mensaje traducido por `code` + lista de requisitos de contraseña (data.errors)
    const finalMessage = apiErrorMessage(payload, t('register.generic_error'))

    if (field && ['email', 'username', 'password', 'first_name', 'last_name'].includes(field)) {
      const targetField = field === 'username' ? 'email' : field
      errors.value[targetField] = finalMessage
      if (targetField === 'email' || targetField === 'first_name' || targetField === 'last_name') step.value = 1
      else step.value = 2
    }
    else {
      const lowered = baseMessage.toLowerCase()
      if (lowered.includes('email') || lowered.includes('correo')) {
        errors.value.email = finalMessage
        step.value = 1
      }
      else if (lowered.includes('contraseña') || lowered.includes('password')) {
        errors.value.password = finalMessage
        step.value = 2
      }
      else {
        genericError.value = finalMessage
      }
    }
    return
  }

  if (data.value) {
    const payload = data.value as any
    if (payload.dev_verify_url) {
      try { sessionStorage.setItem('motorlan_dev_verify_url', payload.dev_verify_url) }
      catch {}
    }
    try { sessionStorage.setItem('motorlan_register_email', form.value.email) }
    catch {}
    // isSubmitting sigue en true hasta la redirección: antes un segundo clic en ese
    // intervalo volvía a enviar el registro ("ya registrado" bajo un toast de éxito).
    showToast(apiErrorMessage(payload, t('register.success_toast', 'Usuario registrado con éxito.')), 'success')
    setTimeout(() => {
      window.location.href = accountUrl('register-success')
    }, 1200)
  }
  else {
    isSubmitting.value = false
  }
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
        :max-width="560"
        class="pa-6 pa-sm-8 elevation-10 rounded-xl w-100"
      >
        <VCardText class="pb-2">
          <h4 class="text-h4 font-weight-bold mb-1">
            {{ t('register.title') }}
          </h4>
          <p class="mb-0 text-body-1 text-medium-emphasis">
            {{ t('register.subtitle') }}
          </p>
        </VCardText>

        <VCardText>
          <VStepper
            v-model="step"
            :items="[t('register.step_account'), t('register.step_credentials'), t('register.step_confirm')]"
            hide-actions
            flat
            class="mb-4 bg-transparent"
          />

          <VAlert
            v-if="genericError"
            color="error"
            variant="tonal"
            class="mb-4 rounded-lg"
            icon="tabler-alert-triangle"
          >
            {{ genericError }}
          </VAlert>

          <!-- Step 1: Personal data -->
          <VForm
            v-if="step === 1"
            ref="refStep1"
            @submit.prevent="goNext"
          >
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="form.first_name"
                  :rules="[requiredValidator]"
                  autofocus
                  :label="t('register.first_name')"
                  :placeholder="t('register.first_name_placeholder')"
                  variant="outlined"
                  density="comfortable"
                  :error-messages="errors.first_name"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="form.last_name"
                  :rules="[requiredValidator]"
                  :label="t('register.last_name')"
                  :placeholder="t('register.last_name_placeholder')"
                  variant="outlined"
                  density="comfortable"
                  :error-messages="errors.last_name"
                />
              </VCol>
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
              <VCol cols="12">
                <VBtn
                  block
                  type="submit"
                  size="large"
                  rounded="lg"
                  class="font-weight-bold"
                >
                  {{ t('register.next') }}
                </VBtn>
              </VCol>
            </VRow>
          </VForm>

          <!-- Step 2: Credentials -->
          <VForm
            v-else-if="step === 2"
            ref="refStep2"
            @submit.prevent="goNext"
          >
            <VRow>
              <VCol cols="12">
                <VTextField
                  v-model="form.password"
                  :rules="[requiredValidator]"
                  :label="t('register.password')"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  autocomplete="new-password"
                  variant="outlined"
                  density="comfortable"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :error-messages="errors.password"
                  autofocus
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />
              </VCol>

              <VCol cols="12" class="pt-0">
                <VProgressLinear
                  :model-value="passwordScore * 20"
                  :color="passwordStrengthColor"
                  height="6"
                  rounded
                  class="mb-3"
                />
                <div class="text-caption text-medium-emphasis font-weight-medium mb-2">
                  {{ t('register.password_requirements_title') }}
                </div>
                <div class="d-flex flex-column gap-1">
                  <div class="d-flex align-center gap-2 text-caption">
                    <VIcon
                      :icon="passwordChecks.length ? 'tabler-circle-check-filled' : 'tabler-circle'"
                      :color="passwordChecks.length ? 'success' : 'medium-emphasis'"
                      size="16"
                    />
                    <span :class="passwordChecks.length ? 'text-success' : 'text-medium-emphasis'">
                      {{ t('register.password_req_length') }}
                    </span>
                  </div>
                  <div class="d-flex align-center gap-2 text-caption">
                    <VIcon
                      :icon="passwordChecks.upper ? 'tabler-circle-check-filled' : 'tabler-circle'"
                      :color="passwordChecks.upper ? 'success' : 'medium-emphasis'"
                      size="16"
                    />
                    <span :class="passwordChecks.upper ? 'text-success' : 'text-medium-emphasis'">
                      {{ t('register.password_req_upper') }}
                    </span>
                  </div>
                  <div class="d-flex align-center gap-2 text-caption">
                    <VIcon
                      :icon="passwordChecks.lower ? 'tabler-circle-check-filled' : 'tabler-circle'"
                      :color="passwordChecks.lower ? 'success' : 'medium-emphasis'"
                      size="16"
                    />
                    <span :class="passwordChecks.lower ? 'text-success' : 'text-medium-emphasis'">
                      {{ t('register.password_req_lower') }}
                    </span>
                  </div>
                  <div class="d-flex align-center gap-2 text-caption">
                    <VIcon
                      :icon="passwordChecks.digit ? 'tabler-circle-check-filled' : 'tabler-circle'"
                      :color="passwordChecks.digit ? 'success' : 'medium-emphasis'"
                      size="16"
                    />
                    <span :class="passwordChecks.digit ? 'text-success' : 'text-medium-emphasis'">
                      {{ t('register.password_req_digit') }}
                    </span>
                  </div>
                  <div class="d-flex align-center gap-2 text-caption">
                    <VIcon
                      :icon="passwordChecks.special ? 'tabler-circle-check-filled' : 'tabler-circle'"
                      :color="passwordChecks.special ? 'success' : 'medium-emphasis'"
                      size="16"
                    />
                    <span :class="passwordChecks.special ? 'text-success' : 'text-medium-emphasis'">
                      {{ t('register.password_req_special') }}
                    </span>
                  </div>
                </div>
              </VCol>

              <VCol cols="12">
                <VTextField
                  v-model="form.password_confirm"
                  :rules="[requiredValidator, passwordConfirmRule]"
                  :label="t('register.password_confirm')"
                  placeholder="············"
                  :type="isPasswordConfirmVisible ? 'text' : 'password'"
                  autocomplete="new-password"
                  variant="outlined"
                  density="comfortable"
                  :append-inner-icon="isPasswordConfirmVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :error-messages="errors.password_confirm"
                  @click:append-inner="isPasswordConfirmVisible = !isPasswordConfirmVisible"
                />
              </VCol>

              <VCol cols="6">
                <VBtn
                  block
                  variant="tonal"
                  color="secondary"
                  size="large"
                  rounded="lg"
                  @click="goBack"
                >
                  {{ t('register.back') }}
                </VBtn>
              </VCol>
              <VCol cols="6">
                <VBtn
                  block
                  type="submit"
                  size="large"
                  rounded="lg"
                  :disabled="!isPasswordValid || !passwordsMatch"
                  class="font-weight-bold"
                >
                  {{ t('register.next') }}
                </VBtn>
              </VCol>
            </VRow>
          </VForm>

          <!-- Step 3: Confirmation -->
          <div v-else-if="step === 3">
            <VAlert
              color="info"
              variant="tonal"
              density="compact"
              class="mb-4"
              icon="tabler-info-circle"
            >
              <span class="text-body-2">
                {{ t('register.review_notice', 'Revisa que los datos sean correctos antes de crear tu cuenta. Recibirás un email para activarla.') }}
              </span>
            </VAlert>

            <VList density="comfortable" class="rounded-lg border mb-4 pa-0">
              <VListItem>
                <template #prepend>
                  <VIcon icon="tabler-user" size="20" class="me-3 text-primary" />
                </template>
                <VListItemTitle class="text-caption text-medium-emphasis">{{ t('register.first_name') }} / {{ t('register.last_name') }}</VListItemTitle>
                <VListItemSubtitle class="text-body-2 text-high-emphasis font-weight-medium">
                  {{ form.first_name }} {{ form.last_name }}
                </VListItemSubtitle>
              </VListItem>
              <VDivider />
              <VListItem>
                <template #prepend>
                  <VIcon icon="tabler-mail" size="20" class="me-3 text-primary" />
                </template>
                <VListItemTitle class="text-caption text-medium-emphasis">{{ t('register.email') }}</VListItemTitle>
                <VListItemSubtitle class="text-body-2 text-high-emphasis font-weight-medium">
                  {{ form.email }}
                </VListItemSubtitle>
              </VListItem>
            </VList>

            <div class="d-flex align-center mb-4">
              <VCheckbox
                id="privacy-policy"
                v-model="form.privacyPolicies"
                inline
                hide-details
              />
              <VLabel for="privacy-policy" style="opacity: 1;">
                <span class="me-1 text-medium-emphasis">{{ t('register.agree_to') }}</span>
                <a
                  :href="privacyUrl"
                  target="_blank"
                  rel="noopener"
                  class="text-primary font-weight-medium text-decoration-none"
                >{{ t('register.privacy_policy') }}</a>
              </VLabel>
            </div>

            <VRow>
              <VCol cols="6">
                <VBtn
                  block
                  variant="tonal"
                  color="secondary"
                  size="large"
                  rounded="lg"
                  :disabled="isSubmitting"
                  @click="goBack"
                >
                  {{ t('register.back') }}
                </VBtn>
              </VCol>
              <VCol cols="6">
                <VBtn
                  block
                  size="large"
                  rounded="lg"
                  color="primary"
                  :loading="isSubmitting"
                  :disabled="isSubmitting || !form.privacyPolicies"
                  class="font-weight-bold"
                  @click="register"
                >
                  {{ t('register.sign_up') }}
                </VBtn>
              </VCol>
            </VRow>
          </div>

          <div class="text-center text-base pt-4">
            <span class="d-inline-block text-body-2 text-medium-emphasis">{{ t('register.already_account') }}</span>
            <RouterLink
              class="text-primary font-weight-semibold ms-1 d-inline-block text-decoration-none"
              :to="{ name: 'login' }"
            >
              {{ t('register.sign_in_instead') }}
            </RouterLink>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core/scss/template/pages/page-auth";
</style>
