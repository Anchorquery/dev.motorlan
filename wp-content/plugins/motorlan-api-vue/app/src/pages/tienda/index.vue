<script setup lang="ts">
import { ref, computed } from 'vue';
import { useApi } from '@/composables/useApi';
import { createUrl } from '@/@core/composable/createUrl';
import type { Motor } from '@/interfaces/motor';

// Define interfaces for our data structures
interface Term {
  id: number;
  name: string;
  slug: string;
}

// -- State Management --

// Filter state
const selectedCategory = ref<string | null>(null);
const selectedBrand = ref<number | null>(null);
const selectedCountry = ref<string | null>(null);
const selectedState = ref<string | null>(null);

// Pagination state
const itemsPerPage = ref(9);
const page = ref(1);

// -- Data Fetching --

// Fetch categories for the filter dropdown
const { data: categoriesData } = await useApi<Term[]>(createUrl('/wp-json/motorlan/v1/motor-categories'));
const categories = computed(() => categoriesData.value || []);

// Fetch brands for the filter dropdown
const { data: brandsData } = await useApi<Term[]>(createUrl('/wp-json/motorlan/v1/marcas'));
const marcas = computed(() => brandsData.value || []);

// Reactive URL for fetching motors
const motorsApiUrl = createUrl('/wp-json/motorlan/v1/motors', {
  query: {
    per_page: itemsPerPage,
    page,
    status: 'publish', // Always fetch published for the shop
    category: selectedCategory,
    marca: selectedBrand,
    pais: selectedCountry,
    estado_del_articulo: selectedState,
  },
});

// Fetch motors
const { data: motorsData, isFetching: loading } = await useApi<any>(motorsApiUrl).get().json();

// Computed properties to extract data from the API response
const motors = computed((): Motor[] => motorsData.value?.data || []);
const totalMotors = computed(() => motorsData.value?.pagination.total || 0);
const totalPages = computed(() => motorsData.value?.pagination.totalPages || 1);

</script>

<template>
  <VRow>
    <!-- Filters Sidebar -->
    <VCol cols="12" md="3">
      <VCard>
        <VCardItem>
          <VCardTitle class="text-error">Filtros</VCardTitle>
        </VCardItem>
        <VCardText>
            <!-- Category Filter -->
            <AppSelect
              v-model="selectedCategory"
              label="Categoría"
              :items="categories"
              item-title="name"
              item-value="slug"
              clearable
              class="mb-4"
            />

            <!-- Brand Filter -->
            <AppSelect
              v-model="selectedBrand"
              label="Marca"
              :items="marcas"
              item-title="name"
              item-value="id"
              clearable
              class="mb-4"
            />

            <!-- Country Filter -->
            <AppSelect
              v-model="selectedCountry"
              label="País"
              :items="['España', 'Portugal', 'Francia']"
              clearable
              class="mb-4"
            />

            <!-- State Filter -->
            <AppSelect
              v-model="selectedState"
              label="Estado"
              :items="['Nuevo', 'Usado', 'Restaurado']"
              clearable
              class="mb-4"
            />
        </VCardText>
      </VCard>
    </VCol>

    <!-- Products Content -->
    <VCol cols="12" md="9">
      <!-- Loading Indicator -->
      <div v-if="loading && !motors.length" class="text-center pa-12">
        <VProgressCircular indeterminate size="64"></VProgressCircular>
        <p class="mt-4">Cargando motores...</p>
      </div>

      <!-- Motors Grid -->
      <div v-else>
        <VRow v-if="motors.length > 0">
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
                <div class="font-weight-bold text-h6 text-error">
                  {{ motor.acf.precio_de_venta ? `${motor.acf.precio_de_venta} €` : 'Consultar precio' }}
                </div>
              </VCardText>
              <VCardActions>
                  <!-- The :to prop is temporarily removed to prevent a router crash. -->
                  <!-- A separate task will be needed to create the single motor page and re-enable this link. -->
                  <VBtn color="error">
                  + INFO
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
      </div>

      <!-- Pagination -->
      <VPagination
        v-if="totalPages > 1"
        v-model="page"
        :length="totalPages"
        :total-visible="5"
        class="mt-6"
      ></VPagination>
    </VCol>
  </VRow>
</template>

<style scoped>
/* Add any specific styles here */
</style>
