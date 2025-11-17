<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { ref, computed } from 'vue'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'

const { t } = useI18n()

const headers = [
  { title: 'Publicacion', key: 'publicacion' },
  { title: 'Pregunta', key: 'pregunta' },
  { title: 'Respuesta', key: 'respuesta' },
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

const { data: questionsData, execute: fetchQuestions, isFetching: isTableLoading } = await useApi<any>(createUrl('/wp-json/motorlan/v1/user/questions',
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

const questions = computed(() => (questionsData.value?.data || []))
const totalQuestions = computed(() => questionsData.value?.pagination.total || 0)
</script>

<template>
  <div>
    <VCard
      :title="t('Preguntas')"
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
        :items="questions"
        :items-length="totalQuestions"
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
       <template #item.publicacion="{ item }">
         <div
            v-if="item.publicacion"
            class="d-flex align-center gap-x-4"
          >
            <VAvatar
              v-if="item.publicacion.imagen_destacada"
              size="38"
              variant="tonal"
              rounded
              :image="item.publicacion.imagen_destacada.url"
            />
            <div class="d-flex flex-column">
              <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.publicacion.title }}</span>
              <span class="text-body-2">{{ item.publicacion.acf.marca.name }}</span>
            </div>
          </div>
        </template>

        <!-- pregunta -->
        <template #item.pregunta="{ item }">
          <span class="text-body-1 text-high-emphasis">{{ item.pregunta }}</span>
        </template>

        <!-- respuesta -->
        <template #item.respuesta="{ item }">
          <span class="text-body-1 text-high-emphasis">{{ item.respuesta }}</span>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <IconBtn @click="$router.push(`/public-store/${item.publicacion.slug}`)">
            <VIcon icon="tabler-eye" />
          </IconBtn>
        </template>

        <!-- pagination -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalQuestions"
          />
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
