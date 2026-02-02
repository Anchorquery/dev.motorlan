<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppTextField from '@/@core/components/app-form-elements/AppTextField.vue'

import type { PublicationFormState } from '@/composables/usePublicationForm'

const props = defineProps<{
  formState: PublicationFormState
}>()

const emit = defineEmits(['update:formState'])

const { t } = useI18n()

// Computed proxy for docs array
const docs = computed(() => props.formState.acf.documentacion_adicional || [])

const addDocument = () => {
    if (docs.value.length < 5) {
        const newDoc = { nombre: '', archivo: null }
        const newDocs = [...docs.value, newDoc]
        updateDocs(newDocs)
    }
}

const removeDocument = (index: number) => {
    const newDocs = [...docs.value]
    newDocs.splice(Number(index), 1)
    updateDocs(newDocs)
}

const updateDocs = (newDocs: any[]) => {
    emit('update:formState', {
        ...props.formState,
        acf: {
            ...props.formState.acf,
            documentacion_adicional: newDocs
        }
    })
}

const handleFileUpload = (event: Event, index: number) => {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]
    
    // We need to mutate the specific item in the array, then emit the whole state
    const newDocs = [...docs.value]
    newDocs[index] = { ...newDocs[index], archivo: file } // Store the File object
    updateDocs(newDocs)
  }
}

</script>

<template>
  <VRow>
    <VCol cols="12">
        <h3 class="text-h5 font-weight-bold mb-4">{{ t('add_publication.documentacion.title', 'Documentación Adicional') }}</h3>
        <p class="text-body-2 text-medium-emphasis mb-6">
            {{ t('add_publication.documentacion.subtitle', 'Adjunta fichas técnicas, planos o manuales (PDF, JPG, PNG). Máximo 5 archivos.') }}
        </p>
    </VCol>

    <VCol cols="12">
         <div v-if="docs.length === 0" class="text-center py-8 border-dashed rounded mb-4">
             <VIcon icon="tabler-files" size="48" class="text-disabled mb-2" />
             <p class="text-body-1 text-disabled">
                 {{ t('add_publication.documentacion.no_docs', 'No hay documentos adjuntos.') }}
             </p>
         </div>

         <div
            v-for="(doc, index) in docs"
            :key="index"
            class="d-flex gap-4 mb-4 align-center pa-4 bg-var-theme-background surface-variant rounded border"
          >
             <div class="flex-grow-1">
                 <AppTextField
                  v-model="doc.nombre"
                  :label="t('add_publication.documentacion.doc_name', 'Nombre del documento')"
                  density="compact"
                  hide-details
                  variant="outlined"
                  class="mb-2"
                />
                
                <div class="d-flex align-center gap-2">
                    <VBtn
                        variant="tonal"
                        size="small"
                        prepend-icon="tabler-upload"
                        color="primary"
                        @click="(($refs[`fileInput${index}`] as any)[0] as HTMLInputElement).click()"
                    >
                        {{ doc.archivo ? (('name' in doc.archivo ? doc.archivo.name : '') || ('filename' in doc.archivo ? doc.archivo.filename : '') || 'Archivo seleccionado') : 'Subir archivo' }}
                    </VBtn>
                    <input
                        type="file"
                        class="d-none"
                        :ref="`fileInput${index}`"
                        @change="(e) => handleFileUpload(e, index)"
                    />
                    <span v-if="doc.archivo" class="text-caption text-success">
                        <VIcon icon="tabler-check" size="16" start /> Listo
                    </span>
                </div>
             </div>

            <VBtn
              icon
              variant="text"
              color="error"
              size="small"
              @click="removeDocument(index)"
            >
              <VIcon icon="tabler-trash" size="20" />
            </VBtn>
          </div>

        <VBtn
            v-if="docs.length < 5"
            variant="outlined"
            color="primary"
            prepend-icon="tabler-plus"
            @click="addDocument"
            class="w-100 mt-2 dashed-border-btn"
            style="border-style: dashed;"
        >
            {{ t('add_publication.documentacion.add_btn', 'Añadir Documento') }}
        </VBtn>
    </VCol>
  </VRow>
</template>
