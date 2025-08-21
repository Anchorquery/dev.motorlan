<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useApi } from '@/composables/useApi'

// Define interfaces for our data structures
interface Term {
  id: number
  name: string
  slug: string
}

interface Motor {
  id: number
  uuid: string
  title: string
  imagen_destacada: {
    url: string
    alt: string
  }
  acf: {
    precio_de_venta: string
    marca: Term
    // Add other ACF fields as needed
  }
}

interface Pagination {
  currentPage: number
  totalPages: number
  perPage: number
  total: number
}

// Reactive state
const motors = ref<Motor[]>([])
const categories = ref<Term[]>([])
const marcas = ref<Term[]>([])

// static option lists for filters and sorting
const parOptions = ['0-50 Nm', '50-100 Nm', '100-150 Nm', '150+ Nm']
const potenciaOptions = ['0-10 kW', '10-50 kW', '50-100 kW', '100+ kW']
const velocidadOptions = ['750 rpm', '1000 rpm', '1500 rpm', '3000 rpm']
const sortOptions = [
  { title: 'Precio más bajo', value: 'price_asc' },
  { title: 'Precio más alto', value: 'price_desc' },
]

const loading = ref(true)

const filters = ref({
  category: null,
  marca: null,
  pais: null,
  estado_del_articulo: null,
  par_nominal: null,
  potencia: null,
  velocidad: null,
  orderby: null,
  status: 'publish', // Always fetch only published motors for the shop
  s: '', // for search term
  product_type: [], // for checkboxes
})

const pagination = ref<Pagination>({
  currentPage: 1,
  totalPages: 1,
  perPage: 9, // Show 9 motors per page
  total: 0,
})

// API composable instances
const api = useApi()

// --- DATA FETCHING ---

// Fetch categories and brands for the filter dropdowns
const fetchFilterData = async () => {
  try {
    const { data: catData, error: catError } = await api('/motor-categories').get().json<Term[]>()
    if (catError.value)
      throw new Error('Failed to fetch categories')
    categories.value = catData.value || []

    const { data: brandData, error: brandError } = await api('/marcas').get().json<Term[]>()
    if (brandError.value)
      throw new Error('Failed to fetch brands')
    marcas.value = brandData.value || []
  }
  catch (error) {
    console.error('Error fetching filter data:', error)
  }
}

// Fetch motors from the API based on current filters and pagination
const fetchMotors = async () => {
  loading.value = true

  const queryParams = {
    page: pagination.value.currentPage,
    per_page: pagination.value.perPage,
    ...filters.value,
  }

  // Remove null/undefined params
  Object.keys(queryParams).forEach(key => (queryParams[key] === null || queryParams[key] === '' || queryParams[key] === undefined || (Array.isArray(queryParams[key]) && queryParams[key].length === 0)) && delete queryParams[key])

  if (queryParams.product_type) {
    queryParams.product_type = queryParams.product_type.join(',')
  }


  try {
    const { data, error } = await api('/motors', { params: queryParams }).get().json<{ data: Motor[]; pagination: Pagination }>()
    if (error.value)
      throw new Error('Failed to fetch motors')

    motors.value = data.value?.data || []
    pagination.value = { ...pagination.value, ...data.value?.pagination }
  }
  catch (error) {
    console.error('Error fetching motors:', error)
    motors.value = []
  }
  finally {
    loading.value = false
  }
}

// --- LIFECYCLE & WATCHERS ---

// Fetch initial data when component is mounted
onMounted(() => {
  fetchFilterData()
  fetchMotors()
})

// Watch for changes in pagination and refetch motors
watch(() => pagination.value.currentPage, () => {
  fetchMotors()
})

// Function to be called when user clicks the search button
const handleFilterSearch = () => {
  pagination.value.currentPage = 1 // Reset to first page on new search
  fetchMotors()
}
</script>

