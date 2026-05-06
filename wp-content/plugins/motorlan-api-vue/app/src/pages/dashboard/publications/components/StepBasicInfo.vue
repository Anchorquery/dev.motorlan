<script setup lang="ts">
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { requiredValidator } from '@/@core/utils/validators'
import AppTextField from '@/@core/components/app-form-elements/AppTextField.vue'
import AppSelect from '@/@core/components/app-form-elements/AppSelect.vue'
import { useMotorFormatter } from '@/composables/useMotorFormatter'

const OTHER_BRAND_VALUE = -1

const props = defineProps<{
  formState: any
  marcas: any[]
  categories: any[]
  tipos?: any[]
}>()

const emit = defineEmits(['update:formState'])

const { t } = useI18n()
const { getFormattedPreview } = useMotorFormatter()

// Añadir opción "Otros" al inicio de la lista de marcas
const marcasWithOther = computed(() => [
  { title: t('add_publication.post_details.brand_other', 'Otros'), value: OTHER_BRAND_VALUE },
  ...props.marcas,
])

const isCustomBrand = computed(() => props.formState.acf.marca === OTHER_BRAND_VALUE)


const localReference = computed({
    get: () => props.formState.acf.tipo_o_referencia,
    set: (val) => emit('update:formState', { ...props.formState, acf: { ...props.formState.acf, tipo_o_referencia: val } })
})

const localMarca = computed({
    get: () => props.formState.acf.marca,
    set: (val) => {
      const updates: any = { marca: val }
      // Limpiar marca_custom si se selecciona una marca existente
      if (val !== OTHER_BRAND_VALUE) {
        updates.marca_custom = ''
      }
      emit('update:formState', { ...props.formState, acf: { ...props.formState.acf, ...updates } })
    }
})

const localMarcaCustom = computed({
    get: () => props.formState.acf.marca_custom || '',
    set: (val) => emit('update:formState', { ...props.formState, acf: { ...props.formState.acf, marca_custom: val.toUpperCase() } })
})

// Validador para marca custom
const customBrandValidator = (v: any) => {
  if (!v || typeof v !== 'string' || v.trim() === '') return 'El nombre de la marca es obligatorio'
  return true
}

// Validador específico para el select de marca
const brandValidator = (v: any) => {
  if (v === null || v === undefined || v === '') return 'Selecciona una marca'
  return true
}

// Vista previa del nombre formateado
const formattedPreview = computed(() => {
    return getFormattedPreview(props.formState, props.marcas, props.tipos || [])
})

// Auto-rellenar el título con el nombre formateado (oculto al usuario)
watch(formattedPreview, (newVal) => {
    if (newVal) {
        emit('update:formState', { ...props.formState, title: newVal })
    }
}, { immediate: true })

</script>

<template>
  <VRow>
    <VCol cols="12">
        <h3 class="text-h5 font-weight-bold mb-4">{{ t('add_publication.sections.basic_info', 'Información Básica') }}</h3>
    </VCol>
    <VCol cols="12" md="6">
      <AppSelect
        v-model="localMarca"
        :label="t('add_publication.post_details.brand') + ' *'"
        :items="marcasWithOther"
        item-title="title"
        item-value="value"
        :rules="[brandValidator]"
        variant="outlined"
      />
    </VCol>
    <VCol cols="12" md="6">
      <AppTextField
        v-model="localReference"
        :label="t('add_publication.post_details.reference') + ' *'"
        :placeholder="t('add_publication.post_details.reference_placeholder')"
        :rules="[requiredValidator]"
        variant="outlined"
      />
    </VCol>
    <VCol v-if="isCustomBrand" cols="12" md="6">
      <AppTextField
        v-model="localMarcaCustom"
        :label="t('add_publication.post_details.brand_custom_label', 'Nueva marca') + ' *'"
        :placeholder="t('add_publication.post_details.brand_custom_placeholder', 'Nombre de la nueva marca')"
        :rules="[customBrandValidator]"
        variant="outlined"
        style="text-transform: uppercase;"
      />
    </VCol>
    <!-- Vista previa del nombre formateado -->
    <VCol v-if="formattedPreview" cols="12">
      <VAlert 
        type="info" 
        variant="tonal" 
        density="compact"
        class="mt-2"
      >
        <div class="d-flex align-center">
          <VIcon icon="tabler-eye" class="me-2" size="20" />
          <div>
            <div class="text-caption text-medium-emphasis">Vista previa del nombre estandarizado:</div>
            <div class="font-weight-bold">{{ formattedPreview }}</div>
          </div>
        </div>
      </VAlert>
    </VCol>
  </VRow>
</template>
