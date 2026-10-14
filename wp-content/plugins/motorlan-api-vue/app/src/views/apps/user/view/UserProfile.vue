<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import type { ComponentPublicInstance } from 'vue'
import { useI18n } from 'vue-i18n'
import type { UserProfile } from '../types'
import { apiErrorMessage, useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { requiredValidator, emailValidator } from '@/@core/utils/validators'
import { VForm } from 'vuetify/components/VForm'
import DropZone from '@/@core/components/DropZone.vue'
import ChangePasswordDialog from './ChangePasswordDialog.vue'

const profileForm = ref<VForm | null>(null)
const dropZoneRef = ref<ComponentPublicInstance<{ open: () => void }> | null>(null)
const isChangePasswordDialogVisible = ref(false)
const isSaving = ref(false)
const { showToast } = useToast()
const { t } = useI18n()
const isUploadingAvatar = ref(false)
const {
  data: userProfile,
  error: userProfileError,
  execute: fetchUserProfile,
  isFetching: isLoading,
} = useApi('/wp-json/motorlan/v1/profile', { immediate: false }).get().json<UserProfile>()

const isCompany = ref(false)

// Cambio de email: no se aplica hasta confirmarlo desde el enlace enviado al email
// NUEVO, y para pedirlo hace falta la contraseña actual.
const pendingEmail = ref('')
const isEmailPasswordDialogVisible = ref(false)
const emailChangePassword = ref('')
const emailChangePasswordError = ref('')
const isCancellingEmailChange = ref(false)

// Cloned data for editing
const personalFormData = ref<UserProfile['personal_data']>({
  nombre: '',
  apellidos: '',
  email: '',
  telefono: '',
  avatar: '',
})

const companyFormData = ref<UserProfile['company_data']>({
  nombre: '',
  direccion: '',
  cp: '',
  persona_contacto: '',
  email_contacto: '',
  tel_contacto: '',
  cif_nif: '',
})

watch(userProfile, (newData) => {
  if (newData) {
    pendingEmail.value = (newData as any).pending_email || ''
    personalFormData.value = JSON.parse(JSON.stringify(newData.personal_data))
    if (newData.company_data && Object.keys(newData.company_data).length > 0 && newData.company_data.nombre) {
      companyFormData.value = JSON.parse(JSON.stringify(newData.company_data))
      isCompany.value = true
    }
    else {
      isCompany.value = false
    }
  }
}, { immediate: true, deep: true })

watch(isCompany, (isCompanyValue) => {
  if (isCompanyValue) {
    // Pre-fill contact person data from personal data if it's empty
    if (!companyFormData.value.persona_contacto) {
      companyFormData.value.persona_contacto = `${personalFormData.value.nombre} ${personalFormData.value.apellidos}`.trim()
    }
    if (!companyFormData.value.email_contacto) {
      companyFormData.value.email_contacto = personalFormData.value.email
    }
    if (!companyFormData.value.tel_contacto) {
      companyFormData.value.tel_contacto = personalFormData.value.telefono
    }
  }
})

const resizeImage = (file: File, maxWidth = 800, maxHeight = 800): Promise<Blob> => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.readAsDataURL(file)
    reader.onload = (event) => {
      const img = new Image()
      img.src = event.target?.result as string
      img.onload = () => {
        const canvas = document.createElement('canvas')
        let width = img.width
        let height = img.height

        if (width > height) {
          if (width > maxWidth) {
            height *= maxWidth / width
            width = maxWidth
          }
        }
        else {
          if (height > maxHeight) {
            width *= maxHeight / height
            height = maxHeight
          }
        }

        canvas.width = width
        canvas.height = height
        const ctx = canvas.getContext('2d')
        ctx?.drawImage(img, 0, 0, width, height)

        canvas.toBlob((blob) => {
          if (blob) resolve(blob)
          else reject(new Error('Canvas to Blob failed'))
        }, 'image/jpeg', 0.8)
      }
      img.onerror = reject
    }
    reader.onerror = reject
  })
}

