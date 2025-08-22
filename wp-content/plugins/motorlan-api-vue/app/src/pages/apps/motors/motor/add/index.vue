<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from '@/composables/useToast'
import { useApi } from '@/composables/useApi'

const { showToast } = useToast()
const router = useRouter()

// Stepper state
const currentStep = ref(1)
const newPostId = ref<number | null>(null)

// Form data
const postType = ref('motor')

const postData = ref({
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
    stock: 1,
    documentacion_adicional: [],
  },
})

const garantiaData = ref({
  motor_id: null,
  is_same_address: 'SÍ',
  direccion_motor: '',
  cp_motor: '',
  agencia_transporte: '',
  modalidad_pago: 'Contra reembolso',
  comentarios: '',
})

const userData = ref<any>(null)
const marcas = ref([])
const categories = ref([])

const postTypeOptions = [
  { title: 'Motor', value: 'motor' },
  { title: 'Regulador', value: 'regulador' },
  { title: 'Otro Repuesto', value: 'otro_repuesto' },
]

const pageTitle = computed(() => {
  const selectedType = postTypeOptions.find(o => o.value === postType.value)

  return selectedType ? `Añadir Nuevo ${selectedType.title}` : 'Añadir Nuevo'
})

const apiEndpoint = computed(() => {
  switch (postType.value) {
    case 'motor':
      return '/wp-json/wp/v2/motors'
    case 'regulador':
      return '/wp-json/wp/v2/regulador'
    case 'otro_repuesto':
      return '/wp-json/wp/v2/otro_repuesto'
    default:
      return ''
  }
})

// Fetch initial data for selects
onMounted(async () => {
  try {
    const [
      marcasResponse,
      categoriesResponse,
      userResponse,
    ] = await Promise.all([
      useApi('/wp-json/motorlan/v1/marcas'),
      useApi('/wp-json/motorlan/v1/motor-categories'),
      useApi('/wp-json/wp/v2/users/me?context=edit'),
    ])

    if (marcasResponse && marcasResponse.data.value) {
      marcas.value = marcasResponse.data.value.map((marca: { name: any; id: any }) => ({
        title: marca.name,
        value: marca.id,
      }))
    }

    if (categoriesResponse && categoriesResponse.data.value) {
      categories.value = categoriesResponse.data.value.map((category: { name: any; term_id: any }) => ({
        title: category.name,
        value: category.term_id,
      }))
    }

    if (userResponse && userResponse.data.value) {
      userData.value = userResponse.data.value
    }
  }
  catch (error) {
    console.error('Error al obtener los datos iniciales:', error)
  }
})

// Media upload utility
const uploadMedia = async (file: File) => {
  const formData = new FormData()

  formData.append('file', file)

  try {
    const { data } = await useApi<any>('/wp-json/wp/v2/media', {
      method: 'POST',
      body: formData,
    })

    return data.value.id
  }
  catch (error) {
    console.error('Failed to upload media:', error)

    return null
  }
}

const handleFeaturedImageUpload = async (file: File) => {
  const imageId = await uploadMedia(file)
  if (imageId)
    postData.value.acf.motor_image = imageId
}

const handleGalleryImageUpload = async (file: File) => {
  const imageId = await uploadMedia(file)
  if (imageId)
    postData.value.acf.motor_gallery.push(imageId)
}

const addDocument = () => {
  if (postData.value.acf.documentacion_adicional.length < 5)
    postData.value.acf.documentacion_adicional.push({ nombre: '', archivo: null })
}

const handleFileUpload = async (event: Event, index: number) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) {
    const fileId = await uploadMedia(file)
    if (fileId)
      postData.value.acf.documentacion_adicional[index].archivo = fileId
  }
}

// Step 1: Create Post and move to step 2
const createPostAndContinue = async () => {
  try {
    // Handle file uploads for additional documentation
    for (const doc of postData.value.acf.documentacion_adicional) {
      if (doc.archivo instanceof File) {
        const fileId = await uploadMedia(doc.archivo)
        doc.archivo = fileId
      }
    }

    const response = await useApi<any>(apiEndpoint.value, {
      method: 'POST',
      body: postData.value,
    })

    newPostId.value = response.data.value.id
    showToast('Publicación creada. Ahora decide sobre la garantía.', 'success')

    if (postType.value === 'otro_repuesto') {
      router.push('/apps/motors/motor/list')
    }
    else {
      currentStep.value = 2
    }
  }
  catch (error: any) {
    showToast(`Error al crear la publicación: ${error.message}`, 'error')
    console.error('Failed to create post:', error)
  }
}

