<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'
import { useUserStore } from '@/@core/stores/user'
import { useMotorFormatter } from '@/composables/useMotorFormatter'
import type { Publicacion } from '@/interfaces/publicacion'

const { t } = useI18n()
const userStore = useUserStore()

const { formatMotorName } = useMotorFormatter()
const currentUser = computed(() => userStore.getUser)

const headers = [
  { title: t('publication_list.publication'), key: 'publicacion' },
  { title: t('publication_list.reference'), key: 'referencia' },
  { title: t('publication_list.status'), key: 'status' },
  { title: t('publication_list.actions'), key: 'actions', sortable: false },
]

const searchQuery = ref('')
const selectedStatus = ref()

const status = [
  { title: t('publication_list.status_options.published'), value: 'publish' },
  { title: t('publication_list.status_options.draft'), value: 'draft' },
  { title: t('publication_list.status_options.paused'), value: 'paused' },
  { title: t('publication_list.status_options.sold'), value: 'sold' },
]

const resolveStatus = (status: string) => {
  const statusMap: Record<string, { text: string; color: string }> = {
    publish: { text: t('custom.published'), color: 'success' },
    draft: { text: t('custom.draft'), color: 'secondary' },
    paused: { text: t('custom.paused'), color: 'warning' },
    sold: { text: t('custom.sold'), color: 'error' },
  }

  return statusMap[status] || { text: t('custom.unknown'), color: 'info' }
}

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

const { data: publicationsData, execute: fetchPublications, isFetching } = useApi<any>(createUrl('/wp-json/motorlan/v1/favorites',
  {
    query: {
      search: searchQuery,
      status: selectedStatus,
      page,
      per_page: itemsPerPage,
      orderby: sortBy,
      order: orderBy,
    },
  },
), { immediate: false }).get().json()

const publications = computed((): Publicacion[] => (publicationsData.value?.data || []).filter(Boolean))
const totalPublications = computed(() => publicationsData.value?.pagination?.total || 0)

watch([page, itemsPerPage, sortBy, orderBy, searchQuery, selectedStatus], () => {
  void fetchPublications()
}, { immediate: true })

const refresh = () => {
  fetchPublications()
}

// Estado para el modal de confirmación de eliminación
const showDeleteConfirm = ref(false)
const selectedFavorite = ref<Publicacion | null>(null)
const isDeleting = ref(false)

// Obtener URL de la publicación
const getPublicationUrl = (item: Publicacion): string => {
  return `/marketplace-motorlan/${(item as any).slug}/`
}

// Función para confirmar eliminación de favorito
const confirmRemoveFavorite = (item: Publicacion) => {
  selectedFavorite.value = item
  showDeleteConfirm.value = true
}

// Función para eliminar de favoritos
const removeFavorite = async () => {
  if (!selectedFavorite.value) return
  
  isDeleting.value = true
  try {
    const { error } = await useApi(`/wp-json/motorlan/v1/favorites/${(selectedFavorite.value as any).id}`, {
      method: 'DELETE',
    })
    
    if (!error.value) {
      // Refrescar la lista después de eliminar
      refresh()
    }
  } finally {
    isDeleting.value = false
    showDeleteConfirm.value = false
    selectedFavorite.value = null
  }
}
</script>

