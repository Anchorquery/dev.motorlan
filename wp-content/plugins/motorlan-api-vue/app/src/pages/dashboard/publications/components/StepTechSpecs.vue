<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { requiredValidator } from '@/@core/utils/validators'
import AppTextField from '@/@core/components/app-form-elements/AppTextField.vue'
import { positiveNumberRule } from '@/composables/usePublicationForm'
import type { PublicationFormState } from '@/composables/usePublicationForm'
import { findTipoForUiSlug, uiSlugForTipo } from '@/utils/tipoMapping'

const props = defineProps<{
  formState: PublicationFormState
  tipos: { title: string; value: number; slug: string }[]
  showTypeSelector?: boolean
}>()

const emit = defineEmits(['update:formState'])

const { t } = useI18n()

const acf = computed(() => props.formState.acf)

const selectedTypeSlug = computed(() => {
    if (!props.formState.tipo || props.formState.tipo.length === 0) return null
    if (!props.tipos || props.tipos.length === 0) return null

    const selectedId = props.formState.tipo[0]
    const typeObj = props.tipos.find(t => t.value === selectedId)
    if (!typeObj) return null

    return uiSlugForTipo(typeObj.slug, acf.value.tipo_de_alimentacion)
})

const updateAcf = (key: string, value: string | number | boolean | null) => {
    emit('update:formState', {
        ...props.formState,
        acf: {
            ...props.formState.acf,
            [key]: value
        }
    })
}

const onProductTypeChange = (slug: string | null) => {
    if (!props.tipos || !slug) return

    let supplyType = acf.value.tipo_de_alimentacion || 'ac'
    if (slug === 'motor-ac') supplyType = 'ac'
    else if (slug === 'motor-dc') supplyType = 'dc'

    const typeObj = findTipoForUiSlug(props.tipos, slug)
    if (typeObj) {
        const isMotor = typeObj.slug === 'motor'
        emit('update:formState', {
            ...props.formState,
            tipo: [typeObj.value],
            acf: {
                ...props.formState.acf,
                tipo_de_alimentacion: supplyType,
                // Limpiar campos de motor si ya no es motor
                ...(!isMotor ? {
                    potencia: null,
                    velocidad: null,
                    par_nominal: null,
                    voltaje: null,
                    intensidad: null
                } : {})
            }
        })
    }
}

const isAC = computed(() => acf.value.tipo_de_alimentacion === 'ac')

// Mismas reglas que el backend (create/update-publicacion): velocidad obligatoria;
// AC exige potencia; DC exige potencia O par nominal; voltaje e intensidad opcionales.
const hasVal = (v: any) => v !== null && v !== undefined && String(v).trim() !== ''
const powerOrTorqueValidator = () => hasVal(acf.value.potencia) || hasVal(acf.value.par_nominal)
  || t('add_publication.validation.power_or_torque', 'Indica la potencia o el par nominal')
const positiveRule = positiveNumberRule(t('add_publication.validation.positive_number', 'Debe ser un número mayor que 0'))

const isCurrentMotor = computed(() => {
    const slug = selectedTypeSlug.value
    return slug === 'motor-ac' || slug === 'motor-dc'
})

</script>

<template>
  <VRow class="mx-0">
    <VCol cols="12">
        <h3 class="text-h5 font-weight-bold mb-4">{{ t('add_publication.sections.tech_specs', 'Especificaciones Técnicas') }}</h3>
    </VCol>

    <VCol v-if="showTypeSelector" cols="12">
      <VLabel class="mb-2 text-body-2 font-weight-medium">{{ t('add_publication.post_details.type') }} *</VLabel>
      <VRadioGroup
        :model-value="selectedTypeSlug"
        @update:model-value="onProductTypeChange"
        inline
        :rules="[requiredValidator]"
      >
        <VRadio
          :label="t('select_publication_type.motor_ac_title')"
          value="motor-ac"
          class="mr-4"
          color="primary"
        />
        <VRadio
          :label="t('select_publication_type.motor_dc_title')"
          value="motor-dc"
          class="mr-4"
          color="primary"
        />
        <VRadio
          :label="t('select_publication_type.regulator_title')"
          value="regulador"
          class="mr-4"
          color="primary"
        />
        <VRadio
          :label="t('select_publication_type.other_spare_part_title')"
          value="otro-repuesto"
          color="primary"
        />
      </VRadioGroup>
    </VCol>

    <VCol cols="12">
      <VDivider class="my-4" />
    </VCol>

    <!-- Motor Specifications -->
    <template v-if="isCurrentMotor">
        <VCol cols="12" md="4">
        <AppTextField
            :model-value="acf.potencia"
            @update:model-value="updateAcf('potencia', $event)"
            :label="t('add_publication.post_details.power') + (isAC ? ' *' : '')"
            type="number"
            min="0"
            :placeholder="t('add_publication.post_details.power_placeholder')"
            :rules="isAC ? [requiredValidator, positiveRule] : [powerOrTorqueValidator, positiveRule]"
            variant="outlined"
        />
        </VCol>
        <VCol cols="12" md="4">
        <AppTextField
            :model-value="acf.velocidad"
            @update:model-value="updateAcf('velocidad', $event)"
            :label="t('add_publication.post_details.speed') + ' *'"
            type="number"
            min="0"
            :placeholder="t('add_publication.post_details.speed_placeholder')"
            :rules="[requiredValidator, positiveRule]"
            variant="outlined"
        />
        </VCol>
        <VCol v-if="!isAC" cols="12" md="4">
        <AppTextField
            :model-value="acf.par_nominal"
            @update:model-value="updateAcf('par_nominal', $event)"
            :label="t('add_publication.post_details.torque')"
            type="number"
            min="0"
            :placeholder="t('add_publication.post_details.torque_placeholder')"
            :rules="[powerOrTorqueValidator, positiveRule]"
            variant="outlined"
        />
        </VCol>
        <VCol cols="12" md="6">
        <AppTextField
            :model-value="acf.voltaje"
            @update:model-value="updateAcf('voltaje', $event)"
            :label="t('add_publication.post_details.voltage')"
            type="number"
            min="0"
            :placeholder="t('add_publication.post_details.voltage_placeholder')"
            :rules="[positiveRule]"
            variant="outlined"
        />
        </VCol>
        <VCol cols="12" md="6">
        <AppTextField
            :model-value="acf.intensidad"
            @update:model-value="updateAcf('intensidad', $event)"
            :label="t('add_publication.post_details.intensity')"
            type="number"
            min="0"
            :placeholder="t('add_publication.post_details.intensity_placeholder')"
            :rules="[positiveRule]"
            variant="outlined"
        />
        </VCol>
        <VCol v-if="!isAC" cols="12" class="pt-0">
          <span class="text-caption text-medium-emphasis">
            {{ t('add_publication.validation.power_or_torque_hint', 'En motores DC indica al menos la potencia o el par nominal.') }}
          </span>
        </VCol>
    </template>
    
    <!-- Not a Motor Alert -->
    <VCol v-else cols="12">
        <VAlert type="info" variant="tonal">
            {{ t('add_publication.tech_specs_not_motor', 'No hay especificaciones técnicas especiales para este tipo de producto.') }}
        </VAlert>
    </VCol>
  </VRow>
</template>
