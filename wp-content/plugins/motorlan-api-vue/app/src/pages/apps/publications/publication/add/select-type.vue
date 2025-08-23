<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const router = useRouter()
const { t } = useI18n()

const postTypes = computed(() => [
  {
    title: t('select_publication_type.motor_title'),
    value: 'motor',
    description: t('select_publication_type.motor_description'),
    icon: 'mdi-engine-outline',
  },
  {
    title: t('select_publication_type.regulator_title'),
    value: 'regulador',
    description: t('select_publication_type.regulator_description'),
    icon: 'mdi-cog-outline',
  },
  {
    title: t('select_publication_type.other_spare_part_title'),
    value: 'otro_repuesto',
    description: t('select_publication_type.other_spare_part_description'),
    icon: 'mdi-cogs',
  },
])

const selectPostType = (type: string) => {
  router.push({ path: '/apps/publications/publication/add', query: { type } })
}
</script>

<template>
  <div>
    <VRow>
      <VCol
        cols="12"
        class="text-center"
      >
        <h4 class="text-h4 font-weight-medium mb-2">
          {{ t('select_publication_type.title') }}
        </h4>
        <p class="text-body-1">
          {{ t('select_publication_type.subtitle') }}
        </p>
      </VCol>
    </VRow>

    <VRow>
      <VCol
        v-for="type in postTypes"
        :key="type.value"
        cols="12"
        md="4"
      >
        <VCard
          class="d-flex flex-column align-center text-center"
          @click="selectPostType(type.value)"
        >
          <VCardText>
            <VIcon
              :icon="type.icon"
              size="60"
              class="mb-4"
            />
            <h5 class="text-h5 font-weight-medium">
              {{ type.title }}
            </h5>
            <p class="text-body-1 mt-2">
              {{ type.description }}
            </p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style lang="scss" scoped>
.v-card {
  cursor: pointer;
  transition: all 0.3s ease-in-out;

  &:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
  }
}
</style>
