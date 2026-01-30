<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'
import { debounce } from '@/utils/debounce'
import { useToast } from '@/composables/useToast'

const { t } = useI18n()
const router = useRouter()
const { showToast } = useToast()

const headers = [
  { title: t('questions.publication'), key: 'motor' },
  { title: t('questions.question'), key: 'pregunta', sortable: false },
  { title: t('User'), key: 'user_name' },
  { title: t('questions.question_date'), key: 'question_date' },
  { title: t('questions.status'), key: 'status' },
  { title: t('questions.actions'), key: 'actions', sortable: false },
]

const searchQuery = ref('')
const selectedStatus = ref('pending')
const selectedPublication = ref()
const publicationsList = ref([])

const statusOptions = computed(() => [
  { title: t('questions.answered'), value: 'answered' },
  { title: t('questions.pending'), value: 'pending' },
])

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()

const updateOptions = (options: any) => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const resolveStatus = (respuesta: string | null) => {
  if (respuesta)
    return { text: t('questions.answered'), color: 'success' }
  
  return { text: t('questions.pending'), color: 'warning' }
}

const apiUrl = computed(() => {
  const params = new URLSearchParams()
  if (searchQuery.value)
    params.append('search', searchQuery.value)
  if (selectedStatus.value)
    params.append('status', selectedStatus.value)
  if (selectedPublication.value)
    params.append('publication_id', selectedPublication.value)
  if (page.value)
    params.append('page', page.value.toString())
  if (itemsPerPage.value)
    params.append('per_page', itemsPerPage.value.toString())
  if (sortBy.value)
    params.append('orderby', sortBy.value)
  if (orderBy.value)
    params.append('order', orderBy.value)

  return `/wp-json/motorlan/v1/user/questions?${params.toString()}`
})

const { data: questionsData, execute: fetchQuestions, isFetching: isTableLoading } = useApi<any>(apiUrl, { immediate: false }).get().json()
const isSearching = ref(false)

const debouncedFetch = debounce(async () => {
  isSearching.value = true
  await fetchQuestions()
  isSearching.value = false
}, 300)

watch(
  [searchQuery, selectedStatus, page, itemsPerPage, sortBy, orderBy],
  () => {
    debouncedFetch()
  },
  { deep: true },
)

onMounted(() => {
  fetchQuestions()
  useApi<any>('/wp-json/motorlan/v1/user/publications-list').get().json().then(response => {
    publicationsList.value = response.data.value
  })
})

const questions = computed(() => (questionsData.value?.data || []).map((q: any) => ({ ...q, newAnswer: '' })))
const totalQuestions = computed(() => questionsData.value?.pagination?.total || 0)

const isSubmitting = ref(false)

const submitAnswer = async (question: any, isActive: any) => {
  if (!question.newAnswer) {
    showToast(t('questions.answer_empty_error'), 'error')
    
    return
  }

  isSubmitting.value = true
  try {
    await useApi(`/wp-json/motorlan/v1/questions/${question.id}/answer`).post({ respuesta: question.newAnswer })
    showToast(t('questions.answer_success'), 'success')
    fetchQuestions() // Refresh data
    isActive.value = false
  }
  catch (error) {
    console.error('Error submitting answer:', error)
    showToast(t('questions.answer_error'), 'error')
  }
  finally {
    isSubmitting.value = false
  }
}

const getImageBySize = (image: any, size = 'thumbnail'): string => {
  if (!image) return ''
  
  if (Array.isArray(image) && image.length > 0)
    image = image[0]
    
  if (image.sizes && image.sizes[size])
    return image.sizes[size].url || image.sizes[size].src || image.sizes[size]
    
  return image.url || image.src || ''
}
</script>

