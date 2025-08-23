<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
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
  id: number
  name: string
  slug: string
}

// -- State Management --
const selectedBrand = ref<number | null>(null)
const selectedState = ref<string | null>(null)
const typeModel = ref('')
const productTypes = ref<string[]>([])
const selectedTechnology = ref<string | null>(null)
const selectedPar = ref<string | null>(null)
const selectedPotencia = ref<string | null>(null)
const selectedVelocidad = ref<string | null>(null)
const searchTerm = ref('')
const order = ref<string | null>(t('tienda.order_options.recents'))
const selectedTipo = ref<string | null>(null)

const parOptions = computed(() => [t('tienda.par_options.range1'), t('tienda.par_options.range2')])
const potenciaOptions = computed(() => [t('tienda.potencia_options.range1'), t('tienda.potencia_options.range2')])
const velocidadOptions = computed(() => [t('tienda.velocidad_options.range1'), t('tienda.velocidad_options.range2')])
const technologyOptions = computed(() => [t('tienda.technology_options.dc'), t('tienda.technology_options.ac')])
const orderOptions = computed(() => [t('tienda.order_options.recents'), t('tienda.order_options.price_asc'), t('tienda.order_options.price_desc')])

const itemsPerPage = ref(9)
const page = ref(1)

// -- Data Fetching --
const { data: brandsData } = await useApi<Term[]>(createUrl('/wp-json/motorlan/v1/marcas'))
const marcas = computed(() => brandsData.value || [])

const publicacionesApiUrl = computed(() => {
  const baseUrl = '/wp-json/motorlan/v1/publicaciones'

  const sortOptions = {
    [t('tienda.order_options.recents')]: { orderby: 'date', order: 'desc' },
    [t('tienda.order_options.price_asc')]: { orderby: 'price', order: 'asc' },
    [t('tienda.order_options.price_desc')]: { orderby: 'price', order: 'desc' },
  }

  const queryParams = {
    per_page: itemsPerPage.value,
    page: page.value,
    status: 'publish',
    s: searchTerm.value,
    category: productTypes.value.join(','),
    tipo: selectedTipo.value,
    marca: selectedBrand.value,
    estado_del_articulo: selectedState.value,
    potencia: selectedPotencia.value,
    velocidad: selectedVelocidad.value,
    par_nominal: selectedPar.value,
    tipo_de_alimentacion: selectedTechnology.value,
    tipo_o_referencia: typeModel.value,
    ...(order.value ? sortOptions[order.value] : {}),
  }

  const filteredParams = Object.entries(queryParams)
    .filter(([_, value]) => value !== null && value !== undefined && value !== '')
    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
    .join('&')

  return `${baseUrl}?${filteredParams}`
})

const { data: publicacionesData, isFetching: loading, execute: fetchPublicaciones } = useApi<any>(publicacionesApiUrl, { immediate: false }).get()

watch(
  () => publicacionesApiUrl.value,
  () => {
    fetchPublicaciones()
  },
)

onMounted(fetchPublicaciones)

const publicaciones = computed((): Publicacion[] => publicacionesData.value?.data || [])
const totalPublicaciones = computed(() => publicacionesData.value?.pagination.total || 0)
const totalPages = computed(() => publicacionesData.value?.pagination.totalPages || 1)

const search = () => {
  page.value = 1
  fetchPublicaciones()
}
</script>

<template>
  <div class="tienda d-flex">
    <TiendaFilters
      v-model:type-model="typeModel"
      v-model:product-types="productTypes"
      v-model:selected-technology="selectedTechnology"
      v-model:selected-par="selectedPar"
      v-model:selected-potencia="selectedPotencia"
      v-model:selected-velocidad="selectedVelocidad"
      v-model:selected-brand="selectedBrand"
      v-model:selected-state="selectedState"
      v-model:selected-tipo="selectedTipo"
      :marcas="marcas"
      :technology-options="technologyOptions"
      :par-options="parOptions"
      :potencia-options="potenciaOptions"
      :velocidad-options="velocidadOptions"
    />

    <section class="flex-grow-1 ps-6">
      <SearchBar
        v-model:search-term="searchTerm"
        v-model:order="order"
        :loading="loading"
        :order-options="orderOptions"
        @search="search"
      />

      <PublicacionItems
        :publicaciones="publicaciones"
        :loading="loading"
      />

      <PaginationControls
        v-model:page="page"
        :total-pages="totalPages"
      />
    </section>
  </div>
</template>

<style scoped>
.tienda {
  align-items: flex-start;
}
</style>
