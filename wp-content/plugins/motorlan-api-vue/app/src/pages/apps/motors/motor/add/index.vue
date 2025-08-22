<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from '@/composables/useToast'
import { useApi } from '@/composables/useApi'
import { useI18n } from 'vue-i18n'
import ar from '../i18n/ar.json'
import en from '../i18n/en.json'
import es from '../i18n/es.json'
import eu from '../i18n/eu.json'
import fr from '../i18n/fr.json'

const { showToast } = useToast()
const router = useRouter()
const { t, mergeLocaleMessage } = useI18n()

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

const postTypeOptions = computed(() => [
  { title: t('add_motor.post_type_options.motor'), value: 'motor' },
  { title: t('add_motor.post_type_options.regulator'), value: 'regulador' },
  { title: t('add_motor.post_type_options.other_spare_part'), value: 'otro_repuesto' },
])

const conditionOptions = computed(() => [
  { title: t('add_motor.condition_options.new'), value: 'Nuevo' },
  { title: t('add_motor.condition_options.used'), value: 'Usado' },
  { title: t('add_motor.condition_options.restored'), value: 'Restaurado' },
])

const countryOptions = computed(() => [
  { title: t('add_motor.country_options.spain'), value: 'España' },
  { title: t('add_motor.country_options.portugal'), value: 'Portugal' },
  { title: t('add_motor.country_options.france'), value: 'Francia' },
])

const pageTitle = computed(() => {
  const selectedType = postTypeOptions.value.find(o => o.value === postType.value)
  const postTypeTitle = selectedType ? selectedType.title : ''

  return t('add_motor.title', { postType: postTypeTitle })
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
  mergeLocaleMessage('ar', ar)
  mergeLocaleMessage('en', en)
  mergeLocaleMessage('es', es)
  mergeLocaleMessage('eu', eu)
  mergeLocaleMessage('fr', fr)
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
    showToast(t('add_motor.toasts.post_created_success'), 'success')

    if (postType.value === 'otro_repuesto') {
      router.push('/apps/motors/motor/list')
    }
    else {
      currentStep.value = 2
    }
  }
  catch (error: any) {
    showToast(t('add_motor.toasts.post_created_error', { message: error.message }), 'error')
    console.error('Failed to create post:', error)
  }
}

