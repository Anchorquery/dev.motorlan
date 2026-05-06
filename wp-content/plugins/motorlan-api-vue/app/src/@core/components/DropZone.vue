<script setup lang="ts">
import { useDropZone, useFileDialog, useObjectUrl } from '@vueuse/core'
import { computed, ref } from 'vue'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  multiple: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['update:modelValue', 'file-added'])

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

function onDrop(DroppedFiles: File[] | null) {
  if (!DroppedFiles)
    return

  if (!props.multiple && DroppedFiles.length > 1) {
    // eslint-disable-next-line no-alert
    alert('Only one file is allowed')
    return
  }

  const newFiles = DroppedFiles.map(file => ({
    file,
    url: useObjectUrl(file).value ?? '',
  }))

  // Emitir evento file-added por cada archivo
  DroppedFiles.forEach(file => emit('file-added', file))

  if (props.multiple)
    fileData.value = [...fileData.value, ...newFiles]
  else
    fileData.value = newFiles
}

onChange((selectedFiles: FileList | null) => {
  if (!selectedFiles)
    return

  const filesArray = Array.from(selectedFiles)
  const newFiles = filesArray.map((file: File) => ({
    file,
    url: useObjectUrl(file).value ?? '',
  }))

  // Emitir evento file-added por cada archivo seleccionado
  filesArray.forEach(file => emit('file-added', file))

  if (props.multiple)
    fileData.value = [...fileData.value, ...newFiles]
  else
    fileData.value = newFiles
})

useDropZone(dropZoneRef, onDrop)

const removeFile = (index: number) => {
  const currentFiles = [...fileData.value]

  currentFiles.splice(index, 1)
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
          class="d-flex flex-column justify-center align-center gap-y-2 pa-12 drop-zone rounded"
        >
          <IconBtn
            variant="tonal"
            class="rounded-sm"
          >
            <VIcon icon="tabler-upload" />
          </IconBtn>
          <h4 class="text-h4">
            Drag and drop your image here.
          </h4>
        </div>

        <div
          v-else
          class="d-flex justify-center align-center gap-3 pa-8 drop-zone flex-wrap"
        >
          <VRow class="match-height w-100">
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
                        class="w-100 h-100"
                        bg-color="grey-lighten-4"
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
                          <VTooltip activator="parent">Ver</VTooltip>
                        </IconBtn>
                        <IconBtn
                          color="error"
                          variant="text"
                          size="small"
                          @click.stop="removeFile(index)"
                        >
                          <VIcon icon="tabler-trash" />
                          <VTooltip activator="parent">Eliminar</VTooltip>
                        </IconBtn>
                      </div>
                    </div>
                    
                    <div class="text-center w-100">
                      <div class="text-caption font-weight-medium text-truncate">
                        {{ item.file?.name || 'Imagen' }}
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
