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
</script>

<template>
  <VCard>
    <VCardText>
      <VRow>
        <VCol cols="12" sm="4">
          <AppTextField
            v-model="searchQuery"
            :placeholder="t('questions.search_placeholder')"
            style="inline-size: 200px;"
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

    <VDivider class="mt-4" />

    <VDataTableServer
      v-model:items-per-page="itemsPerPage"
      v-model:page="page"
      :headers="headers"
      :items="questions"
      :items-length="totalQuestions"
      :loading="isTableLoading || isSearching"
      class="text-no-wrap"
      item-value="id"
      @update:options="updateOptions"
    >
      <template #item.motor="{ item }: { item: any }">
        <div
          v-if="item.motor"
          class="d-flex align-center gap-x-4"
        >
          <div class="d-flex flex-column">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis cursor-pointer"
              @click="router.push(`/apps/publications/publication/edit/${item.motor.uuid}`)"
            >{{ item.motor.title }}</span>
            <span class="text-body-2">{{ item.motor.acf.marca?.name }}</span>
          </div>
        </div>
      </template>

      <template #item.question_date="{ item }:{ item: any }">
        {{ new Date(item.question_date).toLocaleDateString() }}
      </template>

      <template #item.status="{ item }: { item: any }">
        <VChip
          v-bind="resolveStatus(item.respuesta)"
          density="default"
          label
          size="small"
        />
      </template>

      <template #item.actions="{ item }: { item: any }">
        <!-- Reply Dialog -->
        <VDialog
          v-if="!item.respuesta"
          max-width="600px"
        >
          <template #activator="{ props }">
            <IconBtn v-bind="props">
              <VIcon icon="tabler-pencil" />
            </IconBtn>
          </template>
          <template #default="{ isActive }">
            <VCard :title="`${t('questions.reply_to_question')} - ${item.user_name}`">
              <VCardText>
                <p class="mb-4">
                  <strong>{{ t('questions.question') }}:</strong> {{ item.pregunta }}
                </p>
                <VForm @submit.prevent="() => submitAnswer(item, isActive)">
                  <AppTextarea
                    v-model="item.newAnswer"
                    :label="t('questions.your_answer')"
                    rows="3"
                  />
                  <VCardActions>
                    <VSpacer />
                    <VBtn
                      color="secondary"
                      @click="isActive.value = false"
                    >
                      {{ t('questions.cancel') }}
                    </VBtn>
                    <VBtn
                      color="primary"
                      type="submit"
                      :loading="isSubmitting"
                      :disabled="isSubmitting"
                    >
                      {{ t('questions.reply') }}
                    </VBtn>
                  </VCardActions>
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
            <IconBtn v-bind="props">
              <VIcon icon="tabler-eye" />
            </IconBtn>
          </template>
          <template #default="{ isActive }">
            <VCard :title="t('questions.reply_to_question')">
              <VCardText>
                <p class="mb-2">
                  <strong>{{ t('questions.question') }}:</strong> {{ item.pregunta }}
                </p>
                <p><strong>{{ t('questions.answer') }}:</strong> {{ item.respuesta }}</p>
              </VCardText>
              <VCardActions>
                <VSpacer />
                <VBtn
                  color="secondary"
                  @click="isActive.value = false"
                >
                  {{ t('questions.cancel') }}
                </VBtn>
              </VCardActions>
            </VCard>
          </template>
        </VDialog>
      </template>

      <template #bottom>
        <TablePagination
          v-model:page="page"
          :items-per-page="itemsPerPage"
          :total-items="totalQuestions"
        />
      </template>
    </VDataTableServer>
  </VCard>
</template>