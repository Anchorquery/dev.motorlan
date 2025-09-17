<script setup lang="ts">
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'

const { data: offers, isFetching } = await useApi<any[]>(createUrl('/wp-json/motorlan/v1/offers/sent')).get().json()
</script>

<template>
  <VCard title="Ofertas Enviadas">
    <VCardText>
      <VTable
        v-if="offers && offers.length"
        :items="offers"
        class="text-no-wrap"
      >
        <thead>
          <tr>
            <th>
              Publicación
            </th>
            <th>
              Monto
            </th>
            <th>
              Fecha
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="offer in offers"
            :key="offer.publication_id + offer.offer_date"
          >
            <td>
              {{ offer.publication_title }}
            </td>
            <td>
              {{ offer.offer_amount }}€
            </td>
            <td>
              {{ new Date(offer.offer_date).toLocaleDateString() }}
            </td>
          </tr>
        </tbody>
      </VTable>
      <p v-else-if="isFetching">
        Cargando ofertas...
      </p>
      <p v-else>
        No has enviado ninguna oferta.
      </p>
    </VCardText>
  </VCard>
</template>