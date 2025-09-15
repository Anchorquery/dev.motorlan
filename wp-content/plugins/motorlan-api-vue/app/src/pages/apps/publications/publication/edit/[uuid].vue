<script setup lang="ts">
// @ts-nocheck
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'
import  DropZone  from '@/@core/components/DropZone.vue'
import { requiredValidator } from '@/@core/utils/validators'
import { useToast } from '@/composables/useToast'

const { showToast } = useToast()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const motorUuid = route.params.uuid as string

interface AcfData {
  marca: number | null | { id: number; name: string }
  tipo_o_referencia: string
  motor_image: any
  motor_gallery: any[]
  potencia: number | null
  velocidad: number | null
  par_nominal: number | null
  voltaje: number | null
  intensidad: number | null
  pais: string | null
  provincia: string
  estado_del_articulo: string
  informe_de_reparacion: any
  descripcion: string
  posibilidad_de_alquiler: string
  tipo_de_alimentacion: string
  servomotores: boolean
  regulacion_electronica_drivers: boolean
  precio_de_venta: number | null
  precio_negociable: string
  stock: number | null
  documentacion_adicional: { nombre: string; archivo: any }[]
}

interface MotorData {
  title: string
  categories: any[]
  acf: AcfData
  tipo?: any[]
}

interface Tipo {
  title: string
  value: number
  slug: string
}

