<script setup lang="ts">
import { ref } from 'vue'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'

const props = defineProps<{ publicacionId: number }>()

const emit = defineEmits({
  close: () => true,
})

const offer = ref<number | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const remainingOffers = ref(10)

async function submitOffer() {
  if (!offer.value || offer.value <= 0) {
    error.value = 'Ingresa un monto de oferta valido.'
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
    error.value = e?.data?.message || e?.message || 'Ocurrio un error al enviar la oferta.'
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
        Hacer una oferta
        <VSpacer />
        <VBtn
          icon
          @click="$emit('close')"
        >
          <VIcon>mdi-close</VIcon>
        </VBtn>
      </VCardTitle>
      <VCardText>
        <p>Te interesa? Envia tu mejor oferta.</p>
        <p class="text-caption mb-4">
          Si el vendedor acepta, tendras 24 horas para confirmar y completar la compra.
        </p>
        <VTextField
          v-model.number="offer"
          label="Tu oferta"
          type="number"
          suffix="EUR"
          autofocus
          :error-messages="error"
        />
        <p class="text-caption">
          {{ remainingOffers }} ofertas restantes para hoy
        </p>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          color="primary"
          :loading="loading"
          @click="submitOffer"
        >
          Enviar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
