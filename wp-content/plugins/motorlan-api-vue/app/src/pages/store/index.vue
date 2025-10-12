<script setup lang="ts">
import { computed, onMounted, ref, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import TiendaFilters from './components/TiendaFilters.vue'
import SearchBar from './components/SearchBar.vue'
import PublicacionItems from './components/PublicacionItems.vue'
import PaginationControls from './components/PaginationControls.vue'
import { useApi } from '@/composables/useApi'
import { createUrl } from '@/@core/composable/createUrl'
import type { Publicacion } from '@/interfaces/publicacion'

const { t } = useI18n()

interface Term {
  term_id: number
  name: string
  slug: string
}

// -- State Management --
const selectedBrand = ref<number | null>(null)
const selectedState = ref<string | null>(null)
const typeModel = ref('')
const selectedTechnology = ref<string | null>(null)
const selectedPar = ref<string | null>(null)
const selectedPotencia = ref<string | null>(null)
const selectedVelocidad = ref<string | null>(null)
const searchTerm = ref('')
const order = ref<string | null>(t('store.order_options.recents'))
const selectedTipo = ref<string | null>(null)

const parOptions = computed(() => [t('store.par_options.range1'), t('store.par_options.range2')])
const potenciaOptions = computed(() => [t('store.potencia_options.range1'), t('store.potencia_options.range2')])
const velocidadOptions = computed(() => [t('store.velocidad_options.range1'), t('store.velocidad_options.range2')])
const technologyOptions = computed(() => [t('store.technology_options.dc'), t('store.technology_options.ac')])
const orderOptions = computed(() => [t('store.order_options.recents'), t('store.order_options.price_asc'), t('store.order_options.price_desc')])

const itemsPerPage = ref(9)
const page = ref(1)

// -- Data Fetching --
const { data: brandsData } = useApi<Term[]>(createUrl('/wp-json/motorlan/v1/marcas')).get().json();
const marcas = computed(() => brandsData.value || [])

const tipos = ref<Term[]>([])


const publicacionesApiUrl = computed(() => {
  const baseUrl = '/wp-json/motorlan/v1/store/publicaciones'

  const sortOptions = {
    [t('store.order_options.recents')]: { orderby: 'date', order: 'desc' },
    [t('store.order_options.price_asc')]: { orderby: 'price', order: 'asc' },
    [t('store.order_options.price_desc')]: { orderby: 'price', order: 'desc' },
  }

  const selectedBrandSlug = computed(() => {
    if (!selectedBrand.value)
      return null
    const brand = marcas.value.find(m => m.term_id === selectedBrand.value)

    return brand ? brand.slug : null
  })

  const queryParams = {
    per_page: itemsPerPage.value,
    page: page.value,
    status: 'publish',
    s: searchTerm.value,
    tipo: selectedTipo.value,
    marca: selectedBrandSlug.value,
    estado_del_articulo: selectedState.value,
    potencia: selectedPotencia.value,
    velocidad: selectedVelocidad.value,
    par_nominal: selectedPar.value,
    tipo_de_alimentacion: selectedTechnology.value,
    tipo_o_referencia: typeModel.value,
    ...(order.value ? sortOptions[order.value] : {}),
  }

  const filteredParams = Object.entries(queryParams)
    .filter(([, value]) => value !== null && value !== undefined && value !== '')
    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`)
    .join('&')

  return `${baseUrl}?${filteredParams}`
})

const { data: publicacionesData, isFetching: loading, execute: fetchPublicaciones } = useApi<any>(publicacionesApiUrl, { immediate: false }).get().json()
const isSearching = ref(false)

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

watch([selectedBrand, selectedState, typeModel, selectedTechnology, selectedPar, selectedPotencia, selectedVelocidad, searchTerm, order, selectedTipo, page, itemsPerPage], applyFilters, { deep: true })

onMounted(async () => {
  const { data: tiposData } = await useApi<any>(createUrl('/wp-json/motorlan/v1/tipos')).get().json()
  if (tiposData.value) {
    tipos.value = Array.isArray(tiposData.value) ? tiposData.value : (tiposData.value.data || [])
  }
  applyFilters() // Initial data load
})

const publicaciones = computed((): Publicacion[] => publicacionesData.value?.data || publicacionesData.value || [])
const totalPublicaciones = computed(() => publicacionesData.value?.pagination.total || 0)
const totalPages = computed(() => publicacionesData.value?.pagination.totalPages || 1)

const search = () => {
  page.value = 1
  fetchPublicaciones()
}
</script>

<template>
  <v-container fluid>
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
          v-model:order="order"
          :loading="isSearching"
          :order-options="orderOptions"
          @search="search"
        />

        <PublicacionItems
          :publicaciones="publicaciones"
          :loading="isSearching"
        />

        <PaginationControls
          v-model:page="page"
          :total-pages="totalPages"
        />
      </v-col>
    </v-row>
  </v-container>
</template>
