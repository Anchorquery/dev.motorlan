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

onChange((selectedFiles: any) => {
  if (!selectedFiles)
    return

  const filesArray = Array.from(selectedFiles)
  const newFiles = filesArray.map((file: any) => ({
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
              v-for="(item, index) in fileData"
              :key="index"
            >
              <VCol
                cols="12"
                sm="4"
              >
                <VCard
                  :ripple="false"
                  style="position: relative;"
                >
                  <VCardText
                    class="d-flex flex-column"
                    @click.stop
                  >
                    <VImg
                      :src="item.url"
                      width="150px"
                      height="100px"
                      class="mx-auto"
                      cover
                    />
                    <div class="mt-2">
                      <span class="clamp-text text-wrap">
                        {{ item.file?.name || 'Image' }}
                      </span>
                      <span v-if="item.file?.size">
                        {{ item.file.size / 1000 }} KB
                      </span>
                    </div>
                  </VCardText>
                  <div class="image-actions">
                    <IconBtn
                      icon="tabler-eye"
                      @click.stop="viewImage(item.url)"
                    />
                    <IconBtn
                      icon="tabler-x"
                      @click.stop="removeFile(index)"
                    />
                  </div>
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
  right: 0;
  background-color: rgba(0, 0, 0, 0.5);
  border-radius: 0 0 0 6px;
  padding: 2px;
  display: flex;
}
</style>