const handleAvatarUpload = async (file: File) => {
  if (!file) return

  try {
    isUploadingAvatar.value = true

    // Resize image client-side to improve speed and reliability
    const resizedBlob = await resizeImage(file)
    const formData = new FormData()
    formData.append('avatar', resizedBlob, 'avatar.jpg')

    const apiCall = useApi('/wp-json/motorlan/v1/profile', { immediate: false }).post(formData).json()
    await apiCall.execute()

    if (apiCall.error.value) {
      showToast(apiErrorMessage(apiCall.data.value, t('user_profile.avatar_error', 'Error al actualizar el avatar')), 'error')
    }
    else if (apiCall.data.value && userProfile.value) {
      userProfile.value.personal_data.avatar = apiCall.data.value.avatar
      showToast(t('user_profile.avatar_updated', 'Avatar actualizado correctamente'), 'success')
    }
  }
  catch (e) {
    console.error('Fatal error during avatar upload:', e)
    showToast(t('user_profile.avatar_process_error', 'Ha ocurrido un error al procesar la imagen.'), 'error')
  }
  finally {
    isUploadingAvatar.value = false
  }
}


onMounted(async () => {
  await fetchUserProfile()
  if (userProfileError.value) {
    showToast(t('user_profile.load_error', 'Error al cargar el perfil'), 'error')
    console.error('Error fetching profile data:', userProfileError.value)
  }
})

const emailChanged = () => {
  const current = (userProfile.value?.personal_data.email || '').trim().toLowerCase()
  const next = (personalFormData.value.email || '').trim().toLowerCase()

  return !!next && next !== current
}

const sendProfile = async (currentPassword?: string) => {
  if (!userProfile.value)
    return
  isSaving.value = true
  try {
    const payload: Record<string, any> = {
      personal_data: personalFormData.value,
      company_data: isCompany.value ? companyFormData.value : {},
    }
    if (currentPassword)
      payload.current_password = currentPassword

    const { error, data: saveData } = await useApi('/wp-json/motorlan/v1/profile').post(payload).json()
    const result: any = saveData.value || {}

    if (error.value) {
      // Contraseña ausente o incorrecta: se queda en el diálogo para reintentar
      if (result.code === 'password_required' || result.code === 'incorrect_password') {
        emailChangePasswordError.value = apiErrorMessage(result, t('user_profile.email_change_password_error', 'La contraseña actual no es correcta.'))
        isEmailPasswordDialogVisible.value = true

        return
      }
      isEmailPasswordDialogVisible.value = false
      // Motivo real (email inválido o ya en uso...)
      showToast(apiErrorMessage(result, t('user_profile.save_error', 'Error al guardar los datos')), 'error')

      return
    }

    isEmailPasswordDialogVisible.value = false
    emailChangePassword.value = ''
    emailChangePasswordError.value = ''

    const savedPersonal = JSON.parse(JSON.stringify(personalFormData.value))
    if (result.email_change_pending) {
      // El email de la cuenta no cambia hasta confirmar el enlace
      savedPersonal.email = userProfile.value.personal_data.email
      personalFormData.value.email = userProfile.value.personal_data.email
    }
    if (typeof result.pending_email === 'string') {
      pendingEmail.value = result.pending_email
      // El watch de userProfile vuelve a leer pending_email: mantenerlo al día
      ;(userProfile.value as any).pending_email = result.pending_email
    }

    userProfile.value.personal_data = savedPersonal
    if (isCompany.value) {
      userProfile.value.company_data = JSON.parse(JSON.stringify(companyFormData.value))
    }
    else {
      userProfile.value.company_data = {
        nombre: '',
        direccion: '',
        cp: '',
        persona_contacto: '',
        email_contacto: '',
        tel_contacto: '',
        cif_nif: '',
      }
    }

    if (result.email_change_pending)
      showToast(t('user_profile.email_change_sent', { email: pendingEmail.value }, 'Te hemos enviado un enlace a {email}. Tu email no cambiará hasta que lo confirmes.'), 'success')
    else
      showToast(t('user_profile.saved', 'Datos guardados correctamente'), 'success')
  }
  catch (error) {
    console.error('Error saving profile data:', error)
    showToast(t('user_profile.save_error', 'Error al guardar los datos'), 'error')
  }
  finally {
    isSaving.value = false
  }
}

