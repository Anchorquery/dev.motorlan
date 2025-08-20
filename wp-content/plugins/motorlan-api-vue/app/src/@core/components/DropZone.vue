<script setup lang="ts">
import { useDropZone, useFileDialog, useObjectUrl } from '@vueuse/core'
import { computed } from 'vue'

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

const emit = defineEmits(['update:modelValue'])

const dropZoneRef = ref<HTMLDivElement>()

interface FileData {
  file: File
  url: string
}

const fileData = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
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

  if (props.multiple)
    fileData.value = [...fileData.value, ...newFiles]
  else
    fileData.value = newFiles
}

onChange((selectedFiles: any) => {
  if (!selectedFiles)
    return

  const newFiles = Array.from(selectedFiles).map((file: any) => ({
    file,
    url: useObjectUrl(file).value ?? '',
  }))

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
          <span class="text-disabled">or</span>

          <VBtn
            variant="tonal"
            size="small"
          >
            Browse Images
          </VBtn>
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
                <VCard :ripple="false">
                  <VCardText
                    class="d-flex flex-column"
                    @click.stop
                  >
                    <VImg
                      :src="item.url"
                      width="200px"
                      height="150px"
                      class="w-100 mx-auto"
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
                  <VCardActions>
                    <VBtn
                      variant="text"
                      block
                      @click.stop="removeFile(index)"
                    >
                      Remove File
                    </VBtn>
                  </VCardActions>
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
</style>
