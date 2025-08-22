<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()

const postTypes = [
  {
    title: 'Motor',
    value: 'motor',
    description: 'Publica un motor eléctrico, con todos sus detalles técnicos como potencia, velocidad, etc.',
    icon: 'mdi-engine-outline',
  },
  {
    title: 'Regulador',
    value: 'regulador',
    description: 'Publica un regulador o driver, con sus especificaciones técnicas.',
    icon: 'mdi-cog-outline',
  },
  {
    title: 'Otro Repuesto',
    value: 'otro_repuesto',
    description: 'Publica cualquier otro tipo de repuesto o componente relacionado.',
    icon: 'mdi-cogs',
  },
]

const selectPostType = (type: string) => {
  router.push({ path: '/apps/motors/motor/add', query: { type } })
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
          Selecciona el Tipo de Publicación
        </h4>
        <p class="text-body-1">
          Elige qué tipo de producto quieres añadir a la tienda.
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
