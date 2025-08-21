<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useApi } from '@/composables/useApi';

// Define interfaces for our data structures
interface Term {
  id: number;
  name: string;
  slug: string;
}

interface Motor {
  id: number;
  uuid: string;
  title: string;
  imagen_destacada: {
    url: string;
    alt: string;
  };
  acf: {
    precio_de_venta: string;
    marca: Term;
    // Add other ACF fields as needed
  };
}

interface Pagination {
  currentPage: number;
  totalPages: number;
  perPage: number;
  total: number;
}

// Reactive state
const motors = ref<Motor[]>([]);
const categories = ref<Term[]>([]);
const marcas = ref<Term[]>([]);
const loading = ref(true);

const filters = ref({
  category: null,
  marca: null,
  pais: null,
  estado_del_articulo: null,
  status: 'publish', // Always fetch only published motors for the shop
});

const pagination = ref<Pagination>({
  currentPage: 1,
  totalPages: 1,
  perPage: 9, // Show 9 motors per page
  total: 0,
});

// API composable instances
const api = useApi();

// --- DATA FETCHING ---

// Fetch categories and brands for the filter dropdowns
const fetchFilterData = async () => {
  try {
    const { data: catData, error: catError } = await api('/motor-categories').get().json<Term[]>();
    if (catError.value) throw new Error('Failed to fetch categories');
    categories.value = catData.value || [];

    const { data: brandData, error: brandError } = await api('/marcas').get().json<Term[]>();
    if (brandError.value) throw new Error('Failed to fetch brands');
    marcas.value = brandData.value || [];
  } catch (error) {
    console.error('Error fetching filter data:', error);
  }
};

// Fetch motors from the API based on current filters and pagination
const fetchMotors = async () => {
  loading.value = true;

  const queryParams = {
    page: pagination.value.currentPage,
    per_page: pagination.value.perPage,
    ...filters.value
  };

  // Remove null/undefined params
  Object.keys(queryParams).forEach(key => (queryParams[key] === null || queryParams[key] === '' || queryParams[key] === undefined) && delete queryParams[key]);

  try {
    const { data, error } = await api('/motors', { params: queryParams }).get().json<{ data: Motor[], pagination: Pagination }>();
    if (error.value) throw new Error('Failed to fetch motors');

    motors.value = data.value?.data || [];
    pagination.value = { ...pagination.value, ...data.value?.pagination };

  } catch (error) {
    console.error('Error fetching motors:', error);
    motors.value = [];
  } finally {
    loading.value = false;
  }
};

// --- LIFECYCLE & WATCHERS ---

// Fetch initial data when component is mounted
onMounted(() => {
  fetchFilterData();
  fetchMotors();
});

// Watch for changes in pagination and refetch motors
watch(() => pagination.value.currentPage, () => {
  fetchMotors();
});

// Function to be called when user clicks the search button
const handleFilterSearch = () => {
  pagination.value.currentPage = 1; // Reset to first page on new search
  fetchMotors();
}

</script>

<template>
  <div>
    <!-- Filters Card -->
    <VCard class="mb-6">
      <VCardItem>
        <VCardTitle>Buscar Motores</VCardTitle>
      </VCardItem>
      <VCardText>
        <VRow>
          <!-- Category Filter -->
          <VCol cols="12" md="3">
            <VSelect
              v-model="filters.category"
              label="Categoría"
              :items="categories"
              item-title="name"
              item-value="slug"
              clearable
            ></VSelect>
          </VCol>

          <!-- Brand Filter -->
          <VCol cols="12" md="3">
            <VSelect
              v-model="filters.marca"
              label="Marca"
              :items="marcas"
              item-title="name"
              item-value="id"
              clearable
            ></VSelect>
          </VCol>

          <VCol cols="12" md="3">
            <VSelect
              v-model="filters.pais"
              label="País"
              :items="['España', 'Portugal', 'Francia']"
              clearable
            ></VSelect>
          </VCol>

          <VCol cols="12" md="3">
            <VSelect
              v-model="filters.estado_del_articulo"
              label="Estado"
              :items="['Nuevo', 'Usado', 'Restaurado']"
              clearable
            ></VSelect>
          </VCol>

          <!-- Search Button -->
          <VCol cols="12" md="3" class="d-flex align-center">
            <VBtn @click="handleFilterSearch" :loading="loading">
              Buscar
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Loading Indicator -->
    <div v-if="loading" class="text-center pa-12">
      <VProgressCircular indeterminate size="64"></VProgressCircular>
      <p class="mt-4">Cargando motores...</p>
    </div>

    <!-- Motors Grid -->
    <VRow v-else-if="!loading && motors.length > 0">
      <VCol
        v-for="motor in motors"
        :key="motor.id"
        cols="12"
        sm="6"
        md="4"
      >
        <VCard>
          <VImg
            :src="motor.imagen_destacada?.url || '/placeholder.png'"
            height="200px"
            cover
          ></VImg>
          <VCardItem>
            <VCardTitle>{{ motor.title }}</VCardTitle>
            <VCardSubtitle v-if="motor.acf.marca">
              {{ motor.acf.marca.name }}
            </VCardSubtitle>
          </VCardItem>
          <VCardText>
            <div class="font-weight-bold text-h6">
              {{ motor.acf.precio_de_venta ? `${motor.acf.precio_de_venta} €` : 'Consultar precio' }}
            </div>
          </VCardText>
          <VCardActions>
              <!-- The :to prop is temporarily removed to prevent a router crash. -->
              <!-- A separate task will be needed to create the single motor page and re-enable this link. -->
              <VBtn>
              Ver Detalles
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>

    <!-- No Results Message -->
    <VCard v-else class="pa-8 text-center">
      <VCardText>
        <p class="text-h6">No se encontraron motores</p>
        <p>Intenta ajustar los filtros de búsqueda.</p>
      </VCardText>
    </VCard>

    <!-- Pagination -->
    <VPagination
      v-if="pagination.totalPages > 1"
      v-model="pagination.currentPage"
      :length="pagination.totalPages"
      :total-visible="5"
      class="mt-6"
    ></VPagination>
  </div>
</template>

<style scoped>
/* Add any specific styles here */
</style>