// Step 2: Skip warranty
const skipGarantia = () => {
  // A simple confirm dialog
  if (confirm('La publicación se publicará sin la garantía Motorlan. ¿Estás seguro?')) {
    showToast('Publicación sin garantía.', 'info')
    router.push('/apps/motors/motor/list')
  }
}

const goToGarantiaForm = () => {
  if (userData.value && userData.value.acf) {
    garantiaData.value.direccion_motor = userData.value.acf.direccion || ''
    garantiaData.value.cp_motor = userData.value.acf.codigo_postal || ''
  }
  currentStep.value = 3
}

// Step 3: Submit warranty
const submitGarantia = async () => {
  if (!newPostId.value) {
    showToast('Error: No se ha encontrado el ID de la publicación.', 'error')

    return
  }

  garantiaData.value.motor_id = newPostId.value

  try {
    await useApi('/wp-json/motorlan/v1/garantias', {
      method: 'POST',
      body: garantiaData.value,
    })
    showToast('Garantía solicitada con éxito. Publicación realizada.', 'success')
    router.push('/apps/motors/motor/list')
  }
  catch (error: any) {
    showToast(`Error al solicitar la garantía: ${error.message}`, 'error')
    console.error('Failed to submit garantia:', error)
  }
}
</script>

<template>
  <div>
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex flex-column justify-center">
        <h4 class="text-h4 font-weight-medium">
          {{ pageTitle }}
        </h4>
        <span
          v-if="postType !== 'otro_repuesto'"
          class="text-body-1"
        >Paso {{ currentStep }} de 3</span>
      </div>
      <div class="d-flex gap-4 align-center flex-wrap">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="router.push('/apps/motors/motor/list')"
        >
          Descartar
        </VBtn>
        <VBtn
          v-if="currentStep === 1"
          @click="createPostAndContinue"
        >
          Guardar y Continuar
        </VBtn>
      </div>
    </div>

    <!-- Step 1: Post Details -->
    <VRow v-if="currentStep === 1">
      <VCol>
        <VCard class="mb-6">
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="postType"
                  label="Tipo de Publicación"
                  :items="postTypeOptions"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
        <VCard
          class="mb-6"
          :title="`Detalles del ${postType}`"
        >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="postData.title"
                  label="Título de la publicación"
                  placeholder="Título"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="postData.acf.tipo_o_referencia"
                  label="Tipo o referencia"
                  placeholder="Referencia"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="postData.acf.marca"
                  label="Marca"
                  :items="marcas"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="postData.categories"
                  label="Categoría"
                  :items="categories"
                  multiple
                />
              </VCol>
              <template v-if="postType === 'motor' || postType === 'regulador'">
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="postData.acf.potencia"
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
                    v-model="postData.acf.velocidad"
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
                    v-model="postData.acf.par_nominal"
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
                    v-model="postData.acf.voltaje"
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
                    v-model="postData.acf.intensidad"
                    label="Intensidad (A)"
                    type="number"
                    placeholder="10"
                  />
                </VCol>
              </template>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="postData.acf.pais"
                  label="País (localización)"
                  :items="['España', 'Portugal', 'Francia']"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="postData.acf.provincia"
                  label="Provincia"
                  placeholder="Madrid"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="postData.acf.estado_del_articulo"
                  label="Estado del artículo"
                  :items="['Nuevo', 'Usado', 'Restaurado']"
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="postData.acf.descripcion"
                  label="Descripción"
                  placeholder="Descripción del producto"
                />
              </VCol>
              <template v-if="postType === 'motor' || postType === 'regulador'">
                <VCol
                  cols="12"
                  md="6"
                >
                  <VRadioGroup
                    v-model="postData.acf.posibilidad_de_alquiler"
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
                    v-model="postData.acf.tipo_de_alimentacion"
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
                    v-model="postData.acf.servomotores"
                    label="Servomotores"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <VCheckbox
                    v-model="postData.acf.regulacion_electronica_drivers"
                    label="Regulación electrónica/Drivers"
                  />
                </VCol>
              </template>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="postData.acf.precio_de_venta"
                  label="Precio de venta (€)"
                  type="number"
                  placeholder="1000"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="postData.acf.stock"
                  label="Stock"
                  type="number"
                  placeholder="1"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VRadioGroup
                  v-model="postData.acf.precio_negociable"
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
                  v-model="postData.status"
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
          title="Imagen Principal"
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
        <VCard
          class="mb-6"
          title="Documentación Adicional"
        >
          <VCardText>
            <div
              v-for="(doc, index) in postData.acf.documentacion_adicional"
              :key="index"
              class="d-flex gap-4 mb-4"
            >
              <AppTextField
                v-model="doc.nombre"
                label="Nombre del Documento"
                placeholder="Manual de usuario"
                style="width: 300px;"
              />
              <VFileInput
                label="Subir Archivo"
                @change="event => handleFileUpload(event, index)"
              />
            </div>
            <VBtn
              v-if="postData.acf.documentacion_adicional.length < 5"
              @click="addDocument"
            >
              Añadir Documento
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Step 2: Warranty Offer -->
    <VCard
      v-if="currentStep === 2"
      title="Añadir Garantía Motorlan"
      class="mb-6"
    >
      <VCardText>
        <p class="mb-4">
          <strong>¡Solicita la Garantía Motorlan, tu producto se venderá mejor!</strong>
        </p>
        <p>
          Envía el producto a Motorlan para su revisión y puesta a punto. El envío corre a cargo del solicitante.
          Una vez inspeccionado, te enviaremos un presupuesto para su puesta a punto con garantía.
        </p>
        <p class="mt-2">
          La garantía de los trabajos y materiales es de 6 meses.
        </p>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          color="secondary"
          @click="skipGarantia"
        >
          Omitir
        </VBtn>
        <VBtn @click="goToGarantiaForm">
          Aceptar y Añadir Garantía
        </VBtn>
      </VCardActions>
    </VCard>

    <!-- Step 3: Warranty Form -->
    <VCard
      v-if="currentStep === 3"
      title="Formulario de Garantía"
      class="mb-6"
    >
      <VCardText>
        <VRow>
          <VCol cols="12">
            <VRadioGroup
              v-model="garantiaData.is_same_address"
              inline
              label="¿El producto se encuentra en la misma dirección de tu empresa?"
            >
              <VRadio
                label="Sí"
                value="SÍ"
              />
              <VRadio
                label="No"
                value="NO"
              />
            </VRadioGroup>
          </VCol>
          <template v-if="garantiaData.is_same_address === 'NO'">
            <VCol
              cols="12"
              md="8"
            >
              <AppTextField
                v-model="garantiaData.direccion_motor"
                label="Dirección de recogida del producto"
                placeholder="Calle, número, piso"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="garantiaData.cp_motor"
                label="Código Postal"
                placeholder="28001"
              />
            </VCol>
          </template>
          <VCol cols="12">
            <p class="text-body-1 font-weight-medium">
              ENVÍO
            </p>
            <p class="text-caption">
              En los casos de envío de material todo transporte se realiza a portes pagados por el cliente mediante el transportista que nos indique.
            </p>
            <AppTextField
              v-model="garantiaData.agencia_transporte"
              label="Agencia de transporte *"
              placeholder="Indique su agencia"
              class="mt-4"
            />
          </VCol>

          <VCol cols="12">
            <p class="text-body-1 font-weight-medium">
              FORMA DE PAGO
            </p>
            <p class="text-caption">
              La forma de pago en las reparaciones se realiza al contado mediante una de estas dos modalidades.
            </p>
            <VRadioGroup
              v-model="garantiaData.modalidad_pago"
              class="mt-4"
            >
              <VRadio
                label="Contra reembolso"
                value="Contra reembolso"
              />
              <VRadio
                label="Transferencia a nuestra cuenta previa a la entrega"
                value="Transferencia"
              />
            </VRadioGroup>
          </VCol>

          <VCol cols="12">
            <VTextarea
              v-model="garantiaData.comentarios"
              label="Comentarios"
              placeholder="Cualquier información adicional"
            />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          @click="currentStep = 2"
        >
          Volver
        </VBtn>
        <VBtn @click="submitGarantia">
          Solicitar Garantía
        </VBtn>
      </VCardActions>
    </VCard>
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
