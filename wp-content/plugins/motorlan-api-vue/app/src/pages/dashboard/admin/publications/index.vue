<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'
import { debounce } from '@/utils/debounce'
import { useUserStore } from '@/@core/stores/user'
import AppSelect from '@/@core/components/app-form-elements/AppSelect.vue'
import AppTextField from '@/@core/components/app-form-elements/AppTextField.vue'
import ContactPublisherModal from './components/ContactPublisherModal.vue'

// Basic types
interface Author {
  id: number
  name: string
  email: string
}

interface PublicationItem {
  id: number
  uuid: string
  title: string
  status: string
  date: string
  link: string
  image: string
  price: string
  author: Author
}

interface ApiResponse {
  data: PublicationItem[]
  total: number
  pages: number
}

const { t } = useI18n()
const router = useRouter()
const userStore = useUserStore()

// Data table headers
const headers = [
  { title: t('Imagen'), value: 'image', sortable: false },
  { title: t('Título'), value: 'title' },
  { title: t('Autor'), value: 'author' },
  { title: t('Precio'), value: 'price' },
  { title: t('Estado'), value: 'status' },
  { title: t('Fecha'), value: 'date' },
  { title: t('Acciones'), value: 'actions', sortable: false },
]

// Filters & State
const searchQuery = ref('')
const selectedStatus = ref()
const statusOptions = computed(() => [
  { title: t('Todos'), value: 'all' },
  { title: t('Publicado'), value: 'publish' },
  { title: t('Pendiente'), value: 'pending' },
  { title: t('Borrador'), value: 'draft' },
  { title: t('Pausado'), value: 'paused' },
  { title: t('Vendido'), value: 'sold' },
  { title: t('Papelera'), value: 'trash' },
])

// Pagination
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()

// API URL Construction
const apiUrl = computed(() => {
  const params = new URLSearchParams()
  if (searchQuery.value) params.append('search', searchQuery.value)
  if (selectedStatus.value && selectedStatus.value !== 'all') params.append('status', selectedStatus.value)
  
  // Exclude current user's publications
  const userId = userStore.user?.id
  if (userId) {
      params.append('exclude_author', userId.toString())
  }
  
  params.append('page', page.value.toString())
  params.append('per_page', itemsPerPage.value.toString())
  
  if (sortBy.value) params.append('orderby', sortBy.value)
  if (orderBy.value) params.append('order', orderBy.value)

  return `/wp-json/motorlan/v1/admin/publications?${params.toString()}`
})

// Fetch Data
const { data: apiData, execute: fetchPublications, isFetching: isTableLoading } = useApi<ApiResponse>(apiUrl, { immediate: false }).get().json()

const publications = computed((): PublicationItem[] => {
  if (!apiData.value) return []
  return apiData.value.data || []
})

const totalPublications = computed(() => apiData.value?.total || 0)

// Debounced Search
const debouncedFetch = debounce(async () => {
    page.value = 1 // Reset to first page on search
    await fetchPublications()
}, 500)

watch([searchQuery, selectedStatus, itemsPerPage], () => debouncedFetch())
watch([page], () => fetchPublications())

onMounted(() => {
  fetchPublications()
})

const updateOptions = (options: any) => {
  if (options.sortBy && options.sortBy.length) {
    sortBy.value = options.sortBy[0].key
    orderBy.value = options.sortBy[0].order
    fetchPublications()
  }
}

// Actions State
const isLoadingAction = ref(false)
const isDeleteDialogVisible = ref(false)
const itemToDelete = ref<number | null>(null)

const isStatusDialogVisible = ref(false)
const itemToChangeStatus = ref<{ id: number, status: string } | null>(null)
const selectedNewStatus = ref('publish')

const isContactDialogVisible = ref(false)
const contactTarget = ref<{ id: number, title: string } | null>(null)

