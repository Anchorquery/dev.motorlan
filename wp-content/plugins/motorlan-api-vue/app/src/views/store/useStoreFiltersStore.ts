import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useStoreFiltersStore = defineStore('storeFilters', () => {
  const selectedBrand = ref<number | null>(null)
  const selectedState = ref<string | null>(null)
  const typeModel = ref('')
  const selectedTechnology = ref<string | null>(null)
  const selectedPar = ref<string | null>(null)
  const selectedPotencia = ref<string | null>(null)
  const selectedVelocidad = ref<string | null>(null)
  const searchTerm = ref('')
  const selectedTipo = ref<string | null>(null)
  const page = ref(1)

  const resetFilters = () => {
    selectedBrand.value = null
    selectedState.value = null
    typeModel.value = ''
    selectedTechnology.value = null
    selectedPar.value = null
    selectedPotencia.value = null
    selectedVelocidad.value = null
    searchTerm.value = ''
    selectedTipo.value = null
    page.value = 1
  }

  return {
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
    resetFilters,
  }
})
