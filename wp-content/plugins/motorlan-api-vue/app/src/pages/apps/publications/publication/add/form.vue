<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '../../../../../composables/useToast'
import { useApi } from '../../../../../composables/useApi'
import api from '../../../../../services/api'
import { requiredValidator } from '../../../../../@core/utils/validators'

const { showToast } = useToast()
const router = useRouter()
const route = useRoute()
const { t } = useI18n()

const publicationType = computed(() => route.query.type as string)

// Stepper state
const currentStep = ref(1)
const newPostId = ref<number | null>(null)

const isLoading = ref(false)
import type { VForm } from 'vuetify/components'

const form = ref<VForm | null>(null)
const isFormValid = ref(false)

// Form data
const postData = ref<{
  title: string
  status: string
  categories: number[]
  tipo: number[]
  acf: {
    [key: string]: any
    marca: null | number
    tipo_o_referencia: string
    motor_image: null | number
    motor_gallery: (number | string)[]
    potencia: null | number
    velocidad: null | number
    par_nominal: null | number
    voltaje: null | number
    intensidad: null | number
    pais: null | string
    provincia: string
    estado_del_articulo: string
    informe_de_reparacion: null | number
    descripcion: string
    posibilidad_de_alquiler: string
    tipo_de_alimentacion: string
    servomotores: boolean
    regulacion_electronica_drivers: boolean
    precio_de_venta: null | number
    precio_negociable: string
    documentacion_adjunta: null | number
    publicar_acf: string
    stock: number
    documentacion_adicional: Documento[]
  }
  author_id: null | number
}>({
  title: '',
  status: 'publish',
  categories: [],
  tipo: [],
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
    documentacion_adjunta: null,
    publicar_acf: 'publish',
    stock: 1,
    documentacion_adicional: [],
  },
  author_id: null,
})

const garantiaData = ref<{
  motor_id: number | null
  is_same_address: string
  direccion_motor: string
  cp_motor: string
  agencia_transporte: string
  modalidad_pago: string
  comentarios: string
}>({
  motor_id: null,
  is_same_address: 'yes',
  direccion_motor: '',
  cp_motor: '',
  agencia_transporte: '',
  modalidad_pago: 'cod',
  comentarios: '',
})

const userData = ref<any>(null)

interface FileData {
  file: File
  url: string
}
interface Marca { id: number; name: string; title: string; value: number }
interface Categoria { term_id: number; name: string; title: string; value: number }
interface Tipo { term_id: number; name: string; slug: string; title: string; value: number }
interface Documento { nombre: string; archivo: File | number | null }

const marcas = ref<Marca[]>([])
const categories = ref<Categoria[]>([])
const tipos = ref<Tipo[]>([])
const motorImageFile = ref<FileData[]>([])
const motorGalleryFiles = ref<FileData[]>([])

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

const pageTitle = computed(() => {
  const baseTitle = t('add_publication.title')
  if (publicationType.value && tipos.value.length) {
    const selectedType = tipos.value.find(t => t.slug === publicationType.value)
    if (selectedType)
      return `${baseTitle}: ${selectedType.name}`
  }

  return baseTitle
})

const cardTitle = computed(() => {
  const slug = publicationType.value
  if (slug === 'motor')
    return t('add_publication.post_details.motor_section_title', 'Detalles del Motor')

  if (slug === 'regulador')
    return t('add_publication.post_details.regulator_section_title', 'Detalles del Regulador')

  if (slug === 'otro-repuesto')
    return t('add_publication.post_details.spare_part_section_title', 'Detalles del Repuesto')

  return t('add_publication.post_details.section_title', 'Detalles de la Publicación')
})

const apiEndpoint = '/wp-json/motorlan/v1/publicaciones'