// Helpers
const resolveStatus = (status: string) => {
  const map: Record<string, { color: string, text: string }> = {
    publish: { color: 'success', text: t('Publicado') },
    pending: { color: 'warning', text: t('Pendiente') },
    draft: { color: 'secondary', text: t('Borrador') },
    trash: { color: 'error', text: t('Papelera') },
    paused: { color: 'info', text: t('Pausado') },
    sold: { color: 'success', text: t('Vendido') },
  }
  return map[status] || { color: 'primary', text: status }
}

const formatDate = (dateStr: string) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString()
}

// Action Handlers
const openDeleteDialog = (id: number) => {
  itemToDelete.value = id
  isDeleteDialogVisible.value = true
}

const confirmDelete = async () => {
  if (!itemToDelete.value) return
  
  isLoadingAction.value = true
  try {
    await useApi(`/wp-json/motorlan/v1/admin/publications/${itemToDelete.value}`, { method: 'DELETE' })
    fetchPublications()
  } catch (e) {
    console.error(e)
    // Show error toast
  } finally {
    isLoadingAction.value = false
    isDeleteDialogVisible.value = false
    itemToDelete.value = null
  }
}

const openStatusDialog = (item: PublicationItem) => {
  itemToChangeStatus.value = { id: item.id, status: item.status }
  selectedNewStatus.value = item.status // Default to current
  isStatusDialogVisible.value = true
}

const confirmStatusChange = async () => {
  if (!itemToChangeStatus.value) return

  isLoadingAction.value = true
  try {
    await useApi(`/wp-json/motorlan/v1/admin/publications/${itemToChangeStatus.value.id}`, {
      method: 'PUT',
      body: JSON.stringify({ status: selectedNewStatus.value })
    })
    fetchPublications()
  } catch (e) {
    console.error(e)
  } finally {
    isLoadingAction.value = false
    isStatusDialogVisible.value = false
    itemToChangeStatus.value = null
  }
}

const openContactDialog = (item: PublicationItem) => {
  contactTarget.value = { id: item.id, title: item.title }
  isContactDialogVisible.value = true
}

const openEdit = (uuid: string) => {
    if (uuid) {
        router.push(`/dashboard/publications/publication/edit/${uuid}`)
    }
}

</script>

