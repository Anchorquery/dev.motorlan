<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { requiredValidator } from '@/@core/utils/validators'
import AppTextField from '@/@core/components/app-form-elements/AppTextField.vue'
import AppSelect from '@/@core/components/app-form-elements/AppSelect.vue'
import { useMotorFormatter } from '@/composables/useMotorFormatter'

const props = defineProps<{
  formState: any
  marcas: any[]
  categories: any[]
  tipos?: any[]
}>()

const emit = defineEmits(['update:formState'])

const { t } = useI18n()
const { getFormattedPreview } = useMotorFormatter()

// Computed wrappers for v-model to avoid direct prop mutation warnings if strict
const localTitle = computed({
    get: () => props.formState.title,
    set: (val) => emit('update:formState', { ...props.formState, title: val })
})

const localReference = computed({
    get: () => props.formState.acf.tipo_o_referencia,
    set: (val) => emit('update:formState', { ...props.formState, acf: { ...props.formState.acf, tipo_o_referencia: val } })
})

const localMarca = computed({
    get: () => props.formState.acf.marca,
    set: (val) => emit('update:formState', { ...props.formState, acf: { ...props.formState.acf, marca: val } })
})

const localCategories = computed({
    get: () => props.formState.categories,
    set: (val) => emit('update:formState', { ...props.formState, categories: val })
})

// Vista previa del nombre formateado
const formattedPreview = computed(() => {
    return getFormattedPreview(props.formState, props.marcas, props.tipos || [])
})

</script>

<template>
  <VRow>
    <VCol cols="12">
        <h3 class="text-h5 font-weight-bold mb-4">{{ t('add_publication.sections.basic_info', 'Información Básica') }}</h3>
    </VCol>
    <VCol cols="12">
      <AppTextField
        v-model="localTitle"
        :label="t('add_publication.post_details.title') + ' *'"
        :placeholder="t('add_publication.post_details.title_placeholder')"
        :rules="[requiredValidator]"
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
    <VCol cols="12" md="6">
      <AppSelect
        v-model="localMarca"
        :label="t('add_publication.post_details.brand') + ' *'"
        :items="marcas"
        item-title="title"
        item-value="value"
        :rules="[requiredValidator]"
        variant="outlined"
      />
    </VCol>
    <VCol cols="12">
      <AppSelect
        v-model="localCategories"
        :label="t('add_publication.post_details.category') + ' *'"
        :items="categories"
        multiple
        chips
        closable-chips
        :rules="[requiredValidator]"
        variant="outlined"
      />
    </VCol>
    
    <!-- Vista previa del nombre formateado -->
    <VCol v-if="formattedPreview && formattedPreview !== formState.title" cols="12">
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
