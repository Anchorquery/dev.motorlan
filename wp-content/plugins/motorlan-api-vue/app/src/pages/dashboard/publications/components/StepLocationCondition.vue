<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { requiredValidator } from '@/@core/utils/validators'
import AppTextField from '@/@core/components/app-form-elements/AppTextField.vue'
import AppSelect from '@/@core/components/app-form-elements/AppSelect.vue'
import AppAutocomplete from '@/@core/components/app-form-elements/AppAutocomplete.vue'
import AppTextarea from '@/@core/components/app-form-elements/AppTextarea.vue'

const props = defineProps<{
  formState: any
  countryOptions?: { title: string; value: string }[]
}>()

const emit = defineEmits(['update:formState'])

const { t } = useI18n()

const updateAcf = (key: string, value: any) => {
    emit('update:formState', {
        ...props.formState,
        acf: {
            ...props.formState.acf,
            [key]: value
        }
    })
}

const acf = computed(() => props.formState.acf)

const conditionOptions = computed(() => [
  { title: t('add_publication.condition_options.new'), value: 'new' },
  { title: t('add_publication.condition_options.used'), value: 'used' },
  { title: t('add_publication.condition_options.restored'), value: 'restored' },
])

const defaultCountryOptions = computed(() => [
  { title: t('add_publication.country_options.spain'), value: 'es' },
  { title: t('add_publication.country_options.portugal'), value: 'pt' },
  { title: t('add_publication.country_options.france'), value: 'fr' },
])

const resolvedCountryOptions = computed(() => props.countryOptions && props.countryOptions.length > 0 ? props.countryOptions : defaultCountryOptions.value)

</script>

<template>
  <VRow>
     <VCol cols="12">
        <h3 class="text-h5 font-weight-bold mb-4">{{ t('add_publication.sections.description', 'Descripción y Estado') }}</h3>
    </VCol>
    
    <VCol cols="12">
       <AppTextarea
        :model-value="acf.descripcion"
        @update:model-value="updateAcf('descripcion', $event)"
        :label="t('add_publication.post_details.description') + ' *'"
        :placeholder="t('add_publication.post_details.description_placeholder')"
        :rules="[requiredValidator]"
        auto-grow
        rows="4"
        variant="outlined"
      />
    </VCol>

    <VCol cols="12">
      <VDivider class="my-4" />
      <h3 class="text-h6 font-weight-bold mb-4">{{ t('add_publication.sections.location_condition', 'Estado y Ubicación') }}</h3>
    </VCol>

    <VCol cols="12" md="4">
      <AppSelect
        :model-value="acf.estado_del_articulo"
        @update:model-value="updateAcf('estado_del_articulo', $event)"
        :label="t('add_publication.post_details.condition') + ' *'"
        :items="conditionOptions"
        item-title="title"
        item-value="value"
        :rules="[requiredValidator]"
        variant="outlined"
      />
    </VCol>

    <VCol cols="12" md="4">
      <AppAutocomplete
        :model-value="acf.pais"
        @update:model-value="updateAcf('pais', $event)"
        :label="t('add_publication.post_details.country') + ' *'"
        :items="resolvedCountryOptions"
        item-title="title"
        item-value="value"
        :placeholder="t('add_publication.post_details.country_placeholder', 'Buscar país...')"
        :rules="[requiredValidator]"
        variant="outlined"
        :no-data-text="t('add_publication.post_details.no_country_found', 'No se encontró el país')"
        auto-select-first
      />
    </VCol>

    <VCol cols="12" md="4">
      <AppTextField
        :model-value="acf.provincia"
        @update:model-value="updateAcf('provincia', $event)"
        :label="t('add_publication.post_details.province') + ' *'"
        :placeholder="t('add_publication.post_details.province_placeholder')"
        :rules="[requiredValidator]"
        variant="outlined"
      />
    </VCol>

    <VCol cols="12" md="4">
        <AppTextField
            :model-value="acf.precio_de_venta"
            @update:model-value="updateAcf('precio_de_venta', $event)"
            :label="t('add_publication.post_details.price')"
            type="number"
            prefix="€"
            variant="outlined"
        />
    </VCol>

    <VCol cols="12" md="4" class="d-flex flex-column justify-center gap-2">
         <VCheckbox
          :model-value="acf.precio_negociable === 'yes'"
          @update:model-value="(val) => updateAcf('precio_negociable', val ? 'yes' : 'no')"
          :label="t('add_publication.post_details.consult_price', 'Consultar precio')"
          hide-details
        />
        <p class="text-caption text-medium-emphasis mt-1 ml-10">
          {{ t('add_publication.post_details.consult_price_hint', 'Si se marca, el público verá "Consultar precio". Tú seguirás viendo el precio como referencia.') }}
        </p>
    </VCol>
    
     <VCol cols="12" md="4">
        <AppTextField
            :model-value="acf.stock"
            @update:model-value="updateAcf('stock', $event)"
            :label="t('add_publication.post_details.stock') + ' *'"
            type="number"
            :rules="[requiredValidator, (v: any) => v > 0 || t('add_publication.validation.stock_positive', 'El stock debe ser mayor a 0')]"
             variant="outlined"
        />
    </VCol>

  </VRow>
</template>
