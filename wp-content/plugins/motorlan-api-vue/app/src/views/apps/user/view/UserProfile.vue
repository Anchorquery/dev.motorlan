<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import type { ComponentPublicInstance } from 'vue'
import type { UserProfile } from '../types'
import { useApi } from '@/composables/useApi'
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
const isUploadingAvatar = ref(false)
const {
  data: userProfile,
  error: userProfileError,
  execute: fetchUserProfile,
  isFetching: isLoading,
} = useApi('/wp-json/motorlan/v1/profile', { immediate: false }).get().json<UserProfile>()

const isCompany = ref(false)

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
      const errorMessage = apiCall.error.value.data?.message || 'Error al actualizar el avatar'
      showToast(errorMessage, 'error')
    }
    else if (apiCall.data.value && userProfile.value) {
      userProfile.value.personal_data.avatar = apiCall.data.value.avatar
      showToast('Avatar actualizado correctamente', 'success')
    }
  }
  catch (e) {
    console.error('Fatal error during avatar upload:', e)
    showToast('Ha ocurrido un error al procesar la imagen.', 'error')
  }
  finally {
    isUploadingAvatar.value = false
  }
}


onMounted(async () => {
  await fetchUserProfile()
  if (userProfileError.value) {
    const errorMessage = userProfileError.value.data?.message || 'Error al cargar el perfil'
    showToast(errorMessage, 'error')
    console.error('Error fetching profile data:', userProfileError.value)
  }
})

const saveProfileData = async () => {
  if (!profileForm.value) return

  const { valid } = await profileForm.value.validate()

  if (valid) {
    isSaving.value = true
    try {
      if (userProfile.value) {
        const payload = {
          personal_data: personalFormData.value,
          company_data: isCompany.value ? companyFormData.value : {},
        }

        const { error } = await useApi('/wp-json/motorlan/v1/profile').post(payload).json()

        if (!error.value && userProfile.value) {
          // On success, update the original state
          userProfile.value.personal_data = JSON.parse(JSON.stringify(personalFormData.value))
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
          showToast('Datos guardados correctamente', 'success')
        }
        else {
          showToast('Error al guardar los datos', 'error')
        }
      }
    }
    catch (error) {
      console.error('Error saving profile data:', error)
      showToast('Error al guardar los datos', 'error')
    }
    finally {
      isSaving.value = false
    }
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
              <span class="text-h6 font-weight-bold">Información Personal</span>
            </div>

            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="personalFormData.nombre"
                  label="Nombre"
                  placeholder="Tu nombre"
                  :rules="[requiredValidator]"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="personalFormData.apellidos"
                  label="Apellidos"
                  placeholder="Tus apellidos"
                  :rules="[requiredValidator]"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="personalFormData.email"
                  label="Email"
                  type="email"
                  placeholder="tu@email.com"
                  :rules="[requiredValidator, emailValidator]"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="personalFormData.telefono"
                  label="Teléfono"
                  placeholder="+34 000 000 000"
                  :rules="[requiredValidator]"
                />
              </VCol>
            </VRow>
          </VCardText>

          <VDivider />

          <VCardText class="pa-6">
            <div class="d-flex align-center justify-space-between mb-4">
              <div class="d-flex align-center gap-2">
                <VIcon
                  icon="tabler-building"
                  color="primary"
                />
                <span class="text-h6 font-weight-bold">Información de Empresa</span>
              </div>
              <VSwitch
                v-model="isCompany"
                label="¿Eres una empresa?"
                density="compact"
                hide-details
              />
            </div>

            <VExpandTransition>
              <div v-if="isCompany">
                <p class="text-body-2 text-muted mb-6">
                  Completa los datos de tu empresa para facturación y contacto comercial. Los campos con <span class="text-error">*</span> son obligatorios.
                </p>
                <VRow>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="companyFormData.nombre"
                      label="Nombre de la empresa *"
                      placeholder="Empresa S.L."
                      :rules="isCompany ? [requiredValidator] : []"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="companyFormData.cif_nif"
                      label="CIF / NIF *"
                      placeholder="A00000000"
                      :rules="isCompany ? [requiredValidator] : []"
                    />
                  </VCol>
                  <VCol cols="12">
                    <AppTextField
                      v-model="companyFormData.direccion"
                      label="Dirección *"
                      placeholder="Calle, Número, Piso, Puerta"
                      :rules="isCompany ? [requiredValidator] : []"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="companyFormData.cp"
                      label="C.P. *"
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
                      label="Persona de contacto *"
                      placeholder="Nombre del responsable"
                      :rules="isCompany ? [requiredValidator] : []"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="companyFormData.email_contacto"
                      label="Email de contacto *"
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
                      label="Tel. de contacto *"
                      placeholder="+34 000 000 000"
                      :rules="isCompany ? [requiredValidator] : []"
                    />
                  </VCol>
                </VRow>
              </div>
            </VExpandTransition>
          </VCardText>

          <VCardActions class="pa-6 pt-0">
            <VBtn
              color="primary"
              variant="elevated"
              :loading="isSaving"
              type="submit"
              class="px-8 rounded-pill"
            >
              Guardar Cambios
            </VBtn>
            <VBtn
              variant="tonal"
              color="secondary"
              :disabled="isSaving"
              class="rounded-pill"
              @click="isChangePasswordDialogVisible = true"
            >
              Cambiar Contraseña
            </VBtn>
          </VCardActions>
        </VForm>
      </VCard>
    </VCol>
  </VRow>
  <ChangePasswordDialog v-if="userProfile" v-model="isChangePasswordDialogVisible" :email="userProfile.personal_data.email" />
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
</style>
