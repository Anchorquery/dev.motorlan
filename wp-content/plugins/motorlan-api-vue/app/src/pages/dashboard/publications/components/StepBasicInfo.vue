<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { requiredValidator } from '@/@core/utils/validators'
import AppTextField from '@/@core/components/app-form-elements/AppTextField.vue'
import AppSelect from '@/@core/components/app-form-elements/AppSelect.vue'

const props = defineProps<{
  formState: any
  marcas: any[]
  categories: any[]
}>()

const emit = defineEmits(['update:formState'])

const { t } = useI18n()

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
  </VRow>
</template>
