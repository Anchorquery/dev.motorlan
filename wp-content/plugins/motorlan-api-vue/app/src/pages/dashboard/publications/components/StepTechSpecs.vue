<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { requiredValidator } from '@/@core/utils/validators'
import AppTextField from '@/@core/components/app-form-elements/AppTextField.vue'

const props = defineProps<{
  formState: any
  isMotor: boolean
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

</script>

<template>
  <VRow v-if="isMotor">
    <VCol cols="12">
        <h3 class="text-h5 font-weight-bold mb-4">{{ t('add_publication.sections.tech_specs', 'Especificaciones Técnicas') }}</h3>
    </VCol>
    <VCol cols="12" md="4">
      <AppTextField
        :model-value="acf.potencia"
        @update:model-value="updateAcf('potencia', $event)"
        :label="t('add_publication.post_details.power') + ' *'"
        type="number"
        :placeholder="t('add_publication.post_details.power_placeholder')"
        :rules="[requiredValidator]"
        variant="outlined"
      />
    </VCol>
    <VCol cols="12" md="4">
      <AppTextField
        :model-value="acf.velocidad"
         @update:model-value="updateAcf('velocidad', $event)"
        :label="t('add_publication.post_details.speed') + ' *'"
        type="number"
        :placeholder="t('add_publication.post_details.speed_placeholder')"
         :rules="[requiredValidator]"
         variant="outlined"
      />
    </VCol>
    <VCol cols="12" md="4">
      <AppTextField
        :model-value="acf.par_nominal"
         @update:model-value="updateAcf('par_nominal', $event)"
        :label="t('add_publication.post_details.torque') + ' *'"
        type="number"
        :placeholder="t('add_publication.post_details.torque_placeholder')"
         :rules="[requiredValidator]"
         variant="outlined"
      />
    </VCol>
    <VCol cols="12" md="6">
      <AppTextField
         :model-value="acf.voltaje"
         @update:model-value="updateAcf('voltaje', $event)"
        :label="t('add_publication.post_details.voltage') + ' *'"
        type="number"
        :placeholder="t('add_publication.post_details.voltage_placeholder')"
         :rules="[requiredValidator]"
         variant="outlined"
      />
    </VCol>
    <VCol cols="12" md="6">
      <AppTextField
        :model-value="acf.intensidad"
         @update:model-value="updateAcf('intensidad', $event)"
        :label="t('add_publication.post_details.intensity') + ' *'"
        type="number"
        :placeholder="t('add_publication.post_details.intensity_placeholder')"
         :rules="[requiredValidator]"
         variant="outlined"
      />
    </VCol>
    
    <VCol cols="12">
      <VDivider class="my-4" />
    </VCol>

    <VCol cols="12" md="6">
      <VLabel class="mb-2 text-body-2 font-weight-medium">{{ t('add_publication.post_details.power_supply_type') }} *</VLabel>
      <VRadioGroup
        :model-value="acf.tipo_de_alimentacion"
         @update:model-value="updateAcf('tipo_de_alimentacion', $event)"
        inline
        :rules="[requiredValidator]"
      >
        <VRadio
          :label="t('add_publication.power_supply_options.dc')"
          value="dc"
          class="mr-4"
        />
        <VRadio
          :label="t('add_publication.power_supply_options.ac')"
          value="ac"
        />
      </VRadioGroup>
    </VCol>

    <VCol cols="12" md="6">
      <div class="d-flex flex-column gap-2">
        <VCheckbox
          :model-value="acf.servomotores"
           @update:model-value="updateAcf('servomotores', $event)"
          :label="t('add_publication.post_details.servomotors')"
          class="mt-0"
          hide-details
        />
        <VCheckbox
          :model-value="acf.regulacion_electronica_drivers"
           @update:model-value="updateAcf('regulacion_electronica_drivers', $event)"
          :label="t('add_publication.post_details.electronic_regulation')"
          class="mt-0"
          hide-details
        />
      </div>
    </VCol>
  </VRow>
  <VRow v-else>
      <VCol cols="12">
           <VAlert type="info" variant="tonal">
               {{ t('add_publication.tech_specs_not_motor', 'No hay especificaciones técnicas especiales para este tipo de publicación.') }}
           </VAlert>
      </VCol>
  </VRow>
</template>
