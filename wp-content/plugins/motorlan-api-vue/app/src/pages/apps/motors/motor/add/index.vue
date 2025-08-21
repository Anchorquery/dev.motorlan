<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const motorData = ref({
  title: '',
  status: 'publish',
  categories: [],
  acf: {
    marca: null,
    tipo_o_referencia: '',
    motor_image: null,
    motor_gallery: [],
    potencia: null,
    velocidad: null,
    par_nominal: null,
    voltaje: null,
    intensidad: null,
    pais: null,
    provincia: '',
    estado_del_articulo: 'Nuevo',
    informe_de_reparacion: null,
    descripcion: '',
    posibilidad_de_alquiler: 'No',
    tipo_de_alimentacion: 'Alterna (C.A.)',
    servomotores: false,
    regulacion_electronica_drivers: false,
    precio_de_venta: null,
    precio_negociable: 'No',
    documentacion_adjunta: null,
    publicar_acf: 'publish',
  },
})

const marcas = ref([])
const categories = ref([])
const form = ref(null)

onMounted(async () => {
  try {
    // 1. Crear un arreglo de promesas
    const promises = [
      useApi('/wp-json/motorlan/v1/marcas'),
      useApi('/wp-json/motorlan/v1/motor-categories'),
    ]

    // 2. Ejecutar todas las promesas en paralelo
    const [marcasResponse, categoriesResponse] = await Promise.all(promises)

    // 3. Procesar los resultados una vez que todos han llegado

    // Procesar marcas
    if (marcasResponse && marcasResponse.data.value) {
      marcas.value = marcasResponse.data.value.map((marca: { name: any; id: any }) => ({
        name: marca.name,
        id: marca.id,
      }))
    }

    // Procesar categorías
    if (categoriesResponse && categoriesResponse.data.value) {
      categories.value = categoriesResponse.data.value.map((category: { name: any; term_id: any }) => ({
        name: category.name,
        id: category.term_id,
      }))
    }
  }
  catch (error) {
    console.error('Error al obtener los datos iniciales:', error)
  }
})

const uploadMedia = async (file: File) => {
  const api = useApi()
  const formData = new FormData()

  formData.append('file', file)

  try {
    const response = await api('/wp-json/wp/v2/media', {
      method: 'POST',
      body: formData,
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })

    return response.data.value.id
  }
  catch (error) {
    console.error('Failed to upload media:', error)

    return null
  }
}

const handleFeaturedImageUpload = async (file: File) => {
  const imageId = await uploadMedia(file)
  if (imageId)
    motorData.value.acf.motor_image = imageId
}

const handleGalleryImageUpload = async (file: File) => {
  const imageId = await uploadMedia(file)
  if (imageId)
    motorData.value.acf.motor_gallery.push(imageId)
}

const publishMotor = async () => {
  const api = useApi()
  const url = '/wp-json/wp/v2/motors'
  const method = 'POST'

  try {
    await api(url, {
      method,
      body: motorData.value,
    })
    router.push('/apps/motors/motor/list')
  }
  catch (error) {
    console.error('Failed to publish motor:', error)
  }
}

const content = ref(
  `<p>
    Keep your account secure with authentication step.
    </p>`)
</script>

