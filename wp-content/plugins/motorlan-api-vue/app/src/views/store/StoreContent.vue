<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useDisplay } from 'vuetify'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import TiendaFilters from '@/pages/store/components/TiendaFilters.vue'
import SearchBar from '@/pages/store/components/SearchBar.vue'
import PublicacionItems from '@/pages/store/components/PublicacionItems.vue'
import PaginationControls from '@/pages/store/components/PaginationControls.vue'
import { useApi } from '@/composables/useApi'
import type { Publicacion } from '@/interfaces/publicacion'
import { useStoreFiltersStore } from '@/views/store/useStoreFiltersStore'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const filtersStore = useStoreFiltersStore()
const display = useDisplay()

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

const isFiltersDrawerOpen = ref(false)

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

  router.replace({ query })
}

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

const activeFiltersCount = computed(() => {
  const values = [
    selectedBrand.value,
    selectedState.value,
    typeModel.value?.trim(),
    selectedTechnology.value,
    selectedPar.value,
    selectedPotencia.value,
    selectedVelocidad.value,
    searchTerm.value?.trim(),
    selectedTipo.value,
  ]

  return values.filter(value => value !== null && value !== undefined && value !== '').length
})

const clearAllFilters = () => {
  filtersStore.resetFilters()
  isFiltersDrawerOpen.value = false
}

watch(
  [
    selectedBrand,
    selectedState,
    typeModel,
    selectedTechnology,
    selectedPar,
    selectedPotencia,
    selectedVelocidad,
    searchTerm,
    selectedTipo,
    itemsPerPage,
  ],
  () => {
    page.value = 1
    syncFiltersToUrl()
    applyFilters()
  },
  { deep: true },
)

watch(page, () => {
  syncFiltersToUrl()
  applyFilters()
})

watch(display.mdAndUp, value => {
  if (value)
    isFiltersDrawerOpen.value = false
})

onMounted(async () => {
  loadFiltersFromUrl()

  try {
    const { data: brandsData, execute: executeBrands } = useApi<any>('/wp-json/motorlan/v1/marcas', { immediate: false }).get().json()
    await executeBrands()

    if (brandsData.value) {
      const raw = brandsData.value
      marcas.value = Array.isArray(raw) ? raw : (raw.data || [])
    }
  }
  catch (e) {
    console.error('Exception fetching marcas:', e)
  }

  try {
    const { data: tiposData, execute: executeTipos } = useApi<any>('/wp-json/motorlan/v1/tipos', { immediate: false }).get().json()
    await executeTipos()

    if (tiposData.value) {
      const raw = tiposData.value
      tipos.value = Array.isArray(raw) ? raw : (raw.data || [])
    }
  }
  catch (e) {
    console.error('Exception fetching tipos:', e)
  }

  applyFilters()
})

const totalPublicaciones = computed(() => publicacionesData.value?.pagination.total || 0)
const totalPages = computed(() => publicacionesData.value?.pagination.totalPages || 1)

const resultSummary = computed(() => {
  if (!totalPublicaciones.value)
    return 'Sin resultados'

  return `${totalPublicaciones.value} publicaciones encontradas`
})

const publicaciones = computed((): Publicacion[] => publicacionesData.value?.data || publicacionesData.value || [])

const search = () => {
  page.value = 1
  syncFiltersToUrl()
  applyFilters()
  isFiltersDrawerOpen.value = false
}
</script>

<template>
  <div class="store-shell">
    <VNavigationDrawer
      v-model="isFiltersDrawerOpen"
      temporary
      location="end"
      width="390"
      class="store-filters-drawer"
    >
      <div class="d-flex flex-column h-100 pa-4">
        <div class="d-flex align-center justify-space-between mb-4">
          <div>
            <div class="text-overline text-error font-weight-bold">
              Filtros
            </div>
            <div class="text-body-2 text-medium-emphasis">
              Refina la busqueda desde un panel comodo para mobile.
            </div>
          </div>

          <IconBtn
            variant="text"
            color="default"
            @click="isFiltersDrawerOpen = false"
          >
            <VIcon icon="tabler-x" />
          </IconBtn>
        </div>

        <div class="flex-grow-1 overflow-y-auto pe-1">
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
        </div>
      </div>
    </VNavigationDrawer>

    <VContainer
      fluid
      class="store-container px-4 px-md-6 py-4 py-md-6"
    >
      <SearchBar
        v-model:search-term="searchTerm"
        :loading="isSearching"
        :active-filters-count="activeFiltersCount"
        @search="search"
        @open-filters="isFiltersDrawerOpen = true"
        @reset="clearAllFilters"
      />

      <VRow
        align="start"
        class="store-grid"
      >
        <VCol
          cols="12"
          md="4"
          lg="3"
          class="d-none d-md-block"
        >
          <div class="store-sidebar">
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
          </div>
        </VCol>

        <VCol
          cols="12"
          md="8"
          lg="9"
          class="store-main"
        >
          <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-4">
            <div>
              <div class="text-subtitle-1 font-weight-bold text-primary">
                Resultados
              </div>
              <div class="text-body-2 text-medium-emphasis">
                {{ resultSummary }}
              </div>
            </div>

            <VChip
              v-if="activeFiltersCount"
              color="error"
              variant="tonal"
              size="small"
            >
              {{ activeFiltersCount }} filtros activos
            </VChip>
          </div>

          <PublicacionItems
            :publicaciones="publicaciones"
            :loading="isLoading"
          />

          <div class="d-flex justify-center">
            <PaginationControls
              v-model:page="page"
              :total-pages="totalPages"
            />
          </div>
        </VCol>
      </VRow>
    </VContainer>
  </div>
</template>

<style scoped>
.store-shell {
  min-height: 100vh;
  background:
    radial-gradient(circle at top, rgba(218, 41, 28, 0.06), transparent 26%),
    linear-gradient(180deg, #fff 0%, #fff7f6 100%);
}

.store-container {
  max-width: 1600px;
}

.store-sidebar {
  position: sticky;
  top: 1.5rem;
}

.store-main {
  min-width: 0;
}

.store-filters-drawer {
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(255, 248, 247, 0.98));
}

@media (max-width: 959px) {
  .store-sidebar {
    position: static;
  }
}
</style>
