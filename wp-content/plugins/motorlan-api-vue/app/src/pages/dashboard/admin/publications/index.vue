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
import { useMotorFormatter } from '@/composables/useMotorFormatter'
import { storeUrl } from '@/utils/urls'

// Basic types
interface Author {
  id: number
  name: string
  email: string
}

interface PublicationItem {
  id: number
  uuid: string
  slug: string
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
const { formatMotorName } = useMotorFormatter()

// Data table headers
const headers = computed(() => [
  { title: t('admin_publications.headers.image'), value: 'image', sortable: false },
  { title: t('admin_publications.headers.title'), value: 'title' },
  { title: t('admin_publications.headers.author'), value: 'author' },
  { title: t('admin_publications.headers.price'), value: 'price' },
  { title: t('admin_publications.headers.status'), value: 'status' },
  { title: t('admin_publications.headers.date'), value: 'date' },
  { title: t('admin_publications.headers.actions'), value: 'actions', sortable: false },
])

// Filters & State
const searchQuery = ref('')
const selectedStatus = ref()
const statusOptions = computed(() => [
  { title: t('admin_publications.status_options.all'), value: 'all' },
  { title: t('admin_publications.status_options.publish'), value: 'publish' },
  { title: t('admin_publications.status_options.pending'), value: 'pending' },
  { title: t('admin_publications.status_options.draft'), value: 'draft' },
  { title: t('admin_publications.status_options.paused'), value: 'paused' },
  { title: t('admin_publications.status_options.sold'), value: 'sold' },
  { title: t('admin_publications.status_options.trash'), value: 'trash' },
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
    publish: { color: 'success', text: t('admin_publications.status_options.publish') },
    pending: { color: 'warning', text: t('admin_publications.status_options.pending') },
    draft: { color: 'secondary', text: t('admin_publications.status_options.draft') },
    trash: { color: 'error', text: t('admin_publications.status_options.trash') },
    paused: { color: 'info', text: t('admin_publications.status_options.paused') },
    sold: { color: 'success', text: t('admin_publications.status_options.sold') },
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
    <VCardTitle class="pa-6 pb-4 d-flex justify-space-between align-center admin-publications-toolbar">
      <span class="text-h5 text-premium-title">{{ t('admin_publications.title') }}</span>
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
            :placeholder="t('admin_publications.search_placeholder')"
            prepend-inner-icon="tabler-search"
            clearable
          />
        </VCol>
        <VCol cols="12" md="4">
          <AppSelect
            v-model="selectedStatus"
            :items="statusOptions"
            :placeholder="t('admin_publications.status_filter_placeholder')"
            item-title="title"
            item-value="value"
            prepend-inner-icon="tabler-filter"
            clearable
          />
        </VCol>
      </VRow>
    </VCardText>

    <div class="table-responsive-shell admin-publications-table-shell admin-pub-table--desktop">
    <VDataTableServer
      v-model:items-per-page="itemsPerPage"
      v-model:page="page"
      :headers="headers"
      :items="publications"
      :items-length="totalPublications"
      :loading="isTableLoading"
      :mobile-breakpoint="0"
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
          <VImg v-if="item.image" :src="typeof item.image === 'string' ? item.image : (item.image as any)?.url" cover />
          <VIcon v-else icon="tabler-photo" />
        </VAvatar>
      </template>

      <!-- Title Column -->
      <template #item.title="{ item }">
        <span 
          class="text-body-1 font-weight-medium text-high-emphasis text-truncate d-block"
          style="max-width: 250px;"
        >
          {{ formatMotorName(item) || item.title }}
          <VTooltip activator="parent" location="top">{{ formatMotorName(item) || item.title }}</VTooltip>
        </span>
      </template>
      
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
            :href="storeUrl(item.slug)"
            target="_blank"
            rel="noopener noreferrer"
          >
            <VIcon icon="tabler-eye" />
            <VTooltip activator="parent" location="top">{{ t('admin_publications.actions.view') }}</VTooltip>
          </IconBtn>

          <!-- Contact -->
          <IconBtn size="small" variant="tonal" color="info" @click="openContactDialog(item)">
            <VIcon icon="tabler-mail" />
            <VTooltip activator="parent" location="top">{{ t('admin_publications.actions.contact') }}</VTooltip>
          </IconBtn>

          <!-- Edit -->
          <IconBtn size="small" variant="tonal" color="primary" @click="openEdit(item.uuid)">
            <VIcon icon="tabler-pencil" />
             <VTooltip activator="parent" location="top">{{ t('admin_publications.actions.edit') }}</VTooltip>
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
                <VListItemTitle>{{ t('admin_publications.actions.change_status') }}</VListItemTitle>
              </VListItem>
              <VDivider />
              <VListItem @click="openDeleteDialog(item.id)" class="text-error">
                <template #prepend><VIcon icon="tabler-trash" size="16" class="me-2" /></template>
                <VListItemTitle>{{ t('admin_publications.actions.delete') }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </div>
      </template>

      <!-- pagination -->
      <template #bottom>
        <VDivider />
        <TablePagination
          v-model:page="page"
          :items-per-page="itemsPerPage"
          :total-items="totalPublications"
          class="pa-4"
        />
      </template>
    </VDataTableServer>
    </div>

    <!-- Mobile card list -->
    <div class="admin-pub-mobile-list">
      <div
        v-for="item in publications"
        :key="item.id"
        class="admin-pub-mobile-card"
      >
        <div class="admin-pub-mobile-card__top">
          <VChip
            :color="resolveStatus(item.status).color"
            density="comfortable"
            size="small"
            label
          >
            {{ resolveStatus(item.status).text }}
          </VChip>
          <span class="admin-pub-mobile-card__date">{{ formatDate(item.date) }}</span>
        </div>

        <div class="admin-pub-mobile-card__body">
          <VAvatar
            size="52"
            rounded
            variant="tonal"
            class="admin-pub-mobile-card__thumb flex-shrink-0"
          >
            <VImg v-if="item.image" :src="typeof item.image === 'string' ? item.image : (item.image as any)?.url" cover />
            <VIcon v-else icon="tabler-photo" />
          </VAvatar>

          <div class="admin-pub-mobile-card__content">
            <div class="admin-pub-mobile-card__title">
              {{ formatMotorName(item) || item.title }}
            </div>
            <div class="admin-pub-mobile-card__author">
              {{ item.author?.name }}
              <span v-if="item.author?.email" class="admin-pub-mobile-card__email">{{ item.author.email }}</span>
            </div>
            <div v-if="item.price" class="admin-pub-mobile-card__price">{{ item.price }}€</div>
          </div>
        </div>

        <div class="admin-pub-mobile-card__actions">
          <IconBtn
            v-if="item.status === 'publish'"
            color="success"
            variant="tonal"
            :href="storeUrl(item.slug)"
            target="_blank"
          >
            <VIcon icon="tabler-eye" />
          </IconBtn>

          <IconBtn color="info" variant="tonal" @click="openContactDialog(item)">
            <VIcon icon="tabler-mail" />
          </IconBtn>

          <IconBtn color="primary" variant="tonal" @click="openEdit(item.uuid)">
            <VIcon icon="tabler-pencil" />
          </IconBtn>

          <VMenu>
            <template #activator="{ props }">
              <IconBtn color="secondary" variant="tonal" v-bind="props">
                <VIcon icon="tabler-dots-vertical" />
              </IconBtn>
            </template>
            <VList density="compact">
              <VListItem @click="openStatusDialog(item)">
                <template #prepend><VIcon icon="tabler-refresh" size="16" class="me-2" /></template>
                <VListItemTitle>{{ t('admin_publications.actions.change_status') }}</VListItemTitle>
              </VListItem>
              <VDivider />
              <VListItem @click="openDeleteDialog(item.id)" class="text-error">
                <template #prepend><VIcon icon="tabler-trash" size="16" class="me-2" /></template>
                <VListItemTitle>{{ t('admin_publications.actions.delete') }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </div>
      </div>

      <TablePagination
        v-model:page="page"
        :items-per-page="itemsPerPage"
        :total-items="totalPublications"
        class="admin-pub-mobile-list__pagination"
      />
    </div>
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
      <VCardTitle class="text-h6 pa-4">{{ t('admin_publications.delete_dialog_title') }}</VCardTitle>
      <VCardText>{{ t('admin_publications.delete_dialog_body') }}</VCardText>
      <VCardActions class="justify-end">
        <VBtn variant="text" @click="isDeleteDialogVisible = false">{{ t('admin_publications.cancel') }}</VBtn>
        <VBtn color="error" :loading="isLoadingAction" @click="confirmDelete">{{ t('admin_publications.actions.delete') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Status Dialog -->
  <VDialog v-model="isStatusDialogVisible" max-width="400">
    <VCard>
      <VCardTitle>{{ t('admin_publications.actions.change_status') }}</VCardTitle>
      <VCardText>
        <AppSelect
          v-model="selectedNewStatus"
          :items="statusOptions.filter(o => o.value !== 'all')"
          item-title="title"
          item-value="value"
          :label="t('admin_publications.new_status_label')"
          class="mt-2"
        />
      </VCardText>
      <VCardActions class="justify-end">
         <VBtn variant="text" @click="isStatusDialogVisible = false">{{ t('admin_publications.cancel') }}</VBtn>
        <VBtn color="primary" :loading="isLoadingAction" @click="confirmStatusChange">{{ t('admin_publications.save') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.table-responsive-shell {
  overflow-x: auto;
  width: 100%;
  -webkit-overflow-scrolling: touch;
}

/* Mobile cards — hidden on desktop */
.admin-pub-mobile-list {
  display: none;
}

@media (max-width: 959px) {
  /* Hide desktop table, show mobile cards */
  .admin-pub-table--desktop {
    display: none;
  }

  .admin-pub-mobile-list {
    display: block;
    padding: 0 1rem 1.5rem;
  }

  .admin-pub-mobile-list__pagination {
    margin-top: 0.5rem;
  }

  /* Card */
  .admin-pub-mobile-card {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
    padding: 0.9rem;
    margin-top: 0.75rem;
  }

  /* Top row: status + date */
  .admin-pub-mobile-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.65rem;
  }

  .admin-pub-mobile-card__date {
    font-size: 0.75rem;
    color: rgba(15, 23, 42, 0.5);
  }

  /* Body: avatar + content */
  .admin-pub-mobile-card__body {
    display: grid;
    grid-template-columns: 52px minmax(0, 1fr);
    gap: 0.75rem;
    align-items: flex-start;
  }

  .admin-pub-mobile-card__content {
    min-width: 0;
  }

  .admin-pub-mobile-card__title {
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1.3;
    color: rgba(15, 23, 42, 0.95);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    overflow-wrap: anywhere;
  }

  .admin-pub-mobile-card__author {
    margin-top: 0.2rem;
    font-size: 0.82rem;
    font-weight: 500;
    color: rgba(15, 23, 42, 0.75);
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .admin-pub-mobile-card__email {
    display: block;
    font-size: 0.75rem;
    font-weight: 400;
    color: rgba(15, 23, 42, 0.5);
  }

  .admin-pub-mobile-card__price {
    margin-top: 0.4rem;
    font-size: 1rem;
    font-weight: 700;
    color: rgb(var(--v-theme-primary));
  }

  /* Actions row */
  .admin-pub-mobile-card__actions {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.5rem;
    margin-top: 0.8rem;
  }

  .admin-pub-mobile-card__actions :deep(.v-btn) {
    width: 100%;
    min-width: 0;
  }
}
</style>
