<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'
import { useUserStore } from '@/@core/stores/user'
import type { Publicacion } from '@/interfaces/publicacion'

const { t } = useI18n()
const router = useRouter()
const userStore = useUserStore()

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

type BrandTerm = { term_id: number; name: string; slug: string }
const { data: brandsResponse, execute: fetchBrands } = useApi<BrandTerm[]>('/wp-json/motorlan/v1/marcas', { immediate: false }).get().json()
const brands = computed<BrandTerm[]>(() => brandsResponse.value || [])
const brandById = computed<Record<number, BrandTerm>>(() => Object.fromEntries(brands.value.map(b => [Number(b.term_id), b])))
const brandBySlug = computed<Record<string, BrandTerm>>(() => Object.fromEntries(brands.value.map(b => [String(b.slug), b])))

const resolveBrandName = (value: any): string | null => {
  if (!value && value !== 0)
    return null
  if (typeof value === 'object') {
    const name = (value?.name || value?.label || value?.title) as string | undefined
    if (name && name.trim().length)
      return name
    const id = Number(value?.id ?? value?.term_id ?? 0) || null
    if (id && brandById.value[id])
      return brandById.value[id].name
  }
  const asNum = Number(value)
  if (Number.isFinite(asNum) && asNum > 0 && brandById.value[asNum])
    return brandById.value[asNum].name
  const asStr = String(value)
  if (brandBySlug.value[asStr])
    return brandBySlug.value[asStr].name
  return asStr && asStr !== 'null' && asStr !== 'undefined' ? asStr : null
}

const formatPublicationTitle = (pub: any, fallbackTitle?: string): string => {
  if (!pub)
    return fallbackTitle || ''
  const acf = pub.acf || {}
  const parts = [
    pub.title || fallbackTitle,
    resolveBrandName(acf.marca),
    acf.velocidad ? `${acf.velocidad} rpm` : null,
    acf.potencia ? `${acf.potencia} kW` : null,
  ].filter(Boolean) as string[]
  return parts.join(' · ')
}

watch([page, itemsPerPage, sortBy, orderBy, searchQuery, selectedStatus], () => {
  void fetchPublications()
}, { immediate: true })

onMounted(() => {
  void fetchBrands()
})

const refresh = () => {
  fetchPublications()
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

      <div class="px-6 pb-6">
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
          <div class="d-flex align-center gap-x-4 py-2">
            <VAvatar
              v-if="(item as any).imagen_destacada"
              size="48"
              variant="tonal"
              rounded
              class="border"
            >
              <VImg :src="(item as any).imagen_destacada.url" alt="Publication Image" cover />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ formatPublicationTitle(item, (item as any).title) }}
              </span>
              <span
                v-if="(item as any).acf?.tipo_o_referencia"
                class="text-caption text-medium-emphasis"
              >
                Ref: {{ (item as any).acf.tipo_o_referencia }}
              </span>
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
              <IconBtn
                v-if="(item as any).author?.id === currentUser?.id || currentUser?.isAdmin"
                color="primary"
                variant="tonal"
                size="small"
                 @click="router.push(`/dashboard/publications/publication/edit/${(item as any).uuid}`)"
              >
                <VIcon
                  icon="tabler-pencil"
                  size="18"
                />
                 <VTooltip activator="parent" location="top">Editar</VTooltip>
              </IconBtn>
              <IconBtn
                color="secondary"
                variant="tonal"
                size="small"
                :to="`/${(item as any).slug}`"
                 target="_blank"
              >
                <VIcon
                  icon="tabler-eye"
                  size="18"
                />
                 <VTooltip activator="parent" location="top">Ver</VTooltip>
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
</div>
</template>
