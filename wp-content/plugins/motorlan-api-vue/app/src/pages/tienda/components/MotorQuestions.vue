<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { createUrl } from '@/@core/composable/createUrl'
import { useApi } from '@/composables/useApi'

const props = defineProps<{ motorId: number }>()

const newQuestion = ref('')
const questions = ref<any[]>([])
const loading = ref(false)
const snackbar = ref({ show: false, text: '', color: 'success' as 'success' | 'error' })

const fetchQuestions = async () => {
  try {
    const { data } = await useApi<any>(
      createUrl(`/wp-json/motorlan/v1/motors/${props.motorId}/questions`),
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
      createUrl(`/wp-json/motorlan/v1/motors/${props.motorId}/questions`),
    ).post({ body: { pregunta: newQuestion.value } })
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
  <div class="motor-questions mt-8">
    <h2 class="text-h5 mb-4">Preguntas y respuestas</h2>
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
        class="mb-4"
      >
        <p class="mb-1 font-weight-medium">{{ q.pregunta }}</p>
        <p v-if="q.respuesta" class="text-secondary">
          {{ q.respuesta }}
        </p>
      </div>
    </div>
    <div v-else class="text-body-2">
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
.motor-questions {
  border-top: 1px solid #E6E6E6;
  padding-top: 1rem;
}
</style>
