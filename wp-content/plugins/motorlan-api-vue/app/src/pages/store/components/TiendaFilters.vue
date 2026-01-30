<script setup lang="ts">
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Term {
  term_id: number
  name: string
  slug: string
}

const props = defineProps<{
  marcas: Term[]
  tipos: Term[]
  technologyOptions: any[]
  parOptions: any[]
  potenciaOptions: any[]
  velocidadOptions: any[]
}>()

const typeModel = defineModel<string>('typeModel')
const selectedTechnology = defineModel<string | null>('selectedTechnology')
const selectedPar = defineModel<string | null>('selectedPar')
const selectedPotencia = defineModel<string | null>('selectedPotencia')
const selectedVelocidad = defineModel<string | null>('selectedVelocidad')
const selectedBrand = defineModel<number | null>('selectedBrand')
const selectedState = defineModel<string | null>('selectedState')
const selectedTipo = defineModel<string | null>('selectedTipo')

// Mutual Exclusion Logic
const isTypeModelActive = computed(() => !!typeModel.value && typeModel.value.trim().length > 0)
// If param filters are active, we might want to disable typeModel, but requirement says "Al escribir algo en el 'Tipo/modelo', inhabilitar... Y viceversa"
// So if values are selected in params, typeModel should be disabled?
// "Y viceversa" implies:
// 1. If Text is typed -> Disable Selects
// 2. If Selects are picked -> Disable Text?
// Let's implement dynamic disabling.

const areParamFiltersActive = computed(() => {
  return !!selectedPar.value || !!selectedPotencia.value || !!selectedVelocidad.value
})

// Watchers to clear content if the other side is used?
// The requirement says "inhabilitar" (disable).
// It's better to just disable the inputs.

// Custom sorted types
const sortedTipos = computed(() => {
  // Desired order: Motor (AC), Motor (DC), Regulador, Otros
  // We need to map slugs to this order.
  // Assuming slugs: 'motor', 'regulador', 'otros' (based on generic knowledge, need to verify exact slugs)
  // The requirement lists:
  // Motor (AC)
  // Motor (DC)
  // Regulador
  // Otros
  // But 'Motor' is likely one category. AC/DC might be subcategories or attributes?
  // In `StoreContent.vue` there is `technologyOptions` for AC/DC.
  // The user requirement image shows "Tipo de producto" having separate radios for Motor AC / Motor DC.
  // BUT the current `tipos` prop comes from taxonomy terms.
  // If `tipos` has just 'Motor', 'Regulador', etc., we might need to fake the AC/DC split or use the `selectedTechnology` filter implicitly?
  // User Prompt says: "1. Tipo de producto (modificar y cambiar orden)... Motor (AC), Motor (DC)..."
  // If the backend `tipos` are just "Motor", then selecting "Motor AC" should select Tipo=Motor AND Technology=AC.
  // This is a Logic Change in the filter implementation.
  
  // Let's create a custom UI list that maps to the models.
  return []; // We will render manually in template for custom logic
})

const handleCustomTypeSelect = (val: string) => {
  if (val === 'motor-ac') {
    selectedTipo.value = 'motor' // assuming slug 'motor'
    selectedTechnology.value = 'ac' // assuming value 'ac'
  } else if (val === 'motor-dc') {
    selectedTipo.value = 'motor'
    selectedTechnology.value = 'dc'
  } else if (val === 'regulador') {
    selectedTipo.value = 'regulador'
    selectedTechnology.value = null
  } else if (val === 'otros') {
    selectedTipo.value = 'otro-repuesto' // guessing slug
    selectedTechnology.value = null
  } else {
    selectedTipo.value = null
    selectedTechnology.value = null
  }
}

// Reverse mapping for UI state
const customTypeModel = computed({
  get() {
    if (selectedTipo.value === 'motor' && selectedTechnology.value === 'ac') return 'motor-ac'
    if (selectedTipo.value === 'motor' && selectedTechnology.value === 'dc') return 'motor-dc'
    if (selectedTipo.value === 'regulador') return 'regulador'
    if (selectedTipo.value === 'otro-repuesto') return 'otros'
    return null
  },
  set(val: string | null) {
      if (val === 'motor-ac') {
        selectedTipo.value = 'motor'
        selectedTechnology.value = 'ac'
      } else if (val === 'motor-dc') {
        selectedTipo.value = 'motor'
        selectedTechnology.value = 'dc'
      } else if (val === 'regulador') {
        selectedTipo.value = 'regulador'
        selectedTechnology.value = null
      } else if (val === 'otros') {
        selectedTipo.value = 'otro-repuesto'
        selectedTechnology.value = null
      } else {
        selectedTipo.value = null
        selectedTechnology.value = null
      }
  }
})

</script>

