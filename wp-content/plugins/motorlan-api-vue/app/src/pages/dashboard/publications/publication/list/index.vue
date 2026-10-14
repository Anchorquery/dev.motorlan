<script setup lang="ts">
// @ts-nocheck
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import type { ImagenDestacada, Publicacion } from '../../../../../interfaces/publicacion'
import { assertApiOk, useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { debounce } from '@/utils/debounce'
import { useUserStore } from '@/@core/stores/user'
import { useMotorFormatter } from '@/composables/useMotorFormatter'
import { storeUrl } from '@/utils/urls'

const { t } = useI18n()
const { showToast } = useToast()
const router = useRouter()
const userStore = useUserStore()
const { formatMotorName } = useMotorFormatter()

const headers = [
  { title: t('publication_list.publication'), value: 'publicacion' },
  { title: t('publication_list.reference'), value: 'referencia' },
  { title: t('publication_list.price'), value: 'precio' },
  { title: t('publication_list.status'), value: 'status' },
  { title: t('publication_list.actions'), value: 'actions', sortable: false },
]

const selectedStatus = ref()
const selectedTipo = ref()
const searchQuery = ref('')
const selectedRows = ref([])

const status = computed(() => [
  { title: t('publication_list.status_options.published'), value: 'publish' },
  { title: t('publication_list.status_options.draft'), value: 'draft' },
  { title: t('publication_list.status_options.pending'), value: 'pending' },
  { title: t('publication_list.status_options.paused'), value: 'paused' },
  { title: t('publication_list.status_options.sold'), value: 'sold' },
])

const tipos = ref<{ title: string; value: string }[]>([])

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()

// Update data table options. Las cabeceras usan claves de la tabla; el backend
// (my-publications-routes.php) solo entiende estas (antes se enviaban tal cual y se ignoraban).
const sortKeyMap: Record<string, string> = {
  publicacion: 'title',
  referencia: 'tipo_o_referencia',
  precio: 'price',
  status: 'post_status',
}
const updateOptions = (options: any) => {
  const key = options.sortBy?.[0]?.key
  sortBy.value = key ? (sortKeyMap[key] ?? undefined) : undefined
  orderBy.value = options.sortBy?.[0]?.order
}

const resolveStatus = (statusMsg: string | number) => {
  const statusString = String(statusMsg)

  // Handle the expected string values first
  if (statusString === 'publish')
    return { text: t('custom.published'), color: 'success' }
  if (statusString === 'draft')
    return { text: t('custom.draft'), color: 'secondary' }
  if (statusString === 'pending')
    return { text: t('custom.pending'), color: 'warning' }
  if (statusString === 'paused')
    return { text: t('custom.paused'), color: 'warning' }
  if (statusString === 'sold')
    return { text: t('publication_list.status_options.sold', 'Vendido'), color: 'error' }
  if (statusString === 'private')
    return { text: t('custom.draft'), color: 'secondary' }
  if (statusString === 'future')
    return { text: t('custom.published'), color: 'success' }

  // For unknown or empty statuses, default to 'draft' or 'not_found'
  if (!statusString || statusString === 'false' || statusString === 'undefined' || statusString === 'null')
     return { text: t('custom.draft'), color: 'secondary' }

  // If it's not one of the expected strings, it's an unknown/invalid status
  return { text: statusString.charAt(0).toUpperCase() + statusString.slice(1) || t('custom.unknown'), color: 'info' }
}

const apiUrl = computed(() => {
  const params = new URLSearchParams()
  if (searchQuery.value)
    params.append('search', searchQuery.value)
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

  return `/wp-json/motorlan/v1/my-publications?${params.toString()}`
})

const { data: publicacionesData, execute: fetchPublicaciones, isFetching: isTableLoading } = useApi<any>(apiUrl, { immediate: false }).get().json()
const isSearching = ref(false)

const debouncedFetch = debounce(async () => {
  isSearching.value = true
  await fetchPublicaciones()
  isSearching.value = false
}, 300)

// Al cambiar búsqueda/filtros/"por página" se vuelve a la página 1 (antes, desde la
// página 3, un filtro con 1 sola página dejaba la tabla vacía).
watch(
  [searchQuery, selectedTipo, selectedStatus, itemsPerPage],
  () => {
    if (page.value !== 1)
      page.value = 1 // dispara el watch de abajo
    else
      debouncedFetch()
  },
  { deep: true },
)
watch([page, sortBy, orderBy], () => debouncedFetch())

onMounted(async () => {
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

  await Promise.all([fetchTipos()])
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
  catch (error: any) {
    console.error(error)
    showToast(error?.message || t('publication_list.action_error', 'No se pudo completar la acción'), 'error')
  }
  finally {
    isLoading.value = false
  }
}


const deleteSelectedPublicaciones = () => {
  isDeleteDialogVisible.value = false
  handlePublicacionAction(t('publication_list.deleting_selected_publications'), async () => {
    const { data } = assertApiOk(await useApi('/wp-json/motorlan/v1/publicaciones/bulk-delete', {
      method: 'POST',
      body: JSON.stringify({ ids: selectedRows.value }),
    }).json())
    selectedRows.value = []
    // Algunas no se borran si tienen compras asociadas: avisar con el mensaje del backend
    if ((data.value as any)?.skipped_purchases > 0)
      showToast((data.value as any).message, 'warning')
  })
}

const deletePublicacion = () => {
  if (publicacionToDelete.value !== null && publicacionToDelete.value !== 0) {
    // Single publication deletion
    isDeleteDialogVisible.value = false
    handlePublicacionAction(t('publication_list.deleting_publication'), async () => {
      if (publicacionToDelete.value === null)
        return
      assertApiOk(await useApi(`/wp-json/motorlan/v1/publicaciones/${publicacionToDelete.value}`, { method: 'DELETE' }).json())

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
  let newUuid = ''
  handlePublicacionAction(t('publication_list.duplicating_publication'), async () => {
    if (publicacionToDuplicate.value === null)
      return
    const { data } = assertApiOk(await useApi(`/wp-json/motorlan/v1/publicaciones/duplicate/${publicacionToDuplicate.value}`, { method: 'POST' }).json())
    newUuid = (data.value as any)?.uuid || ''
  }).then(() => {
    if (!newUuid)
      return
    showToast(t('publication_list.duplicate_success', 'Copia creada como borrador. Revísala antes de publicarla.'), 'success')
    router.push(`/dashboard/publications/publication/edit/${newUuid}`)
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
    const { data } = assertApiOk(await useApi(`/wp-json/motorlan/v1/publicaciones/${id}/status`, {
      method: 'POST',
      body: JSON.stringify({ status }),
    }).json())
    if (status === 'publish' && (data.value as any)?.new_status === 'pending')
      showToast(t('publication_list.sent_to_review', 'Enviada a revisión. Quedará visible cuando un administrador la apruebe.'), 'info')
  })
}

const getImageBySize = (image: any, size = 'thumbnail'): string => {
  if (!image)
    return ''
  
  if (typeof image === 'string')
    return image

  let imageObj: ImagenDestacada | null = null
  if (Array.isArray(image) && image.length > 0)
    imageObj = image[0]
  else if (image && !Array.isArray(image))
    imageObj = image as ImagenDestacada

  if (!imageObj)
    return ''

  const hrefFromSizes = (sizes: any) => {
    if (!sizes)
      return null

    if (Array.isArray(sizes)) {
      const match = sizes.find(sizeItem => sizeItem?.name === size || sizeItem?.slug === size)
      return match?.url || match?.src || null
    }

    if (typeof sizes === 'object') {
      const sizeEntry = sizes[size]
      if (sizeEntry && typeof sizeEntry === 'object')
        return sizeEntry.url || sizeEntry.src || null

      if (typeof sizeEntry === 'string')
        return sizeEntry
    }

    return null
  }

  const sizedUrl = hrefFromSizes(imageObj.sizes)
  if (sizedUrl)
    return sizedUrl

  return imageObj.url || ''
}


// Exporta a CSV las publicaciones que cumplen los filtros actuales (todas las páginas).
const isExporting = ref(false)
const exportPublicaciones = async () => {
  if (isExporting.value)
    return
  isExporting.value = true
  try {
    const rows: any[] = []
    let currentPage = 1
    let totalPages = 1
    do {
      const params = new URLSearchParams()
      if (searchQuery.value) params.append('search', searchQuery.value)
      if (selectedTipo.value) params.append('tipo', selectedTipo.value)
      if (selectedStatus.value) params.append('status', selectedStatus.value)
      params.append('page', String(currentPage))
      params.append('per_page', '100')
      const { data } = assertApiOk(await useApi<any>(`/wp-json/motorlan/v1/my-publications?${params.toString()}`).get().json())
      rows.push(...((data.value as any)?.data || []))
      totalPages = Number((data.value as any)?.pagination?.totalPages) || 1
      currentPage++
    } while (currentPage <= totalPages && currentPage <= 50)

    const csvCell = (v: any) => {
      const str = v === null || v === undefined ? '' : String(v)

      return /[";\n\r]/.test(str) ? `"${str.replace(/"/g, '""')}"` : str
    }
    const header = [
      'ID',
      t('publication_list.publication', 'Publicación'),
      t('publication_list.reference', 'Referencia'),
      t('add_publication.post_details.brand', 'Marca'),
      t('publication_list.price', 'Precio'),
      t('publication_list.status', 'Estado'),
      'URL',
    ]
    const lines = rows.map(item => [
      item.id,
      formatMotorName(item) || item.title,
      item.acf?.tipo_o_referencia,
      item.acf?.marca?.name,
      item.acf?.precio_de_venta,
      resolveStatus(item.status).text,
      item.status === 'publish' ? item.link : '',
    ].map(csvCell).join(';'))
    const csv = `\uFEFF${[header.map(csvCell).join(';'), ...lines].join('\r\n')}`
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
    const link = document.createElement('a')
    link.href = url
    link.download = `publicaciones-${new Date().toISOString().slice(0, 10)}.csv`
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  }
  catch (error: any) {
    console.error(error)
    showToast(error?.message || t('publication_list.export_error', 'No se pudo exportar'), 'error')
  }
  finally {
    isExporting.value = false
  }
}

const canEdit = (item: any) => {
  if (userStore.isAdmin) return true
  // Si está en revisión (pending), y no es admin, no puede editar
  return item.status !== 'pending'
}
</script>

<template>
  <div>
    <VCard class="motor-card-enhanced overflow-visible">
      <VCardTitle class="pa-6 pb-0">
         <span class="text-h5 text-premium-title">{{ t('Publicaciones') }}</span>
      </VCardTitle>

      <VCardText class="pa-6">
        <VRow class="publication-list-toolbar">
           <VCol cols="12" md="4">
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
            sm="4"
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

          <!-- 👉 Select Tipo -->
          <VCol
            cols="12"
            sm="4"
            md="2"
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

      <div class="px-6 py-4 d-flex align-center justify-space-between flex-wrap gap-4 publication-list-actions">
         <div class="d-flex align-center gap-2">
            <AppSelect
            v-model="itemsPerPage"
            :items="[5, 10, 20, 25, 50]"
            style="inline-size: 80px;"
          />
         </div>
         <div class="d-flex align-center gap-2 flex-wrap">
             <!-- 👉 Export button -->
          <VBtn
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-upload"
            :loading="isExporting"
            @click="exportPublicaciones"
          >
            {{ t('publication_list.export') }}
          </VBtn>

          <!-- 👉 Delete button -->
          <VBtn
            v-if="selectedRows.length > 0"
            color="error"
            variant="elevated"
            prepend-icon="tabler-trash"
            @click="openDeleteDialog(0)"
          >
            {{ t('publication_list.delete_selected') }} ({{ selectedRows.length }})
          </VBtn>

          <VBtn
            color="primary"
            variant="elevated"
            prepend-icon="tabler-plus"
            @click="router.push({ path: '/dashboard/publications/publication/add' })"
          >
            {{ t('publication_list.add_publication') }}
          </VBtn>
         </div>
      </div>

      <VDivider />

      <div class="publication-list-mobile-controls">
        <AppTextField
          v-model="searchQuery"
          :placeholder="t('publication_list.search_publication')"
          prepend-inner-icon="tabler-search"
          clearable
        />

        <AppSelect
          v-model="selectedStatus"
          :placeholder="t('publication_list.status')"
          :items="status"
          prepend-inner-icon="tabler-filter"
          clearable
          clear-icon="tabler-x"
        />

        <AppSelect
          v-model="selectedTipo"
          :placeholder="t('Tipo')"
          :items="tipos"
          clearable
          clear-icon="tabler-x"
        />

        <AppSelect
          v-model="itemsPerPage"
          :items="[
            { value: 10, title: '10' },
            { value: 25, title: '25' },
            { value: 50, title: '50' },
            { value: 100, title: '100' },
          ]"
          class="publication-list-mobile-per-page"
        />

        <VBtn
          color="secondary"
          variant="tonal"
          prepend-icon="tabler-upload"
          block
          :loading="isExporting"
          @click="exportPublicaciones"
        >
          {{ t('publication_list.export', 'Exportar') }}
        </VBtn>

        <VBtn
          color="primary"
          variant="flat"
          prepend-icon="tabler-plus"
          block
          @click="router.push('/dashboard/publications/publication/add')"
        >
          {{ t('publication_list.add_publication', 'Añadir Publicación') }}
        </VBtn>
      </div>

      <!-- 👉 Datatable  -->
      <div class="publication-table-shell publication-table-shell--desktop">
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:model-value="selectedRows"
        v-model:page="page"
        :headers="headers"
        show-select
        :items="publicaciones"
        :items-length="totalPublicaciones"
        :loading="isTableLoading || isSearching"
        class="text-no-wrap px-6 pb-6"
        item-value="id"
        :return-object="false"
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
               :image="getImageBySize((item as any).imagen_destacada, 'thumbnail')"
             />
             <div class="d-flex flex-column overflow-hidden">
               <span 
                 class="text-body-1 font-weight-medium text-high-emphasis text-truncate"
                 style="max-width: 200px;"
               >
                 {{ formatMotorName(item as any) || (item as any).title }}
                 <VTooltip activator="parent" location="top">{{ formatMotorName(item as any) || (item as any).title }}</VTooltip>
               </span>
               <span class="text-caption text-medium-emphasis">{{ (item as any).acf.marca?.name }}</span>
             </div>
           </div>
         </template>

        <!-- referencia -->
        <template #item.referencia="{ item }">
          <span class="text-body-1 text-high-emphasis font-weight-medium">{{ (item as any).acf.tipo_o_referencia }}</span>
        </template>

        <!-- precio -->
        <template #item.precio="{ item }">
          <span v-if="(item as any).acf.precio_negociable === 'yes' || (item as any).acf.precio_negociable === true" class="text-body-1 text-warning font-weight-bold">{{ t('publication_list_table.consult_price') }}</span>
          <span v-else-if="(item as any).acf.precio_de_venta" class="text-body-1 text-primary font-weight-bold">{{ (item as any).acf.precio_de_venta }}€</span>
          <span v-else class="text-body-2 text-medium-emphasis">-</span>
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
            <IconBtn
              v-if="(item as any).status === 'publish'"
              color="success"
              variant="tonal"
              size="small"
              :href="storeUrl((item as any).slug)"
              target="_blank"
            >
              <VIcon icon="tabler-eye" size="18" />
              <VTooltip activator="parent" location="top">{{ t('publication_list.view_publication', 'Ver publicación') }}</VTooltip>
            </IconBtn>

            <IconBtn 
              :color="canEdit(item) ? 'primary' : 'warning'" 
              variant="tonal" 
              size="small"
              @click="canEdit(item) ? router.push(`/dashboard/publications/publication/edit/${(item as any).uuid}`) : null"
            >
              <VIcon :icon="canEdit(item) ? 'tabler-pencil' : 'tabler-lock'" size="18" />
              <VTooltip activator="parent" location="top">
                {{ canEdit(item) ? t('publication_list.edit', 'Editar') : t('edit_publication.pending_review_warning') }}
              </VTooltip>
            </IconBtn>

            <IconBtn
              v-if="canEdit(item)"
              color="secondary"
              variant="tonal"
              size="small"
            >
              <VIcon icon="tabler-dots-vertical" size="18" />
              <VMenu activator="parent">
                <VList density="compact">
<!--
                  <VListItem
                    value="questions"
                    @click="router.push(`/dashboard/publications/publication/questions/${(item as any).uuid}`)"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-help" size="18" class="me-2" />
                    </template>
                    <VListItemTitle>{{ t('publication_list.questions') }}</VListItemTitle>
                  </VListItem>
-->
                  
                  <VListItem
                    value="duplicate"
                    @click="openDuplicateDialog((item as any).id)"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-copy" size="18" class="me-2" />
                    </template>
                    <VListItemTitle>{{ t('publication_list.duplicate') }}</VListItemTitle>
                  </VListItem>

                  <VDivider
                    v-if="!['publish', 'paused', 'draft'].includes((item as any).status)"
                    class="my-1"
                  />

                  <VListItem
                    v-if="(item as any).status !== 'publish'"
                    value="publish"
                    @click="openStatusDialog((item as any).id, 'publish')"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-player-play" color="success" size="18" class="me-2" />
                    </template>
                    <VListItemTitle>{{ t('publication_list.publish') }}</VListItemTitle>
                  </VListItem>

                  <VListItem
                    v-if="(item as any).status !== 'paused'"
                    value="pause"
                    @click="openStatusDialog((item as any).id, 'paused')"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-player-pause" color="warning" size="18" class="me-2" />
                    </template>
                    <VListItemTitle>{{ t('publication_list.pause') }}</VListItemTitle>
                  </VListItem>

                  <VListItem
                    v-if="(item as any).status !== 'draft'"
                    value="draft"
                    @click="openStatusDialog((item as any).id, 'draft')"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-file-text" size="18" class="me-2" />
                    </template>
                    <VListItemTitle>{{ t('publication_list.move_to_draft') }}</VListItemTitle>
                  </VListItem>

                  <VDivider class="my-1" />

                  <VListItem
                    value="delete"
                    color="error"
                    @click="openDeleteDialog((item as any).id)"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-trash" color="error" size="18" class="me-2" />
                    </template>
                    <VListItemTitle class="text-error">{{ t('publication_list.delete') }}</VListItemTitle>
                  </VListItem>
                </VList>
              </VMenu>
            </IconBtn>
          </div>
        </template>

        <!-- pagination -->
        <template #bottom>
          <VDivider />
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalPublicaciones"
            class="mt-4"
          />
        </template>
      </VDataTableServer>
      </div>

      <div class="publication-mobile-list">
        <div
          v-for="item in publicaciones"
          :key="(item as any).id"
          class="publication-mobile-card"
        >
          <div class="publication-mobile-card__top">
            <VCheckbox
              v-model="selectedRows"
              :value="(item as any).id"
              density="compact"
              hide-details
              class="ma-0"
            />

            <VChip
              v-bind="resolveStatus((item as any).status)"
              density="comfortable"
              size="small"
              label
            >
              {{ resolveStatus((item as any).status).text }}
            </VChip>
          </div>

          <div class="publication-mobile-card__body">
            <VAvatar
              v-if="(item as any).imagen_destacada"
              :image="getImageBySize((item as any).imagen_destacada)"
              size="56"
              rounded
              class="publication-mobile-card__thumb"
            />

            <div class="publication-mobile-card__content">
              <div class="publication-mobile-card__title">
                {{ formatMotorName(item as any) }}
              </div>
              <div class="publication-mobile-card__ref">
                {{ (item as any).acf?.tipo_o_referencia || '-' }}
              </div>
              <div class="publication-mobile-card__price">
                <span v-if="(item as any).acf?.precio_negociable === 'yes' || (item as any).acf?.precio_negociable === true">
                  {{ t('publication_list_table.consult_price', 'Consultar precio') }}
                </span>
                <span v-else-if="(item as any).acf?.precio_de_venta">
                  {{ (item as any).acf?.precio_de_venta }}€
                </span>
                <span v-else>-</span>
              </div>
            </div>
          </div>

          <div class="publication-mobile-card__actions">
            <IconBtn
              v-if="(item as any).status === 'publish'"
              color="success"
              variant="tonal"
              :href="storeUrl((item as any).slug)"
              target="_blank"
            >
              <VIcon icon="tabler-eye" />
            </IconBtn>

            <IconBtn
              :color="canEdit(item) ? 'primary' : 'warning'"
              variant="tonal"
              :disabled="!canEdit(item)"
              @click="canEdit(item) ? router.push(`/dashboard/publications/publication/edit/${(item as any).uuid}`) : null"
            >
              <VIcon :icon="canEdit(item) ? 'tabler-pencil' : 'tabler-lock'" />
            </IconBtn>

            <template v-if="canEdit(item)">
              <IconBtn
                color="info"
                variant="tonal"
              >
                <VIcon icon="tabler-switch-horizontal" />
                <VMenu activator="parent">
                  <VList density="compact">
                    <VListItem
                      v-if="(item as any).status !== 'publish'"
                      @click="openStatusDialog((item as any).id, 'publish')"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-player-play" color="success" size="18" class="me-2" />
                      </template>
                      <VListItemTitle>{{ t('publication_list.publish') }}</VListItemTitle>
                    </VListItem>
                    <VListItem
                      v-if="(item as any).status !== 'paused'"
                      @click="openStatusDialog((item as any).id, 'paused')"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-player-pause" color="warning" size="18" class="me-2" />
                      </template>
                      <VListItemTitle>{{ t('publication_list.pause') }}</VListItemTitle>
                    </VListItem>
                    <VListItem
                      v-if="(item as any).status !== 'draft'"
                      @click="openStatusDialog((item as any).id, 'draft')"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-file-text" size="18" class="me-2" />
                      </template>
                      <VListItemTitle>{{ t('publication_list.move_to_draft') }}</VListItemTitle>
                    </VListItem>
                  </VList>
                </VMenu>
              </IconBtn>

              <IconBtn
                color="secondary"
                variant="tonal"
                @click="openDuplicateDialog((item as any).id)"
              >
                <VIcon icon="tabler-copy" />
              </IconBtn>

              <IconBtn
                color="error"
                variant="tonal"
                @click="openDeleteDialog((item as any).id)"
              >
                <VIcon icon="tabler-trash" />
              </IconBtn>
            </template>
          </div>
        </div>

        <TablePagination
          v-model:page="page"
          :items-per-page="itemsPerPage"
          :total-items="totalPublicaciones"
          class="publication-mobile-list__pagination"
        />
      </div>
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
      <VCard class="motor-card-enhanced overflow-hidden">
        <VCardTitle class="pa-0">
          <div class="d-flex align-center gap-2 pa-4 bg-surface border-b">
            <VIcon icon="tabler-alert-triangle" color="error" />
            <span class="text-h6 font-weight-bold text-premium-title">
              {{ publicacionToDelete === 0 ? t('publication_list.delete_selected_dialog_title') : t('publication_list.delete_dialog_title') }}
            </span>
          </div>
        </VCardTitle>
        <VCardText class="pa-6 pt-8">
          <div class="d-flex flex-column align-center text-center">
            <VAvatar size="72" color="error" variant="tonal" class="mb-4">
              <VIcon icon="tabler-trash-x" size="40" />
            </VAvatar>
            <p class="text-body-1 text-high-emphasis font-weight-medium">
              {{ publicacionToDelete === 0 ? t('publication_list.delete_selected_dialog_text', { count: selectedRows.length }) : t('publication_list.delete_dialog_text') }}
            </p>
            <p class="text-caption text-medium-emphasis mt-2">
              {{ t('publication_list.delete_irreversible', 'Esta acción no se puede deshacer.') }}
            </p>
          </div>
        </VCardText>
        <VCardActions class="pa-6 pt-0">
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            class="px-6"
            @click="isDeleteDialogVisible = false"
          >
            {{ t('publication_list.cancel') }}
          </VBtn>
          <VBtn
            color="error"
            variant="flat"
            class="px-6"
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
      <VCard class="motor-card-enhanced overflow-hidden">
        <VCardTitle class="pa-0">
          <div class="d-flex align-center gap-2 pa-4 bg-surface border-b">
            <VIcon icon="tabler-copy" color="primary" />
            <span class="text-h6 font-weight-bold text-premium-title">
              {{ t('publication_list.duplicate_dialog_title') }}
            </span>
          </div>
        </VCardTitle>
        <VCardText class="pa-6 pt-8 text-center">
          <VAvatar size="72" color="primary" variant="tonal" class="mb-4">
            <VIcon icon="tabler-copy-check" size="40" />
          </VAvatar>
          <p class="text-body-1 text-high-emphasis">
            {{ t('publication_list.duplicate_dialog_text') }}
          </p>
        </VCardText>
        <VCardActions class="pa-6 pt-0">
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            class="px-6"
            @click="isDuplicateDialogVisible = false"
          >
             {{ t('publication_list.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            class="px-6"
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
      <VCard class="motor-card-enhanced overflow-hidden">
        <VCardTitle class="pa-0">
          <div class="d-flex align-center gap-2 pa-4 bg-surface border-b">
            <VIcon icon="tabler-refresh" color="info" />
            <span class="text-h6 font-weight-bold text-premium-title">
              {{ t('publication_list.status_dialog_title') }}
            </span>
          </div>
        </VCardTitle>
        <VCardText class="pa-6 pt-8 text-center">
          <VAvatar size="72" color="info" variant="tonal" class="mb-4">
            <VIcon icon="tabler-zoom-check" size="40" />
          </VAvatar>
          <p class="text-body-1 text-high-emphasis">
            {{ t('publication_list.status_dialog_text') }}
          </p>
        </VCardText>
        <VCardActions class="pa-6 pt-0">
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            class="px-6"
            @click="isStatusDialogVisible = false"
          >
            {{ t('publication_list.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            class="px-6"
            @click="changeStatus"
          >
            {{ t('publication_list.confirm_status') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.publication-table-shell {
  overflow-x: auto;
  width: 100%;
  -webkit-overflow-scrolling: touch;
}

.publication-list-mobile-controls,
.publication-mobile-list {
  display: none;
}

  .publication-list-toolbar {
  row-gap: 1rem;
}

.publication-list-actions {
  row-gap: 0.75rem;
}

@media (max-width: 959px) {
  .publication-list-toolbar {
    display: none;
  }

  .publication-table-shell--desktop {
    display: none;
  }

  .publication-list-actions > * {
    width: 100%;
  }

  .publication-list-actions {
    align-items: stretch;
  }

  .publication-list-actions .v-btn {
    width: 100%;
  }

  .publication-list-mobile-controls,
  .publication-mobile-list {
    display: block;
  }

  .publication-list-mobile-controls {
    padding: 0 1.5rem 1rem;
    display: grid;
    gap: 0.75rem;
  }

  .publication-list-mobile-controls :deep(.v-field),
  .publication-list-mobile-controls :deep(.v-input),
  .publication-list-mobile-controls :deep(.v-select),
  .publication-list-mobile-controls :deep(.v-text-field),
  .publication-list-mobile-controls :deep(.v-btn) {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    inline-size: 100%;
  }

  .publication-list-mobile-per-page {
    width: 100%;
  }

  .publication-mobile-list {
    padding: 0 1.5rem 1.5rem;
  }

  .publication-mobile-card {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
    padding: 0.9rem;
    margin-top: 0.75rem;
  }

  .publication-mobile-card__top,
  .publication-mobile-card__body,
  .publication-mobile-card__actions {
    display: flex;
    align-items: center;
  }

  .publication-mobile-card__top {
    justify-content: space-between;
    margin-bottom: 0.5rem;
  }

  .publication-mobile-card__body {
    display: grid;
    grid-template-columns: 56px minmax(0, 1fr);
    align-items: flex-start;
    gap: 0.75rem;
  }

  .publication-mobile-card__thumb {
    flex: 0 0 auto;
  }

  .publication-mobile-card__content {
    flex: 1 1 auto;
    min-width: 0;
  }

  .publication-mobile-card__title {
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.25;
    color: rgba(15, 23, 42, 0.96);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    overflow-wrap: anywhere;
  }

  .publication-mobile-card__ref {
    margin-top: 0.2rem;
    color: rgba(15, 23, 42, 0.7);
    font-size: 0.875rem;
    line-height: 1.2;
    overflow-wrap: anywhere;
  }

  .publication-mobile-card__price {
    margin-top: 0.55rem;
    color: rgb(var(--v-theme-primary));
    font-weight: 700;
  }

  .publication-mobile-card__actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(44px, 1fr));
    gap: 0.5rem;
    margin-top: 0.75rem;
  }

  .publication-mobile-card__actions :deep(.v-btn) {
    width: 100%;
    min-width: 0;
  }
}
</style>