<template>
  <VCard class="motor-card-enhanced">
    <VCardTitle class="pa-6 pb-4 d-flex justify-space-between align-center">
      <span class="text-h5 text-premium-title">{{ t('Administrar Publicaciones') }}</span>
      <div class="d-flex gap-4">
        <!-- Add export or other global actions here -->
      </div>
    </VCardTitle>

    <VDivider />

    <VCardText class="pa-6">
      <VRow>
        <VCol cols="12" md="4">
          <AppTextField
            v-model="searchQuery"
            :placeholder="t('Buscar publicación...')"
            prepend-inner-icon="tabler-search"
            clearable
          />
        </VCol>
        <VCol cols="12" md="4">
          <AppSelect
            v-model="selectedStatus"
            :items="statusOptions"
            :placeholder="t('Filtrar por estado')"
            item-title="title"
            item-value="value"
            prepend-inner-icon="tabler-filter"
            clearable
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDataTableServer
      v-model:items-per-page="itemsPerPage"
      v-model:page="page"
      :headers="headers"
      :items="publications"
      :items-length="totalPublications"
      :loading="isTableLoading"
      @update:options="updateOptions"

    >
      <!-- Image Column -->
      <template #item.image="{ item }">
        <VAvatar
          size="40"
          rounded
          variant="tonal"
          class="me-3"
        >
          <VImg v-if="item.image" :src="item.image" cover />
          <VIcon v-else icon="tabler-photo" />
        </VAvatar>
      </template>

      <!-- Title & Author Column (Custom) -->
      <!-- Actually I have separate columns defined in headers. -->
      
      <!-- Author Column -->
      <template #item.author="{ item }">
        <div class="d-flex flex-column">
          <span class="font-weight-medium text-body-2">{{ item.author?.name }}</span>
          <span class="text-caption text-disabled">{{ item.author?.email }}</span>
        </div>
      </template>

      <!-- Price Column -->
      <template #item.price="{ item }">
        <span v-if="item.price" class="font-weight-bold text-primary">{{ item.price }}€</span>
        <span v-else class="text-disabled">-</span>
      </template>

      <!-- Status Column -->
      <template #item.status="{ item }">
        <VChip
          :color="resolveStatus(item.status).color"
          size="small"
          label
        >
          {{ resolveStatus(item.status).text }}
        </VChip>
      </template>

      <!-- Date Column -->
      <template #item.date="{ item }">
        <span class="text-body-2">{{ formatDate(item.date) }}</span>
      </template>

      <!-- Actions Column -->
      <template #item.actions="{ item }">
        <div class="d-flex gap-2 justify-end">
          
          <!-- View (only if published) -->
          <IconBtn 
            v-if="item.status === 'publish'"
            size="small" 
            variant="tonal" 
            color="success" 
            :href="item.link" 
            target="_blank"
            rel="noopener noreferrer"
          >
            <VIcon icon="tabler-eye" />
            <VTooltip activator="parent" location="top">{{ t('Ver Publicación') }}</VTooltip>
          </IconBtn>
          
          <!-- Contact -->
          <IconBtn size="small" variant="tonal" color="info" @click="openContactDialog(item)">
            <VIcon icon="tabler-mail" />
            <VTooltip activator="parent" location="top">{{ t('Contactar') }}</VTooltip>
          </IconBtn>

          <!-- Edit -->
          <IconBtn size="small" variant="tonal" color="primary" @click="openEdit(item.uuid)">
            <VIcon icon="tabler-pencil" />
             <VTooltip activator="parent" location="top">{{ t('Editar') }}</VTooltip>
          </IconBtn>

          <VMenu>
            <template #activator="{ props }">
              <IconBtn size="small" variant="tonal" color="secondary" v-bind="props">
                <VIcon icon="tabler-dots-vertical" />
              </IconBtn>
            </template>
            <VList density="compact">
               <VListItem @click="openStatusDialog(item)">
                <template #prepend><VIcon icon="tabler-refresh" size="16" class="me-2" /></template>
                <VListItemTitle>{{ t('Cambiar Estado') }}</VListItemTitle>
              </VListItem>
              <VDivider />
              <VListItem @click="openDeleteDialog(item.id)" class="text-error">
                <template #prepend><VIcon icon="tabler-trash" size="16" class="me-2" /></template>
                <VListItemTitle>{{ t('Eliminar') }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </div>
      </template>
    </VDataTableServer>
  </VCard>

  <!-- Modals -->
  <ContactPublisherModal
    v-model:isDialogVisible="isContactDialogVisible"
    :publication-id="contactTarget?.id || null"
    :publication-title="contactTarget?.title || ''"
  />

  <!-- Delete Dialog -->
  <VDialog v-model="isDeleteDialogVisible" max-width="400">
    <VCard>
      <VCardTitle class="text-h6 pa-4">{{ t('¿Estás seguro?') }}</VCardTitle>
      <VCardText>{{ t('Esta acción eliminará la publicación permanentemente.') }}</VCardText>
      <VCardActions class="justify-end">
        <VBtn variant="text" @click="isDeleteDialogVisible = false">{{ t('Cancelar') }}</VBtn>
        <VBtn color="error" :loading="isLoadingAction" @click="confirmDelete">{{ t('Eliminar') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Status Dialog -->
  <VDialog v-model="isStatusDialogVisible" max-width="400">
    <VCard>
      <VCardTitle>{{ t('Cambiar Estado') }}</VCardTitle>
      <VCardText>
        <AppSelect
          v-model="selectedNewStatus"
          :items="statusOptions.filter(o => o.value !== 'all')"
          item-title="title"
          item-value="value"
          label="Nuevo Estado"
          class="mt-2"
        />
      </VCardText>
      <VCardActions class="justify-end">
         <VBtn variant="text" @click="isStatusDialogVisible = false">{{ t('Cancelar') }}</VBtn>
        <VBtn color="primary" :loading="isLoadingAction" @click="confirmStatusChange">{{ t('Guardar') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
