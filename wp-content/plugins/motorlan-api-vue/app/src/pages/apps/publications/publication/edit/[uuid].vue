<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import DropZone from '@/@core/components/DropZone.vue'
import { requiredValidator } from '@/@core/utils/validators'
import { useToast } from '@/composables/useToast'

const { showToast } = useToast()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const motorUuid = route.params.uuid as string

const motorData = ref({
  title: '',
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
    estado_del_articulo: 'new',
    informe_de_reparacion: null,
    descripcion: '',
    posibilidad_de_alquiler: 'no',
    tipo_de_alimentacion: 'ac',
    servomotores: false,
    regulacion_electronica_drivers: false,
    precio_de_venta: null,
    precio_negociable: 'no',
    stock: null,
    documentacion_adicional: [],
  },
})

const motorImageFile = ref([])
const motorGalleryFiles = ref([])

const marcas = ref([])
const categories = ref([])
const form = ref(null)
const isFormValid = ref(false)

onMounted(async () => {
  try {
    // 1. Crear un arreglo de promesas
    const promises = [
      useApi('/wp-json/motorlan/v1/marcas'),
      useApi('/wp-json/motorlan/v1/motor-categories'),
    ]

    // Añadir la promesa del motor solo si existe el UUID
    if (motorUuid)
      promises.push(useApi(`/wp-json/motorlan/v1/publications/uuid/${motorUuid}`))

    // 2. Ejecutar todas las promesas en paralelo
    const [marcasResponse, categoriesResponse, motorResponse] = await Promise.all(promises)

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

    // Procesar datos del motor (si se solicitó)
    if (motorUuid && motorResponse && motorResponse.data.value) {
      const post = motorResponse.data.value

      // Assign data from post to motorData
      motorData.value.title = post.title
      motorData.value.categories = post.categories ? post.categories.map((cat: { id: any }) => cat.id) : []
      motorData.value.acf = { ...motorData.value.acf, ...post.acf }

      // Poblar las referencias de archivos para DropZone
      if (motorData.value.acf.motor_image) {
        motorImageFile.value = [{
          url: motorData.value.acf.motor_image.url,
          id: motorData.value.acf.motor_image.id,
        }]
      }
      if (motorData.value.acf.motor_gallery) {
        motorGalleryFiles.value = motorData.value.acf.motor_gallery.map(img => ({
          url: img.url,
          id: img.id,
        }))
      }

      if (motorData.value.acf.stock === null || motorData.value.acf.stock === undefined) {
        motorData.value.acf.stock = 1
      }
    }
  }
  catch (error) {
    console.error('Error al obtener los datos iniciales:', error)
  }
})

