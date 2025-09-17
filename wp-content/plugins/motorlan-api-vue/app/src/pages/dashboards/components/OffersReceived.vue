<script setup lang="ts">
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'

const { data: offers, isFetching } = await useApi<any[]>(createUrl('/wp-json/motorlan/v1/offers/received')).get().json()
</script>

<template>
  <VCard title="Ofertas Recibidas">
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
              Ofertante
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
            :key="offer.publication_id + offer.user_name + offer.offer_date"
          >
            <td>
              {{ offer.publication_title }}
            </td>
            <td>
              {{ offer.user_name }}
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
        No has recibido ninguna oferta.
      </p>
    </VCardText>
  </VCard>
</template>