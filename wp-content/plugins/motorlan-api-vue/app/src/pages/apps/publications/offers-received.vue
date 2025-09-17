<script setup lang="ts">
// @ts-nocheck
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import { debounce } from '@/utils/debounce'

const { t } = useI18n()

const headers = [
  { title: 'Publicación', key: 'publication_title' },
  { title: 'Ofertante', key: 'user_name' },
  { title: 'Monto', key: 'offer_amount' },
  { title: 'Fecha', key: 'offer_date' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const searchQuery = ref('')
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()

const updateOptions = (options: any) => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const apiUrl = computed(() => {
  const params = new URLSearchParams()
  if (searchQuery.value)
    params.append('search', searchQuery.value)
  if (page.value)
    params.append('page', page.value.toString())
  if (itemsPerPage.value)
    params.append('per_page', itemsPerPage.value.toString())
  if (sortBy.value)
    params.append('orderby', sortBy.value)
  if (orderBy.value)
    params.append('order', orderBy.value)

  return `/wp-json/motorlan/v1/offers/received?${params.toString()}`
})

const { data: offersData, execute: fetchOffers, isFetching: isTableLoading } = useApi<any>(apiUrl, { immediate: false }).get().json()
const isSearching = ref(false)

const debouncedFetch = debounce(async () => {
  isSearching.value = true
  await fetchOffers()
  isSearching.value = false
}, 300)

watch(
  [searchQuery, page, itemsPerPage, sortBy, orderBy],
  () => {
    debouncedFetch()
  },
  { deep: true },
)

onMounted(() => {
  fetchOffers()
})

const offers = computed(() => (offersData.value?.data || offersData.value || []).filter(Boolean))
const totalOffers = computed(() => (offersData.value?.pagination?.total) || 0)

const updateOfferStatus = async (offerId: number, status: 'accepted' | 'rejected') => {
  try {
    await useApi(`/wp-json/motorlan/v1/offers/${offerId}/status`).post({ status }).execute()
    fetchOffers()
  }
  catch (error) {
    console.error(error)
  }
}
</script>

<template>
  <VCard :title="t('Ofertas Recibidas')">
    <VCardText>
      <div class="d-flex flex-wrap gap-4">
        <div class="d-flex align-center">
          <AppTextField
            v-model="searchQuery"
            placeholder="Buscar oferta..."
            style="inline-size: 200px;"
            class="me-3"
          />
        </div>
        <VSpacer />
        <div class="d-flex gap-4 flex-wrap align-center">
          <AppSelect
            v-model="itemsPerPage"
            :items="[5, 10, 20, 25, 50]"
          />
        </div>
      </div>
    </VCardText>

    <VDivider class="mt-4" />

    <VDataTableServer
      v-model:items-per-page="itemsPerPage"
      v-model:page="page"
      :headers="headers"
      :items="offers"
      :items-length="totalOffers"
      :loading="isTableLoading || isSearching"
      class="text-no-wrap"
      @update:options="updateOptions"
    >
      <template #item.offer_amount="{ item }">
        <span>{{ item.offer_amount }}€</span>
      </template>

      <template #item.offer_date="{ item }">
        <span>{{ new Date(item.offer_date).toLocaleDateString() }}</span>
      </template>

      <template #item.actions>
        <IconBtn>
          <VIcon icon="tabler-dots-vertical" />
          <VMenu activator="parent">
            <VList>
              <VListItem
                value="accept"
                prepend-icon="tabler-check"
                @click="updateOfferStatus(item.id, 'accepted')"
              >
                Aceptar
              </VListItem>
              <VListItem
                value="reject"
                prepend-icon="tabler-x"
                @click="updateOfferStatus(item.id, 'rejected')"
              >
                Rechazar
              </VListItem>
            </VList>
          </VMenu>
        </IconBtn>
      </template>

      <template #bottom>
        <TablePagination
          v-model:page="page"
          :items-per-page="itemsPerPage"
          :total-items="totalOffers"
        />
      </template>
    </VDataTableServer>
  </VCard>
</template>