const motorData = ref<MotorData>({
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

interface DropZoneFile {
  file?: File
  url?: string
  id?: number
}

const motorImageFile = ref<DropZoneFile[]>([])
const motorGalleryFiles = ref<DropZoneFile[]>([])

const marcas = ref([])
const categories = ref([])
const tipos = ref<Tipo[]>([])
import type { VForm } from 'vuetify/components'

const form = ref<VForm | null>(null)
const isFormValid = ref(false)
const garantiaData = ref<any>(null)
const isLoading = ref(false)
const isWarrantyModalVisible = ref(false)
const postId = ref<number | null>(null)

const newGarantiaData = ref({
  motor_id: null as number | null,
  is_same_address: 'yes',
  direccion_motor: '',
  cp_motor: '',
  agencia_transporte: '',
  modalidad_pago: 'cod',
  comentarios: '',
})

onMounted(async () => {
  try {
    const fetchPromises = [
      useApi('/wp-json/motorlan/v1/marcas').get().json(),
      useApi('/wp-json/motorlan/v1/publicacion-categories').get().json(),
      useApi('/wp-json/motorlan/v1/tipos').get().json(),
    ]

    if (motorUuid) {
      fetchPromises.push(useApi(`/wp-json/motorlan/v1/publicaciones/uuid/${motorUuid}`).get().json())
      fetchPromises.push(useApi(`/wp-json/motorlan/v1/garantias/publicacion/${motorUuid}`).get().json())
    }

    const [
      { data: marcasData },
      { data: categoriesData },
      { data: tiposData },
      motorResponse,
      garantiaResponse,
    ] = await Promise.all(fetchPromises)

    if (marcasData.value) {
      const uniqueMarcas = new Map()
      marcasData.value.forEach((marca: { id: any; name: any }) => {
        uniqueMarcas.set(Number(marca.id), { title: marca.name, value: Number(marca.id) })
      })
      marcas.value = Array.from(uniqueMarcas.values())
    }

    if (categoriesData.value) {
      categories.value = categoriesData.value.map((category: { name: any; term_id: any }) => ({
        name: category.name,
        id: category.term_id,
      }))
    }

    if (tiposData.value) {
      tipos.value = tiposData.value.map((tipo: { name: any; term_id: any; slug: any }) => ({
        title: tipo.name,
        value: tipo.term_id,
        slug: tipo.slug,
      }))
    }

    if (motorUuid && motorResponse && motorResponse.data.value) {
      const post = motorResponse.data.value
      postId.value = post.id

      motorData.value.title = post.title
      motorData.value.categories = post.categories ? post.categories.map((cat: { id: any }) => cat.id) : []
      motorData.value.acf = { ...motorData.value.acf, ...post.acf }
      motorData.value.tipo = post.tipo ? post.tipo.map((t: { id: any }) => t.id) : []

      if (motorData.value.acf.marca && typeof motorData.value.acf.marca === 'object')
        motorData.value.acf.marca = motorData.value.acf.marca.id

      if (motorData.value.acf.marca !== null && motorData.value.acf.marca !== undefined)
        motorData.value.acf.marca = Number(motorData.value.acf.marca)

      if (motorData.value.acf.motor_image) {
        motorImageFile.value = [{
          url: motorData.value.acf.motor_image.url,
          id: motorData.value.acf.motor_image.id,
        }]
      }

      if (motorData.value.acf.motor_gallery) {
        motorGalleryFiles.value = motorData.value.acf.motor_gallery.map((img: any) => ({
          url: img.url,
          id: img.id,
        }))
      }

      if (motorData.value.acf.stock === null || motorData.value.acf.stock === undefined)
        motorData.value.acf.stock = 1

      if (!motorData.value.acf.documentacion_adicional)
        motorData.value.acf.documentacion_adicional = []

      // Normalize legacy values
      const countryMap: { [key: string]: string } = {
        'España': 'spain', 'España': 'spain', 'Spain': 'spain', 'Portugal': 'portugal', 'Francia': 'france', 'France': 'france',
      }
      const conditionMap: { [key: string]: string } = {
        'Nuevo': 'new', 'Usado': 'used', 'Restaurado': 'restored',
      }

      const pais = motorData.value.acf.pais
      if (typeof pais === 'string' && countryMap[pais])
        motorData.value.acf.pais = countryMap[pais]

      const estado = motorData.value.acf.estado_del_articulo
      if (typeof estado === 'string' && conditionMap[estado])
        motorData.value.acf.estado_del_articulo = conditionMap[estado]
    }

    if (garantiaResponse && garantiaResponse.data.value)
      garantiaData.value = garantiaResponse.data.value
  }
  catch (error) {
    console.error('Error al obtener los datos iniciales:', error)
  }
})

const uploadMedia = async (file: File, attachToPostId?: number) => {
  const formData = new FormData()
  formData.append('file', file)
  if (attachToPostId)
    formData.append('post', String(attachToPostId))
  try {
    const { data } = await useApi<any>('/wp-json/wp/v2/media', {
      method: 'POST',
      body: formData,
    })
    return data.value?.id ?? null
  }
  catch (error) {
    console.error('Failed to upload media:', error)
    return null
  }
}

const updateMotor = async (status: string) => {
  if (!form.value)
    return

  const { valid } = await form.value.validate()

  if (!valid) {
    showToast(t('edit_publication.required_fields_error'), 'error')

    return
  }

  const url = `/wp-json/motorlan/v1/publicaciones/uuid/${motorUuid}`
  const method = 'POST'

  isLoading.value = true
  try {
    const payload = JSON.parse(JSON.stringify(motorData.value))

    payload.status = status
    // Asegurar que el estado también se guarde en el campo ACF correcto
    if (!payload.acf) payload.acf = {}
    payload.acf.publicar_acf = status

    if (payload.acf.marca && typeof payload.acf.marca === 'object')
      payload.acf.marca = payload.acf.marca.id

    // Handle main image upload (ensure ID)
    if (motorImageFile.value.length > 0) {
      const image = motorImageFile.value[0]
      if (image.file) {
        const uploadedImageId = await uploadMedia(image.file, postId.value ?? undefined)
        if (uploadedImageId)
          payload.acf.motor_image = uploadedImageId
      } else if (image.id) {
        payload.acf.motor_image = image.id
      }
    } else {
      payload.acf.motor_image = null
    }

    // Handle gallery images upload (ensure IDs array)
    if (motorGalleryFiles.value.length > 0) {
      const galleryIds: number[] = []
      for (const image of motorGalleryFiles.value) {
        if (image.file) {
          const uploadedImageId = await uploadMedia(image.file, postId.value ?? undefined)
          if (uploadedImageId)
            galleryIds.push(uploadedImageId)
        } else if (image.id) {
          galleryIds.push(image.id)
        }
      }
      payload.acf.motor_gallery = galleryIds
    } else {
      payload.acf.motor_gallery = []
    }

    // Handle additional documentation (ensure IDs only)
    if (payload.acf.documentacion_adicional) {
      for (let i = 0; i < payload.acf.documentacion_adicional.length; i++) {
        const doc = payload.acf.documentacion_adicional[i]
        if (doc.archivo instanceof File) {
          const uploadedFile = await uploadMedia(doc.archivo)
          if (uploadedFile)
            doc.archivo = uploadedFile
        } else if (doc.archivo && doc.archivo.id) {
          doc.archivo = doc.archivo.id
        }
      }
    }

    console.log('Data to send:', payload)
    await useApi(url, {
      method,
      body: JSON.stringify(payload),
    })
    showToast(t('edit_publication.update_success'), 'success')
    router.push('/apps/publications/publication/list')
  }
  catch (error: any) {
    showToast(t('edit_publication.update_error', { message: error.message }), 'error')
    console.error('Failed to update motor:', error)
  }
  finally {
    isLoading.value = false
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

const pageTitle = computed(() => {
  const baseTitle = t('edit_publication.title')
  if (motorData.value.tipo && motorData.value.tipo.length > 0 && tipos.value.length > 0) {
    const tipoId = motorData.value.tipo[0]
    const tipoEncontrado = tipos.value.find(t => t.value === tipoId)
    if (tipoEncontrado)
      return `${baseTitle}: ${tipoEncontrado.title}`
  }

  return baseTitle
})

// v-model para Marca como ID numérico
const formattedMarca = computed({
  get() {
    const current = motorData.value.acf.marca
    if (current === null || current === undefined)
      return null
    return Number(typeof current === 'object' ? current.id : current)
  },
  set(newValue) {
    motorData.value.acf.marca = newValue !== null && newValue !== undefined ? Number(newValue) : null
  },
})

// Keep v-model as object for proper label display, store id internally

// Options for selects to map stored keys to labels
const conditionOptions = computed(() => [
  { title: t('add_publication.condition_options.new'), value: 'new' },
  { title: t('add_publication.condition_options.used'), value: 'used' },
  { title: t('add_publication.condition_options.restored'), value: 'restored' },
])

const countryOptions = computed(() => [
  { title: t('add_publication.country_options.spain'), value: 'spain' },
  { title: t('add_publication.country_options.portugal'), value: 'portugal' },
  { title: t('add_publication.country_options.france'), value: 'france' },
])

const addDocument = () => {
  if (motorData.value.acf.documentacion_adicional.length < 5)
    motorData.value.acf.documentacion_adicional.push({ nombre: '', archivo: null })
}

const removeDocument = (index: number) => {
  motorData.value.acf.documentacion_adicional.splice(index, 1)
}

const submitGarantia = async () => {
  if (!postId.value) {
    showToast(t('edit_publication.no_post_id_error', 'No se encontró el ID de la publicación.'), 'error')

    return
  }

  newGarantiaData.value.motor_id = postId.value

  try {
    isLoading.value = true
    await useApi('/wp-json/motorlan/v1/garantias').post(newGarantiaData.value).json()
    showToast(t('edit_publication.warranty_request_success', 'Solicitud de garantía enviada con éxito.'), 'success')
    isWarrantyModalVisible.value = false

    // Refresh warranty data
    const { data: garantiaDataResponse } = await useApi(`/wp-json/motorlan/v1/garantias/publicacion/${motorUuid}`).get().json()
    if (garantiaDataResponse.value)
      garantiaData.value = garantiaDataResponse.value
  }
  catch (error: any) {
    showToast(t('edit_publication.warranty_request_error', { message: error.message }), 'error')
    console.error('Failed to submit garantia:', error)
  }
  finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div>
    <VForm
      ref="form"
      v-model="isFormValid"
      @submit.prevent="updateMotor"
    >
      <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
        <div class="d-flex flex-column justify-center">
          <h4 class="text-h4 font-weight-medium">
            {{ pageTitle }}
          </h4>
        </div>
        <div class="d-flex gap-4 align-center flex-wrap">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="router.push('/apps/publications/publication/list')"
          >
            {{ t('edit_publication.discard') }}
          </VBtn>
          <VBtn
            variant="tonal"
            color="secondary"
            @click="updateMotor('draft')"
          >
            {{ t('edit_publication.save_draft', 'Guardar como borrador') }}
          </VBtn>
          <VBtn
            type="submit"
            @click="updateMotor('publish')"
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
                    item-title="title"
                    item-value="value"
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
                    :items="countryOptions"
                    item-title="title"
                    item-value="value"
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
                    :items="conditionOptions"
                    item-title="title"
                    item-value="value"
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
                  v-model="doc.archivo"
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

          <!-- Warranty Section -->
          <VCard
            class="mb-6"
            :title="t('edit_publication.warranty_section_title', 'Garantía')"
          >
            <VCardText v-if="garantiaData && garantiaData.id">
              <p><strong>{{ t('edit_publication.warranty_status', 'Estado') }}:</strong> {{ garantiaData.garantia_status }}</p>
              <p><strong>{{ t('edit_publication.warranty_pickup_address', 'Dirección de recogida') }}:</strong> {{ garantiaData.direccion_motor }}</p>
              <p><strong>{{ t('edit_publication.warranty_postal_code', 'Código Postal') }}:</strong> {{ garantiaData.cp_motor }}</p>
              <p><strong>{{ t('edit_publication.warranty_transport_agency', 'Agencia de transporte') }}:</strong> {{ garantiaData.agencia_transporte }}</p>
              <p><strong>{{ t('edit_publication.warranty_payment_method', 'Modalidad de pago') }}:</strong> {{ garantiaData.modalidad_pago }}</p>
              <p><strong>{{ t('edit_publication.warranty_comments', 'Comentarios') }}:</strong> {{ garantiaData.comentarios }}</p>
            </VCardText>
            <VCardText v-else>
              <p>{{ t('edit_publication.no_warranty_info', 'Esta publicación no tiene una garantía asociada.') }}</p>
              <VBtn
                class="mt-4"
                @click="isWarrantyModalVisible = true"
              >
                {{ t('edit_publication.request_warranty', 'Solicitar Garantía') }}
              </VBtn>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VForm>
    <!-- 👉 Loading overlay -->
    <VOverlay
      v-model="isLoading"
      class="d-flex align-center justify-center"
      persistent
    >
      <VProgressCircular
        indeterminate
        size="64"
        color="primary"
      />
    </VOverlay>

    <!-- Warranty Request Modal -->
    <VDialog
      v-model="isWarrantyModalVisible"
      max-width="800px"
    >
      <VCard :title="t('edit_publication.warranty_form_title', 'Formulario de Solicitud de Garantía')">
        <VCardText>
          <VRow>
            <VCol cols="12">
              <VRadioGroup
                v-model="newGarantiaData.is_same_address"
                inline
                :label="t('add_publication.warranty.same_address_question')"
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
            <template v-if="newGarantiaData.is_same_address === 'no'">
              <VCol
                cols="12"
                md="8"
              >
                <AppTextField
                  v-model="newGarantiaData.direccion_motor"
                  :label="t('add_publication.warranty.pickup_address')"
                  :placeholder="t('add_publication.warranty.pickup_address_placeholder')"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model="newGarantiaData.cp_motor"
                  :label="t('add_publication.warranty.postal_code')"
                  :placeholder="t('add_publication.warranty.postal_code_placeholder')"
                />
              </VCol>
            </template>
            <VCol cols="12">
              <p class="text-body-1 font-weight-medium">
                {{ t('add_publication.warranty.shipping_title') }}
              </p>
              <p class="text-caption">
                {{ t('add_publication.warranty.shipping_text') }}
              </p>
              <AppTextField
                v-model="newGarantiaData.agencia_transporte"
                :label="t('add_publication.warranty.shipping_agency')"
                :placeholder="t('add_publication.warranty.shipping_agency_placeholder')"
                class="mt-4"
              />
            </VCol>

            <VCol cols="12">
              <p class="text-body-1 font-weight-medium">
                {{ t('add_publication.warranty.payment_method_title') }}
              </p>
              <p class="text-caption">
                {{ t('add_publication.warranty.payment_method_text') }}
              </p>
              <VRadioGroup
                v-model="newGarantiaData.modalidad_pago"
                class="mt-4"
              >
                <VRadio
                  :label="t('add_publication.warranty.payment_method_cod')"
                  value="cod"
                />
                <VRadio
                  :label="t('add_publication.warranty.payment_method_transfer')"
                  value="transfer"
                />
              </VRadioGroup>
            </VCol>

            <VCol cols="12">
              <VTextarea
                v-model="newGarantiaData.comentarios"
                :label="t('add_publication.warranty.comments')"
                :placeholder="t('add_publication.warranty.comments_placeholder')"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isWarrantyModalVisible = false"
          >
            {{ t('edit_publication.cancel', 'Cancelar') }}
          </VBtn>
          <VBtn @click="submitGarantia">
            {{ t('edit_publication.submit_warranty_request', 'Enviar Solicitud') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
