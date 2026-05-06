<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useI18n } from 'vue-i18n'

const route = useRoute()
const { showToast } = useToast()
const { t } = useI18n()

const publication = ref<any>(null)
const questions = ref<any[]>([])
const isLoading = ref(true)
const motorUuid = route.params.uuid as string

const fetchPublicationAndQuestions = async () => {
  try {
    isLoading.value = true
    const { data: pubData } = await useApi(`/wp-json/motorlan/v1/publicaciones/uuid/${motorUuid}`).get().json()
    publication.value = pubData.value

    if (publication.value) {
      const { data: questionsData } = await useApi(`/wp-json/motorlan/v1/publicaciones/${publication.value.id}/questions`).get().json()
      questions.value = questionsData.value?.data.map((q: any) => ({ ...q, newAnswer: '' })) || []
    }
  }
  catch (error) {
    console.error('Error fetching data:', error)
    showToast(t('questions.fetch_error'), 'error')
  }
  finally {
    isLoading.value = false
  }
}

const submitAnswer = async (question: any) => {
  if (!question.newAnswer) {
    showToast(t('questions.answer_empty_error'), 'error')
    
    return
  }

  try {
    await useApi(`/wp-json/motorlan/v1/questions/${question.id}/answer`).post({ respuesta: question.newAnswer })
    showToast(t('questions.answer_success'), 'success')
    question.respuesta = question.newAnswer
    question.newAnswer = ''
  }
  catch (error) {
    console.error('Error submitting answer:', error)
    showToast(t('questions.answer_error'), 'error')
  }
}

onMounted(fetchPublicationAndQuestions)
</script>

<template>
  <VCard>
    <VCardTitle v-if="publication">
      {{ t('questions.page_title') }} "{{ publication.title }}"
    </VCardTitle>
    <VCardText>
      <VProgressCircular
        v-if="isLoading"
        indeterminate
        class="d-block mx-auto"
      />
      <div v-else-if="questions.length > 0">
        <VList lines="three">
          <template
            v-for="(question, index) in questions"
            :key="question.id"
          >
            <VListItem>
              <VListItemTitle>{{ question.pregunta }}</VListItemTitle>
              <VListItemSubtitle>
                <p v-if="question.respuesta" class="text-success">
                  {{ t('questions.answered') }}: {{ question.respuesta }}
                </p>
                <p v-else class="text-warning">
                  {{ t('questions.pending') }}
                </p>
              </VListItemSubtitle>
              <template v-if="!question.respuesta" #append>
                <VForm @submit.prevent="submitAnswer(question)">
                  <div class="d-flex align-center gap-2">
                    <AppTextField
                      v-model="question.newAnswer"
                      :label="t('questions.your_answer')"
                      dense
                      style="min-width: 300px;"
                    />
                    <VBtn
                      type="submit"
                      color="primary"
                    >
                      {{ t('questions.reply') }}
                    </VBtn>
                  </div>
                </VForm>
              </template>
            </VListItem>
            <VDivider v-if="index < questions.length - 1" />
          </template>
        </VList>
      </div>
      <p v-else>
        {{ t('questions.no_questions') }}
      </p>
    </VCardText>
  </VCard>
</template>