<template>
  <div>
    <VCard class="motor-card-enhanced overflow-visible">
      <VCardTitle class="pa-6 d-flex align-center justify-space-between flex-wrap gap-4">
        <span class="text-h5 text-premium-title">{{ t('Favorites') }}</span>
      </VCardTitle>

      <VCardText class="pa-6">
        <VRow>
           <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model="searchQuery"
              :placeholder="t('publication_list.search_publication')"
              prepend-inner-icon="tabler-search"
              clearable
              class="elevation-0"
            />
          </VCol>
          <!-- 👉 Select Status -->
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="selectedStatus"
              :placeholder="t('publication_list.status')"
              :items="status"
              prepend-inner-icon="tabler-filter"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>

          <!-- Per Page -->
          <VCol
            cols="12"
            md="2"
          >
            <AppSelect
              v-model="itemsPerPage"
              :items="[5, 10, 20, 25, 50]"
              placeholder="Mostrar"
              prepend-inner-icon="tabler-list-numbers"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider class="mt-4" />

      <div class="px-6 pb-6" style="overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch;">
        <VDataTableServer
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          :headers="headers"
          :items="publications"
          :items-length="totalPublications"
          :loading="isFetching"
          class="text-no-wrap"
          @update:options="updateOptions"
        >
        <!-- publicacion  -->
        <template #item.publicacion="{ item }">
          <div class="d-flex align-center gap-3 py-2" style="max-width: 280px;">
            <VAvatar
              v-if="(item as any).imagen_destacada"
              size="48"
              variant="tonal"
              rounded
              class="border flex-shrink-0"
              :image="(item as any).imagen_destacada?.url"
            />
            <div class="d-flex flex-column overflow-hidden">
              <span
                class="text-body-1 font-weight-medium text-high-emphasis text-truncate"
                style="max-width: 200px;"
              >
                {{ formatMotorName(item as any) || (item as any).title }}
                <VTooltip activator="parent" location="top">{{ formatMotorName(item as any) || (item as any).title }}</VTooltip>
              </span>
              <span class="text-caption text-medium-emphasis">{{ (item as any).acf?.marca?.name || (item as any).marca_name }}</span>
            </div>
          </div>
        </template>

        <!-- referencia -->
        <template #item.referencia="{ item }">
          <span class="text-body-1 text-high-emphasis font-weight-medium">{{ (item as any).acf.tipo_o_referencia }}</span>
        </template>


        <!-- status -->
        <template #item.status="{ item }">
          <VChip
            v-bind="resolveStatus((item as any).status)"
            density="comfortable"
            label
            size="small"
            class="font-weight-medium"
          >
            {{ resolveStatus((item as any).status).text }}
          </VChip>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
           <div class="d-flex justify-end gap-2">
              <!-- Ver Publicación -->
              <IconBtn
                color="success"
                variant="tonal"
                size="small"
                :href="getPublicationUrl(item)"
                target="_blank"
              >
                <VIcon
                  icon="tabler-eye"
                  size="18"
                />
                <VTooltip activator="parent" location="top">{{ t('Ver Publicación') }}</VTooltip>
              </IconBtn>

              <!-- Eliminar de Favoritos -->
              <IconBtn
                color="error"
                variant="tonal"
                size="small"
                @click="confirmRemoveFavorite(item)"
              >
                <VIcon
                  icon="tabler-heart-off"
                  size="18"
                />
                <VTooltip activator="parent" location="top">{{ t('Eliminar de Favoritos') }}</VTooltip>
              </IconBtn>
            </div>
        </template>

        <!-- pagination -->
        <template #bottom>
          <VDivider />
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalPublications"
            class="mt-4"
          />
        </template>
      </VDataTableServer>
    </div>
  </VCard>

  <!-- Modal de confirmación para eliminar favorito -->
  <VDialog
    v-model="showDeleteConfirm"
    max-width="450"
    persistent
  >
    <VCard class="motor-card-enhanced">
      <VCardTitle class="pa-4 d-flex justify-space-between align-center border-b">
        <span class="text-h6 text-premium-title">
          <VIcon icon="tabler-heart-off" class="me-2" color="error" />
          {{ t('Eliminar de Favoritos') }}
        </span>
        <VBtn icon variant="text" size="small" @click="showDeleteConfirm = false" :disabled="isDeleting">
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>
      
      <VCardText class="pa-6">
        <p class="text-body-1 mb-0">
          {{ t('¿Estás seguro que deseas eliminar esta publicación de tus favoritos?') }}
        </p>
        <p v-if="selectedFavorite" class="text-body-2 text-medium-emphasis mt-2 font-weight-medium">
          "{{ formatMotorName(selectedFavorite as any) || (selectedFavorite as any).title }}"
        </p>
      </VCardText>
      
      <VCardActions class="pa-6 pt-0 justify-end gap-2">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="showDeleteConfirm = false"
          :disabled="isDeleting"
        >
          {{ t('Cancelar') }}
        </VBtn>
        <VBtn
          color="error"
          @click="removeFavorite"
          :loading="isDeleting"
        >
          <VIcon icon="tabler-heart-off" class="me-1" />
          {{ t('Eliminar') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</div>
</template>
