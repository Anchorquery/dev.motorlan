<script setup lang="ts">
interface Term {
  id: number
  name: string
  slug: string
}

defineProps<{
  marcas: Term[]
  tipos: Term[]
  technologyOptions: string[]
  parOptions: string[]
  potenciaOptions: string[]
  velocidadOptions: string[]
}>()

const typeModel = defineModel<string>('typeModel')
const selectedTechnology = defineModel<string | null>('selectedTechnology')
const selectedPar = defineModel<string | null>('selectedPar')
const selectedPotencia = defineModel<string | null>('selectedPotencia')
const selectedVelocidad = defineModel<string | null>('selectedVelocidad')
const selectedBrand = defineModel<number | null>('selectedBrand')
const selectedState = defineModel<string | null>('selectedState')
const selectedTipo = defineModel<string | null>('selectedTipo')
</script>

<template>
  <aside class="filters pa-4">
    <div class="d-flex align-center mb-2">
      <VIcon size="18" class="me-2" color="error">
        mdi-filter-variant
      </VIcon>
      <span class="text-error font-weight-semibold">FILTROS</span>
    </div>
    <VDivider thickness="3" class="mb-4" color="error" />

    <VTextField
      v-model="typeModel"
      label="Tipo / modelo"
      variant="outlined"
      density="comfortable"
      class="mb-6"
    />

    <div class="mb-4">
      <span class="text-sm font-weight-medium text-high-emphasis">Tipo de producto</span>
      <VRadioGroup v-if="tipos.length" v-model="selectedTipo" class="mt-2">
        <VRadio label="Todos" :value="null" />
        <VRadio
          v-for="tipo in tipos"
          :key="tipo.slug"
          :label="tipo.name"
          :value="tipo.slug"
        />
      </VRadioGroup>
    </div>

    <AppSelect v-model="selectedPar" label="PAR (Nm)" placeholder="Seleccionar PAR (Nm)" :items="parOptions" class="mb-4" variant="outlined" color="error" clearable />
    <AppSelect v-model="selectedPotencia" label="Potencia" placeholder="Seleccionar potencia" :items="potenciaOptions" class="mb-4" variant="outlined" color="error" clearable />
    <AppSelect v-model="selectedVelocidad" label="Velocidad" placeholder="Seleccionar velocidad" :items="velocidadOptions" class="mb-4" variant="outlined" color="error" clearable />
    <AppSelect v-model="selectedBrand" label="Marcas" placeholder="Seleccionar marcas" :items="marcas" item-title="name" item-value="id" class="mb-4" variant="outlined" color="error" clearable />
    <AppSelect v-model="selectedState" label="Estado" placeholder="Seleccionar estado" :items="['Nuevo','Usado','Restaurado']" class="mb-4" variant="outlined" color="error" clearable />
  </aside>
</template>

<style>
.filters {
  width: 300px;
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
</style>

