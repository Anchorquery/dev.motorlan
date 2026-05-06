<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { ref, computed } from 'vue'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'
import { useRouter } from 'vue-router'

const { t } = useI18n()
const router = useRouter()

const headers = [
  { title: 'Publicacion', key: 'publicacion' },
  { title: 'Pregunta', key: 'pregunta' },
  { title: 'Respuesta', key: 'respuesta' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const searchQuery = ref('')
const selectedStatus = ref(null)

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()

// Status options (Placeholder as API support wasn't clear in original code, but fixing runtime error)
const statusOptions = [
  { title: 'Todas', value: null },
  { title: 'Pendientes', value: 'pending' },
  { title: 'Respondidas', value: 'replied' },
]

// Update data table options
const updateOptions = (options: any) => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const { data: questionsData, execute: fetchQuestions, isFetching: isTableLoading } = useApi<any>(createUrl('/wp-json/motorlan/v1/user/questions',
  {
    query: {
      search: searchQuery,
      page,
      per_page: itemsPerPage,
      orderby: sortBy,
      order: orderBy,
      status: selectedStatus, // Attempting to pass status if API supports it
    },
  }
)).get().json()

const questions = computed(() => (questionsData.value?.data || []))
const totalQuestions = computed(() => questionsData.value?.pagination?.total || 0)
</script>

<template>
  <div>
    <VCard class="motor-card-enhanced overflow-visible">
      <VCardTitle class="pa-6 pb-0">
        <span class="text-h5 text-premium-title">{{ t('Preguntas') }}</span>
      </VCardTitle>

      <VCardText class="pa-6">
        <VRow>
          <!-- Search -->
          <VCol cols="12" md="4">
            <AppTextField
              v-model="searchQuery"
              :placeholder="t('publication_list.search_publication')"
              prepend-inner-icon="tabler-search"
              clearable
              class="elevation-0"
            />
          </VCol>

          <!-- Status Filter -->
          <VCol cols="12" md="4">
            <AppSelect
              v-model="selectedStatus"
              :placeholder="t('publication_list.status')"
              :items="statusOptions"
              item-title="title"
              item-value="value"
              prepend-inner-icon="tabler-filter"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>

          <!-- Per Page -->
          <VCol cols="12" md="4">
             <AppSelect
               v-model="itemsPerPage"
               :items="[5, 10, 20, 25, 50]"
               placeholder="Mostrar"
               prepend-inner-icon="tabler-list-numbers"
             />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <!-- Datatable -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :headers="headers"
        :items="questions"
        :items-length="totalQuestions"
        :loading="isTableLoading"
        class="text-no-wrap px-6 pb-6"
        @update:options="updateOptions"
      >

       <!-- Publicacion -->
       <template #item.publicacion="{ item }: { item: any }">
         <div
            v-if="item.publicacion"
            class="d-flex align-center gap-3 py-2"
          >
            <VAvatar
              v-if="item.publicacion.imagen_destacada"
              size="48"
              variant="tonal"
              rounded
              class="border"
              :image="item.publicacion.imagen_destacada.url"
            />
            <div class="d-flex flex-column">
              <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.publicacion.title }}</span>
              <span v-if="item.publicacion.acf?.marca?.name" class="text-caption text-medium-emphasis">
                {{ item.publicacion.acf.marca.name }}
              </span>
            </div>
          </div>
       </template>

        <!-- Pregunta -->
        <template #item.pregunta="{ item }: { item: any }">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-message-circle-question" size="16" class="text-medium-emphasis" />
            <span class="text-body-1 text-high-emphasis">{{ item.pregunta }}</span>
          </div>
        </template>

        <!-- Respuesta -->
        <template #item.respuesta="{ item }: { item: any }">
          <div v-if="item.respuesta" class="d-flex align-center gap-2">
            <VIcon icon="tabler-message-check" size="16" color="success" />
            <span class="text-body-1 text-high-emphasis">{{ item.respuesta }}</span>
          </div>
          <VChip v-else size="small" color="warning" variant="tonal" label>
            Pendiente
          </VChip>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }: { item: any }">
          <IconBtn 
            size="small" 
            variant="tonal"
            color="secondary"
            :to="`/${item.publicacion.slug}`"
          >
            <VIcon icon="tabler-external-link" />
            <VTooltip activator="parent" location="top">Ver publicación</VTooltip>
          </IconBtn>
        </template>

        <!-- Pagination -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalQuestions"
            class="mt-4"
          />
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
