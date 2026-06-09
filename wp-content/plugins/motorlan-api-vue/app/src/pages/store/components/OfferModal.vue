<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'

const props = defineProps<{ publicacionId: number }>()

const emit = defineEmits({
  close: () => true,
})

const { t } = useI18n()
const offer = ref<number | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const remainingOffers = ref(10)

async function submitOffer() {
  if (!offer.value || offer.value <= 0) {
    error.value = t('offer_modal.invalid_amount')
    return
  }

  loading.value = true
  error.value = null

  try {
    const url = createUrl(`/wp-json/motorlan/v1/publicaciones/${props.publicacionId}/offers`)
    const { data: response, error: apiError } = await useApi(url).post({
      amount: offer.value,
    }).json()

    if (apiError.value)
      throw apiError.value

    if (response.value?.remaining_offers !== undefined)
      remainingOffers.value = Number(response.value.remaining_offers)

    emit('close')
  }
  catch (e: any) {
    error.value = e?.data?.message || e?.message || t('offer_modal.send_error')
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <VDialog
    max-width="500px"
    persistent
  >
    <VCard>
      <VCardTitle class="d-flex align-center">
        {{ t('offer_modal.title') }}
        <VSpacer />
        <VBtn
          icon
          @click="$emit('close')"
        >
          <VIcon>mdi-close</VIcon>
        </VBtn>
      </VCardTitle>
      <VCardText>
        <p>{{ t('offer_modal.intro') }}</p>
        <p class="text-caption mb-4">
          {{ t('offer_modal.info') }}
        </p>
        <VTextField
          v-model.number="offer"
          :label="t('offer_modal.amount_label')"
          type="number"
          suffix="EUR"
          autofocus
          :error-messages="error"
        />
        <p class="text-caption">
          {{ t('offer_modal.remaining_offers', { count: remainingOffers }) }}
        </p>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          color="primary"
          :loading="loading"
          @click="submitOffer"
        >
          {{ t('offer_modal.submit') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
