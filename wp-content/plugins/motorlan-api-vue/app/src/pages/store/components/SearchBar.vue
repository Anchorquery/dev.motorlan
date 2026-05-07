<script setup lang="ts">
defineProps<{
  loading: boolean
  activeFiltersCount?: number
}>()

const emit = defineEmits(['search', 'reset'])
const searchTerm = defineModel<string>('searchTerm')
const onSearch = () => emit('search')
</script>

<template>
  <section class="store-navbar mb-6">
    <div class="store-navbar__hero">
      <div class="text-overline text-error font-weight-bold mb-1">
        Tienda Motorlan
      </div>
      <h1 class="text-h4 text-md-h3 font-weight-bold mb-2 text-primary">
        Motores, reguladores, repuestos y más.
      </h1>
    </div>

    <div class="store-navbar__controls">
      <AppTextField
        v-model="searchTerm"
        placeholder="Buscar..."
        hide-details
        class="flex-grow-1"
        @keydown.enter="onSearch"
      />

      <VBtn
        color="error"
        class="store-navbar__btn"
        :loading="loading"
        @click="onSearch"
      >
        BUSCAR
      </VBtn>

      <VBtn
        v-if="activeFiltersCount"
        variant="text"
        color="error"
        class="store-navbar__btn"
        @click="emit('reset')"
      >
        Limpiar
      </VBtn>
    </div>
  </section>
</template>

<style>
.store-navbar {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  padding: 1rem 1rem 1.25rem;
  margin-inline: -0.25rem;
  border-radius: 24px;
  background:
    radial-gradient(circle at top left, rgba(218, 41, 28, 0.12), transparent 32%),
    linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(255, 248, 247, 0.96));
  border: 1px solid rgba(218, 41, 28, 0.12);
  box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
}

.store-navbar__hero {
  min-width: 280px;
  max-width: 700px;
}

.store-navbar__subtitle {
  max-width: 62ch;
}

.store-navbar__controls {
  display: flex;
  align-items: center;
  width: 100%;
  gap: 12px;
  flex-wrap: wrap;
}

.store-navbar__btn {
  min-height: 44px;
}

@media (min-width: 960px) {
  .store-navbar__controls {
    flex: 1 1 460px;
    justify-content: flex-end;
    width: auto;
  }

  .store-navbar__controls > *:first-child {
    flex: 1 1 320px;
  }
}

@media (max-width: 959px) {
  .store-navbar {
    padding: 1rem;
  }

  .store-navbar__hero {
    max-width: none;
  }
}
</style>
