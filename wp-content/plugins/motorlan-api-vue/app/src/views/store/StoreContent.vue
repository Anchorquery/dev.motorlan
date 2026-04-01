<script setup lang="ts">
import { computed, onMounted, ref, watch, nextTick } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import TiendaFilters from '@/pages/store/components/TiendaFilters.vue'
import SearchBar from '@/pages/store/components/SearchBar.vue'
import PublicacionItems from '@/pages/store/components/PublicacionItems.vue'
import PaginationControls from '@/pages/store/components/PaginationControls.vue'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'
import type { Publicacion } from '@/interfaces/publicacion'
import { useStoreFiltersStore } from '@/views/store/useStoreFiltersStore'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const filtersStore = useStoreFiltersStore()

const {
  selectedBrand,
  selectedState,
  typeModel,
  selectedTechnology,
  selectedPar,
  selectedPotencia,
  selectedVelocidad,
  searchTerm,
  selectedTipo,
  page,
} = storeToRefs(filtersStore)

interface Term {
  term_id: number
  name: string
  slug: string
}

// -- URL Sync Logic --
const loadFiltersFromUrl = () => {
  const query = route.query
  if (query.brand) selectedBrand.value = Number(query.brand)
  if (query.state) selectedState.value = String(query.state)
  if (query.type) typeModel.value = String(query.type)
  if (query.tech) selectedTechnology.value = String(query.tech)
  if (query.par) selectedPar.value = String(query.par)
  if (query.pot) selectedPotencia.value = String(query.pot)
  if (query.vel) selectedVelocidad.value = String(query.vel)
  if (query.q) searchTerm.value = String(query.q)
  if (query.tipo) selectedTipo.value = String(query.tipo)
  if (query.page) page.value = Number(query.page)
}

const syncFiltersToUrl = () => {
  const query = {
    brand: selectedBrand.value || undefined,
    state: selectedState.value || undefined,
    type: typeModel.value || undefined,
    tech: selectedTechnology.value || undefined,
    par: selectedPar.value || undefined,
    pot: selectedPotencia.value || undefined,
    vel: selectedVelocidad.value || undefined,
    q: searchTerm.value || undefined,
    tipo: selectedTipo.value || undefined,
    page: page.value > 1 ? page.value : undefined,
  }

  router.replace({ query: { ...route.query, ...query } })
}

// -- State Management --
// (Eliminated local refs as they are now in the store)

// Options with display labels and machine values (min-max)
const parOptions = computed(() => [
  { title: '0-5', value: '0-5' },
  { title: '5-20', value: '5-20' },
  { title: '20-50', value: '20-50' },
  { title: '>50', value: '50-999999' },
])
const potenciaOptions = computed(() => [
  { title: '0-100 kW / 0-75 CV', value: '0-100' },
  { title: '100-300 kW / 75-135 CV', value: '100-300' },
  { title: '> 300 kW / > 135 CV', value: '300-999999' },
])
const velocidadOptions = computed(() => [
  { title: '0-1.500 rpm', value: '0-1500' },
  { title: '1.500-3.000 rpm', value: '1500-3000' },
  { title: '3.000-5.000 rpm', value: '3000-5000' },
  { title: 'mayor que 5.000 rpm', value: '5000-999999' },
])
const technologyOptions = computed(() => [t('store.technology_options.dc'), t('store.technology_options.ac')])

const itemsPerPage = ref(9)

