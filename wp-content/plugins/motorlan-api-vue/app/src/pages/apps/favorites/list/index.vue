<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { Publicacion } from '../../../../../interfaces/publicacion'

const { t } = useI18n()

const headers = [
  { title: 'Publicacion', key: 'publicacion' },
  { title: 'Referencia', key: 'referencia' },
  { title: 'Precio', key: 'precio' },
  { title: 'Estado', key: 'status' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const searchQuery = ref('')

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

const { data: publicationsData, execute: fetchPublications } = await useApi<any>(createUrl('/wp-json/motorlan/v1/favorites',
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

const publications = computed((): Publicacion[] => (publicationsData.value?.data || []).filter(Boolean))
const totalPublications = computed(() => publicationsData.value?.pagination.total || 0)
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
        :items="publications"
        :items-length="totalPublications"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- publicacion  -->
        <template #item.publicacion="{ item }">
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              v-if="(item as any).imagen_destacada"
              size="38"
              variant="tonal"
              rounded
            />
            <div class="d-flex flex-column">
              <span class="text-body-1 font-weight-medium text-high-emphasis">{{ (item as any).title }}</span>
              <span class="text-body-2">{{ (item as any).acf.marca.name }}</span>
            </div>
          </div>
        </template>

        <!-- referencia -->
        <template #item.referencia="{ item }">
          <span class="text-body-1 text-high-emphasis">{{ (item as any).acf.tipo_o_referencia }}</span>
        </template>

        <!-- precio -->
        <template #item.precio="{ item }">
          <span class="text-body-1 text-high-emphasis">{{ (item as any).acf.precio_de_venta }}</span>
        </template>

        <!-- status -->
        <template #item.status="{ item }">
          <VChip
            density="default"
            label
            size="small"
          />
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <IconBtn @click="$router.push(`/apps/publicaciones/publicacion/edit/${(item as any).uuid}`)">
            <VIcon icon="tabler-edit" />
          </IconBtn>
        </template>

        <!-- pagination -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalPublications"
          />
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
