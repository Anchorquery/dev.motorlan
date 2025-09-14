<script setup lang="ts">
// @ts-nocheck
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import type { ImagenDestacada, Publicacion } from '../../../../../interfaces/publicacion'
import { useApi } from '@/composables/useApi'
import { debounce } from '@/utils/debounce'

const { t } = useI18n()
const router = useRouter()

const headers = [
  { title: t('publication_list.publication'), key: 'publicacion' },
  { title: t('publication_list.reference'), key: 'referencia' },
  { title: t('publication_list.price'), key: 'precio' },
  { title: t('publication_list.status'), key: 'status' },
  { title: t('publication_list.actions'), key: 'actions', sortable: false },
]

const selectedStatus = ref()
const selectedCategory = ref()
const selectedTipo = ref()
const searchQuery = ref('')
const selectedRows = ref([])

const status = computed(() => [
  { title: t('publication_list.status_options.published'), value: 'publish' },
  { title: t('publication_list.status_options.draft'), value: 'draft' },
  { title: t('publication_list.status_options.paused'), value: 'paused' },
  { title: t('publication_list.status_options.sold'), value: 'sold' },
])

const categories = ref<{ title: string; value: string }[]>([])
const tipos = ref<{ title: string; value: string }[]>([])

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

const resolveStatus = (statusMsg: string) => {
  if (statusMsg === 'publish')
    return { text: t('custom.published'), color: 'success' }
  if (statusMsg === 'draft')
    return { text: t('custom.draft'), color: 'secondary' }
  if (statusMsg === 'paused')
    return { text: t('custom.paused'), color: 'warning' }
  if (statusMsg === 'sold')
    return { text: t('custom.sold'), color: 'error' }

  return { text: t('custom.unknown'), color: 'info' }
}

const apiUrl = computed(() => {
  const params = new URLSearchParams()
  if (searchQuery.value)
    params.append('search', searchQuery.value)
  if (selectedCategory.value)
    params.append('category', selectedCategory.value)
  if (selectedTipo.value)
    params.append('tipo', selectedTipo.value)
  if (selectedStatus.value)
    params.append('status', selectedStatus.value)
  if (page.value)
    params.append('page', page.value.toString())
  if (itemsPerPage.value)
    params.append('per_page', itemsPerPage.value.toString())
  if (sortBy.value)
    params.append('orderby', sortBy.value)
  if (orderBy.value)
    params.append('order', orderBy.value)

  return `/wp-json/motorlan/v1/publicaciones?${params.toString()}`
})

const { data: publicacionesData, execute: fetchPublicaciones, isLoading: isTableLoading } = useApi<any>(apiUrl, { immediate: false }).get().json()
const isSearching = ref(false)

const debouncedFetch = debounce(async () => {
  isSearching.value = true
  await fetchPublicaciones()
  isSearching.value = false
}, 300)

watch(
  [searchQuery, selectedCategory, selectedTipo, selectedStatus, page, itemsPerPage, sortBy, orderBy],
  () => {
    debouncedFetch()
  },
  { deep: true },
)

onMounted(async () => {
  const fetchCategories = async () => {
    const { data: categoriesData } = await useApi<any>('/wp-json/motorlan/v1/publicacion-categories').get().json()
    const cats = Array.isArray(categoriesData.value)
      ? categoriesData.value
      : (categoriesData.value?.data ?? [])
    if (cats && Array.isArray(cats)) {
      categories.value = cats.map((cat: any) => ({
        title: cat.name,
        value: cat.slug,
      }))
    }
  }

  const fetchTipos = async () => {
    const { data: tiposData } = await useApi<any>('/wp-json/motorlan/v1/tipos').get().json()
    const tiposArr = Array.isArray(tiposData.value)
      ? tiposData.value
      : (tiposData.value?.data ?? [])
    if (tiposArr && Array.isArray(tiposArr)) {
      tipos.value = tiposArr.map((tipo: any) => ({
        title: tipo.name,
        value: tipo.slug,
      }))
    }
  }

  await Promise.all([fetchCategories(), fetchTipos()])
  fetchPublicaciones()
})

