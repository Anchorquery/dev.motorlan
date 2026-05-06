<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { ref, computed, onMounted, unref, watch } from 'vue'
import { useRouter } from 'vue-router'
import type { ImagenDestacada } from '@/interfaces/publicacion'
import type { Question } from '@/interfaces/question'
import type { Pagination } from '@/interfaces/pagination'
import { useApi } from '@/composables/useApi'

const router = useRouter()
const { t } = useI18n()

interface QuestionsData {
  data: Question[]
  pagination: Pagination
}

const headers = [
  { title: t('questions.publication'), key: 'motor' },
  { title: t('questions.question'), key: 'pregunta' },
  { title: t('User'), key: 'user_name' },
  { title: t('questions.question_date'), key: 'question_date' },
  { title: t('questions.answer_date'), key: 'answer_date' },
  { title: t('questions.status'), key: 'estado' },
  { title: t('questions.actions'), key: 'actions', sortable: false },
]

const searchQuery = ref('')

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()

// Update data table options
const updateOptions = (options: any) => {
  page.value = options.page
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

let questionsData = ref<QuestionsData | null>(null)

const fetchUrl = computed(() => {
  const params = new URLSearchParams()
  params.append('page', String(unref(page)))
  params.append('per_page', String(unref(itemsPerPage)))
  if (unref(sortBy))
    params.append('orderby', unref(sortBy))
  if (unref(orderBy))
    params.append('order', unref(orderBy))
  if (unref(searchQuery))
    params.append('search', unref(searchQuery))

  return `/wp-json/motorlan/v1/user/questions?${params.toString()}`
})

const fetchData = async () => {
  const { data } = await useApi<QuestionsData>(fetchUrl).get()
  questionsData.value = data.value
}

watch(fetchUrl, fetchData)

onMounted(fetchData)

const questions = computed(() => questionsData.value?.data || [])
const totalQuestions = computed(() => questionsData.value?.pagination.total || 0)

const resolveStatus = (respuesta: string | null) => {
  if (respuesta)
    return { text: 'Respondida', color: 'success' }

  return { text: 'Pendiente', color: 'warning' }
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
  <VCard
    id="question-list"
    class="motor-card-enhanced"
  >
    <VCardTitle class="pa-6 pb-0">
      <span class="text-h5 text-premium-title">Mis Preguntas</span>
    </VCardTitle>
    <VCardText class="pa-6">
      <div class="d-flex flex-wrap gap-4">
        <div class="d-flex align-center">
          <!-- 👉 Search  -->
          <AppTextField
            v-model="searchQuery"
            placeholder="Buscar Pregunta"
            style="inline-size: 260px;"
            class="me-3"
            prepend-inner-icon="tabler-search"
          />
        </div>

        <VSpacer />
        <div class="d-flex gap-4 flex-wrap align-center">
          <AppSelect
            v-model="itemsPerPage"
            :items="[5, 10, 20, 25, 50]"
            style="inline-size: 80px;"
          />
        </div>
      </div>
    </VCardText>

    <VDivider />

    <!-- 👉 Datatable  -->
    <VDataTableServer
      v-model:items-per-page="itemsPerPage"
      v-model:page="page"
      :headers="headers"
      :items="questions"
      :items-length="totalQuestions"
      class="text-no-wrap pb-4"
      @update:options="updateOptions"
    >
      <!-- publicacion -->
      <template #item.motor="{ item }: { item: Question }">
        <div
          v-if="item.motor"
          class="d-flex align-center gap-x-4 py-3"
        >
          <VAvatar
            v-if="item.motor.imagen_destacada"
            size="44"
            variant="tonal"
            rounded
            class="border"
            :image="getImageBySize(item.motor.imagen_destacada, 'thumbnail')"
          />
          <div class="d-flex flex-column">
            <span
              class="text-body-1 font-weight-medium text-premium-title cursor-pointer"
              @click="router.push(`/dashboard/publications/publication/edit/${item.motor.uuid}`)"
            >{{ item.motor.title }}</span>
            <span class="text-body-2 text-muted">{{ item.motor.acf.marca?.name }}</span>
          </div>
        </div>
      </template>

      <!-- pregunta -->
      <template #item.pregunta="{ item }: { item: Question }">
        <span class="text-body-2 text-high-emphasis">{{ item.pregunta }}</span>
      </template>

      <!-- respuesta -->
      <template #item.respuesta="{ item }: { item: Question }">
        <span class="text-body-2 text-medium-emphasis">
          {{ item.respuesta || 'Esperando respuesta...' }}
        </span>
      </template>

      <template #item.question_date="{ item }:{ item: any }">
        <span class="text-body-2">{{ new Date(item.question_date).toLocaleDateString() }}</span>
      </template>

      <template #item.answer_date="{ item }:{ item: any }">
        <span
          v-if="item.answer_date"
          class="text-body-2"
        >{{ new Date(item.answer_date).toLocaleDateString() }}</span>
      </template>

      <!-- estado -->
      <template #item.estado="{ item }: { item: Question }">
        <VChip
          v-bind="resolveStatus(item.respuesta)"
          density="comfortable"
          label
          size="small"
          class="font-weight-medium"
        >
          {{ resolveStatus(item.respuesta).text }}
        </VChip>
      </template>

      <!-- Actions -->
      <template #item.actions="{ item }: { item: Question }">
        <div class="d-flex gap-1">
          <IconBtn
            v-if="item.motor"
            color="primary"
            variant="tonal"
            size="small"
            @click="router.push(`/dashboard/publications/publication/edit/${item.motor.uuid}`)"
          >
            <VIcon
              icon="tabler-eye"
              size="18"
            />
          </IconBtn>
        </div>
      </template>

      <!-- pagination -->
      <template #bottom>
        <VDivider />
        <TablePagination
          v-model:page="page"
          :items-per-page="itemsPerPage"
          :total-items="totalQuestions"
          class="pa-4"
        />
      </template>
    </VDataTableServer>
  </VCard>
</template>
