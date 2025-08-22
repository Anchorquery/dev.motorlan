<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'
import type { Motor } from '@/interfaces/motor'

interface Term {
  id: number
  name: string
  slug: string
}

// -- State Management --
const selectedBrand = ref<number | null>(null);
const productTypes = ref<string[]>([]);
const selectedTechnology = ref<string | null>(null);
const selectedDCSubtype = ref<string | null>(null);
const selectedACSubtype = ref<string | null>(null);
const selectedPar = ref<string | null>(null);
const selectedPotencia = ref<string | null>(null);
const selectedVelocidad = ref<string | null>(null);
const searchInput = ref('');
const searchTerm = ref('');
const order = ref<string | null>(null);

const parOptions = ['0-5', '5-20', '20-50', '>50'];
const potenciaOptions = ['0-1 kW', '1-5 kW'];
const velocidadOptions = ['500 rpm', '1500 rpm'];
const technologyOptions = ['Continua (DC)', 'Alterna (AC)'];
const dcTypeOptions = ['Imanes Permanentes', 'Excitación Independiente'];
const acTypeOptions = ['Convencional', 'Brushless', 'Cabezal'];
const orderOptions = ['Recientes', 'Precio asc', 'Precio desc'];

const itemsPerPage = ref(9);
const page = ref(1);
const sanitize = (str: string) =>
  str
    .normalize('NFD').replace(new RegExp('[\\u0300-\\u036f]', 'g'), '')
    .replace(/[¿?.,!]/g, '')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase();

watch(searchInput, val => {
  const term = sanitize(val);
  searchTerm.value = term;
  if (term.includes('motor')) productTypes.value = ['motor'];
  else if (term.includes('regulador')) productTypes.value = ['regulador'];
  else if (term) productTypes.value = ['otros-repuestos'];
  else productTypes.value = [];
  page.value = 1;
});

const selectedCategory = computed(() => productTypes.value[0] || null);
const showTechnology = computed(() => ['motor', 'regulador'].includes(selectedCategory.value || ''));
const showDcType = computed(() => selectedCategory.value === 'motor' && selectedTechnology.value === 'Continua (DC)');
const showAcType = computed(() => selectedCategory.value === 'motor' && selectedTechnology.value === 'Alterna (AC)');
const showParFilter = computed(() => showDcType.value && selectedDCSubtype.value === 'Imanes Permanentes');
const showDcPotenciaFilter = computed(() => showDcType.value && selectedDCSubtype.value === 'Excitación Independiente');
const showAcFilters = computed(() => showAcType.value && !!selectedACSubtype.value);
const showVelocidadFilter = computed(() => showParFilter.value || showDcPotenciaFilter.value || showAcFilters.value);
const showPotenciaFilter = computed(() => showDcPotenciaFilter.value || showAcFilters.value);

watch(selectedTechnology, () => {
  selectedDCSubtype.value = null;
  selectedACSubtype.value = null;
  selectedPar.value = null;
  selectedPotencia.value = null;
  selectedVelocidad.value = null;
  page.value = 1;
});

watch(selectedDCSubtype, () => {
  selectedPar.value = null;
  selectedPotencia.value = null;
  selectedVelocidad.value = null;
  page.value = 1;
});

watch(selectedACSubtype, () => {
  selectedPotencia.value = null;
  selectedVelocidad.value = null;
  page.value = 1;
});
// -- Data Fetching --
const { data: brandsData } = await useApi<Term[]>(createUrl('/wp-json/motorlan/v1/marcas'));
const marcas = computed(() => brandsData.value || []);

const motorsApiUrl = computed(() => {
  const baseUrl = '/wp-json/motorlan/v1/motors'

  const sortOptions = {
    'Recientes': { orderby: 'date', order: 'desc' },
    'Precio asc': { orderby: 'price', order: 'asc' },
    'Precio desc': { orderby: 'price', order: 'desc' },
  }

  const queryParams = {
    per_page: itemsPerPage.value,
    page: page.value,
    status: 'publish',
    s: searchTerm.value,
    category: productTypes.value.join(','),
    marca: selectedBrand.value,

    potencia: selectedPotencia.value,
    velocidad: selectedVelocidad.value,
    par_nominal: selectedPar.value,
    tipo_de_alimentacion: selectedTechnology.value,


    ...(order.value ? sortOptions[order.value] : {}),
  }

  const filteredParams = Object.entries(queryParams)
    .filter(([_, value]) => value !== null && value !== undefined && value !== '')
    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
    .join('&')

  return `${baseUrl}?${filteredParams}`
})

