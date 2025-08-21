<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import DropZone from '@/@core/components/DropZone.vue'
import { requiredValidator } from '@/@core/utils/validators'
import { useApi } from '@/composables/useApi'

const route = useRoute()
const router = useRouter()
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
    estado_del_articulo: 'Nuevo',
    informe_de_reparacion: null,
    descripcion: '',
    posibilidad_de_alquiler: 'No',
    tipo_de_alimentacion: 'Alterna (C.A.)',
    servomotores: false,
    regulacion_electronica_drivers: false,
    precio_de_venta: null,
    precio_negociable: 'No',
  },
})

const motorImageFile = ref([])
const motorGalleryFiles = ref([])

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

    // Añadir la promesa del motor solo si existe el UUID
    if (motorUuid)
      promises.push(useApi(`/wp-json/motorlan/v1/motors/uuid/${motorUuid}`))

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
    }
  }
  catch (error) {
    console.error('Error al obtener los datos iniciales:', error)

    // Aquí puedes manejar el error, por ejemplo, mostrando una notificación al usuario.
  }
})

const uploadImage = async (file: File) => {
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
console.log(valid )  
  if (!valid)
    return

  
  const url = `/wp-json/motorlan/v1/motors/uuid/${motorUuid}`
  const method = 'POST'

  try {
    // Handle main image upload
    if (motorImageFile.value.length > 0) {
      const image = motorImageFile.value[0]
      if (image.file) { // New file
        const uploadedImage = await uploadImage(image.file)

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
          const uploadedImage = await uploadImage(image.file)

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

    await useApi(url, {
      method,
      body: motorData.value,
    })
    router.push('/apps/motors/motor/list')
  }
  catch (error) {
    console.error('Failed to update motor:', error)
  }
}

const formattedCategories = computed({
  get() {
    if (Array.isArray(motorData.value.categories))
      return motorData.value.categories.map(cat => (typeof cat === 'object' ? cat.id : cat))

    return []
  },

  // 'set' se ejecuta cuando el usuario cambia la selección en AppSelect
  set(newValue) {
    // 'newValue' es lo que envía el componente AppSelect
    // Actualizamos la variable original con el nuevo valor
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
</script>

<template>
  <div>
    <VForm
      ref="form"
      @submit.prevent="updateMotor"
    >
      <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
        <div class="d-flex flex-column justify-center">
          <h4 class="text-h4 font-weight-medium">
            Edit motor
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
          <VBtn type="submit">
            Update Motor
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
                <!-- Fields from here -->
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="motorData.title"
                    label="Título de la publicación"
                    placeholder="Título"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="motorData.acf.tipo_o_referencia"
                    label="Tipo o referencia"
                    placeholder="Referencia"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppSelect
                    v-model="formattedMarca"
                    label="Marca"
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
                    label="Categoría"
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
                    label="Potencia (kW)"
                    type="number"
                    placeholder="100"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
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
                  md="4"
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
                  md="4"
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
                  md="4"
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
                  md="4"
                >
                  <AppSelect
                    v-model="motorData.acf.pais"
                    label="País (localización)"
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
                    label="Provincia"
                    placeholder="Madrid"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppSelect
                    v-model="motorData.acf.estado_del_articulo"
                    label="Estado del artículo"
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
                    label="Precio de venta (€)"
                    type="number"
                    placeholder="1000"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
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
                <VCol
                  cols="12"
                  md="4"
                >
                  <VRadioGroup
                    v-model="motorData.acf.posibilidad_de_alquiler"
                    inline
                    label="Posibilidad de alquiler"
                    :rules="[requiredValidator]"
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
                  md="4"
                >
                  <VRadioGroup
                    v-model="motorData.acf.tipo_de_alimentacion"
                    inline
                    label="Tipo de alimentación"
                    :rules="[requiredValidator]"
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
                  md="4"
                >
                  <VCheckbox
                    v-model="motorData.acf.servomotores"
                    label="Servomotores"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <VCheckbox
                    v-model="motorData.acf.regulacion_electronica_drivers"
                    label="Regulación electrónica/Drivers"
                  />
                </VCol>
                <VCol cols="12">
                  <VTextarea
                    v-model="motorData.acf.descripcion"
                    label="Descripción"
                    placeholder="Descripción del motor"
                    :rules="[requiredValidator]"
                  />
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
          <VCard
            class="mb-6"
            title="Imágenes del Motor"
          >
            <VCardText>
              <VRow>
                <VCol
                  cols="12"
                  md="6"
                >
                  <VLabel class="mb-1 text-body-2 text-high-emphasis">
                    Imagen Principal
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
                    Galería de Imágenes
                  </VLabel>
                  <DropZone v-model="motorGalleryFiles" />
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VForm>
  </div>
</template>