// -- Data Fetching --
// -- Data Fetching --
const marcas = ref<Term[]>([])
const tipos = ref<Term[]>([])
const publicacionesApiUrl = computed(() => {
  const baseUrl = '/wp-json/motorlan/v1/store/publicaciones'

  const queryParams = {
    per_page: itemsPerPage.value,
    page: page.value,
    status: 'publish',
    search: searchTerm.value,
    tipo: selectedTipo.value,

    // pass brand by term_id as stored in ACF meta
    marca: selectedBrand.value,
    estado_del_articulo: selectedState.value,
    potencia: selectedPotencia.value,
    velocidad: selectedVelocidad.value,
    par_nominal: selectedPar.value,
    tipo_de_alimentacion: selectedTechnology.value,
    tipo_o_referencia: typeModel.value ? typeModel.value.replace(/[\.\-\/\s]/g, '') : '',
    orderby: 'date',
    order: 'desc',
  }

  const filteredParams = Object.entries(queryParams)
    .filter(([, value]) => value !== null && value !== undefined && value !== '')
    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`)
    .join('&')

  return `${baseUrl}?${filteredParams}`
})

const { data: publicacionesData, isFetching, execute: fetchPublicaciones } = useApi<any>(publicacionesApiUrl, { immediate: false }).get().json()
const isSearching = ref(true)

const isLoading = computed(() => isFetching.value || isSearching.value)

const applyFilters = async () => {
  isSearching.value = true
  await nextTick()
  try {
    await fetchPublicaciones()
  }
  finally {
    isSearching.value = false
  }
}

watch([selectedBrand, selectedState, typeModel, selectedTechnology, selectedPar, selectedPotencia, selectedVelocidad, searchTerm, selectedTipo, itemsPerPage], () => {
  page.value = 1
  syncFiltersToUrl()
  applyFilters()
}, { deep: true })

watch(page, () => {
  syncFiltersToUrl()
  applyFilters()
})

onMounted(async () => {
  loadFiltersFromUrl()

  // Fetch Brands
  try {

    const { data: brandsData, execute: executeBrands } = useApi<any>('/wp-json/motorlan/v1/marcas', { immediate: false }).get().json()
    await executeBrands()

    
    if (brandsData.value) {
      const raw = brandsData.value
      marcas.value = Array.isArray(raw) ? raw : (raw.data || [])

    }
  } catch (e) {
    console.error('Exception fetching marcas:', e)
  }

  // Fetch Types
  try {

    const { data: tiposData, execute: executeTipos } = useApi<any>('/wp-json/motorlan/v1/tipos', { immediate: false }).get().json()
    await executeTipos()


    if (tiposData.value) {
      const raw = tiposData.value
      tipos.value = Array.isArray(raw) ? raw : (raw.data || [])

    }
  } catch (e) {
     console.error('Exception fetching tipos:', e)
  }

  applyFilters() // Initial data load
})

const publicaciones = computed((): Publicacion[] => publicacionesData.value?.data || publicacionesData.value || [])
const totalPublicaciones = computed(() => publicacionesData.value?.pagination.total || 0)
const totalPages = computed(() => publicacionesData.value?.pagination.totalPages || 1)

const search = () => {
  page.value = 1
  syncFiltersToUrl()
  applyFilters()
}
</script>

<template>
  <v-container fluid style="background-color: white !important; min-height: 100vh;">
    <v-row>
      <v-col
        cols="12"
        md="3"
      >
        <TiendaFilters
          v-model:type-model="typeModel"
          v-model:selected-technology="selectedTechnology"
          v-model:selected-par="selectedPar"
          v-model:selected-potencia="selectedPotencia"
          v-model:selected-velocidad="selectedVelocidad"
          v-model:selected-brand="selectedBrand"
          v-model:selected-state="selectedState"
          v-model:selected-tipo="selectedTipo"
          :marcas="marcas"
          :tipos="tipos"
          :technology-options="technologyOptions"
          :par-options="parOptions"
          :potencia-options="potenciaOptions"
          :velocidad-options="velocidadOptions"
        />
      </v-col>

      <v-col
        cols="12"
        md="9"
      >
        <SearchBar
          v-model:search-term="searchTerm"
          :loading="isSearching"
          @search="search"
        />

        <PublicacionItems
          :publicaciones="publicaciones"
          :loading="isLoading"
        />

        <PaginationControls
          v-model:page="page"
          :total-pages="totalPages"
        />
      </v-col>
    </v-row>
  </v-container>
</template>
