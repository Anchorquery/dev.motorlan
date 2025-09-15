<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import type { Compra } from '@/interfaces/compra'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'

const { t } = useI18n()
const router = useRouter()

const headers = [
  { title: 'Publicacion', key: 'publicacion' },
  { title: 'Vendedor', key: 'vendedor' },
  { title: 'Precio', key: 'precio' },
  { title: 'Estado', key: 'estado' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const searchQuery = ref('')
const selectedStatus = ref()
const status = ref([])

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()

// Update data table options
const updateOptions = (options: any) => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const { data: purchasesData, execute: fetchPurchases, isFetching: isTableLoading } = useApi<any>(createUrl('/wp-json/motorlan/v1/purchases',
  {
    query: {
      search: searchQuery,
      page,
      per_page: itemsPerPage,
      orderby: sortBy,
      order: orderBy,
    },
  },
))

const purchases = computed((): Compra[] => (purchasesData.value?.data || []).filter(Boolean))
const totalPurchases = computed(() => purchasesData.value?.pagination.total || 0)
</script>

<template>
  <div>
    <VCard
      :title="t('publication_list.filters')"
      class="mb-6"
    >
      <VCardText>
        <VRow>
          <!-- 👉 Select Status -->
          <VCol
            cols="12"
            sm="4"
          >
            <AppSelect
              v-model="selectedStatus"
              :placeholder="t('publication_list.status')"
              :items="status"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <div class="d-flex flex-wrap gap-4 ma-6">
        <div class="d-flex align-center">
          <!-- 👉 Search  -->
          <AppTextField
            v-model="searchQuery"
            :placeholder="t('publication_list.search_publication')"
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

      <VDivider class="mt-4" />

      <!-- 👉 Datatable  -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :headers="headers"
        :items="purchases"
        :items-length="totalPurchases"
        :loading="isTableLoading"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
       <template #loading>
         <VProgressLinear
           height="6"
           indeterminate
           color="primary"
         />
       </template>
       <!-- publicacion  -->
       <template #item.publicacion="{ item }: { item: Compra }">
         <div class="d-flex align-center gap-x-4">
            <VAvatar
              v-if="item.acf.motor.imagen_destacada"
              size="38"
              variant="tonal"
              rounded
            />
            <div class="d-flex flex-column">
              <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.acf.motor.title }}</span>
              <span class="text-body-2">{{ item.acf.motor.acf.marca }}</span>
            </div>
          </div>
        </template>

        <!-- vendedor -->
        <template #item.vendedor="{ item }: { item: Compra }">
          <span class="text-body-1 text-high-emphasis">{{ item.acf.vendedor.user_nicename }}</span>
        </template>

        <!-- precio -->
        <template #item.precio="{ item }: { item: Compra }">
          <span class="text-body-1 text-high-emphasis">{{ item.acf.precio_compra }}</span>
        </template>

        <!-- estado -->
        <template #item.estado="{ item }: { item: Compra }">
          <VChip
            :color="item.acf.estado === 'completed' ? 'success' : 'warning'"
            density="default"
            label
            size="small"
          >
            {{ item.acf.estado }}
          </VChip>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }: { item: Compra }">
          <IconBtn @click="router.push(`/apps/purchases/edit/${item.uuid}`)">
            <VIcon icon="tabler-eye" />
          </IconBtn>
        </template>

        <!-- pagination -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalPurchases"
          />
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
