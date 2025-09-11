<script setup lang="ts">
import { ref, onMounted } from 'vue'
import type { UserProfile } from '../types'
import { requiredValidator, emailValidator } from '@/@core/utils/validators'
import { VForm } from 'vuetify/components/VForm'
import { useApi } from '@/composables/useApi'

const isEditingCompany = ref(false)
const companyForm = ref<VForm | null>(null)
const isLoading = ref(false)

const userProfile = ref<UserProfile>({
  personal_data: {
    nombre: '',
    apellidos: '',
    email: '',
    telefono: '',
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
const companyFormData = ref(JSON.parse(JSON.stringify(userProfile.value.company_data)))

const fetchUserProfile = async () => {
  isLoading.value = true
  try {
    const { data, error } = await useApi<UserProfile>('/me')
    if (error.value) throw error.value
    if (data.value) {
      userProfile.value = data.value
      companyFormData.value = JSON.parse(JSON.stringify(data.value.company_data))
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

const toggleEditCompany = () => {
  isEditingCompany.value = !isEditingCompany.value
  if (isEditingCompany.value) {
    // When editing starts, clone the data
    companyFormData.value = JSON.parse(JSON.stringify(userProfile.value.company_data))
  }
}

const saveCompanyData = async () => {
  if (companyForm.value) {
    const { valid } = await companyForm.value.validate()
    if (valid) {
      isLoading.value = true
      try {
        const { data, error } = await useApi('/me/company', {
          method: 'PUT',
          body: companyFormData.value,
        })
        if (error.value) throw error.value
        // On success, update the original state
        userProfile.value.company_data = JSON.parse(JSON.stringify(companyFormData.value))
        toggleEditCompany()
      }
      catch (error) {
        console.error('Error saving company data:', error)
      }
      finally {
        isLoading.value = false
      }
    }
  }
}
</script>

<template>
  <VRow>
    <!-- Datos de Empresa -->
    <VCol cols="12">
      <VCard :loading="isLoading">
        <VCardTitle>Datos de empresa</VCardTitle>
        <VCardText>
          <VForm ref="companyForm" @submit.prevent="saveCompanyData">
            <VTextField
              v-model="companyFormData.nombre"
              label="Nombre"
              :readonly="!isEditingCompany"
              :rules="[requiredValidator]"
              class="mb-4"
            />
            <VTextField
              v-model="companyFormData.direccion"
              label="Dirección"
              :readonly="!isEditingCompany"
              :rules="[requiredValidator]"
              class="mb-4"
            />
            <VTextField
              v-model="companyFormData.cp"
              label="C.P."
              :readonly="!isEditingCompany"
              :rules="[requiredValidator]"
              class="mb-4"
            />
            <VTextField
              v-model="companyFormData.persona_contacto"
              label="Persona de contacto"
              :readonly="!isEditingCompany"
              :rules="[requiredValidator]"
              class="mb-4"
            />
            <VTextField
              v-model="companyFormData.email_contacto"
              label="Email de contacto"
              type="email"
              :readonly="!isEditingCompany"
              :rules="[requiredValidator, emailValidator]"
              class="mb-4"
            />
            <VTextField
              v-model="companyFormData.tel_contacto"
              label="Tel. de contacto"
              :readonly="!isEditingCompany"
              :rules="[requiredValidator]"
              class="mb-4"
            />
            <VTextField
              v-model="companyFormData.cif_nif"
              label="CIF / NIF"
              :readonly="!isEditingCompany"
              :rules="[requiredValidator]"
              class="mb-4"
            />
            <div class="d-flex gap-4">
              <VBtn v-if="!isEditingCompany" color="primary" @click="toggleEditCompany">
                Editar
              </VBtn>
              <VBtn v-else color="primary" type="submit">
                Guardar
              </VBtn>
              <VBtn v-if="isEditingCompany" color="secondary" @click="toggleEditCompany">
                Cancelar
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>