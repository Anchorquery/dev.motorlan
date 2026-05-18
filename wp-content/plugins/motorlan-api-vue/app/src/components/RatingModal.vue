<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'

const props = defineProps<{
  modelValue: boolean
  purchaseUuid: string
  targetRole: 'seller' | 'buyer'
  targetName?: string
}>()

const emit = defineEmits(['update:modelValue', 'success'])

const rating = ref(0)
const comment = ref('')
const isLoading = ref(false)
const error = ref<string | null>(null)
const { t } = useI18n()

const isFormValid = computed(() => {
  return rating.value > 0 && comment.value.trim().length > 0
})

const submitReview = async () => {
  if (!isFormValid.value) return

  isLoading.value = true
  error.value = null

  try {
    const { response, error: apiError } = await useApi<any>(createUrl('/wp-json/motorlan/v1/reviews')).post({
      purchase_uuid: props.purchaseUuid,
      rating: rating.value,
      comment: comment.value,
      target_role: props.targetRole
    }).json()

    if (apiError.value) {
      error.value = apiError.value.value?.message || apiError.value.data?.message || t('rating_modal.submit_error')
      return
    }

    emit('success')
    emit('update:modelValue', false)
  } catch (e) {
    console.error(e)
    error.value = t('rating_modal.connection_error')
  } finally {
    isLoading.value = false
  }
}

const handleClose = () => {
  emit('update:modelValue', false)
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="500"
    persistent
    @update:model-value="handleClose"
  >
    <VCard class="rounded-xl elevation-10">
      <VCardTitle class="d-flex justify-space-between align-center py-4 px-5 bg-surface border-b">
        <span class="text-h6 font-weight-bold ml-1">{{ targetRole === 'seller' ? t('rating_modal.title_seller') : t('rating_modal.title_buyer') }}</span>
        <VBtn icon="tabler-x" variant="text" size="small" @click="handleClose" />
      </VCardTitle>

      <VCardText class="pt-6 px-5 pb-2">
        <div class="text-center mb-6">
          <p class="text-body-1 mb-2">{{ targetName ? t('rating_modal.experience_with', { name: targetName }) : t('rating_modal.experience_default') }}</p>
          
          <VRating
            v-model="rating"
            hover
            color="warning"
            active-color="warning"
            size="48"
            density="compact"
            class="d-inline-flex"
          />
          <div class="text-caption text-medium-emphasis mt-1" v-if="rating > 0">
            {{ t('rating_modal.stars', { rating }) }}
          </div>
        </div>

        <VTextarea
          v-model="comment"
          :label="t('rating_modal.comment_label')"
          :placeholder="t('rating_modal.comment_placeholder')"
          variant="outlined"
          auto-grow
          rows="3"
          counter="1000"
          :rules="[v => !!v || t('rating_modal.comment_required'), v => v.length <= 1000 || t('rating_modal.max_chars')]"
        ></VTextarea>

        <VAlert
          v-if="error"
          type="error"
          variant="tonal"
          density="compact"
          class="mt-4 mb-2"
          closable
          @click:close="error = null"
        >
          {{ error }}
        </VAlert>
      </VCardText>

      <VCardActions class="px-5 pb-5 pt-2">
        <VSpacer />
        <VBtn
          variant="text"
          color="medium-emphasis"
          @click="handleClose"
          :disabled="isLoading"
        >
          {{ t('rating_modal.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitReview"
          :loading="isLoading"
          :disabled="!isFormValid"
          class="px-6"
        >
          {{ t('rating_modal.submit') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