const uploadMedia = async (file: File) => {
  const accessToken = useCookie('accessToken').value
  const formData = new FormData()

  formData.append('file', file)

  const response = await fetch('/wp-json/wp/v2/media', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${accessToken}`,
    },
    body: formData,
  })

  if (!response.ok)
    throw new Error('Failed to upload image')

  return response.json()
}

const updateMotor = async () => {
  const { valid } = await form.value.validate()

  if (!valid) {
    showToast(t('edit_publication.required_fields_error'), 'error')

    return
  }

  const url = `/wp-json/motorlan/v1/publications/uuid/${motorUuid}`
  const method = 'POST'

  try {
    // Handle main image upload
    if (motorImageFile.value.length > 0) {
      const image = motorImageFile.value[0]
      if (image.file) { // New file
        const uploadedImage = await uploadMedia(image.file)

        motorData.value.acf.motor_image = uploadedImage.id
      }
      else { // Existing image
        motorData.value.acf.motor_image = image.id
      }
    }
    else {
      motorData.value.acf.motor_image = null
    }

    // Handle gallery images upload
    if (motorGalleryFiles.value.length > 0) {
      const newGalleryIds = []
      for (const image of motorGalleryFiles.value) {
        if (image.file) { // New file
          const uploadedImage = await uploadMedia(image.file)

          newGalleryIds.push(uploadedImage.id)
        }
        else { // Existing image
          newGalleryIds.push(image.id)
        }
      }
      motorData.value.acf.motor_gallery = newGalleryIds
    }
    else {
      motorData.value.acf.motor_gallery = []
    }

    // Handle additional documentation
    if (motorData.value.acf.documentacion_adicional) {
      console.log('Processing additional documentation:', motorData.value.acf.documentacion_adicional)
      for (const doc of motorData.value.acf.documentacion_adicional) {
        console.log('Processing doc:', doc)
        if (doc.archivo instanceof File) {
          const uploadedFile = await uploadMedia(doc.archivo)
          console.log('Uploaded file:', uploadedFile)
          doc.archivo = uploadedFile.id
        } else if (doc.archivo && doc.archivo.id) {
          doc.archivo = doc.archivo.id
        }
      }
    }

    console.log('Data to send:', JSON.stringify(motorData.value, null, 2))
    await useApi(url, {
      method,
      body: motorData.value,
    })
    showToast(t('edit_publication.update_success'), 'success')
    router.push('/apps/publications/publication/list')
  }
  catch (error) {
    showToast(t('edit_publication.update_error', { message: error.message }), 'error')
    console.error('Failed to update motor:', error)
  }
}

const formattedCategories = computed({
  get() {
    if (Array.isArray(motorData.value.categories))
      return motorData.value.categories.map(cat => (typeof cat === 'object' ? cat.id : cat))

    return []
  },
  set(newValue) {
    motorData.value.categories = newValue
  },
})

const formattedMarca = computed({
  get() {
    const marca = motorData.value.acf.marca
    if (marca && typeof marca === 'object')
      return marca.id

    return marca
  },
  set(newValue) {
    motorData.value.acf.marca = newValue
  },
})

const addDocument = () => {
  if (motorData.value.acf.documentacion_adicional.length < 5) {
    motorData.value.acf.documentacion_adicional.push({ nombre: '', archivo: null })
  }
}

const removeDocument = (index) => {
  motorData.value.acf.documentacion_adicional.splice(index, 1)
}

const handleFileUpload = (event, index) => {
  const file = event.target.files[0]
  if (file) {
    motorData.value.acf.documentacion_adicional[index].archivo = file
  }
}
</script>

<template>
  <div>
    <VForm
      ref="form"
      v-model="isFormValid"
      @submit.prevent="updatePublicacion"
    >
      <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
        <div class="d-flex flex-column justify-center">
          <h4 class="text-h4 font-weight-medium">
            {{ t('edit_publication.title') }}
          </h4>
        </div>
        <div class="d-flex gap-4 align-center flex-wrap">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="router.push('/apps/publicaciones/publicacion/list')"
          >
            {{ t('edit_publication.discard') }}
          </VBtn>
          <VBtn
            type="submit"
            @click="updateMotor"
          >
            {{ t('edit_publication.update_publication') }}
          </VBtn>
        </div>
      </div>
      <VRow>
        <VCol>
          <VCard
            class="mb-6"
            :title="t('edit_publication.section_title')"
          >
            <VCardText>
              <VRow>
                <!-- Fields from here -->
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="motorData.title"
                    :label="t('edit_publication.publication_title')"
                    :placeholder="t('edit_publication.publication_title_placeholder')"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="motorData.acf.tipo_o_referencia"
                    :label="t('edit_publication.reference')"
                    :placeholder="t('edit_publication.reference_placeholder')"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppSelect
                    v-model="formattedMarca"
                    :label="t('edit_publication.brand')"
                    item-title="name"
                    item-value="id"
                    :items="marcas"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppSelect
                    v-model="formattedCategories"
                    :label="t('edit_publication.category')"
                    :items="categories"
                    item-title="name"
                    item-value="id"
                    multiple
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppSelect
                    v-model="formattedTipo"
                    :label="t('edit_publication.type')"
                    :items="tipos"
                    item-title="name"
                    item-value="id"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="motorData.acf.potencia"
                    :label="t('edit_publication.power')"
                    type="number"
                    :placeholder="t('edit_publication.power_placeholder')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="motorData.acf.velocidad"
                    :label="t('edit_publication.speed')"
                    type="number"
                    :placeholder="t('edit_publication.speed_placeholder')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="motorData.acf.par_nominal"
                    :label="t('edit_publication.torque')"
                    type="number"
                    :placeholder="t('edit_publication.torque_placeholder')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="motorData.acf.voltaje"
                    :label="t('edit_publication.voltage')"
                    type="number"
                    :placeholder="t('edit_publication.voltage_placeholder')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="motorData.acf.intensidad"
                    :label="t('edit_publication.intensity')"
                    type="number"
                    :placeholder="t('edit_publication.intensity_placeholder')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppSelect
                    v-model="motorData.acf.pais"
                    :label="t('edit_publication.country')"
                    :items="['España', 'Portugal', 'Francia']"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="motorData.acf.provincia"
                    :label="t('edit_publication.province')"
                    :placeholder="t('edit_publication.province_placeholder')"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppSelect
                    v-model="motorData.acf.estado_del_articulo"
                    :label="t('edit_publication.condition')"
                    :items="['Nuevo', 'Usado', 'Restaurado']"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="motorData.acf.precio_de_venta"
                    :label="t('edit_publication.price')"
                    type="number"
                    :placeholder="t('edit_publication.price_placeholder')"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="motorData.acf.stock"
                    :label="t('edit_publication.stock')"
                    type="number"
                    :placeholder="t('edit_publication.stock_placeholder')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <VRadioGroup
                    v-model="motorData.acf.precio_negociable"
                    inline
                    :label="t('edit_publication.negotiable_price')"
                  >
                    <VRadio
                      :label="t('add_publication.boolean_options.yes')"
                      value="yes"
                    />
                    <VRadio
                      :label="t('add_publication.boolean_options.no')"
                      value="no"
                    />
                  </VRadioGroup>
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <VRadioGroup
                    v-model="motorData.acf.posibilidad_de_alquiler"
                    inline
                    :label="t('edit_publication.rent_option')"
                    :rules="[requiredValidator]"
                  >
                    <VRadio
                      :label="t('add_publication.boolean_options.yes')"
                      value="yes"
                    />
                    <VRadio
                      :label="t('add_publication.boolean_options.no')"
                      value="no"
                    />
                  </VRadioGroup>
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <VRadioGroup
                    v-model="motorData.acf.tipo_de_alimentacion"
                    inline
                    :label="t('edit_publication.power_supply_type')"
                    :rules="[requiredValidator]"
                  >
                    <VRadio
                      :label="t('add_publication.power_supply_options.dc')"
                      value="dc"
                    />
                    <VRadio
                      :label="t('add_publication.power_supply_options.ac')"
                      value="ac"
                    />
                  </VRadioGroup>
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <VCheckbox
                    v-model="motorData.acf.servomotores"
                    :label="t('edit_publication.servomotors')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <VCheckbox
                    v-model="motorData.acf.regulacion_electronica_drivers"
                    :label="t('edit_publication.electronic_regulation')"
                  />
                </VCol>
                <VCol cols="12">
                  <VTextarea
                    v-model="motorData.acf.descripcion"
                    :label="t('edit_publication.description')"
                    :placeholder="t('edit_publication.description_placeholder')"
                    :rules="[requiredValidator]"
                  />
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
          <VCard
            class="mb-6"
            :title="t('edit_publication.images_section_title')"
          >
            <VCardText>
              <VRow>
                <VCol
                  cols="12"
                  md="6"
                >
                  <VLabel class="mb-1 text-body-2 text-high-emphasis">
                    {{ t('edit_publication.main_image') }}
                  </VLabel>
                  <DropZone
                    v-model="motorImageFile"
                    :multiple="false"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <VLabel class="mb-1 text-body-2 text-high-emphasis">
                    {{ t('edit_publication.image_gallery') }}
                  </VLabel>
                  <DropZone v-model="motorGalleryFiles" />
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <VCard
            class="mb-6"
            :title="t('edit_publication.additional_documentation_section_title')"
          >
            <VCardText>
              <div
                v-for="(doc, index) in motorData.acf.documentacion_adicional"
                :key="index"
                class="document-row d-flex gap-4 mb-4 align-center"
              >
                <AppTextField
                  v-model="doc.nombre"
                  :label="t('edit_publication.document_name')"
                  :placeholder="t('edit_publication.document_name_placeholder')"
                  style="width: 300px;"
                />

                <div v-if="doc.archivo">
                  <a
                    v-if="doc.archivo.url"
                    :href="doc.archivo.url"
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    {{ doc.archivo.filename }}
                  </a>
                  <span v-else>{{ doc.archivo.name }}</span>
                  <VBtn
                    icon
                    variant="text"
                    size="small"
                    @click="doc.archivo = null"
                  >
                    <VIcon
                      icon="tabler-x"
                      size="20"
                    />
                  </VBtn>
                </div>

                <VFileInput
                  v-else
                  :label="t('edit_publication.upload_file')"
                  @change="event => handleFileUpload(event, index)"
                />

                <VBtn
                  icon
                  variant="text"
                  color="error"
                  size="small"
                  @click="removeDocument(index)"
                >
                  <VIcon
                    icon="tabler-trash"
                    size="20"
                  />
                </VBtn>
              </div>
              <VBtn
                v-if="!motorData.acf.documentacion_adicional || motorData.acf.documentacion_adicional.length < 5"
                @click="addDocument"
              >
                {{ t('edit_publication.add_document') }}
              </VBtn>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VForm>
  </div>
</template>
