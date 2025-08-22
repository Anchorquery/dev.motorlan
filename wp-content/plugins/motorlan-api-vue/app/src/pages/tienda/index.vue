<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';
import { createUrl } from '@/@core/composable/createUrl';
import type { Motor } from '@/interfaces/motor';

interface Term {
  id: number;
  name: string;
  slug: string;
}

// -- State Management --
const selectedBrand = ref<number | null>(null);
const selectedState = ref<string | null>(null);
const typeModel = ref('');
const productTypes = ref<string[]>([]);
const selectedTechnology = ref<string | null>(null);
const selectedPar = ref<string | null>(null);
const selectedPotencia = ref<string | null>(null);
const selectedVelocidad = ref<string | null>(null);
const searchTerm = ref('');
const order = ref<string | null>(null);

const parOptions = ['0-50', '50-100'];
const potenciaOptions = ['0-1 kW', '1-5 kW'];
const velocidadOptions = ['500 rpm', '1500 rpm'];
const technologyOptions = ['Continua (C.C.)', 'Alterna (C.A.)'];
const orderOptions = ['Recientes', 'Precio asc', 'Precio desc'];

const itemsPerPage = ref(9);
const page = ref(1);

// -- Data Fetching --
const { data: brandsData } = await useApi<Term[]>(createUrl('/wp-json/motorlan/v1/marcas'));
const marcas = computed(() => brandsData.value || []);

const motorsApiUrl = computed(() => {
  const baseUrl = '/wp-json/motorlan/v1/motors';
  const sortOptions = {
    'Recientes': { orderby: 'date', order: 'desc' },
    'Precio asc': { orderby: 'price', order: 'asc' },
    'Precio desc': { orderby: 'price', order: 'desc' },
  };

  const queryParams = {
    per_page: itemsPerPage.value,
    page: page.value,
    status: 'publish',
    s: searchTerm.value,
    category: productTypes.value.join(','),
    marca: selectedBrand.value,
    estado_del_articulo: selectedState.value,
    potencia: selectedPotencia.value,
    velocidad: selectedVelocidad.value,
    par_nominal: selectedPar.value,
    tipo_de_alimentacion: selectedTechnology.value,
    tipo_o_referencia: typeModel.value,
    ...(order.value ? sortOptions[order.value] : {}),
  };

  const filteredParams = Object.entries(queryParams)
    .filter(([_, value]) => value !== null && value !== undefined && value !== '')
    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
    .join('&');

  return `${baseUrl}?${filteredParams}`;
});

const { data: motorsData, isFetching: loading, execute: fetchMotors } = useApi<any>(motorsApiUrl, { immediate: false }).get();

watch(
  () => motorsApiUrl.value,
  () => {
    fetchMotors();
  }
);

onMounted(fetchMotors);

const motors = computed((): Motor[] => motorsData.value?.data || []);
const totalMotors = computed(() => motorsData.value?.pagination.total || 0);
const totalPages = computed(() => motorsData.value?.pagination.totalPages || 1);

const search = () => {
  page.value = 1;
  fetchMotors();
};
</script>

<template>
  <div class="tienda d-flex">
    <aside class="filters pa-4">
      <div class="d-flex align-center mb-2">
        <VIcon size="18" class="me-2" color="error">mdi-checkbox-blank-outline</VIcon>
        <span class="text-error font-weight-semibold">FILTROS</span>
      </div>
      <VDivider thickness="3" class="mb-4" color="error" />

      <VTextField v-model="typeModel" label="Tipo / modelo" variant="outlined" density="comfortable" class="mb-6" />

      <p class="text-body-2 mb-2">Tipo de producto</p>
      <VCheckbox v-model="productTypes" label="Motor" value="motor" density="compact" hide-details />
      <VCheckbox v-model="productTypes" label="Regulador" value="regulador" density="compact" hide-details />
      <VCheckbox v-model="productTypes" label="Otros repuestos" value="otros-repuestos" density="compact" hide-details class="mb-6" />

      <AppSelect v-model="selectedTechnology" label="Tecnología" :items="technologyOptions" class="mb-4" clearable />
      <AppSelect v-model="selectedPar" label="PAR (Nm)" :items="parOptions" class="mb-4" clearable />
      <AppSelect v-model="selectedPotencia" label="Potencia" :items="potenciaOptions" class="mb-4" clearable />
      <AppSelect v-model="selectedVelocidad" label="Velocidad" :items="velocidadOptions" class="mb-4" clearable />
      <AppSelect v-model="selectedBrand" label="Marcas" :items="marcas" item-title="name" item-value="id" class="mb-4" clearable />
      <AppSelect v-model="selectedState" label="Estado" :items="['Nuevo','Usado','Restaurado']" class="mb-4" clearable />
    </aside>

    <section class="flex-grow-1 ps-6">
      <div class="d-flex align-center mb-6 gap-4">
        <VTextField
          v-model="searchTerm"
          placeholder="Buscar..."
          variant="outlined"
          hide-details
          class="flex-grow-1"
          @keydown.enter="search"
        />
        <VBtn icon color="error" :loading="loading" @click="search">
          <VIcon>mdi-magnify</VIcon>
        </VBtn>
        <AppSelect v-model="order" :items="orderOptions" label="Ordenar" clearable style="max-width:220px" />
      </div>

      <div v-if="loading && !motors.length" class="text-center pa-12">
        <VProgressCircular indeterminate size="64" />
        <p class="mt-4">Cargando motores...</p>
      </div>

      <VRow v-else-if="motors.length" class="motor-grid">
        <VCol
          v-for="motor in motors"
          :key="motor.id"
          cols="12"
          sm="6"
          md="4"
        >
          <div class="motor-card pa-4">
            <div class="motor-image mb-6">
              <img :src="motor.imagen_destacada?.url || '/placeholder.png'" alt="" />
            </div>
            <div class="text-error text-body-1 mb-4">
              {{ motor.title }}
            </div>
            <div class="d-flex justify-space-between align-center">
              <VBtn
                color="error"
                class="rounded-pill px-6"
                :to="'/tienda/' + motor.slug"
              >
                + INFO
              </VBtn>
              <div class="price text-error font-weight-bold">
                {{ motor.acf.precio_de_venta ? `${motor.acf.precio_de_venta} €` : 'Consultar precio' }}
              </div>
            </div>
          </div>
        </VCol>
      </VRow>

      <VCard v-else class="pa-8 text-center">
        <VCardText>
          <p class="text-h6">No se encontraron motores</p>
          <p>Intenta ajustar los filtros de búsqueda.</p>
        </VCardText>
      </VCard>

      <VPagination
        v-if="totalPages > 1"
        v-model="page"
        :length="totalPages"
        :total-visible="5"
        class="mt-6"
      />
    </section>
  </div>
</template>

<style scoped>
.tienda {
  align-items: flex-start;
}
.filters {
  width: 300px;
}
.motor-card {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.motor-image {
  height: 185px;
  border-radius: 8px;
  background: #EEF1F4;
  display: flex;
  align-items: center;
  justify-content: center;
}
.motor-image img {
  max-width: 100%;
  max-height: 100%;
}
.price {
  font-size: 24px;
}
</style>
