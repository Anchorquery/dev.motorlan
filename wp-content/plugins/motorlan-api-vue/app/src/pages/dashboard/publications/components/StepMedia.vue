<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import DropZone from '@/@core/components/DropZone.vue'

import type { PublicationFile } from '@/composables/usePublicationForm'

const props = defineProps<{
  formState: any // Keeping this as any for now or define full state type if possible, but focusing on local state first
}>()

const emit = defineEmits(['update:formState'])

const { t } = useI18n()

// Local state for DropZones - we need to sync this with formState
// DropZone expects an array of objects with { file, url, id }
const mainImage = ref<PublicationFile[]>([])
const galleryImages = ref<PublicationFile[]>([])

// Init from props
watch(() => props.formState.acf.motor_image, (val) => {
    if (val) {
        // Wrap in array if single object, or if it's already an array (shouldn't be for main image but just in case)
        mainImage.value = Array.isArray(val) ? val : [val]
    } else {
        mainImage.value = []
    }
}, { immediate: true })

watch(() => props.formState.acf.motor_gallery, (val) => {
    if (val && Array.isArray(val)) {
        galleryImages.value = val
    } else {
        galleryImages.value = []
    }
}, { immediate: true })

// Sync back to form state on change
watch(mainImage, (newVal) => {
    // Determine the single object for formState
    const val = newVal.length > 0 ? newVal[0] : null
    emit('update:formState', {
        ...props.formState,
        acf: {
            ...props.formState.acf,
            motor_image: val
        }
    })
})

watch(galleryImages, (newVal) => {
    emit('update:formState', {
        ...props.formState,
        acf: {
            ...props.formState.acf,
            motor_gallery: newVal
        }
    })
})

</script>

<template>
  <VRow>
    <VCol cols="12">
        <h3 class="text-h5 font-weight-bold mb-4">{{ t('add_publication.media.title', 'Multimedia') }}</h3>
    </VCol>
    <VCol cols="12" md="6">
      <VLabel class="mb-2 text-body-2 font-weight-medium">{{ t('add_publication.media.main_image') }} *</VLabel>
      <DropZone
        v-model="mainImage"
        :multiple="false"
        class="mb-4"
      />
      <!-- Hidden Input for Validation -->
      <VInput
          :model-value="mainImage"
          :rules="[(v: any) => v.length > 0 || t('add_publication.validation.main_image_required', 'La imagen principal es obligatoria')]"
          style="display: none;"
      >
      </VInput>
      <div v-if="mainImage.length === 0" class="text-caption text-error mb-2">
           {{ t('add_publication.validation.main_image_required', 'La imagen principal es obligatoria') }}
      </div>
    </VCol>
    <VCol cols="12" md="6">
      <VLabel class="mb-2 text-body-2 font-weight-medium">{{ t('add_publication.media.gallery') }}</VLabel>
      <DropZone
        v-model="galleryImages"
        multiple
      />
    </VCol>
  </VRow>
</template>