// Step 2: Skip warranty
const skipGarantia = () => {
  // A simple confirm dialog
  if (confirm(t('add_motor.toasts.warranty_skipped_confirmation'))) {
    showToast(t('add_motor.toasts.warranty_skipped_info'), 'info')
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
    showToast(t('add_motor.toasts.post_id_not_found_error'), 'error')

    return
  }

  garantiaData.value.motor_id = newPostId.value

  try {
    await useApi('/wp-json/motorlan/v1/garantias', {
      method: 'POST',
      body: garantiaData.value,
    })
    showToast(t('add_motor.toasts.warranty_request_success'), 'success')
    router.push('/apps/motors/motor/list')
  }
  catch (error: any) {
    showToast(t('add_motor.toasts.warranty_request_error', { message: error.message }), 'error')
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
        >{{ t('add_motor.step', { currentStep }) }}</span>
      </div>
      <div class="d-flex gap-4 align-center flex-wrap">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="router.push('/apps/motors/motor/list')"
        >
          {{ t('add_motor.buttons.discard') }}
        </VBtn>
        <VBtn
          v-if="currentStep === 1"
          @click="createPostAndContinue"
        >
          {{ t('add_motor.buttons.save_and_continue') }}
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
                  :label="t('add_motor.post_details.publication_type')"
                  :items="postTypeOptions"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
        <VCard
          class="mb-6"
          :title="t('add_motor.post_details.section_title', { postType })"
        >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="postData.title"
                  :label="t('add_motor.post_details.title')"
                  :placeholder="t('add_motor.post_details.title_placeholder')"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="postData.acf.tipo_o_referencia"
                  :label="t('add_motor.post_details.reference')"
                  :placeholder="t('add_motor.post_details.reference_placeholder')"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="postData.acf.marca"
                  :label="t('add_motor.post_details.brand')"
                  :items="marcas"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="postData.categories"
                  :label="t('add_motor.post_details.category')"
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
                    :label="t('add_motor.post_details.power')"
                    type="number"
                    :placeholder="t('add_motor.post_details.power_placeholder')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="postData.acf.velocidad"
                    :label="t('add_motor.post_details.speed')"
                    type="number"
                    :placeholder="t('add_motor.post_details.speed_placeholder')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="postData.acf.par_nominal"
                    :label="t('add_motor.post_details.torque')"
                    type="number"
                    :placeholder="t('add_motor.post_details.torque_placeholder')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="postData.acf.voltaje"
                    :label="t('add_motor.post_details.voltage')"
                    type="number"
                    :placeholder="t('add_motor.post_details.voltage_placeholder')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="postData.acf.intensidad"
                    :label="t('add_motor.post_details.intensity')"
                    type="number"
                    :placeholder="t('add_motor.post_details.intensity_placeholder')"
                  />
                </VCol>
              </template>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="postData.acf.pais"
                  :label="t('add_motor.post_details.country')"
                  :items="countryOptions"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="postData.acf.provincia"
                  :label="t('add_motor.post_details.province')"
                  :placeholder="t('add_motor.post_details.province_placeholder')"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="postData.acf.estado_del_articulo"
                  :label="t('add_motor.post_details.condition')"
                  :items="conditionOptions"
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="postData.acf.descripcion"
                  :label="t('add_motor.post_details.description')"
                  :placeholder="t('add_motor.post_details.description_placeholder')"
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
                    :label="t('add_motor.post_details.rent_option')"
                  >
                    <VRadio
                      :label="t('add_motor.boolean_options.yes')"
                      value="Sí"
                    />
                    <VRadio
                      :label="t('add_motor.boolean_options.no')"
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
                    :label="t('add_motor.post_details.power_supply_type')"
                  >
                    <VRadio
                      :label="t('add_motor.power_supply_options.dc')"
                      value="Continua (C.C.)"
                    />
                    <VRadio
                      :label="t('add_motor.power_supply_options.ac')"
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
                    :label="t('add_motor.post_details.servomotors')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <VCheckbox
                    v-model="postData.acf.regulacion_electronica_drivers"
                    :label="t('add_motor.post_details.electronic_regulation')"
                  />
                </VCol>
              </template>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="postData.acf.precio_de_venta"
                  :label="t('add_motor.post_details.price')"
                  type="number"
                  :placeholder="t('add_motor.post_details.price_placeholder')"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="postData.acf.stock"
                  :label="t('add_motor.post_details.stock')"
                  type="number"
                  :placeholder="t('add_motor.post_details.stock_placeholder')"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VRadioGroup
                  v-model="postData.acf.precio_negociable"
                  inline
                  :label="t('add_motor.post_details.negotiable_price')"
                >
                  <VRadio
                    :label="t('add_motor.boolean_options.yes')"
                    value="Sí"
                  />
                  <VRadio
                    :label="t('add_motor.boolean_options.no')"
                    value="No"
                  />
                </VRadioGroup>
              </VCol>
              <VCol cols="12">
                <VRadioGroup
                  v-model="postData.status"
                  inline
                  :label="t('add_motor.post_details.publish_acf')"
                >
                  <VRadio
                    :label="t('add_motor.post_details.publish_status_publish')"
                    value="publish"
                  />
                  <VRadio
                    :label="t('add_motor.post_details.publish_status_draft')"
                    value="draft"
                  />
                </VRadioGroup>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
        <VCard
          class="mb-6"
          :title="t('add_motor.media.main_image')"
        >
          <VCardText>
            <DropZone @file-added="handleFeaturedImageUpload" />
          </VCardText>
        </VCard>
        <VCard
          class="mb-6"
          :title="t('add_motor.media.image_gallery')"
        >
          <VCardText>
            <DropZone @file-added="handleGalleryImageUpload" />
          </VCardText>
        </VCard>
        <VCard
          class="mb-6"
          :title="t('add_motor.media.additional_documentation')"
        >
          <VCardText>
            <div
              v-for="(doc, index) in postData.acf.documentacion_adicional"
              :key="index"
              class="d-flex gap-4 mb-4"
            >
              <AppTextField
                v-model="doc.nombre"
                :label="t('add_motor.media.document_name')"
                :placeholder="t('add_motor.media.document_name_placeholder')"
                style="width: 300px;"
              />
              <VFileInput
                :label="t('add_motor.media.upload_file')"
                @change="event => handleFileUpload(event, index)"
              />
            </div>
            <VBtn
              v-if="postData.acf.documentacion_adicional.length < 5"
              @click="addDocument"
            >
              {{ t('add_motor.buttons.add_document') }}
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Step 2: Warranty Offer -->
    <VCard
      v-if="currentStep === 2"
      :title="t('add_motor.warranty.add_warranty_title')"
      class="mb-6"
    >
      <VCardText>
        <p class="mb-4">
          <strong>{{ t('add_motor.warranty.add_warranty_subtitle') }}</strong>
        </p>
        <p>
          {{ t('add_motor.warranty.add_warranty_text1') }}
          {{ t('add_motor.warranty.add_warranty_text2') }}
        </p>
        <p class="mt-2">
          {{ t('add_motor.warranty.add_warranty_text3') }}
        </p>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          color="secondary"
          @click="skipGarantia"
        >
          {{ t('add_motor.buttons.skip') }}
        </VBtn>
        <VBtn @click="goToGarantiaForm">
          {{ t('add_motor.buttons.accept_and_add_warranty') }}
        </VBtn>
      </VCardActions>
    </VCard>

    <!-- Step 3: Warranty Form -->
    <VCard
      v-if="currentStep === 3"
      :title="t('add_motor.warranty.form_title')"
      class="mb-6"
    >
      <VCardText>
        <VRow>
          <VCol cols="12">
            <VRadioGroup
              v-model="garantiaData.is_same_address"
              inline
              :label="t('add_motor.warranty.same_address_question')"
            >
              <VRadio
                :label="t('add_motor.boolean_options.yes')"
                value="SÍ"
              />
              <VRadio
                :label="t('add_motor.boolean_options.no')"
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
                :label="t('add_motor.warranty.pickup_address')"
                :placeholder="t('add_motor.warranty.pickup_address_placeholder')"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="garantiaData.cp_motor"
                :label="t('add_motor.warranty.postal_code')"
                :placeholder="t('add_motor.warranty.postal_code_placeholder')"
              />
            </VCol>
          </template>
          <VCol cols="12">
            <p class="text-body-1 font-weight-medium">
              {{ t('add_motor.warranty.shipping_title') }}
            </p>
            <p class="text-caption">
              {{ t('add_motor.warranty.shipping_text') }}
            </p>
            <AppTextField
              v-model="garantiaData.agencia_transporte"
              :label="t('add_motor.warranty.shipping_agency')"
              :placeholder="t('add_motor.warranty.shipping_agency_placeholder')"
              class="mt-4"
            />
          </VCol>

          <VCol cols="12">
            <p class="text-body-1 font-weight-medium">
              {{ t('add_motor.warranty.payment_method_title') }}
            </p>
            <p class="text-caption">
              {{ t('add_motor.warranty.payment_method_text') }}
            </p>
            <VRadioGroup
              v-model="garantiaData.modalidad_pago"
              class="mt-4"
            >
              <VRadio
                :label="t('add_motor.warranty.payment_method_cod')"
                value="Contra reembolso"
              />
              <VRadio
                :label="t('add_motor.warranty.payment_method_transfer')"
                value="Transferencia"
              />
            </VRadioGroup>
          </VCol>

          <VCol cols="12">
            <VTextarea
              v-model="garantiaData.comentarios"
              :label="t('add_motor.warranty.comments')"
              :placeholder="t('add_motor.warranty.comments_placeholder')"
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
          {{ t('add_motor.buttons.go_back') }}
        </VBtn>
        <VBtn @click="submitGarantia">
          {{ t('add_motor.buttons.request_warranty') }}
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
