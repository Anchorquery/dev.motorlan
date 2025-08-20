<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const motorUuid = route.params.uuid as string

const motorData = ref({
  title: '',
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

const marcas = ref([])

useApi('/wp-json/wp/v2/marca').then(response => {
  marcas.value = response.data.value.map(marca => ({
    title: marca.name,
    value: marca.id,
  }))
})

onMounted(async () => {
  if (motorUuid) {
    const { data } = await useApi<any>(`/wp-json/wp/v2/motors/uuid/${motorUuid}`)
    const post = data.value
    if (post) {
      motorData.value = {
        ...motorData.value,
        ...post,
        acf: {
          ...motorData.value.acf,
          ...post.acf,
        },
      }
    }
  }
})

const updateMotor = async () => {
  const api = useApi()
  const url = `/wp-json/wp/v2/motors/uuid/${motorUuid}`
  const method = 'POST'

  try {
    await api(url, {
      method,
      body: motorData.value,
    })
    router.push('/apps/motors/motor/list')
  }
  catch (error) {
    console.error('Failed to update motor:', error)
  }
}
</script>

<template>
  <div>
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
        <VBtn @click="updateMotor">
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
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