const publicaciones = computed((): Publicacion[] => (publicacionesData.value?.data || publicacionesData.value || []).filter(Boolean))
const totalPublicaciones = computed(() => (publicacionesData.value?.pagination?.total) || 0)

const isLoading = ref(false)
const loadingMessage = ref('')
const isDeleteDialogVisible = ref(false)
const publicacionToDelete = ref<number | null>(null)
const isDuplicateDialogVisible = ref(false)
const publicacionToDuplicate = ref<number | null>(null)
const isStatusDialogVisible = ref(false)
const publicacionToChangeStatus = ref<{ id: number; status: string } | null>(null)

const openDeleteDialog = (id: number) => {
  publicacionToDelete.value = id
  isDeleteDialogVisible.value = true
}

const openDuplicateDialog = (id: number) => {
  publicacionToDuplicate.value = id
  isDuplicateDialogVisible.value = true
}

const openStatusDialog = (id: number, status: string) => {
  publicacionToChangeStatus.value = { id, status }
  isStatusDialogVisible.value = true
}

const handlePublicacionAction = async (message: string, action: () => Promise<void>) => {
  isLoading.value = true
  loadingMessage.value = message
  try {
    await action()
    await fetchPublicaciones()
  }
  catch (error) {
    console.error(error)
  }
  finally {
    isLoading.value = false
  }
}


const deleteSelectedPublicaciones = () => {
  isDeleteDialogVisible.value = false
  handlePublicacionAction(t('publication_list.deleting_selected_publications'), async () => {
    await useApi('/wp-json/motorlan/v1/publicaciones/bulk-delete', {
      method: 'POST',
      body: JSON.stringify({ ids: selectedRows.value }),
    })
    selectedRows.value = []
  })
}

const deletePublicacion = () => {
  if (publicacionToDelete.value !== null && publicacionToDelete.value !== 0) {
    // Single publication deletion
    isDeleteDialogVisible.value = false
    handlePublicacionAction(t('publication_list.deleting_publication'), async () => {
      if (publicacionToDelete.value === null)
        return
      await useApi(`/wp-json/motorlan/v1/publicaciones/${publicacionToDelete.value}`, { method: 'DELETE' })

      const index = selectedRows.value.findIndex(row => row === publicacionToDelete.value)
      if (index !== -1)
        selectedRows.value.splice(index, 1)
    })
  }
  else {
    // Bulk deletion
    deleteSelectedPublicaciones()
  }
}

const duplicatePublicacion = () => {
  if (publicacionToDuplicate.value === null)
    return

  isDuplicateDialogVisible.value = false
  handlePublicacionAction(t('publication_list.duplicating_publication'), async () => {
    if (publicacionToDuplicate.value === null)
      return
    await useApi(`/wp-json/motorlan/v1/publicaciones/duplicate/${publicacionToDuplicate.value}`, { method: 'POST' })
  })
}

const changeStatus = () => {
  if (!publicacionToChangeStatus.value)
    return

  const { id, status } = publicacionToChangeStatus.value
  isStatusDialogVisible.value = false

  const messages: { [key: string]: string } = {
    publish: t('publication_list.publishing_publication'),
    paused: t('publication_list.pausing_publication'),
    draft: t('publication_list.moving_to_draft'),
  }

  handlePublicacionAction(messages[status] || t('publication_list.updating_status'), async () => {
    await useApi(`/wp-json/motorlan/v1/publicaciones/${id}/status`, {
      method: 'POST',
      body: JSON.stringify({ status }),
    })
  })
}