const { data: motorsData, isFetching: loading, execute: fetchMotors } = useApi<any>(motorsApiUrl, { immediate: false }).get()

watch(
  () => motorsApiUrl.value,
  () => {
    fetchMotors()
  },
)

onMounted(fetchMotors)


const motors = computed((): Motor[] => motorsData.value?.data || []);
const totalMotors = computed(() => motorsData.value?.pagination.total || 0);
const totalPages = computed(() => motorsData.value?.pagination.totalPages || 1);

</script>

<template>
  <div class="tienda d-flex">
    <aside class="filters pa-4">
      <div class="d-flex align-center mb-2">
        <VIcon
          size="18"
          class="me-2"
          color="error"
        >
          mdi-checkbox-blank-outline
        </VIcon>
        <span class="text-error font-weight-semibold">FILTROS</span>
      </div>
      <VDivider
        thickness="3"
        class="mb-4"
        color="error"
      />

      <VTextField

        v-model="searchInput"
        placeholder="Motor, Regulador u otros repuestos"

        variant="outlined"
        density="comfortable"
        class="mb-6"
      />

      <AppSelect
        v-if="showTechnology"
        v-model="selectedTechnology"
        label="Tecnología"
        :items="technologyOptions"
        class="mb-4"
        clearable
      />
      <AppSelect
        v-if="showDcType"
        v-model="selectedDCSubtype"
        label="Tipo C.C."
        :items="dcTypeOptions"
        class="mb-4"
        clearable
      />
      <AppSelect
        v-if="showParFilter"
        v-model="selectedPar"
        label="PAR (Mn)"
        :items="parOptions"
        class="mb-4"
        clearable
      />
      <AppSelect
        v-if="showPotenciaFilter"
        v-model="selectedPotencia"
        label="POTENCIA (Kw)"
        :items="potenciaOptions"
        class="mb-4"
        clearable
      />
      <AppSelect
        v-if="showVelocidadFilter"
        v-model="selectedVelocidad"
        label="VELOCIDAD (Rpm)"
        :items="velocidadOptions"
        class="mb-4"
        clearable
      />
      <AppSelect
        v-if="showAcType"
        v-model="selectedACSubtype"
        label="Tipo C.A."
        :items="acTypeOptions"
        class="mb-4"
        clearable
      />
      <VAutocomplete
        v-model="selectedBrand"
        label="Marca"
        :items="marcas"
        item-title="name"
        item-value="id"
        class="mb-4"
        clearable
      />
    </aside>

    <section class="flex-grow-1 ps-6">
      <div class="d-flex justify-end mb-6">
        <AppSelect v-model="order" :items="orderOptions" label="Ordenar" clearable style="max-width:220px" />
      </div>

      <div
        v-if="loading && !motors.length"
        class="text-center pa-12"
      >
        <VProgressCircular
          indeterminate
          size="64"
        />
        <p class="mt-4">
          Cargando motores...
        </p>
      </div>

      <VRow
        v-else-if="motors.length"
        class="motor-grid"
      >
        <VCol
          v-for="motor in motors"
          :key="motor.id"
          cols="12"
          sm="6"
          md="4"
        >
          <div class="motor-card pa-4">
            <div class="motor-image mb-6">
              <img
                :src="motor.imagen_destacada?.url || '/placeholder.png'"
                alt=""
              >
            </div>
            <div class="text-error text-body-1 mb-1">
              {{ motor.title }}
            </div>
            <div class="text-caption mb-4">
              {{ motor.acf.estado_del_articulo }}
            </div>
            <div class="d-flex justify-space-between align-center">
              <VBtn
                color="error"
                class="rounded-pill px-6"
                :to="`/tienda/${motor.slug}`"
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

      <VCard
        v-else
        class="pa-8 text-center"
      >
        <VCardText>
          <p class="text-h6">
            No se encontraron motores
          </p>
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
.top-bar {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
}
.search-btn {
  height: 56px;
  width: 56px;
}
</style>
