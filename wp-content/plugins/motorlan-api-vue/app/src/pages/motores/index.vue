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
const loading = ref(true)

const filters = ref({
  category: null,
  marca: null,
  pais: null,
  estado_del_articulo: null,
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
  <div class="page-container">
    <div class="header-container" data-property-1="Compra-venta">
      <div class="header-background"></div>
      <div class="login-text">Login</div>
      <div class="login-icon-container">
        <div class="login-icon"></div>
      </div>
      <div class="social-icon"></div>
      <div class="header-divider"></div>
      <img class="logo" src="https://placehold.co/194x48" />
      <div class="nav-link" style="left: 452px;">SERVICIOS</div>
      <div class="nav-link" style="left: 333px;">HOME</div>
      <div class="nav-link" style="left: 607px;">SOLICITA PRESUPUESTO</div>
      <div class="nav-link active" style="left: 870px;">COMPRA-VENTA DE MOTORES</div>
      <div class="nav-link" style="left: 1177px;">BLOG</div>
      <div class="nav-link" style="left: 1292px;">CONTACTO</div>
      <img class="social-logo" src="https://placehold.co/124x39" />
    </div>

    <div class="main-content">
      <div class="filters-section">
        <div class="filters-header">
          <div class="filter-icon"></div>
          <div class="filters-title">FILTROS</div>
        </div>
        <div class="filters-divider"></div>

        <div class="filter-group">
          <label class="filter-label">Tipo / modelo</label>
          <VSelect
            v-model="filters.category"
            label="Seleccionar tipo/modelo"
            :items="categories"
            item-title="name"
            item-value="slug"
            clearable
          />
        </div>

        <div class="product-type-group">
          <div class="product-type-label">Tipo de producto</div>
          <div class="checkbox-group">
            <div class="checkbox-item">
              <VCheckbox v-model="filters.product_type" label="Motor" value="motor" />
            </div>
            <div class="checkbox-item">
              <VCheckbox v-model="filters.product_type" label="Regulador" value="regulador" />
            </div>
            <div class="checkbox-item">
              <VCheckbox v-model="filters.product_type" label="Otros repuestos" value="otros" />
            </div>
          </div>
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
          />
        </div>

        <div class="select-group">
          <label class="select-label">Estado</label>
          <VSelect
            v-model="filters.estado_del_articulo"
            label="Seleccionar estado"
            :items="['Nuevo', 'Usado', 'Restaurado']"
            clearable
          />
        </div>

        <!-- Add other filters here if needed -->

      </div>

      <div class="products-section">
        <div class="page-title">COMPRA VENTA DE MOTORES ELÉCTRICOS INDUSTRIALES</div>

        <div class="search-bar-container">
          <VTextField
            v-model="filters.s"
            label="Buscar..."
            dense
            outlined
            class="search-input"
            @keydown.enter="handleFilterSearch"
          />
          <VBtn
            class="search-button"
            @click="handleFilterSearch"
            :loading="loading"
          >
            BUSCAR
          </VBtn>
          <VSelect
            label="Ordenar"
            dense
            outlined
            class="sort-select"
          />
        </div>

        <div v-if="loading" class="text-center pa-12">
          <VProgressCircular indeterminate size="64" />
          <p class="mt-4">Cargando motores...</p>
        </div>

        <div v-else-if="motors.length > 0" class="products-grid">
          <div class="product-card" v-for="motor in motors" :key="motor.id">
            <div class="product-card-inner">
              <img :src="motor.imagen_destacada?.url || 'https://placehold.co/279x185'" :alt="motor.imagen_destacada?.alt" class="product-image-container" />
              <div class="product-name">{{ motor.title }}</div>
              <div class="product-price">{{ motor.acf.precio_de_venta ? `${motor.acf.precio_de_venta} €` : 'Consultar precio' }}</div>
              <VBtn class="info-button" :to="`/apps/motors/motor/${motor.uuid}`">
                + INFO
              </VBtn>
            </div>
          </div>
        </div>

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

      </div>
    </div>
  </div>
</template>

<style scoped>
/* Most styles are the same, just adding a few Vuetify specific ones */
.search-input {
  width: 450px;
}
.sort-select {
  width: 220px;
}
.page-container {
  width: 100%;
  height: 100%;
  position: relative;
  background: white;
  overflow: hidden;
}

.header-container {
  width: 1449px;
  height: 155px;
  left: 0px;
  top: 0px;
  position: absolute;
}

.header-background {
  width: 1449px;
  height: 152px;
  left: 0px;
  top: 0px;
  position: absolute;
  background: white;
}

.login-text {
  left: 1086px;
  top: 18px;
  position: absolute;
  color: black;
  font-size: 16px;
  font-family: Inter, sans-serif;
  font-weight: 400;
  line-height: 28px;
  word-wrap: break-word;
}

.login-icon-container {
  width: 24px;
  height: 24px;
  left: 1054px;
  top: 20px;
  position: absolute;
  overflow: hidden;
}

.login-icon {
  width: 16px;
  height: 16px;
  left: 4px;
  top: 4px;
  position: absolute;
  background: black;
}

.social-icon {
  width: 26.13px;
  height: 26.13px;
  left: 1185.13px;
  top: 18.13px;
  position: absolute;
  background: black;
}

.header-divider {
  width: 1442px;
  height: 3px;
  left: 0px;
  top: 152px;
  position: absolute;
  background: #E1081E;
}

.logo {
  width: 194px;
  height: 48px;
  left: 44px;
  top: 91px;
  position: absolute;
}

.nav-link {
  top: 103px;
  position: absolute;
  color: black;
  font-size: 16px;
  font-family: Inter, sans-serif;
  font-weight: 400;
  line-height: 28px;
  word-wrap: break-word;
}

.nav-link.active {
  color: #E1081E;
  font-weight: 700;
}

.social-logo {
  width: 124.47px;
  height: 39px;
  left: 1262px;
  top: 13px;
  position: absolute;
}

.main-content {
  display: flex;
  position: absolute;
  top: 199px;
  left: 38px;
  right: 38px;
  bottom: 0;
}

.filters-section {
  width: 324px;
  padding-right: 20px;
  border-right: 1px solid #E2E1E5;
}

.filters-header {
  display: flex;
  align-items: center;
  margin-bottom: 15px;
}

.filter-icon {
  width: 18px;
  height: 18px;
  outline: 1.50px #E1081E solid;
  outline-offset: -0.75px;
  margin-right: 11px;
}

.filters-title {
  color: #E1081E;
  font-size: 16px;
  font-family: Inter, sans-serif;
  font-weight: 600;
  line-height: 28px;
  word-wrap: break-word;
}

.filters-divider {
  width: 100%;
  height: 3px;
  background: #E1081E;
  margin-bottom: 30px;
}

.filter-group {
  margin-bottom: 20px;
}

.filter-label {
  display: block;
  color: black;
  font-size: 14px;
  font-family: Inter, sans-serif;
  font-weight: 400;
  line-height: 28px;
  margin-bottom: 1px;
}

.input-container {
  width: 300px;
  padding: 10px 15px;
  background: white;
  border-radius: 5px;
  outline: 1px #E2E1E5 solid;
  outline-offset: -1px;
}

.input-label {
  color: #D2D2D2;
  font-size: 14px;
  font-family: Inter, sans-serif;
  font-weight: 400;
  line-height: 28px;
  word-wrap: break-word;
}

.product-type-group {
  margin-top: 50px;
  margin-bottom: 20px;
}

.product-type-label {
  color: black;
  font-size: 14px;
  font-family: Inter, sans-serif;
  font-weight: 400;
  line-height: 28px;
  margin-bottom: 10px;
}

.checkbox-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.checkbox-item {
  display: flex;
  align-items: center;
}

.checkbox {
  width: 15px;
  height: 15px;
  border-radius: 5px;
  border: 1px #E2E1E5 solid;
  margin-right: 18px;
}

.checkbox-label {
  color: black;
  font-size: 14px;
  font-family: Inter, sans-serif;
  font-weight: 400;
  line-height: 28px;
}

.select-group {
  width: 300px;
  height: 73px;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  gap: 7px;
  margin-bottom: 20px;
}

.select-label {
  color: #DA291C;
  font-size: 14px;
  font-family: Inter, sans-serif;
  font-weight: 400;
  line-height: 19px;
}

.select-container {
  align-self: stretch;
  flex: 1 1 0;
  position: relative;
  background: white;
  border-radius: 6px;
  outline: 1px #DA291C solid;
  outline-offset: -1px;
}

.select-text {
  width: 321px;
  left: 12px;
  top: 13px;
  position: absolute;
  color: #DA291C;
  font-size: 16px;
  font-family: Inter, sans-serif;
  font-weight: 400;
  line-height: 22px;
}

.select-arrow-container {
  width: 24px;
  height: 24px;
  left: 264px;
  top: 12px;
  position: absolute;
  overflow: hidden;
}

.select-arrow {
  width: 8px;
  height: 14px;
  left: 8px;
  top: 5px;
  position: absolute;
  outline: 2px #DA291C solid;
  outline-offset: -1px;
}

.products-section {
  flex: 1;
  padding-left: 40px;
}

.page-title {
  color: #E1081E;
  font-size: 24px;
  font-family: Inter, sans-serif;
  font-weight: 600;
  line-height: 28px;
  margin-bottom: 20px;
}

.section-divider {
  /* This was a vertical line in the original design, but its container is not clear */
  /* For now, it's hidden. Re-evaluate if needed. */
  display: none;
}

.search-bar-container {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 30px;
}

.search-input-container {
  width: 450px;
  padding: 10px 15px;
  background: white;
  border-radius: 5px;
  outline: 1px #E2E1E5 solid;
  outline-offset: -1px;
}

.search-input-label {
  color: #D2D2D2;
  font-size: 14px;
  font-family: Inter, sans-serif;
  font-weight: 400;
  line-height: 28px;
}

.search-button {
  padding: 10px 15px;
  background: #E1081E;
  border-radius: 5px;
  outline: 1px #E1081E solid;
  outline-offset: -1px;
  cursor: pointer;
}

.search-button-text {
  text-align: center;
  color: white;
  font-size: 14px;
  font-family: Inter, sans-serif;
  font-weight: 400;
  line-height: 28px;
}

.sort-select-container {
  width: 220px;
  position: relative;
}

.sort-select {
  height: 47px;
  position: relative;
  background: white;
  border-radius: 6px;
  outline: 1px #DA291C solid;
  outline-offset: -1px;
}

.sort-select-text {
  width: 321px;
  left: 12px;
  top: 13px;
  position: absolute;
  color: #DA291C;
  font-size: 16px;
  font-family: Inter, sans-serif;
  font-weight: 400;
  line-height: 22px;
}

.sort-select-arrow-container {
  width: 24px;
  height: 24px;
  left: 184px;
  top: 12px;
  position: absolute;
  overflow: hidden;
}

.sort-select-arrow {
  width: 8px;
  height: 14px;
  left: 8px;
  top: 5px;
  position: absolute;
  outline: 2px #DA291C solid;
  outline-offset: -1px;
}

.products-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}

.product-card {
  width: 327px;
  height: 339px;
  position: relative;
}

.product-card-inner {
  width: 100%;
  height: 100%;
  background: white;
  overflow: hidden;
  border-radius: 16px;
  position: relative;
  border: 1px solid #eee;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
  width: 279px;
  left: 24px;
  top: 225px;
  position: absolute;
  color: #DA291C;
  font-size: 16px;
  font-family: Inter, sans-serif;
  font-weight: 400;
  line-height: 22px;
}

.product-image-container {
  width: 279px;
  height: 185px;
  left: 24px;
  top: 24px;
  position: absolute;
  background: #EEF1F4;
  border-radius: 8px;
  object-fit: cover;
}

.info-button {
  left: 22.25px;
  top: 282px;
  position: absolute;
}
</style>