const getImageBySize = (image: ImagenDestacada | null | any[], size = 'thumbnail'): string => {
  console.log(image)
  let imageObj: ImagenDestacada | null = null

  if (Array.isArray(image) && image.length > 0)
    imageObj = image[0]
  else if (image && !Array.isArray(image))
    imageObj = image as ImagenDestacada

  if (!imageObj)
    return ''

  if (imageObj.sizes && imageObj.sizes[size])
    return imageObj.sizes[size] as string

  return imageObj.url || ''
}
</script>

<template>
  <div>
    <!-- 👉 widgets -->
    <!--
      <VCard class="mb-6">
      <VCardText class="px-3">
      <VRow>
      <template
      v-for="(data, id) in widgetData"
      :key="id"
      >
      <VCol
      cols="12"
      sm="6"
      md="3"
      class="px-6"
      >
      <div
      class="d-flex justify-space-between"
      :class="$vuetify.display.xs
      ? id !== widgetData.length - 1 ? 'border-b pb-4' : ''
      : $vuetify.display.sm
      ? id < (widgetData.length / 2) ? 'border-b pb-4' : ''
      : ''"
      >
      <div class="d-flex flex-column gap-y-1">
      <div class="text-body-1 text-capitalize">
      {{ data.title }}
      </div>

      <h4 class="text-h4">
      {{ data.value }}
      </h4>

      <div class="d-flex align-center gap-x-2">
      <div class="text-no-wrap">
      {{ data.desc }}
      </div>

      <VChip
      v-if="data.change"
      label
      :color="data.change > 0 ? 'success' : 'error'"
      size="small"
      >
      {{ prefixWithPlus(data.change) }}%
      </VChip>
      </div>
      </div>

      <VAvatar
      variant="tonal"
      rounded
      size="44"
      >
      <VIcon
      :icon="data.icon"
      size="28"
      class="text-high-emphasis"
      />
      </VAvatar>
      </div>
      </VCol>
      <VDivider
      v-if="$vuetify.display.mdAndUp ? id !== widgetData.length - 1
      : $vuetify.display.smAndUp ? id % 2 === 0
      : false"
      vertical
      inset
      length="92"
      />
      </template>
      </VRow>
      </VCardText>
      </VCard>
    -->

    <!-- 👉 publicaciones -->
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

          <!-- 👉 Select Category -->
          <VCol
            cols="12"
            sm="4"
          >
            <AppSelect
              v-model="selectedCategory"
              :placeholder="t('Category')"
              :items="categories"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <!-- 👉 Select Tipo -->
          <VCol
            cols="12"
            sm="4"
          >
            <AppSelect
              v-model="selectedTipo"
              :placeholder="t('Tipo')"
              :items="tipos"
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
          <!-- 👉 Export button -->
          <VBtn
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-upload"
          >
            {{ t('publication_list.export') }}
          </VBtn>

          <!-- 👉 Delete button -->
          <VBtn
            v-if="selectedRows.length > 0"
            color="error"
            prepend-icon="tabler-trash"
            @click="openDeleteDialog(0)"
          >
            {{ t('publication_list.delete_selected') }} ({{ selectedRows.length }})
          </VBtn>

          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="router.push({ path: '/apps/publications/publication/add' })"
          >
            {{ t('publication_list.add_publication') }}
          </VBtn>
        </div>
      </div>

      <VDivider class="mt-4" />

      <!-- 👉 Datatable  -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:model-value="selectedRows"
        v-model:page="page"
        :headers="headers"
        show-select
        :items="publicaciones"
        :items-length="totalPublicaciones"
        :loading="isTableLoading || isSearching"
        class="text-no-wrap"
        item-value="id"
        :return-object="false"
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
              :image="getImageBySize((item as any).imagen_destacada, 'thumbnail')"
            />
            <div class="d-flex flex-column">
              <span class="text-body-1 font-weight-medium text-high-emphasis">{{ (item as any).title }}</span>
              <span class="text-body-2">{{ (item as any).acf.marca?.name }}</span>
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
            v-bind="resolveStatus((item as any).status)"
            density="default"
            label
            size="small"
          />
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <IconBtn @click="router.push(`/apps/publications/publication/edit/${(item as any).uuid}`)">
            <VIcon icon="tabler-eye" />
          </IconBtn>

          <IconBtn>
            <VIcon icon="tabler-dots-vertical" />
            <VMenu activator="parent">
              <VList>
                <VListItem
                  value="delete"
                  prepend-icon="tabler-trash"
                  @click="openDeleteDialog((item as any).id)"
                >
                  {{ t('publication_list.delete') }}
                </VListItem>

                <VListItem
                  value="duplicate"
                  prepend-icon="tabler-copy"
                  @click="openDuplicateDialog((item as any).id)"
                >
                  {{ t('publication_list.duplicate') }}
                </VListItem>

                <VListItem
                  v-if="(item as any).status !== 'publish'"
                  value="publish"
                  prepend-icon="tabler-player-play"
                  @click="openStatusDialog((item as any).id, 'publish')"
                >
                  {{ t('publication_list.publish') }}
                </VListItem>

                <VListItem
                  v-if="(item as any).status !== 'paused'"
                  value="pause"
                  prepend-icon="tabler-player-pause"
                  @click="openStatusDialog((item as any).id, 'paused')"
                >
                  {{ t('publication_list.pause') }}
                </VListItem>

                <VListItem
                  v-if="(item as any).status !== 'draft'"
                  value="draft"
                  prepend-icon="tabler-file-text"
                  @click="openStatusDialog((item as any).id, 'draft')"
                >
                  {{ t('publication_list.move_to_draft') }}
                </VListItem>
              </VList>
            </VMenu>
          </IconBtn>
        </template>

        <!-- pagination -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalPublicaciones"
          />
        </template>
      </VDataTableServer>
    </VCard>
    <!-- 👉 Loading overlay -->
    <VOverlay
      v-model="isLoading"
      class="d-flex align-center justify-center"
      persistent
    >
      <VProgressCircular
        indeterminate
        size="64"
        color="primary"
      />
      <p class="text-center">
        {{ loadingMessage }}
      </p>
    </VOverlay>

    <!-- 👉 Delete Confirmation Dialog -->
    <VDialog
      v-model="isDeleteDialogVisible"
      max-width="500"
      persistent
    >
      <VCard>
        <VCardTitle>
          {{ publicacionToDelete === 0 ? t('publication_list.delete_selected_dialog_title') : t('publication_list.delete_dialog_title') }}
        </VCardTitle>
        <VCardText>
          {{ publicacionToDelete === 0 ? t('publication_list.delete_selected_dialog_text', { count: selectedRows.length }) : t('publication_list.delete_dialog_text') }}
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="error"
            @click="isDeleteDialogVisible = false"
          >
            {{ t('publication_list.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            @click="deletePublicacion"
          >
            {{ t('publication_list.confirm_delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- 👉 Duplicate Confirmation Dialog -->
    <VDialog
      v-model="isDuplicateDialogVisible"
      max-width="500"
      persistent
    >
      <VCard>
        <VCardTitle>
          {{ t('publication_list.duplicate_dialog_title') }}
        </VCardTitle>
        <VCardText>
          {{ t('publication_list.duplicate_dialog_text') }}
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="error"
            @click="isDuplicateDialogVisible = false"
          >
            {{ t('publication_list.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            @click="duplicatePublicacion"
          >
            {{ t('publication_list.confirm_duplicate') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- 👉 Status Confirmation Dialog -->
    <VDialog
      v-model="isStatusDialogVisible"
      max-width="500"
      persistent
    >
      <VCard>
        <VCardTitle>
          {{ t('publication_list.status_dialog_title') }}
        </VCardTitle>
        <VCardText>
          {{ t('publication_list.status_dialog_text') }}
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="error"
            @click="isStatusDialogVisible = false"
          >
            {{ t('publication_list.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            @click="changeStatus"
          >
            {{ t('publication_list.confirm_status') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