<template>
  <div>
    <!-- Header -->
    <VCard class="mb-6">
      <VCardText class="d-flex justify-space-between align-center">
        <img class="logo" src="https://placehold.co/194x48" />
        <div class="d-flex align-center">
          <VBtn variant="text" to="/">HOME</VBtn>
          <VBtn variant="text" to="/servicios">SERVICIOS</VBtn>
          <VBtn variant="text" to="/solicita-presupuesto">SOLICITA PRESUPUESTO</VBtn>
          <VBtn variant="text" to="/motores" class="font-weight-bold text-primary">COMPRA-VENTA DE MOTORES</VBtn>
          <VBtn variant="text" to="/blog">BLOG</VBtn>
          <VBtn variant="text" to="/contacto">CONTACTO</VBtn>
        </div>
        <div>
          <VBtn variant="text">Login</VBtn>
          <VIcon>mdi-account</VIcon>
        </div>
      </VCardText>
    </VCard>

    <VRow>
      <!-- Filters -->
      <VCol
        cols="12"
        md="3"
      >
        <VCard>
          <VCardTitle class="filters-title">
            <VIcon color="#E1081E">mdi-filter-variant</VIcon>
            FILTROS
          </VCardTitle>
          <VDivider />
          <VCardText>
            <div class="filter-group">
              <label class="filter-label">Tipo / modelo</label>
              <VSelect
                v-model="filters.category"
                label="Seleccionar tipo/modelo"
                :items="categories"
                item-title="name"
                item-value="slug"
                clearable
                dense
                outlined
                class="red-select"
              />
            </div>

            <div class="product-type-group">
              <label class="product-type-label">Tipo de producto</label>
              <VCheckbox v-model="filters.product_type" label="Motor" value="motor" />
              <VCheckbox v-model="filters.product_type" label="Regulador" value="regulador" />
              <VCheckbox v-model="filters.product_type" label="Otros repuestos" value="otros" />
            </div>

            <div class="select-group">
              <label class="select-label">PAR (Nm)</label>
              <VSelect
                v-model="filters.par_nominal"
                label="Seleccionar PAR (Nm)"
                :items="parOptions"
                clearable
                dense
                outlined
                class="red-select"
              />
            </div>

            <div class="select-group">
              <label class="select-label">Potencia</label>
              <VSelect
                v-model="filters.potencia"
                label="Seleccionar potencia"
                :items="potenciaOptions"
                clearable
                dense
                outlined
                class="red-select"
              />
            </div>

            <div class="select-group">
              <label class="select-label">Velocidad</label>
              <VSelect
                v-model="filters.velocidad"
                label="Seleccionar velocidad"
                :items="velocidadOptions"
                clearable
                dense
                outlined
                class="red-select"
              />
            </div>

            <div class="select-group">
              <label class="select-label">Marcas</label>
              <VSelect
                v-model="filters.marca"
                label="Seleccionar marcas"
                :items="marcas"
                item-title="name"
                item-value="id"
                clearable
                dense
                outlined
                class="red-select"
              />
            </div>

            <div class="select-group">
              <label class="select-label">Estado</label>
              <VSelect
                v-model="filters.estado_del_articulo"
                label="Seleccionar estado"
                :items="['Nuevo', 'Usado', 'Restaurado']"
                clearable
                dense
                outlined
                class="red-select"
              />
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Products -->
      <VCol
        cols="12"
        md="9"
      >
        <div class="page-header">
          <h1 class="page-title">COMPRA VENTA DE MOTORES ELÉCTRÍCOS INDUSTRIALES</h1>
          <VSelect
            v-model="filters.orderby"
            label="Ordenar"
            :items="sortOptions"
            dense
            outlined
            class="order-select red-select"
          />
        </div>

        <div class="search-row">
          <VTextField
            v-model="filters.s"
            placeholder="Buscar..."
            dense
            outlined
            class="search-input"
            @keydown.enter="handleFilterSearch"
          />
          <VBtn
            class="search-btn"
            @click="handleFilterSearch"
            :loading="loading"
          >
            BUSCAR
          </VBtn>
        </div>

        <div v-if="loading" class="text-center pa-12">
          <VProgressCircular indeterminate size="64" />
          <p class="mt-4">Cargando motores...</p>
        </div>

        <VRow v-else-if="motors.length > 0">
          <VCol
            v-for="motor in motors"
            :key="motor.id"
            cols="12"
            sm="6"
            md="4"
          >
            <VCard
              class="product-card"
              v-bind="motor.uuid ? { to: `/motores/${motor.uuid}` } : {}"
            >
              <div class="product-card-inner">
                <div class="product-image-background">
                  <VImg :src="motor.imagen_destacada?.url" height="185" />
                </div>
                <div class="product-name">{{ motor.title }}</div>
                <div class="product-price">{{ motor.acf.precio_de_venta ? `${motor.acf.precio_de_venta} €` : 'Consultar' }}</div>
                <div class="info-button">
                  <div class="info-button-text">+ INFO</div>
                  <div class="info-button-icon-container">
                    <div class="info-button-icon"></div>
                  </div>
                </div>
              </div>
            </VCard>
          </VCol>
        </VRow>

        <div v-else class="text-center pa-12">
          <p class="text-h6">No se encontraron motores</p>
          <p>Intenta ajustar los filtros de búsqueda.</p>
        </div>

        <VPagination
          v-if="pagination.totalPages > 1"
          v-model="pagination.currentPage"
          :length="pagination.totalPages"
          :total-visible="5"
          class="mt-6"
        />

      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.page-title {
  color: #E1081E;
  font-size: 24px;
  font-family: Inter, sans-serif;
  font-weight: 600;
  line-height: 28px;
}

