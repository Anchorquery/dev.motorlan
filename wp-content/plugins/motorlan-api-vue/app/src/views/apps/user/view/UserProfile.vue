<script setup lang="ts">
import { ref, onMounted } from 'vue'
import type { ComponentPublicInstance } from 'vue'
import type { UserProfile } from '../types'
import api from '@/services/api'
import { requiredValidator, emailValidator } from '@/@core/utils/validators'
import { VForm } from 'vuetify/components/VForm'
import DropZone from '@/@core/components/DropZone.vue'

const isEditingPersonal = ref(false)
const personalForm = ref<VForm | null>(null)
const dropZoneRef = ref<ComponentPublicInstance<{ open: () => void }> | null>(null)
const isLoading = ref(false)

const userProfile = ref<UserProfile>({
  personal_data: {
    nombre: '',
    apellidos: '',
    email: '',
    telefono: '',
    avatar: '',
  },
  company_data: {
    nombre: '',
    direccion: '',
    cp: '',
    persona_contacto: '',
    email_contacto: '',
    tel_contacto: '',
    cif_nif: '',
  },
})

// Cloned data for editing
const personalFormData = ref(JSON.parse(JSON.stringify(userProfile.value.personal_data)))

const handleAvatarUpload = async (files: File[]) => {
  if (files.length > 0) {
    const file = files[0]
    const formData = new FormData()
    formData.append('avatar', file)

    isLoading.value = true
    try {
      const response = await api('/me/avatar', {
        method: 'POST',
        body: formData,
      })
      if (response && response.avatar)
        userProfile.value.personal_data.avatar = response.avatar
    }
    catch (error) {
      console.error('Error uploading avatar:', error)
    }
    finally {
      isLoading.value = false
    }
  }
}

const fetchUserProfile = async () => {
  isLoading.value = true
  try {
    const data = await api('/wp-json/motorlan/v1/profile')
    if (data) {
      userProfile.value = data
      personalFormData.value = JSON.parse(JSON.stringify(data.personal_data))
    }
  }
  catch (error) {
    console.error('Error fetching user profile:', error)
  }
  finally {
    isLoading.value = false
  }
}

onMounted(fetchUserProfile)

const toggleEditPersonal = () => {
  isEditingPersonal.value = !isEditingPersonal.value
  if (isEditingPersonal.value) {
    // When editing starts, clone the data
    personalFormData.value = JSON.parse(JSON.stringify(userProfile.value.personal_data))
  }
}

const savePersonalData = async () => {
  if (personalForm.value) {
    const { valid } = await personalForm.value.validate()
    if (valid) {
      isLoading.value = true
      try {
        const response = await api('/wp-json/motorlan/v1/profile', {
          method: 'POST',
          body: {
            personal_data: personalFormData.value,
            company_data: userProfile.value.company_data,
          },
        })
        // On success, update the original state
        userProfile.value.personal_data = JSON.parse(JSON.stringify(personalFormData.value))
        toggleEditPersonal()
      }
      catch (error) {
        console.error('Error saving personal data:', error)
      }
      finally {
        isLoading.value = false
      }
    }
  }
}

const handleFieldClick = () => {
  if (!isEditingPersonal.value) {
    toggleEditPersonal()
  }
}
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard :loading="isLoading" class="profile-card">
        <VCardText class="d-flex flex-column align-center">
          <VAvatar
            size="120"
            class="avatar-center"
          >
            <VImg :src="userProfile.personal_data.avatar || '/placeholder.png'" />
          </VAvatar>
          <VBtn v-if="dropZoneRef" icon variant="tonal" class="avatar-edit-button" @click="dropZoneRef.open">
            <VIcon icon="tabler-pencil" />
          </VBtn>
          <h5 class="text-h5 mt-4">
            {{ userProfile.personal_data.nombre }} {{ userProfile.personal_data.apellidos }}
          </h5>
          <span class="text-body-1">{{ userProfile.personal_data.email }}</span>
        </VCardText>

        <VCardText>
          <VForm ref="personalForm" @submit.prevent="savePersonalData">
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="personalFormData.nombre"
                  label="Nombre"
                  :readonly="!isEditingPersonal"
                  :rules="[requiredValidator]"
                  class="mb-4"
                  @click="handleFieldClick"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="personalFormData.apellidos"
                  label="Apellidos"
                  :readonly="!isEditingPersonal"
                  :rules="[requiredValidator]"
                  class="mb-4"
                  @click="handleFieldClick"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="personalFormData.email"
                  label="Email"
                  type="email"
                  :readonly="!isEditingPersonal"
                  :rules="[requiredValidator, emailValidator]"
                  class="mb-4"
                  @click="handleFieldClick"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="personalFormData.telefono"
                  label="Teléfono"
                  :readonly="!isEditingPersonal"
                  :rules="[requiredValidator]"
                  class="mb-4"
                  @click="handleFieldClick"
                />
              </VCol>
            </VRow>
            <div class="d-flex gap-4 mt-4">
              <VBtn v-if="!isEditingPersonal" color="primary" @click="toggleEditPersonal">
                Editar tus datos
              </VBtn>
              <VBtn v-else color="primary" type="submit">
                Guardar
              </VBtn>
              <VBtn v-if="isEditingPersonal" color="secondary" @click="toggleEditPersonal">
                Cancelar
              </VBtn>
              <VBtn variant="tonal" color="secondary">
                Cambiar Contraseña
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
  <div style="display: none;">
    <DropZone ref="dropZoneRef" @files-dropped="handleAvatarUpload" />
  </div>
</template>

<style lang="scss" scoped>
.profile-card {
  position: relative;
  padding-top: 60px;
}

.avatar-center {
  position: absolute;
  top: -50px;
  left: 50%;
  transform: translateX(-50%);
  border: 4px solid white;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.avatar-edit-button {
  position: absolute;
  top: 50px;
  left: calc(50% + 40px);
  transform: translate(-50%, -50%);
}
</style>
