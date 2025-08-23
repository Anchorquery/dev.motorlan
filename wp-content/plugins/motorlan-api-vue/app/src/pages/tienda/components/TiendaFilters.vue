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
        mdi-checkbox-blank-outline
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

    <AppSelect v-model="selectedTipo" label="Tipo de producto" :items="tipos" item-title="name" item-value="slug" class="mb-4" clearable />
    <AppSelect v-model="selectedTechnology" label="Tecnología" :items="technologyOptions" class="mb-4" clearable />
    <AppSelect v-model="selectedPar" label="PAR (Nm)" :items="parOptions" class="mb-4" clearable />
    <AppSelect v-model="selectedPotencia" label="Potencia" :items="potenciaOptions" class="mb-4" clearable />
    <AppSelect v-model="selectedVelocidad" label="Velocidad" :items="velocidadOptions" class="mb-4" clearable />
    <AppSelect v-model="selectedBrand" label="Marcas" :items="marcas" item-title="name" item-value="id" class="mb-4" clearable />
    <AppSelect v-model="selectedState" label="Estado" :items="['Nuevo','Usado','Restaurado']" class="mb-4" clearable />
  </aside>
</template>

<style scoped>
.filters {
  width: 300px;
}
</style>