const saveProfileData = async () => {
  if (!profileForm.value) return

  const { valid } = await profileForm.value.validate()
  if (!valid)
    return

  // Cambiar el email exige la contraseña actual
  if (emailChanged()) {
    emailChangePassword.value = ''
    emailChangePasswordError.value = ''
    isEmailPasswordDialogVisible.value = true

    return
  }

  await sendProfile()
}

const confirmEmailChangeWithPassword = async () => {
  if (!emailChangePassword.value) {
    emailChangePasswordError.value = t('user_profile.email_change_password_required', 'Introduce tu contraseña actual para cambiar el email.')

    return
  }
  emailChangePasswordError.value = ''
  await sendProfile(emailChangePassword.value)
}

const cancelEmailChangeDialog = () => {
  isEmailPasswordDialogVisible.value = false
  emailChangePassword.value = ''
  emailChangePasswordError.value = ''
}

const cancelPendingEmailChange = async () => {
  isCancellingEmailChange.value = true
  try {
    const { error } = await useApi('/wp-json/motorlan/v1/profile/cancel-email-change').post({}).json()
    if (!error.value) {
      pendingEmail.value = ''
      if (userProfile.value)
        (userProfile.value as any).pending_email = ''
      showToast(t('user_profile.email_change_cancelled', 'Cambio de email cancelado.'), 'success')
    }
    else {
      showToast(t('user_profile.save_error', 'Error al guardar los datos'), 'error')
    }
  }
  finally {
    isCancellingEmailChange.value = false
  }
}
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard
        :loading="isLoading"
        class="profile-card motor-card-enhanced overflow-visible"
      >
        <VCardText class="d-flex flex-column align-center pa-6">
          <div class="avatar-container mt-n16">
            <VAvatar
              size="120"
              class="avatar-center border-4 border-white shadow-lg"
              color="primary"
              variant="tonal"
            >
              <VImg :src="userProfile?.personal_data.avatar || '/placeholder.png'" />
              <div
                v-if="isUploadingAvatar"
                class="avatar-upload-overlay"
              >
                <VProgressCircular
                  indeterminate
                  color="white"
                />
              </div>
            </VAvatar>
            <DropZone
              class="avatar-dropzone"
              :multiple="false"
              @file-added="handleAvatarUpload"
            />
            <VBtn
              icon
              variant="elevated"
              color="white"
              size="small"
              class="avatar-edit-button rounded-circle"
            >
              <VIcon
                icon="tabler-camera"
                size="18"
                color="primary"
              />
            </VBtn>
          </div>
          <div class="text-center mt-4">
            <h4 class="text-h4 text-premium-title mb-1">
              {{ userProfile?.personal_data.nombre }} {{ userProfile?.personal_data.apellidos }}
            </h4>
            <div class="d-flex align-center justify-center gap-2">
              <VIcon
                icon="tabler-mail"
                size="16"
                class="text-muted"
              />
              <span class="text-body-1 text-medium-emphasis">{{ userProfile?.personal_data.email }}</span>
            </div>
          </div>
        </VCardText>

        <VDivider />

        <VForm
          ref="profileForm"
          @submit.prevent="saveProfileData"
        >
          <VCardText class="pa-6">
            <div class="d-flex align-center gap-2 mb-6">
              <VIcon
                icon="tabler-user"
                color="primary"
              />
              <span class="text-h6 font-weight-bold">{{ t('user_profile.personal_info') }}</span>
            </div>

            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="personalFormData.nombre"
                  :label="t('user_profile.first_name')"
                  :placeholder="t('user_profile.first_name_placeholder')"
                  :rules="[requiredValidator]"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="personalFormData.apellidos"
                  :label="t('user_profile.last_name')"
                  :placeholder="t('user_profile.last_name_placeholder')"
                  :rules="[requiredValidator]"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="personalFormData.email"
                  :label="t('user_profile.email')"
                  type="email"
                  placeholder="tu@email.com"
                  :rules="[requiredValidator, emailValidator]"
                  :hint="t('user_profile.email_change_hint', 'Si lo cambias, te pediremos tu contraseña y tendrás que confirmarlo desde el nuevo email.')"
                />
              </VCol>
              <VCol
                v-if="pendingEmail"
                cols="12"
              >
                <VAlert
                  color="warning"
                  variant="tonal"
                  icon="tabler-mail-forward"
                  class="rounded-lg"
                >
                  <div class="d-flex flex-wrap align-center justify-space-between gap-2">
                    <span>{{ t('user_profile.email_change_pending', { email: pendingEmail }, 'Cambio de email pendiente: confirma el enlace que hemos enviado a {email}.') }}</span>
                    <VBtn
                      size="small"
                      variant="text"
                      color="warning"
                      :loading="isCancellingEmailChange"
                      @click="cancelPendingEmailChange"
                    >
                      {{ t('user_profile.email_change_cancel', 'Cancelar cambio') }}
                    </VBtn>
                  </div>
                </VAlert>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="personalFormData.telefono"
                  :label="t('user_profile.phone')"
                  placeholder="+34 000 000 000"
                  :rules="[requiredValidator]"
                />
              </VCol>
            </VRow>
          </VCardText>

          <VDivider />

          <VCardText class="pa-6">
            <div class="user-profile-company-header d-flex align-center justify-space-between mb-4">
              <div class="d-flex align-center gap-2">
                <VIcon
                  icon="tabler-building"
                  color="primary"
                />
                <span class="text-h6 font-weight-bold">{{ t('user_profile.company_info') }}</span>
              </div>
              <VSwitch
                v-model="isCompany"
                :label="t('user_profile.is_company')"
                density="compact"
                hide-details
              />
            </div>

            <VExpandTransition>
              <div v-if="isCompany">
                <p class="text-body-2 text-muted mb-6">
                  {{ t('user_profile.company_intro', 'Completa los datos de tu empresa para facturación y contacto comercial. Los campos con * son obligatorios.') }}
                </p>
                <VRow>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="companyFormData.nombre"
                      :label="t('user_profile.company_name')"
                      :placeholder="t('user_profile.company_name_placeholder')"
                      :rules="isCompany ? [requiredValidator] : []"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="companyFormData.cif_nif"
                      :label="t('user_profile.cif_nif')"
                      :placeholder="t('user_profile.cif_nif_placeholder')"
                      :rules="isCompany ? [requiredValidator] : []"
                    />
                  </VCol>
                  <VCol cols="12">
                    <AppTextField
                      v-model="companyFormData.direccion"
                      :label="t('user_profile.address')"
                      :placeholder="t('user_profile.address_placeholder')"
                      :rules="isCompany ? [requiredValidator] : []"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="companyFormData.cp"
                      :label="t('user_profile.postal_code')"
                      placeholder="00000"
                      :rules="isCompany ? [requiredValidator] : []"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="companyFormData.persona_contacto"
                      :label="t('user_profile.contact_person')"
                      :placeholder="t('user_profile.contact_person_placeholder')"
                      :rules="isCompany ? [requiredValidator] : []"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="companyFormData.email_contacto"
                      :label="t('user_profile.contact_email')"
                      type="email"
                      placeholder="empresa@email.com"
                      :rules="isCompany ? [requiredValidator, emailValidator] : []"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="companyFormData.tel_contacto"
                      :label="t('user_profile.contact_phone')"
                      placeholder="+34 000 000 000"
                      :rules="isCompany ? [requiredValidator] : []"
                    />
                  </VCol>
                </VRow>
              </div>
            </VExpandTransition>
          </VCardText>

          <VCardActions class="pa-6 pt-0 user-profile-actions">
            <VBtn
              color="primary"
              variant="elevated"
              :loading="isSaving"
              type="submit"
              class="px-8 rounded-pill"
            >
              {{ t('user_profile.save_changes', 'Guardar cambios') }}
            </VBtn>
            <VBtn
              variant="tonal"
              color="secondary"
              :disabled="isSaving"
              class="rounded-pill"
              @click="isChangePasswordDialogVisible = true"
            >
              {{ t('change_password.title', 'Cambiar contraseña') }}
            </VBtn>
          </VCardActions>
        </VForm>
      </VCard>
    </VCol>
  </VRow>
  <ChangePasswordDialog v-if="userProfile" v-model="isChangePasswordDialogVisible" :email="userProfile.personal_data.email" />

  <!-- Contraseña actual para cambiar el email -->
  <VDialog
    v-model="isEmailPasswordDialogVisible"
    max-width="460px"
    persistent
  >
    <VCard class="motor-card-enhanced">
      <VCardTitle class="pa-6 pb-0">
        <span class="text-h5 text-premium-title">{{ t('user_profile.email_change_title', 'Cambiar email') }}</span>
      </VCardTitle>
      <VCardText class="pa-6">
        <p class="text-body-2 mb-4">
          {{ t('user_profile.email_change_explain', { email: personalFormData.email }, 'Para usar {email} introduce tu contraseña actual. Te enviaremos un enlace a ese email y el cambio no se aplicará hasta que lo confirmes.') }}
        </p>
        <AppTextField
          v-model="emailChangePassword"
          :label="t('user_profile.current_password', 'Contraseña actual')"
          type="password"
          autocomplete="current-password"
          autofocus
          :error-messages="emailChangePasswordError ? [emailChangePasswordError] : []"
          @keyup.enter="confirmEmailChangeWithPassword"
        />
      </VCardText>
      <VCardActions class="pa-6 pt-0">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          class="rounded-pill"
          :disabled="isSaving"
          @click="cancelEmailChangeDialog"
        >
          {{ t('change_password.cancel', 'Cancelar') }}
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          class="rounded-pill px-6"
          :loading="isSaving"
          @click="confirmEmailChangeWithPassword"
        >
          {{ t('user_profile.email_change_confirm', 'Enviar enlace') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.profile-card {
  position: relative;
  padding-top: 60px;
}

.avatar-container {
  position: relative;
  width: 120px;
  height: 120px;
  margin-bottom: 1rem;
}

.avatar-center {
  position: absolute;
  top: -50px;
  left: 50%;
  transform: translateX(-50%);
  border: 4px solid white;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
  z-index: 1;
}

.avatar-upload-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  border-radius: 50%;
  z-index: 4;
}

.avatar-dropzone {
  position: absolute;
  top: -50px;
  left: 50%;
  transform: translateX(-50%);
  width: 120px;
  height: 120px;
  border-radius: 50%;
  z-index: 2;
  opacity: 0;
  cursor: pointer;
}

.avatar-edit-button {
  position: absolute;
  top: 50px;
  left: calc(50% + 40px);
  transform: translate(-50%, -50%);
  z-index: 3;
  pointer-events: none; /* The dropzone will handle the click */
}

.user-profile-company-header {
  gap: 1rem;
}

.user-profile-actions {
  gap: 0.75rem;
}

@media (max-width: 959px) {
  .profile-card {
    padding-top: 48px;
  }

  .avatar-container,
  .avatar-dropzone {
    width: 96px;
    height: 96px;
  }

  .avatar-center {
    top: -42px;
  }

  .avatar-edit-button {
    top: 44px;
    left: calc(50% + 30px);
  }

  .user-profile-company-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .user-profile-actions {
    flex-direction: column;
  }

  .user-profile-actions :deep(.v-btn) {
    width: 100%;
  }

  .user-profile-actions :deep(.v-spacer) {
    display: none;
  }
}
</style>
