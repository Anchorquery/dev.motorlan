<script setup lang="ts">
import { useDropZone, useFileDialog } from '@vueuse/core'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  multiple: {
    type: Boolean,
    default: true,
  },
  // Máximo de archivos (0 = sin límite). La galería de publicaciones admite 5.
  max: {
    type: Number,
    default: 0,
  },
})

const emit = defineEmits(['update:modelValue', 'file-added'])

const { t } = useI18n()
const { showToast } = useToast()

const dropZoneRef = ref<HTMLDivElement>()

interface FileData {
  file: File
  url: string
}

const fileData = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const { open, onChange } = useFileDialog({ accept: 'image/*', multiple: props.multiple })

// El diálogo filtra con accept="image/*", pero al arrastrar entraba cualquier archivo
// (PDF, vídeo...) y acababa guardado como "imagen".
const addFiles = (incoming: File[]) => {
  let files = incoming.filter(file => file.type.startsWith('image/'))
  if (files.length < incoming.length)
    showToast(t('dropzone.only_images', 'Solo se admiten imágenes (JPG, PNG, GIF o WebP).'), 'error')

  if (!files.length)
    return

  if (!props.multiple)
    files = files.slice(0, 1)

  if (props.multiple && props.max > 0) {
    const room = Math.max(0, props.max - fileData.value.length)
    if (files.length > room) {
      showToast(t('dropzone.max_files', { max: props.max }, `Máximo ${props.max} imágenes.`), 'warning')
      files = files.slice(0, room)
    }
    if (!files.length)
      return
  }

  const newFiles = files.map(file => ({
    file,
    url: URL.createObjectURL(file),
  }))

  files.forEach(file => emit('file-added', file))

  if (props.multiple) {
    fileData.value = [...fileData.value, ...newFiles]
  }
  else {
    // Liberar la previsualización anterior al sustituir la imagen
    ;(fileData.value as FileData[]).forEach(item => {
      if (item?.url?.startsWith('blob:'))
        URL.revokeObjectURL(item.url)
    })
    fileData.value = newFiles
  }
}

function onDrop(droppedFiles: File[] | null) {
  if (!droppedFiles)
    return

  addFiles(droppedFiles)
}

onChange((selectedFiles: FileList | null) => {
  if (!selectedFiles)
    return

  addFiles(Array.from(selectedFiles))
})

useDropZone(dropZoneRef, onDrop)

const removeFile = (index: number) => {
  const currentFiles = [...fileData.value]
  const removedFile = currentFiles.splice(index, 1)[0] as FileData
  
  // Revocar el URL si es un blob local para liberar memoria
  if (removedFile.url && removedFile.url.startsWith('blob:')) {
    URL.revokeObjectURL(removedFile.url)
  }

  fileData.value = currentFiles
}

const viewImage = (url: string) => {
  window.open(url, '_blank')
}

defineExpose({
  open,
})
</script>

<template>
  <div class="flex">
    <div class="w-full h-auto relative">
      <div
        ref="dropZoneRef"
        class="cursor-pointer"
        @click="() => open()"
      >
        <div
          v-if="fileData.length === 0"
          class="d-flex flex-column justify-center align-center gap-y-2 pa-6 drop-zone rounded"
        >
          <IconBtn
            variant="tonal"
            class="rounded-sm"
          >
            <VIcon icon="tabler-upload" />
          </IconBtn>
          <p class="text-body-1 text-medium-emphasis text-center mb-0">
            {{ t('dropzone.drag_or_tap', 'Arrastra o toca para subir imagen') }}
          </p>
        </div>

        <div
          v-else
          class="d-flex justify-center align-center gap-3 pa-8 drop-zone flex-wrap"
        >
          <VRow class="match-height w-100 mx-0">
            <template
              v-for="(item, index) in fileData as FileData[]"
              :key="index"
            >
              <VCol
                cols="12"
                sm="4"
              >
                  <VCard
                  :ripple="false"
                  class="position-relative overflow-hidden"
                  variant="outlined"
                >
                  <VCardText
                    class="pa-2 d-flex flex-column align-center"
                    @click.stop
                  >
                    <div class="w-100 position-relative rounded overflow-hidden mb-2 bg-background">
                       <VImg
                        :src="item.url"
                        aspect-ratio="1"
                        cover
                        class="w-100"
                      />
                      
                      <!-- Overlay Actions -->
                       <div class="image-actions d-flex justify-center align-center">
                        <IconBtn
                          color="white"
                          variant="text"
                          size="small"
                          class="me-1"
                          @click.stop="viewImage(item.url)"
                        >
                          <VIcon icon="tabler-eye" />
                          <VTooltip activator="parent">{{ t('dropzone.view', 'Ver') }}</VTooltip>
                        </IconBtn>
                        <IconBtn
                          color="error"
                          variant="text"
                          size="small"
                          @click.stop="removeFile(index)"
                        >
                          <VIcon icon="tabler-trash" />
                          <VTooltip activator="parent">{{ t('dropzone.remove', 'Eliminar') }}</VTooltip>
                        </IconBtn>
                      </div>
                    </div>
                    
                    <div class="text-center w-100">
                      <div class="text-caption font-weight-medium text-truncate">
                        {{ item.file?.name || t('dropzone.image', 'Imagen') }}
                      </div>
                      <div v-if="item.file?.size" class="text-caption text-medium-emphasis">
                        {{ (item.file.size / 1024).toFixed(1) }} KB
                      </div>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
            </template>
          </VRow>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.drop-zone {
  border: 1px dashed rgba(var(--v-theme-on-surface), var(--v-border-opacity));
}

.image-actions {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.4);
  opacity: 0;
  transition: opacity 0.2s ease-in-out;
  backdrop-filter: blur(2px);
}

.position-relative:hover .image-actions {
  opacity: 1;
}
</style>