.search-row {
  display: flex;
  gap: 16px;
  margin-bottom: 24px;
}

.search-input {
  flex: 1;
}

.search-btn {
  background: #E1081E !important;
  color: #fff !important;
}

.filters-title,
.filter-label,
.product-type-label,
.select-label {
  color: #DA291C;
  font-family: Inter, sans-serif;
}

.red-select :deep(.v-field__outline) {
  border-color: #DA291C !important;
}

.red-select :deep(.v-field__label),
.red-select :deep(.v-field__input),
.red-select :deep(.v-select__selection-text) {
  color: #DA291C !important;
}

.order-select {
  max-width: 220px;
}

.product-card {
  height: 339px;
  position: relative;
  background: white;
  overflow: hidden;
  border-radius: 16px;
  cursor: pointer;
  transition: all 0.2s ease-in-out;
}

.product-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.product-card-inner {
  width: 100%;
  height: 100%;
  position: relative;
}

.product-price {
  position: absolute;
  right: 24px;
  bottom: 24px;
  text-align: right;
  color: #DA291C;
  font-size: 24px;
  font-family: Inter, sans-serif;
  font-weight: 700;
  line-height: 30px;
}

.product-name {
  width: calc(100% - 48px);
  left: 24px;
  top: 225px;
  position: absolute;
  color: #DA291C;
  font-size: 16px;
  font-family: Inter, sans-serif;
  font-weight: 400;
  line-height: 22px;
}

.product-image-background {
  width: calc(100% - 48px);
  height: 185px;
  left: 24px;
  top: 24px;
  position: absolute;
  background: #EEF1F4;
  border-radius: 8px;
  overflow: hidden;
}

.info-button {
  width: 167px;
  padding: 8px 16px;
  left: 22.25px;
  top: 282px;
  position: absolute;
  background: #DA291C;
  overflow: hidden;
  border-radius: 24px;
  display: inline-flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
}

.info-button-text {
  color: white;
  font-size: 14px;
  font-family: Inter, sans-serif;
  font-weight: 600;
  line-height: 19px;
}

.info-button-icon-container {
  width: 20px;
  height: 20px;
  position: relative;
  overflow: hidden;
}

.info-button-icon {
  width: 11.67px;
  height: 8.33px;
  left: 4.17px;
  top: 5.83px;
  position: absolute;
  outline: 2px white solid;
  outline-offset: -1px;
}
</style>
