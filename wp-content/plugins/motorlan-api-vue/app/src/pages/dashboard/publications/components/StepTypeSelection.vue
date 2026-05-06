<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  modelValue: string | null
}>()

const emit = defineEmits(['update:modelValue', 'next'])

const { t } = useI18n()

const postTypes = computed(() => [
  {
    title: t('select_publication_type.motor_title'),
    slug: 'motor',
    description: t('select_publication_type.motor_description'),
    icon: 'mdi-engine-outline',
  },
  {
    title: t('select_publication_type.regulator_title'),
    slug: 'regulador',
    description: t('select_publication_type.regulator_description'),
    icon: 'mdi-cog-outline',
  },
  {
    title: t('select_publication_type.other_spare_part_title'),
    slug: 'otro-repuesto',
    description: t('select_publication_type.other_spare_part_description'),
    icon: 'mdi-cogs',
  },
])

const selectType = (slug: string) => {
  emit('update:modelValue', slug)
  emit('next')
}
</script>

<template>
  <div class="step-type-selection">
    <div class="text-center mb-8">
      <h4 class="text-h4 font-weight-bold mb-2 text-primary">
        {{ t('select_publication_type.title') }}
      </h4>
      <p class="text-body-1 text-medium-emphasis">
        {{ t('select_publication_type.subtitle') }}
      </p>
    </div>

    <VRow class="justify-center">
      <VCol
        v-for="type in postTypes"
        :key="type.slug"
        cols="12"
        md="4"
        lg="3"
      >
        <VCard
          class="type-card h-100 d-flex flex-column align-center text-center px-4 py-6"
          :class="{ 'selected': modelValue === type.slug }"
          elevation="2"
          @click="selectType(type.slug)"
        >
            <div class="icon-wrapper mb-4 rounded-circle d-flex align-center justify-center bg-primary-lighten-5 text-primary">
                <VIcon :icon="type.icon" size="48" />
            </div>
            
            <h5 class="text-h5 font-weight-bold mb-2">
              {{ type.title }}
            </h5>
            
            <p class="text-body-2 text-medium-emphasis mb-0">
              {{ type.description }}
            </p>

            <div v-if="modelValue === type.slug" class="selection-indicator mt-4">
                <VIcon icon="tabler-check-circle-filled" color="primary" size="24" />
            </div>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style lang="scss" scoped>
.type-card {
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  border: 2px solid transparent;
  
  &:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 30px rgba(var(--v-theme-primary), 0.15) !important;
    border-color: rgba(var(--v-theme-primary), 0.5);
  }

  &.selected {
    border-color: rgb(var(--v-theme-primary));
    background-color: rgb(var(--v-theme-primary), 0.02);
  }
}

.icon-wrapper {
    width: 90px;
    height: 90px;
    transition: all 0.3s ease;
}

.type-card:hover .icon-wrapper {
    transform: scale(1.1);
}
</style>