// Fetch initial data for selects
onMounted(async () => {
  try {
    const [
      marcasResponse,
      categoriesResponse,
      tiposResponse,
      userResponse,
    ] = await Promise.all([
      useApi<Marca[]>('/wp-json/motorlan/v1/marcas'),
      useApi<Categoria[]>('/wp-json/motorlan/v1/publicacion-categories'),
      useApi<Tipo[]>('/wp-json/motorlan/v1/tipos'),
      useApi<any>('/wp-json/wp/v2/users/me?context=edit'),
    ])

    if (marcasResponse && marcasResponse.data.value) {
      marcas.value = marcasResponse.data.value.map(marca => ({
        id: marca.id,
        name: marca.name,
        title: marca.name,
        value: marca.id,
      }))
    }

    if (categoriesResponse && categoriesResponse.data.value) {
      categories.value = categoriesResponse.data.value.map(category => ({
        term_id: category.term_id,
        name: category.name,
        title: category.name,
        value: category.term_id,
      }))
    }

    if (tiposResponse && tiposResponse.data.value) {
      tipos.value = tiposResponse.data.value.map(tipo => ({
        term_id: tipo.term_id,
        name: tipo.name,
        slug: tipo.slug,
        title: tipo.name,
        value: tipo.term_id,
      }))
    }

    if (userResponse && userResponse.data)
      userData.value = userResponse.data.value
  }
  catch (error) {
    console.error('Error al obtener los datos iniciales:', error)
  }
})

watch(tipos, newTipos => {
  if (newTipos.length && publicationType.value) {
    const selectedType = newTipos.find(t => t.slug === publicationType.value)
    if (selectedType)
      postData.value.tipo = [selectedType.value]
  }
}, { immediate: true })

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


const addDocument = () => {
  if (postData.value.acf.documentacion_adicional.length < 5)
    postData.value.acf.documentacion_adicional.push({ nombre: '', archivo: null })
}

const removeDocument = (index: number) => {
  postData.value.acf.documentacion_adicional.splice(index, 1)
}

const handleFileUpload = (event: Event, index: number) => {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]
    postData.value.acf.documentacion_adicional[index].archivo = file
  }
}

const createPostAndContinue = async () => {
  const { valid } = await form.value?.validate() ?? { valid: false }
  if (!valid) {
    showToast(t('add_publication.toasts.required_fields_error'), 'error')

    return
  }

  isLoading.value = true
  try {
    // Handle main image upload
    if (motorImageFile.value.length > 0) {
      const image = motorImageFile.value[0]
      if (image.file) {
        const uploadedImageId = await uploadMedia(image.file)

        postData.value.acf.motor_image = uploadedImageId
      }
    }

    // Handle gallery images upload
    if (motorGalleryFiles.value.length > 0) {
      const newGalleryIds = []
      for (const image of motorGalleryFiles.value) {
        if (image.file) {
          const uploadedImageId = await uploadMedia(image.file)

          newGalleryIds.push(uploadedImageId)
        }
      }
      postData.value.acf.motor_gallery = newGalleryIds
    }

    // Handle file uploads for additional documentation
    for (const doc of postData.value.acf.documentacion_adicional) {
      if (doc.archivo instanceof File) {
        const fileId = await uploadMedia(doc.archivo)

        doc.archivo = fileId
      }
    }
    // Insertar id de usuario como autor
    if (userData.value?.id) {
      postData.value.author_id = userData.value.id
    }

    const response = await useApi<any>(apiEndpoint, {
      method: 'POST',
      body: JSON.stringify(postData.value),
    })

    newPostId.value = response.data.value.id
    showToast(t('add_publication.toasts.post_created_success'), 'success')

    const tipoTerm = tipos.value.find(t => t.value === postData.value.tipo[0])
    if (tipoTerm && tipoTerm.name === 'Otro Repuesto')
      router.push('/apps/publications/publication/list')

    else
      currentStep.value = 2
  }
  catch (error: any) {
    showToast(t('add_publication.toasts.post_created_error', { message: error.message }), 'error')
    console.error('Failed to create post:', error)
  }
  finally {
    isLoading.value = false
  }
}

