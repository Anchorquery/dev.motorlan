<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import AppTextarea from '@/@core/components/app-form-elements/AppTextarea.vue'
import AppTextField from '@/@core/components/app-form-elements/AppTextField.vue'

interface Props {
  isDialogVisible: boolean
  publicationId: number | null
  publicationTitle: string
}

interface Emits {
  (e: 'update:isDialogVisible', value: boolean): void
  (e: 'submitted'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { t } = useI18n()

const subject = ref('')
const message = ref('')
const isLoading = ref(false)
const errorMsg = ref('')

const resetForm = () => {
  subject.value = 'Mensaje del Administrador - Motorlan'
  message.value = ''
  errorMsg.value = ''
}

// Watch dialog visibility to reset form
watch(() => props.isDialogVisible, (val) => {
  if (val) {
    resetForm()
  }
})

const closeDialog = () => {
  emit('update:isDialogVisible', false)
}

const submitContact = async () => {
  if (!message.value) {
    errorMsg.value = t('El mensaje es obligatorio')
    return
  }

  isLoading.value = true
  errorMsg.value = ''

  try {
    const { error } = await useApi(`/wp-json/motorlan/v1/admin/publications/${props.publicationId}/contact`, {
      method: 'POST',
      body: JSON.stringify({
        subject: subject.value,
        message: message.value
      })
    })

    if (error.value) {
      errorMsg.value = error.value.message || t('Error al enviar el mensaje')
    } else {
      emit('submitted')
      closeDialog()
    }
  } catch (e: any) {
    errorMsg.value = e.message || t('Error inesperado')
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="600"
    persistent
    @update:model-value="closeDialog"
  >
    <VCard class="motor-card-enhanced">
      <VCardTitle class="pa-4 d-flex justify-space-between align-center border-b">
        <span class="text-h6 text-premium-title">
          {{ t('Contactar al Publicador') }}
        </span>
        <VBtn icon variant="text" size="small" @click="closeDialog">
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>
      
      <VCardText class="pa-6">
        <div v-if="props.publicationTitle" class="mb-4 text-body-2 text-medium-emphasis">
          {{ t('Referencia a la publicación:') }} <strong>{{ props.publicationTitle }}</strong>
        </div>

        <VAlert
          v-if="errorMsg"
          color="error"
          variant="tonal"
          class="mb-4"
          closable
          @click:close="errorMsg = ''"
        >
          {{ errorMsg }}
        </VAlert>

        <VRow>
          <VCol cols="12">
            <AppTextField
              v-model="subject"
              :label="t('Asunto')"
              placeholder="Asunto del mensaje"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="message"
              :label="t('Mensaje')"
              placeholder="Escribe tu mensaje aquí..."
              auto-grow
              rows="4"
            />
          </VCol>
        </VRow>
      </VCardText>
      
      <VCardActions class="pa-6 pt-0 justify-end">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="closeDialog"
          :disabled="isLoading"
        >
          {{ t('Cancelar') }}
        </VBtn>
        <VBtn
          color="primary"
          @click="submitContact"
          :loading="isLoading"
        >
          {{ t('Enviar Mensaje') }}
          <template #append>
            <VIcon icon="tabler-send" class="ms-1" />
          </template>
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