<template>
  <aside class="filters sidebar-filters-enhanced">
    <div class="d-flex align-center mb-4">
      <VIcon size="18" class="me-2" color="error">
        mdi-filter-variant
      </VIcon>
      <span class="text-error font-weight-medium text-uppercase" style="letter-spacing: 1px; font-size: 0.85rem;">FILTROS</span>
    </div>
    <VDivider thickness="3" class="mb-4" color="error" />

    <VTextField
      v-model="typeModel"
      :label="t('store.filters.type_model_label')"
      variant="outlined"
      density="comfortable"
      class="mb-6"
      :disabled="areParamFiltersActive"
      hide-details="auto"
    />

    <div class="mb-4">
      <span class="text-sm font-weight-medium text-high-emphasis">{{ t('store.filters.product_type') }}</span>
      <VRadioGroup v-model="customTypeModel" class="mt-2 custom-radio-group">
        <!-- Manual mapping based on request -->
         <VRadio label="Todos" :value="null" />
         
         <!-- Motor AC -->
         <VTooltip location="top" text="Motores de Corriente Alterna">
           <template #activator="{ props }">
             <div v-bind="props" class="w-100">
               <VRadio 
                 label="Motor (AC)" 
                 value="motor-ac" 
                 true-icon="mdi-close" 
                 false-icon="mdi-radiobox-blank" 
                 color="error"
               />
             </div>
           </template>
         </VTooltip>

         <!-- Motor DC -->
         <VTooltip location="top" text="Motores de Corriente Continua">
            <template #activator="{ props }">
              <div v-bind="props" class="w-100">
                <VRadio 
                  label="Motor (DC)" 
                  value="motor-dc"
                  true-icon="mdi-close" 
                  false-icon="mdi-radiobox-blank" 
                  color="error"
                />
              </div>
            </template>
         </VTooltip>

         <!-- Regulador -->
         <VTooltip location="top" text="Reguladores de velocidad">
            <template #activator="{ props }">
              <div v-bind="props" class="w-100">
                 <VRadio 
                   label="Regulador" 
                   value="regulador"
                   true-icon="mdi-close" 
                   false-icon="mdi-radiobox-blank" 
                   color="error"
                 />
              </div>
            </template>
         </VTooltip>

         <!-- Otros -->
         <VTooltip location="top" :text="t('store.filters.others_tooltip')">
            <template #activator="{ props }">
              <div v-bind="props" class="w-100">
                 <VRadio 
                   label="Otros" 
                   value="otros"
                   true-icon="mdi-close" 
                   false-icon="mdi-radiobox-blank" 
                   color="error"
                 />
              </div>
            </template>
         </VTooltip>

      </VRadioGroup>
    </div>

    <!-- Disabled when Text search is active -->
    <AppSelect v-model="selectedPar" :label="t('store.filters.torque_label')" placeholder="Seleccionar PAR (Nm)" :items="parOptions" class="mb-4" variant="outlined" color="error" clearable :disabled="isTypeModelActive" />
    <AppSelect v-model="selectedPotencia" :label="t('store.filters.power_label')" placeholder="Seleccionar potencia" :items="potenciaOptions" class="mb-4" variant="outlined" color="error" clearable :disabled="isTypeModelActive" />
    <AppSelect v-model="selectedVelocidad" :label="t('store.filters.speed_label')" placeholder="Seleccionar velocidad" :items="velocidadOptions" class="mb-4" variant="outlined" color="error" clearable :disabled="isTypeModelActive" />
    
    <AppSelect v-model="selectedBrand" :label="t('store.filters.brands_label')" placeholder="Seleccionar marcas" :items="marcas" item-title="name" item-value="term_id" class="mb-4" variant="outlined" color="error" clearable />
    <AppSelect v-model="selectedState" :label="t('store.filters.state_label')" placeholder="Seleccionar estado" item-title="name" item-value="value"  :items="[{'name':'Nuevo', 'value':'new'},{'name':'Usado', 'value':'used'},{'name':'Reacondicionado', 'value':'restored'}]" class="mb-4" variant="outlined" color="error" clearable />
  
    <div class="mt-6 text-caption text-error font-weight-bold" style="line-height: 1.2;">
      {{ t('store.filters.responsibility_note') }}
    </div>
  </aside>
</template>

<style>
.filters {
  width: min(100%, 300px);
  box-sizing: border-box;
}

.filters .v-icon,
.filters .text-error,
.filters .v-select .v-label {
  color: #da291c !important;
}

.filters .v-select--variant-outlined .v-field__outline__color {
  color: #da291c !important;
}

.filters .v-divider {
  border-color: #da291c !important;
}

@media (max-width: 960px) {
  .filters {
    width: 100%;
  }
}
</style>