// Step 2: Skip warranty
const skipGarantia = () => {
  if (confirm(t('add_publication.toasts.warranty_skipped_confirmation'))) {
    showToast(t('add_publication.toasts.warranty_skipped_info'), 'info')
    router.push('/apps/publications/publication/list')
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
    showToast(t('add_publication.toasts.post_id_not_found_error'), 'error')

    return
  }

  garantiaData.value.motor_id = newPostId.value

  try {
    await api('/wp-json/motorlan/v1/garantias', {
      method: 'POST',
      body: garantiaData.value,
    })
    showToast(t('add_publication.toasts.warranty_request_success'), 'success')
    router.push('/apps/publications/publication/list')
  }
  catch (error: any) {
    showToast(t('add_publication.toasts.warranty_request_error', { message: error.message }), 'error')
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
          v-if="postData.tipo[0] !== 3"
          class="text-body-1"
        >{{ t('add_publication.step', { currentStep }) }}</span>
      </div>
      <div class="d-flex gap-4 align-center flex-wrap">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="router.push('/apps/publications/publication/list')"
        >
          {{ t('add_publication.buttons.discard') }}
        </VBtn>
        <VBtn
          v-if="currentStep === 1"
          @click="createPostAndContinue"
        >
          {{ t('add_publication.buttons.save_and_continue') }}
        </VBtn>
      </div>
    </div>

    <!-- Step 1: Post Details -->
    <VForm
      ref="form"
      v-model="isFormValid"
      @submit.prevent="createPostAndContinue"
    >
      <VRow v-if="currentStep === 1">
        <VCol>
          <VCard
            class="mb-6"
            :title="cardTitle"
          >
            <VCardText>
              <VRow>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="postData.title"
                    :label="t('add_publication.post_details.title')"
                    :placeholder="t('add_publication.post_details.title_placeholder')"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="postData.acf.tipo_o_referencia"
                    :label="t('add_publication.post_details.reference')"
                    :placeholder="t('add_publication.post_details.reference_placeholder')"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppSelect
                    v-model="postData.acf.marca"
                    :label="t('add_publication.post_details.brand')"
                    :items="marcas"
                    item-title="title"
                    item-value="value"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppSelect
                    v-model="postData.categories"
                    :label="t('add_publication.post_details.category')"
                    :items="categories"
                    multiple
                  />
                </VCol>
                <template v-if="postData.tipo[0] !== 3">
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="postData.acf.potencia"
                      :label="t('add_publication.post_details.power')"
                      type="number"
                      :placeholder="t('add_publication.post_details.power_placeholder')"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="postData.acf.velocidad"
                      :label="t('add_publication.post_details.speed')"
                      type="number"
                      :placeholder="t('add_publication.post_details.speed_placeholder')"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="postData.acf.par_nominal"
                      :label="t('add_publication.post_details.torque')"
                      type="number"
                      :placeholder="t('add_publication.post_details.torque_placeholder')"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="postData.acf.voltaje"
                      :label="t('add_publication.post_details.voltage')"
                      type="number"
                      :placeholder="t('add_publication.post_details.voltage_placeholder')"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="postData.acf.intensidad"
                      :label="t('add_publication.post_details.intensity')"
                      type="number"
                      :placeholder="t('add_publication.post_details.intensity_placeholder')"
                    />
                  </VCol>
                </template>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppSelect
                    v-model="postData.acf.pais"
                    :label="t('add_publication.post_details.country')"
                    :items="countryOptions"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="postData.acf.provincia"
                    :label="t('add_publication.post_details.province')"
                    :placeholder="t('add_publication.post_details.province_placeholder')"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppSelect
                    v-model="postData.acf.estado_del_articulo"
                    :label="t('add_publication.post_details.condition')"
                    :items="conditionOptions"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol cols="12">
                  <VTextarea
                    v-model="postData.acf.descripcion"
                    :label="t('add_publication.post_details.description')"
                    :placeholder="t('add_publication.post_details.description_placeholder')"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <template v-if="postData.tipo[0] !== 3">
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VRadioGroup
                      v-model="postData.acf.posibilidad_de_alquiler"
                      inline
                      :label="t('add_publication.post_details.rent_option')"
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
                    md="6"
                  >
                    <VRadioGroup
                      v-model="postData.acf.tipo_de_alimentacion"
                      inline
                      :label="t('add_publication.post_details.power_supply_type')"
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
                    md="6"
                  >
                    <VCheckbox
                      v-model="postData.acf.servomotores"
                      :label="t('add_publication.post_details.servomotors')"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VCheckbox
                      v-model="postData.acf.regulacion_electronica_drivers"
                      :label="t('add_publication.post_details.electronic_regulation')"
                    />
                  </VCol>
                </template>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="postData.acf.precio_de_venta"
                    :label="t('add_publication.post_details.price')"
                    type="number"
                    :placeholder="t('add_publication.post_details.price_placeholder')"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="postData.acf.stock"
                    :label="t('add_publication.post_details.stock')"
                    type="number"
                    :placeholder="t('add_publication.post_details.stock_placeholder')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <VRadioGroup
                    v-model="postData.acf.precio_negociable"
                    inline
                    :label="t('add_publication.post_details.negotiable_price')"
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
                <VCol cols="12">
                  <VRadioGroup
                    v-model="postData.status"
                    inline
                    :label="t('add_publication.post_details.publish_acf')"
                  >
                    <VRadio
                      :label="t('add_publication.post_details.publish_status_publish')"
                      value="publish"
                    />
                    <VRadio
                      :label="t('add_publication.post_details.publish_status_draft')"
                      value="draft"
                    />
                  </VRadioGroup>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
          <VCard
            class="mb-6"
            :title="t('add_publication.media.main_image')"
          >
            <VCardText>
              <DropZone
                v-model="motorImageFile"
                :multiple="false"
              />
            </VCardText>
          </VCard>
          <VCard
            class="mb-6"
            :title="t('add_publication.media.image_gallery')"
          >
            <VCardText>
              <DropZone v-model="motorGalleryFiles" />
            </VCardText>
          </VCard>
          <VCard
            class="mb-6"
            :title="t('add_publication.media.additional_documentation')"
          >
            <VCardText>
              <div
                v-for="(doc, index) in postData.acf.documentacion_adicional"
                :key="index"
                class="d-flex gap-4 mb-4 align-center"
              >
                <AppTextField
                  v-model="doc.nombre"
                  :label="t('add_publication.media.document_name')"
                  :placeholder="t('add_publication.media.document_name_placeholder')"
                  style="width: 300px;"
                />
                <VFileInput
                  :label="t('add_publication.media.upload_file')"
                  @change="(event: Event) => handleFileUpload(event, index)"
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
                v-if="postData.acf.documentacion_adicional.length < 5"
                @click="addDocument"
              >
                {{ t('add_publication.buttons.add_document') }}
              </VBtn>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VForm>

    <!-- Step 2: Warranty Offer -->
    <VCard
      v-if="currentStep === 2"
      :title="t('add_publication.warranty.add_warranty_title')"
      class="mb-6"
    >
      <VCardText>
        <p class="mb-4">
          <strong>{{ t('add_publication.warranty.add_warranty_subtitle') }}</strong>
        </p>
        <p>
          {{ t('add_publication.warranty.add_warranty_text1') }}
          {{ t('add_publication.warranty.add_warranty_text2') }}
        </p>
        <p class="mt-2">
          {{ t('add_publication.warranty.add_warranty_text3') }}
        </p>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          color="secondary"
          @click="skipGarantia"
        >
          {{ t('add_publication.buttons.skip') }}
        </VBtn>
        <VBtn @click="goToGarantiaForm">
          {{ t('add_publication.buttons.accept_and_add_warranty') }}
        </VBtn>
      </VCardActions>
    </VCard>

    <!-- Step 3: Warranty Form -->
    <VCard
      v-if="currentStep === 3"
      :title="t('add_publication.warranty.form_title')"
      class="mb-6"
    >
      <VCardText>
        <VRow>
          <VCol cols="12">
            <VRadioGroup
              v-model="garantiaData.is_same_address"
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
          <template v-if="garantiaData.is_same_address === 'no'">
            <VCol
              cols="12"
              md="8"
            >
              <AppTextField
                v-model="garantiaData.direccion_motor"
                :label="t('add_publication.warranty.pickup_address')"
                :placeholder="t('add_publication.warranty.pickup_address_placeholder')"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="garantiaData.cp_motor"
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
              v-model="garantiaData.agencia_transporte"
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
              v-model="garantiaData.modalidad_pago"
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
              v-model="garantiaData.comentarios"
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
          @click="currentStep = 2"
        >
          {{ t('add_publication.buttons.go_back') }}
        </VBtn>
        <VBtn @click="submitGarantia">
          {{ t('add_publication.buttons.request_warranty') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </div>
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
