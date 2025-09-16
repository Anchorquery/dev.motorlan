<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'

const props = defineProps<{ publicacionId: number }>()

const newQuestion = ref('')
const questions = ref<any[]>([])
const loading = ref(false)

const snackbar = ref({ show: false, text: '', color: 'success' as 'success' | 'error' })

const fetchQuestions = async () => {
  try {
    const { data } = await useApi<any>(
      createUrl(`/wp-json/motorlan/v1/publicaciones/${props.publicacionId}/questions`),
    ).get().json()

    questions.value = data.value?.data || []
  }
  catch (error) {
    console.error(error)
  }
}

const submitQuestion = async () => {
  if (!newQuestion.value)
    return
  loading.value = true
  try {
    await useApi(
      createUrl(`/wp-json/motorlan/v1/publicaciones/${props.publicacionId}/questions`),
    ).post({ pregunta: newQuestion.value })
    newQuestion.value = ''
    snackbar.value = { show: true, text: 'Pregunta enviada', color: 'success' }
    await fetchQuestions()
  }
  catch (error) {
    console.error(error)
    snackbar.value = { show: true, text: 'Error al enviar la pregunta', color: 'error' }
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchQuestions)
</script>

<template>
  <div class="publicacion-questions mt-8">
    <h2 class="text-h5 mb-4">
      Preguntas y respuestas
    </h2>
    <VForm
      class="d-flex align-center gap-4 mb-6"
      @submit.prevent="submitQuestion"
    >
      <VTextField
        v-model="newQuestion"
        label="Escribe tu pregunta..."
        class="flex-grow-1"
      />
      <VBtn
        color="primary"
        type="submit"
        :loading="loading"
      >
        Preguntar
      </VBtn>
    </VForm>

    <div v-if="questions.length">
      <div
        v-for="q in questions"
        :key="q.id"
        class="question-item mb-6 pb-6"
      >
        <p class="mb-1 font-weight-medium">
          {{ q.pregunta }}
        </p>
        <div
          v-if="q.respuesta"
          class="answer-wrapper"
        >
          <VIcon
            size="18"
            icon="mdi-subdirectory-arrow-right"
            class="mr-2"
          />
          <div class="answer-content">
            <p class="text-secondary mb-1">
              {{ q.respuesta }}
            </p>
            <span class="text-caption text-disabled">{{ q.fecha_respuesta }}</span>
          </div>
        </div>
      </div>
    </div>
    <div
      v-else
      class="text-body-2"
    >
      No hay preguntas todavía.
    </div>
    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      location="top"
    >
      {{ snackbar.text }}
    </VSnackbar>
  </div>
</template>

<style scoped>
.publicacion-questions {
  border-top: 1px solid #E6E6E6;
  padding-top: 1rem;
}

.question-item {
  border-bottom: 1px solid #E6E6E6;
}

.answer-wrapper {
  display: flex;
  align-items: flex-start;
  margin-left: 1.5rem;
  margin-top: 0.5rem;
}

.answer-content {
  display: flex;
  flex-direction: column;
}
</style>