<template>
  <VCard class="motor-card-enhanced">
    <VCardText class="pa-6">
      <VRow>
        <VCol cols="12" sm="4">
          <AppTextField
            v-model="searchQuery"
            :placeholder="t('questions.search_placeholder')"
            class="me-3"
          />
        </VCol>
        <VCol cols="12" sm="4">
          <AppSelect
            v-model="selectedStatus"
            :placeholder="t('questions.status')"
            :items="statusOptions"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol cols="12" sm="4">
          <AppSelect
            v-model="selectedPublication"
            :placeholder="t('questions.publication')"
            :items="publicationsList"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VDataTableServer
      v-model:items-per-page="itemsPerPage"
      v-model:page="page"
      :headers="headers"
      :items="questions"
      :items-length="totalQuestions"
      :loading="isTableLoading || isSearching"
      class="text-no-wrap pb-4"
      item-value="id"
      @update:options="updateOptions"
    >
      <template #item.motor="{ item }: { item: any }">
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

      <template #item.question_date="{ item }:{ item: any }">
        <span class="text-body-2 text-medium-emphasis">
          {{ new Date(item.question_date).toLocaleDateString() }}
        </span>
      </template>

      <template #item.status="{ item }: { item: any }">
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

      <template #item.actions="{ item }: { item: any }">
        <div class="d-flex gap-1">
          <!-- Reply Dialog -->
          <VDialog
            v-if="!item.respuesta"
            max-width="600px"
          >
            <template #activator="{ props }">
              <IconBtn 
                v-bind="props"
                variant="tonal"
                color="primary"
                size="small"
              >
                <VIcon icon="tabler-pencil" size="18" />
              </IconBtn>
            </template>
            <template #default="{ isActive }">
              <VCard class="motor-card-enhanced overflow-hidden">
                <VCardTitle class="pa-0">
                  <div class="d-flex align-center justify-space-between pa-4 bg-surface border-b">
                    <div class="d-flex align-center gap-2">
                      <VIcon icon="tabler-message-2" color="primary" />
                      <span class="text-h6 font-weight-bold text-premium-title">{{ t('questions.reply_to_question') }}</span>
                    </div>
                    <IconBtn @click="isActive.value = false">
                      <VIcon icon="tabler-x" size="20" />
                    </IconBtn>
                  </div>
                </VCardTitle>

                <VCardText class="pa-6">
                  <div class="bg-light-primary pa-4 rounded-lg mb-6 border-primary border-opacity-10">
                    <div class="d-flex align-center gap-2 mb-2">
                      <VAvatar size="28" color="primary" variant="tonal" class="font-weight-bold">
                        {{ item.user_name?.charAt(0).toUpperCase() }}
                      </VAvatar>
                      <span class="font-weight-bold text-high-emphasis">{{ item.user_name }}</span>
                      <VSpacer />
                      <span class="text-caption text-medium-emphasis">{{ new Date(item.question_date).toLocaleDateString() }}</span>
                    </div>
                    <p class="mb-0 text-body-1 line-height-1.5 text-high-emphasis">
                      {{ item.pregunta }}
                    </p>
                  </div>

                  <VForm @submit.prevent="() => submitAnswer(item, isActive)">
                    <AppTextarea
                      v-model="item.newAnswer"
                      :label="t('questions.your_answer')"
                      rows="4"
                      variant="outlined"
                      class="mb-4"
                      placeholder="Escribe tu respuesta detallada aquí..."
                      auto-grow
                    />
                    <div class="d-flex justify-end gap-3 mt-2">
                      <VBtn
                        color="secondary"
                        variant="tonal"
                        class="px-6"
                        @click="isActive.value = false"
                      >
                        {{ t('questions.cancel') }}
                      </VBtn>
                      <VBtn
                        color="primary"
                        type="submit"
                        class="px-6"
                        :loading="isSubmitting"
                        :disabled="isSubmitting || !item.newAnswer"
                      >
                        <VIcon icon="tabler-send" start size="18" />
                        {{ t('questions.reply') }}
                      </VBtn>
                    </div>
                  </VForm>
                </VCardText>
              </VCard>
            </template>
          </VDialog>

          <!-- View Dialog -->
          <VDialog
            v-if="item.respuesta"
            max-width="600px"
          >
            <template #activator="{ props }">
              <IconBtn 
                v-bind="props"
                variant="tonal"
                color="secondary"
                size="small"
              >
                <VIcon icon="tabler-eye" size="18" />
              </IconBtn>
            </template>
            <template #default="{ isActive }">
              <VCard class="motor-card-enhanced overflow-hidden">
                <VCardTitle class="pa-0">
                  <div class="d-flex align-center justify-space-between pa-4 bg-surface border-b">
                    <div class="d-flex align-center gap-2">
                      <VIcon icon="tabler-message-dots" color="primary" />
                      <span class="text-h6 font-weight-bold text-premium-title">Consulta Respondida</span>
                    </div>
                    <IconBtn @click="isActive.value = false">
                      <VIcon icon="tabler-x" size="20" />
                    </IconBtn>
                  </div>
                </VCardTitle>

                <VCardText class="pa-6">
                  <!-- Pregunta -->
                  <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold mb-2 ml-1">Pregunta del Usuario</div>
                  <div class="bg-light-primary pa-4 rounded-lg mb-6 border-primary border-opacity-10">
                    <div class="d-flex align-center gap-2 mb-2">
                      <VAvatar size="24" color="primary" variant="tonal" class="font-weight-bold text-caption">
                        {{ item.user_name?.charAt(0).toUpperCase() }}
                      </VAvatar>
                      <span class="font-weight-bold text-body-2">{{ item.user_name }}</span>
                    </div>
                    <p class="mb-0 text-body-1 text-high-emphasis">
                      {{ item.pregunta }}
                    </p>
                  </div>
                  
                  <!-- Respuesta -->
                  <div class="text-caption text-success text-uppercase font-weight-bold mb-2 ml-1">Tu Respuesta</div>
                  <div class="bg-surface pa-4 rounded-lg border-success border-opacity-25 border">
                    <div class="d-flex align-center gap-2 mb-2">
                      <VAvatar size="24" color="success" variant="tonal">
                        <VIcon icon="tabler-check" size="14" />
                      </VAvatar>
                      <span class="font-weight-bold text-success text-body-2">{{ t('questions.answer') }}</span>
                    </div>
                    <p class="mb-0 text-body-1 italic text-high-emphasis">
                      {{ item.respuesta }}
                    </p>
                  </div>

                  <div class="d-flex justify-end mt-8">
                    <VBtn
                      color="primary"
                      variant="tonal"
                      class="px-8"
                      @click="isActive.value = false"
                    >
                      Cerrar
                    </VBtn>
                  </div>
                </VCardText>
              </VCard>
            </template>
          </VDialog>
        </div>
      </template>

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
