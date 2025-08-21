<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '@/composables/useApi'

// Define the interface for a single motor
interface Motor {
  id: number
  uuid: string
  title: string
  content: string
  imagen_destacada: {
    url: string
    alt: string
  }
  acf: {
    precio_de_venta: string
    marca: {
      name: string
    }
    tipo_o_referencia: string
    potencia: string
    velocidad: string
    par_nominal: string
    voltaje: string
    intensidad: string
    pais: string
    provincia: string
    estado_del_articulo: string
    descripcion: string
  }
  gallery: {
    url: string
    alt: string
  }[]
}

const route = useRoute()
const api = useApi()

const motor = ref<Motor | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const newQuestion = ref('')

const motorUuid = route.params.uuid as string

onMounted(async () => {
  if (!motorUuid) {
    error.value = 'No motor UUID provided.'
    loading.value = false

    return
  }

  try {
    const { data, error: apiError } = await api(`/wp-json/motorlan/v1/motors/uuid/${motorUuid}`).get().json<Motor>()

    if (apiError.value)
      throw new Error(apiError.value.data?.message || 'Failed to fetch motor data')

    if (data.value) {
      motor.value = data.value
    }
    else {
      throw new Error('Motor not found')
    }
  }
  catch (e: any) {
    error.value = e.message
  }
  finally {
    loading.value = false
  }
})

const handleAskQuestion = () => {
  // Logic to handle question submission will be added here
  console.log('New question:', newQuestion.value)
  newQuestion.value = ''
}
</script>

<template>
  <div>
    <div v-if="loading" class="text-center pa-12">
      <VProgressCircular indeterminate size="64" />
      <p class="mt-4">
        Cargando datos del motor...
      </p>
    </div>

    <VCard v-else-if="error" color="error">
      <VCardText>
        {{ error }}
      </VCardText>
    </VCard>

    <div v-else-if="motor">
      <VRow>
        <VCol
          cols="12"
          md="8"
        >
          <!-- Main motor card -->
          <VCard>
            <VRow no-gutters>
              <VCol
                cols="12"
                md="6"
              >
                <VImg
                  :src="motor.imagen_destacada?.url"
                  height="400px"
                  cover
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VCardText>
                  <VCardTitle>
                    {{ motor.title }}
                  </VCardTitle>
                  <VCardSubtitle>
                    {{ motor.acf.marca?.name }}
                  </VCardSubtitle>
                  <p class="text-h4 font-weight-bold my-4">
                    {{ motor.acf.precio_de_venta ? `${motor.acf.precio_de_venta} €` : 'Consultar precio' }}
                  </p>
                  <VBtn
                    color="primary"
                    class="mb-4"
                    block
                  >
                    Comprar
                  </VBtn>
                  <VBtn
                    variant="tonal"
                    block
                  >
                    Contactar al vendedor
                  </VBtn>
                </VCardText>
              </VCol>
            </VRow>
          </VCard>

          <!-- Gallery -->
          <VCard
            v-if="motor.gallery && motor.gallery.length > 0"
            class="mt-6"
          >
            <VCardTitle>Galería</VCardTitle>
            <VCardText>
              <VRow>
                <VCol
                  v-for="(image, index) in motor.gallery"
                  :key="index"
                  cols="6"
                  sm="4"
                  md="3"
                >
                  <VImg
                    :src="image.url"
                    :alt="image.alt"
                    aspect-ratio="1"
                    cover
                    class="rounded"
                  />
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <!-- Description -->
          <VCard class="mt-6">
            <VCardTitle>Descripción</VCardTitle>
            <VCardText>
              <div v-html="motor.content" />
              <p>{{ motor.acf.descripcion }}</p>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="4"
        >
          <!-- Details card -->
          <VCard>
            <VCardTitle>Detalles</VCardTitle>
            <VCardText>
              <VList dense>
                <VListItem>
                  <VListItemTitle><strong>Referencia:</strong> {{ motor.acf.tipo_o_referencia }}</VListItemTitle>
                </VListItem>
                <VListItem>
                  <VListItemTitle><strong>Potencia:</strong> {{ motor.acf.potencia }} kW</VListItemTitle>
                </VListItem>
                <VListItem>
                  <VListItemTitle><strong>Velocidad:</strong> {{ motor.acf.velocidad }} rpm</VListItemTitle>
                </VListItem>
                <VListItem>
                  <VListItemTitle><strong>Estado:</strong> {{ motor.acf.estado_del_articulo }}</VListItemTitle>
                </VListItem>
                <VListItem>
                  <VListItemTitle><strong>Ubicación:</strong> {{ motor.acf.provincia }}, {{ motor.acf.pais }}</VListItemTitle>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>

          <!-- Q&A Section -->
          <VCard class="mt-6">
            <VCardTitle>Preguntas y Respuestas</VCardTitle>
            <VCardText>
              <div class="q-a-list mb-4">
                <!-- Static Q&A for now -->
                <div class="q-a-item">
                  <p><strong>P:</strong> ¿Hacen envíos a Canarias?</p>
                  <p><strong>R:</strong> Sí, hacemos envíos a toda España.</p>
                </div>
                <VDivider class="my-2" />
                <div class="q-a-item">
                  <p><strong>P:</strong> ¿El precio es negociable?</p>
                  <p><strong>R:</strong> Para este artículo, el precio es final.</p>
                </div>
              </div>
              <VTextarea
                v-model="newQuestion"
                label="Escribe tu pregunta"
                rows="3"
                auto-grow
              />
              <VBtn
                color="primary"
                @click="handleAskQuestion"
              >
                Enviar pregunta
              </VBtn>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </div>
</template>
