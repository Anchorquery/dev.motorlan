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
const { data: userProfile, execute: fetchUserProfile, isFetching: isLoading } = useApi('/wp-json/motorlan/v1/profile').json<UserProfile>()

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

const handleAvatarUpload = async (file: File) => {
  if (!file) return

  const formData = new FormData()
  formData.append('avatar', file)

  // Append other profile data to the form data so the backend can process it all at once
  if (userProfile.value) {
    formData.append('personal_data', JSON.stringify(personalFormData.value))
    formData.append('company_data', JSON.stringify(companyFormData.value))
  }

  isUploadingAvatar.value = true

  const apiCall = useApi('/wp-json/motorlan/v1/profile', { immediate: false }).post(formData).json()

  try {
    await apiCall.execute()

    if (apiCall.error.value) {
      const errorMessage = apiCall.error.value.data?.message || 'Error al actualizar el avatar'
      showToast(errorMessage, 'error')
      console.error('Error uploading avatar:', apiCall.error.value)
    }
    else if (apiCall.data.value && userProfile.value) {
      userProfile.value.personal_data.avatar = apiCall.data.value.avatar
      showToast('Avatar actualizado correctamente', 'success')
    }
  }
  catch (e) {
    console.error('Fatal error during avatar upload:', e)
    showToast('Ha ocurrido un error crítico al subir la imagen.', 'error')
  }
  finally {
    isUploadingAvatar.value = false
  }
}


onMounted(fetchUserProfile)

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
      <VCard :loading="isLoading" class="profile-card">
        <VCardText class="d-flex flex-column align-center">
          <div class="avatar-container">
            <VAvatar
              size="120"
              class="avatar-center"
            >
              <VImg :src="userProfile?.personal_data.avatar || '/placeholder.png'" />
              <div v-if="isUploadingAvatar" class="avatar-upload-overlay">
                <VProgressCircular indeterminate color="primary" />
              </div>
            </VAvatar>
            <DropZone
              class="avatar-dropzone"
              :multiple="false"
              @file-added="handleAvatarUpload"
            />
            <VBtn icon variant="tonal" class="avatar-edit-button">
              <VIcon icon="tabler-pencil" />
            </VBtn>
          </div>
          <h5 class="text-h5 mt-4">
            {{ userProfile?.personal_data.nombre }} {{ userProfile?.personal_data.apellidos }}
          </h5>
          <span class="text-body-1">{{ userProfile?.personal_data.email }}</span>
        </VCardText>

        <VForm ref="profileForm" @submit.prevent="saveProfileData">
          <VCardText>
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="personalFormData.nombre"
                  label="Nombre"
                  :rules="[requiredValidator]"
                  class="mb-4"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="personalFormData.apellidos"
                  label="Apellidos"
                  :rules="[requiredValidator]"
                  class="mb-4"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="personalFormData.email"
                  label="Email"
                  type="email"
                  :rules="[requiredValidator, emailValidator]"
                  class="mb-4"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="personalFormData.telefono"
                  label="Teléfono"
                  :rules="[requiredValidator]"
                  class="mb-4"
                />
              </VCol>
            </VRow>
          </VCardText>

          <VDivider />

          <VCardText>
            <div class="d-flex align-center gap-2">
              <VSwitch v-model="isCompany" label="¿Eres una empresa?" />
            </div>

            <div v-if="isCompany" class="mt-4">
              <p class="text-sm-caption mb-4">
                Los campos marcados con (*) son obligatorios.
              </p>
              <VRow>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="companyFormData.nombre"
                    label="Nombre de la empresa *"
                    :rules="isCompany ? [requiredValidator] : []"
                    class="mb-4"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="companyFormData.cif_nif"
                    label="CIF / NIF *"
                    :rules="isCompany ? [requiredValidator] : []"
                    class="mb-4"
                  />
                </VCol>
                <VCol cols="12">
                  <VTextField
                    v-model="companyFormData.direccion"
                    label="Dirección *"
                    :rules="isCompany ? [requiredValidator] : []"
                    class="mb-4"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="companyFormData.cp"
                    label="C.P. *"
                    :rules="isCompany ? [requiredValidator] : []"
                    class="mb-4"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="companyFormData.persona_contacto"
                    label="Persona de contacto *"
                    :rules="isCompany ? [requiredValidator] : []"
                    class="mb-4"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="companyFormData.email_contacto"
                    label="Email de contacto *"
                    type="email"
                    :rules="isCompany ? [requiredValidator, emailValidator] : []"
                    class="mb-4"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="companyFormData.tel_contacto"
                    label="Tel. de contacto *"
                    :rules="isCompany ? [requiredValidator] : []"
                    class="mb-4"
                  />
                </VCol>
              </VRow>
            </div>
          </VCardText>

          <VCardText>
            <div class="d-flex gap-4">
              <VBtn color="primary" :loading="isSaving" type="submit">
                Guardar Cambios
              </VBtn>
              <VBtn variant="tonal" color="secondary" :disabled="isSaving" @click="isChangePasswordDialogVisible = true">
                Cambiar Contraseña
              </VBtn>
            </div>
          </VCardText>
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