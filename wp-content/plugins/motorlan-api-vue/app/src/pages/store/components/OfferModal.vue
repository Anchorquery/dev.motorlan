<script setup lang="ts">
import { ref } from 'vue'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'
import { useUserStore } from '@/@core/stores/user'

const props = defineProps<{
  publicacionId: number
}>()

const emit = defineEmits({
  close: () => true,
})

const userStore = useUserStore()
const offer = ref<number | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const remainingOffers = ref(10) // Esto debería venir de la API

async function submitOffer() {
  if (!offer.value || offer.value <= 0) {
    error.value = 'Por favor, introduce una oferta válida.'
    return
  }

  loading.value = true
  error.value = null

  try {
    const url = createUrl(`/wp-json/motorlan/v1/publicaciones/${props.publicacionId}/offers`)
    await useApi(url).post({
      amount: offer.value,
      user_id: userStore.user?.id,
    })
    emit('close')
  }
  catch (e: any) {
    error.value = e.data?.message || 'Ha ocurrido un error al enviar la oferta.'
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
        <p>¿Te interesa? Envía tu mejor oferta</p>
        <VTextField
          v-model.number="offer"
          label="Tu oferta"
          type="number"
          suffix="€"
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