<template>
  <div>
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex flex-column justify-center">
        <h4 class="text-h4 font-weight-medium">
          Add a new motor
        </h4>
      </div>
      <div class="d-flex gap-4 align-center flex-wrap">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="router.push('/apps/motors/motor/list')"
        >
          Discard
        </VBtn>
        <VBtn @click="publishMotor">
          Publish Motor
        </VBtn>
      </div>
    </div>

    <VRow>
      <VCol>
        <VCard
          class="mb-6"
          title="Detalles del Motor"
        >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="motorData.title"
                  label="Título de la publicación"
                  placeholder="Título"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="motorData.acf.tipo_o_referencia"
                  label="Tipo o referencia"
                  placeholder="Referencia"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="motorData.acf.marca"
                  label="Marca"
                  :items="marcas"
                  item-title="name"
                  item-value="id"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="motorData.categories"
                  label="Categoría"
                  :items="categories"
                  item-title="name"
                  item-value="id"
                  multiple
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="motorData.acf.potencia"
                  label="Potencia (kW)"
                  type="number"
                  placeholder="100"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="motorData.acf.velocidad"
                  label="Velocidad (rpm)"
                  type="number"
                  placeholder="3000"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="motorData.acf.par_nominal"
                  label="PAR Nominal (Nm)"
                  type="number"
                  placeholder="50"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="motorData.acf.voltaje"
                  label="Voltaje (V)"
                  type="number"
                  placeholder="220"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="motorData.acf.intensidad"
                  label="Intensidad (A)"
                  type="number"
                  placeholder="10"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="motorData.acf.pais"
                  label="País (localización)"
                  :items="['España', 'Portugal', 'Francia']"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="motorData.acf.provincia"
                  label="Provincia"
                  placeholder="Madrid"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="motorData.acf.estado_del_articulo"
                  label="Estado del artículo"
                  :items="['Nuevo', 'Usado', 'Restaurado']"
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="motorData.acf.descripcion"
                  label="Descripción"
                  placeholder="Descripción del motor"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <VRadioGroup
                  v-model="motorData.acf.posibilidad_de_alquiler"
                  inline
                  label="Posibilidad de alquiler"
                >
                  <VRadio
                    label="Sí"
                    value="Sí"
                  />
                  <VRadio
                    label="No"
                    value="No"
                  />
                </VRadioGroup>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VRadioGroup
                  v-model="motorData.acf.tipo_de_alimentacion"
                  inline
                  label="Tipo de alimentación"
                >
                  <VRadio
                    label="Continua (C.C.)"
                    value="Continua (C.C.)"
                  />
                  <VRadio
                    label="Alterna (C.A.)"
                    value="Alterna (C.A.)"
                  />
                </VRadioGroup>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VCheckbox
                  v-model="motorData.acf.servomotores"
                  label="Servomotores"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VCheckbox
                  v-model="motorData.acf.regulacion_electronica_drivers"
                  label="Regulación electrónica/Drivers"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="motorData.acf.precio_de_venta"
                  label="Precio de venta (€)"
                  type="number"
                  placeholder="1000"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VRadioGroup
                  v-model="motorData.acf.precio_negociable"
                  inline
                  label="Precio negociable"
                >
                  <VRadio
                    label="Sí"
                    value="Sí"
                  />
                  <VRadio
                    label="No"
                    value="No"
                  />
                </VRadioGroup>
              </VCol>
              <VCol cols="12">
                <VRadioGroup
                  v-model="motorData.status"
                  inline
                  label="Publicar (ACF)"
                >
                  <VRadio
                    label="Publicar"
                    value="publish"
                  />
                  <VRadio
                    label="Borrador"
                    value="draft"
                  />
                </VRadioGroup>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
        <VCard
          class="mb-6"
          title="Imagen del Motor"
        >
          <VCardText>
            <DropZone @file-added="handleFeaturedImageUpload" />
          </VCardText>
        </VCard>

        <VCard
          class="mb-6"
          title="Galería de Imágenes"
        >
          <VCardText>
            <DropZone @file-added="handleGalleryImageUpload" />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style lang="scss" scoped>
  .drop-zone {
    border: 2px dashed rgba(var(--v-theme-on-surface), 0.12);
    border-radius: 6px;
  }
</style>

<style lang="scss">
.inventory-card {
  .v-tabs.v-tabs-pill {
    .v-slide-group-item--active.v-tab--selected.text-primary {
      h6 {
        color: #fff !important;
      }
    }
  }

  .v-radio-group,
  .v-checkbox {
    .v-selection-control {
      align-items: start !important;
    }

    .v-label.custom-input {
      border: none !important;
    }
  }
}

.ProseMirror {
  p {
    margin-block-end: 0;
  }

  padding: 0.5rem;
  outline: none;

  p.is-editor-empty:first-child::before {
    block-size: 0;
    color: #adb5bd;
    content: attr(data-placeholder);
    float: inline-start;
    pointer-events: none;
  }
}
</style>
