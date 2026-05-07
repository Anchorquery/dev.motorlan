<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Term {
  term_id: number
  name: string
  slug: string
}

defineProps<{
  marcas: Term[]
  tipos: Term[]
  technologyOptions: any[]
  parOptions: any[]
  potenciaOptions: any[]
  velocidadOptions: any[]
  showHeader?: boolean
}>()

const typeModel = defineModel<string>('typeModel')
const selectedTechnology = defineModel<string | null>('selectedTechnology')
const selectedPar = defineModel<string | null>('selectedPar')
const selectedPotencia = defineModel<string | null>('selectedPotencia')
const selectedVelocidad = defineModel<string | null>('selectedVelocidad')
const selectedBrand = defineModel<number | null>('selectedBrand')
const selectedState = defineModel<string | null>('selectedState')
const selectedTipo = defineModel<string | null>('selectedTipo')

const isTypeModelActive = computed(() => !!typeModel.value?.trim())
const areAdvancedFiltersActive = computed(() =>
  !!selectedTechnology.value
  || !!selectedPar.value
  || !!selectedPotencia.value
  || !!selectedVelocidad.value
  || !!selectedBrand.value
  || !!selectedState.value
  || !!selectedTipo.value,
)

const hasFiltersActive = computed(() => isTypeModelActive.value || areAdvancedFiltersActive.value)
const isTypeModelLocked = computed(() => areAdvancedFiltersActive.value)
const isAdvancedFiltersLocked = computed(() => isTypeModelActive.value)

const customTypeModel = computed({
  get() {
    if (selectedTipo.value === 'motor' && selectedTechnology.value === 'ac')
      return 'motor-ac'
    if (selectedTipo.value === 'motor' && selectedTechnology.value === 'dc')
      return 'motor-dc'
    if (selectedTipo.value === 'regulador')
      return 'regulador'
    if (selectedTipo.value === 'otro-repuesto')
      return 'otros'

    return null
  },
  set(val: string | null) {
    if (val === 'motor-ac') {
      selectedTipo.value = 'motor'
      selectedTechnology.value = 'ac'
      selectedPar.value = null
    }
    else if (val === 'motor-dc') {
      selectedTipo.value = 'motor'
      selectedTechnology.value = 'dc'
      selectedPar.value = null
    }
    else if (val === 'regulador') {
      selectedTipo.value = 'regulador'
      selectedTechnology.value = null
      selectedPar.value = null
    }
    else if (val === 'otros') {
      selectedTipo.value = 'otro-repuesto'
      selectedTechnology.value = null
      selectedPar.value = null
    }
    else {
      selectedTipo.value = null
      selectedTechnology.value = null
    }
  },
})

const clearFilters = () => {
  typeModel.value = ''
  selectedTechnology.value = null
  selectedPar.value = null
  selectedPotencia.value = null
  selectedVelocidad.value = null
  selectedBrand.value = null
  selectedState.value = null
  selectedTipo.value = null
}
</script>

<template>
  <VCard
    class="store-filters-card"
    variant="flat"
  >
    <VCardText
      class="pa-4 pa-lg-5"
      :class="{ 'pt-2': !showHeader }"
    >
      <div v-if="showHeader !== false">
        <div class="d-flex align-center justify-space-between gap-3 mb-4">
          <div>
            <div class="d-flex align-center mb-1">
              <VIcon
                size="18"
                class="me-2"
                color="error"
              >
                mdi-filter-variant
              </VIcon>
              <span class="text-error font-weight-bold text-uppercase store-filters-card__eyebrow">
                Filtros
              </span>
            </div>
            <div class="text-body-2 text-medium-emphasis">
              Ajusta la busqueda desde aqui o usa la barra superior.
            </div>
          </div>

          <VBtn
            v-if="hasFiltersActive"
            variant="text"
            color="error"
            density="comfortable"
            class="px-2"
            @click="clearFilters"
          >
            Limpiar
          </VBtn>
        </div>

        <VDivider class="mb-4" color="error" />
      </div>

      <VTextField
        v-model="typeModel"
          :label="t('store.filters.type_model_label')"
          variant="outlined"
          density="comfortable"
          class="mb-5"
          :disabled="isTypeModelLocked"
        hide-details="auto"
      />

      <div class="mb-5">
        <div class="text-sm font-weight-medium text-high-emphasis mb-3">
          {{ t('store.filters.product_type') }}
        </div>

        <VRadioGroup
          v-model="customTypeModel"
          class="custom-radio-group"
          color="error"
          :disabled="isAdvancedFiltersLocked"
        >
          <VRadio
            label="Todos"
            :value="null"
            color="error"
          />
          <VRadio
            label="Motor AC"
            value="motor-ac"
            color="error"
          />
          <VRadio
            label="Motor DC"
            value="motor-dc"
            color="error"
          />
          <VRadio
            label="Regulador"
            value="regulador"
            color="error"
          />
          <VRadio
            label="Otros"
            value="otros"
            color="error"
          />
        </VRadioGroup>
      </div>

      <div class="filters-grid">
        <AppSelect
          v-model="selectedBrand"
          :label="t('store.filters.brands_label')"
          placeholder="Seleccionar marcas"
          :items="marcas"
          item-title="name"
          item-value="term_id"
          variant="outlined"
          color="error"
          clearable
          :disabled="isAdvancedFiltersLocked"
        />

        <AppSelect
          v-model="selectedPotencia"
          :label="t('store.filters.power_label')"
          placeholder="Seleccionar potencia"
          :items="potenciaOptions"
          variant="outlined"
          color="error"
          clearable
          :disabled="isAdvancedFiltersLocked"
        />

        <AppSelect
          v-if="!selectedTechnology || selectedTechnology !== 'ac'"
          v-model="selectedPar"
          :label="t('store.filters.torque_label')"
          placeholder="Seleccionar PAR (Nm)"
          :items="parOptions"
          variant="outlined"
          color="error"
          clearable
          :disabled="isAdvancedFiltersLocked"
        />

        <AppSelect
          v-model="selectedVelocidad"
          :label="t('store.filters.speed_label')"
          placeholder="Seleccionar velocidad"
          :items="velocidadOptions"
          variant="outlined"
          color="error"
          clearable
          :disabled="isAdvancedFiltersLocked"
        />

        <AppSelect
          v-model="selectedState"
          :label="t('store.filters.state_label')"
          placeholder="Seleccionar estado"
          item-title="name"
          item-value="value"
          :items="[
            { name: 'Nuevo', value: 'new' },
            { name: 'Usado', value: 'used' },
            { name: 'Reacondicionado', value: 'restored' },
          ]"
          variant="outlined"
          color="error"
          clearable
          :disabled="isAdvancedFiltersLocked"
        />
      </div>

      <div class="mt-5 text-caption text-error font-weight-bold store-filters-card__note">
        {{ t('store.filters.responsibility_note') }}
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.store-filters-card {
  width: 100%;
  border: 1px solid rgba(218, 41, 28, 0.12);
  border-radius: 24px;
  box-shadow: 0 16px 32px rgba(15, 23, 42, 0.06);
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(255, 250, 249, 0.98));
}

.store-filters-card__eyebrow {
  letter-spacing: 0.12em;
  font-size: 0.78rem;
}

.filters-grid {
  display: grid;
  gap: 0.85rem;
}

  .store-filters-card__note {
    line-height: 1.4;
  }

  .store-filters-card :deep(.v-card-text.pt-2) {
    padding-top: 0.5rem !important;
  }

@media (max-width: 959px) {
  .store-filters-card {
    border-radius: 20px;
  }
}
</style>
