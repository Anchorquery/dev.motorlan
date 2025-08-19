<script setup lang="ts">
import type { ImagenDestacada, Motor } from '../../../../../interfaces/motor'

const widgetData = ref([
  { title: 'Sales', value: '$5,345', icon: 'tabler-smart-home', desc: '5k orders', change: 5.7 },
  { title: 'Website Sales', value: '$674,347', icon: 'tabler-device-laptop', desc: '21k orders', change: 12.4 },
  { title: 'Discount', value: '$14,235', icon: 'tabler-gift', desc: '6k orders' },
  { title: 'Affiliate', value: '$8,345', icon: 'tabler-wallet', desc: '150 orders', change: -3.5 },
])

const headers = [
  { title: 'Motor', key: 'motor' },
  { title: 'Referencia', key: 'referencia' },
  { title: 'Precio', key: 'precio' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions', sortable: false },
]

const selectedStatus = ref()
const selectedCategory = ref()
const searchQuery = ref('')
const selectedRows = ref([])

const status = ref([
  { title: 'Publicado', value: 'publish' },
  { title: 'Borrador', value: 'draft' },
  { title: 'Pausado', value: 'paused' },
  { title: 'Vendido', value: 'sold' },
])
const categories = ref([])

// Fetch categories from the new endpoint
const { data: categoriesData } = await useApi<any>(createUrl('/wp-json/wp/v2/motor-categories'))
if (categoriesData.value) {
  categories.value = categoriesData.value.map((cat: any) => ({
    title: cat.name,
    value: cat.slug,
  }))
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

const resolveStatus = (statusMsg: string) => {
  if (statusMsg === 'publish')
    return { text: 'Publicado', color: 'success' }
  if (statusMsg === 'draft')
    return { text: 'Borrador', color: 'secondary' }
  if (statusMsg === 'paused')
    return { text: 'Pausado', color: 'warning' }
  if (statusMsg === 'sold')
    return { text: 'Vendido', color: 'error' }
  return { text: 'Unknown', color: 'info' }
}

const { data: motorsData, execute: fetchMotors } = await useApi<any>(createUrl('/wp-json/wp/v2/motors',
  {
    query: {
      search: searchQuery,
      category: selectedCategory,
      status: selectedStatus,
      page,
      per_page: itemsPerPage,
      orderby: sortBy,
      order: orderBy,
    },
  },


))

const motors = computed((): Motor[] => (motorsData.value?.data || []).filter(Boolean))
const totalMotors = computed(() => motorsData.value?.pagination.total || 0)

const deleteMotor = async (id: number) => {
  await $api(`/wp-json/wp/v2/motors/${id}`, {
    method: 'DELETE',
  })

  // Delete from selectedRows
  const index = selectedRows.value.findIndex(row => row === id)
  if (index !== -1)
    selectedRows.value.splice(index, 1)

  // Refetch motors
  fetchMotors()
}

const duplicateMotor = async (id: number) => {
  await $api(`/wp-json/wp/v2/motors/${id}/duplicate`, {
    method: 'POST',
  })
  fetchMotors()
}

const changeStatus = async (id: number, status: string) => {
  await $api(`/wp-json/wp/v2/motors/${id}/status`, {
    method: 'POST',
    body: { status },
  })
  fetchMotors()
}

const getImageBySize = (image: ImagenDestacada | null | any[], size = 'thumbnail'): string => {
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

    <!-- 👉 motors -->
    <VCard
      title="Filters"
      class="mb-6"
    >
      <VCardText>
        <VRow>
          <!-- 👉 Select Status -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="selectedStatus"
              placeholder="Status"
              :items="status"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>

          <!-- 👉 Select Category -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="selectedCategory"
              placeholder="Category"
              :items="categories"
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
            placeholder="Search Motor"
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
            Export
          </VBtn>

          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="$router.push('/apps/motors/motor/add')"
          >
            Add Motor
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
        :items="motors"
        :items-length="totalMotors"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- motor  -->
        <template #item.motor="{ item }">
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              v-if="(item as any).raw.imagen_destacada"
              size="38"
              variant="tonal"
              rounded
              :image="getImageBySize((item as any).raw.imagen_destacada, 'thumbnail')"
            />
            <div class="d-flex flex-column">
              <span class="text-body-1 font-weight-medium text-high-emphasis">{{ (item as any).raw.title }}</span>
              <span class="text-body-2">{{ (item as any).raw.acf.marca }}</span>
            </div>
          </div>
        </template>

        <!-- referencia -->
        <template #item.referencia="{ item }">
          <span class="text-body-1 text-high-emphasis">{{ (item as any).raw.acf.tipo_o_referencia }}</span>
        </template>

        <!-- precio -->
        <template #item.precio="{ item }">
          <span class="text-body-1 text-high-emphasis">{{ (item as any).raw.acf.precio_de_venta }}</span>
        </template>

        <!-- status -->
        <template #item.status="{ item }">
          <VChip
            v-bind="resolveStatus((item as any).raw.status)"
            density="default"
            label
            size="small"
          />
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <IconBtn @click="$router.push(`/apps/motors/motor/edit/${(item as any).raw.uuid}`)">
            <VIcon icon="tabler-edit" />
          </IconBtn>

          <IconBtn>
            <VIcon icon="tabler-dots-vertical" />
            <VMenu activator="parent">
              <VList>
                <VListItem
                  value="delete"
                  prepend-icon="tabler-trash"
                  @click="deleteMotor((item as any).raw.id)"
                >
                  Delete
                </VListItem>

                <VListItem
                  value="duplicate"
                  prepend-icon="tabler-copy"
                  @click="duplicateMotor((item as any).raw.id)"
                >
                  Duplicate
                </VListItem>

                <VListItem
                  v-if="(item as any).raw.status !== 'publish'"
                  value="publish"
                  prepend-icon="tabler-player-play"
                  @click="changeStatus((item as any).raw.id, 'publish')"
                >
                  Publish
                </VListItem>

                <VListItem
                  v-if="(item as any).raw.status !== 'paused'"
                  value="pause"
                  prepend-icon="tabler-player-pause"
                  @click="changeStatus((item as any).raw.id, 'paused')"
                >
                  Pause
                </VListItem>

                <VListItem
                  v-if="(item as any).raw.status !== 'draft'"
                  value="draft"
                  prepend-icon="tabler-file-text"
                  @click="changeStatus((item as any).raw.id, 'draft')"
                >
                  Move to Draft
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
            :total-items="totalMotors"
          />
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
