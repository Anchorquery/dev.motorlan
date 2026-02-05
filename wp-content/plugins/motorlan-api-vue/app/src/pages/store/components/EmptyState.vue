<script setup lang="ts">
import { useRouter } from 'vue-router'

interface Props {
  icon?: string
  title?: string
  message?: string
  actionLabel?: string
  actionLink?: string | object
}

const props = withDefaults(defineProps<Props>(), {
  icon: 'tabler-search-off',
  title: 'Publicación no encontrada',
  message: 'Lo sentimos, no hemos podido encontrar la publicación que buscas. Es posible que el enlace sea incorrecto o que la publicación haya sido eliminada.',
  actionLabel: 'Volver a la tienda',
  actionLink: '/store'
})

const router = useRouter()

const handleAction = () => {
  if (typeof props.actionLink === 'string' && props.actionLink.startsWith('http')) {
    window.location.href = props.actionLink
  } else {
    router.push(props.actionLink)
  }
}
</script>

<template>
  <div class="empty-state-container pa-12">
    <VCard variant="flat" class="empty-state-card mx-auto text-center pa-8 glass-effect">
      <div class="icon-wrapper mb-6 mx-auto">
        <VIcon
          :icon="props.icon"
          size="64"
          color="error"
          class="empty-state-icon"
        />
      </div>

      <h2 class="text-h4 font-weight-bold mb-3 text-high-emphasis">
        {{ props.title }}
      </h2>

      <p class="text-body-1 text-medium-emphasis mb-8 mx-auto message-text">
        {{ props.message }}
      </p>

      <div class="d-flex justify-center gap-4">
        <VBtn
          v-if="props.actionLabel"
          color="error"
          size="large"
          prepend-icon="tabler-arrow-left"
          @click="handleAction"
          class="action-btn"
        >
          {{ props.actionLabel }}
        </VBtn>
      </div>
    </VCard>
  </div>
</template>

<style scoped>
.empty-state-container {
  min-height: 400px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-state-card {
  max-width: 600px;
  border-radius: 20px;
  border: 1px solid rgba(var(--v-border-color), 0.12);
  background: transparent;
}

.glass-effect {
  background: rgba(var(--v-theme-surface), 0.7) !important;
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}

.icon-wrapper {
  width: 120px;
  height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(var(--v-theme-error), 0.08);
  border-radius: 50%;
  animation: float 3s ease-in-out infinite;
}

.empty-state-icon {
  filter: drop-shadow(0 4px 8px rgba(var(--v-theme-error), 0.3));
}

.message-text {
  max-width: 450px;
  line-height: 1.6;
}

.action-btn {
  text-transform: none;
  font-weight: 600;
  letter-spacing: 0.5px;
  transition: transform 0.2s ease;
}

.action-btn:hover {
  transform: translateY(-2px);
}

@keyframes float {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-10px);
  }
}

/* Responsive adjustments */
@media (max-width: 600px) {
  .empty-state-container {
    padding: 24px 16px;
  }
  
  .text-h4 {
    font-size: 1.5rem !important;
  }
  
  .icon-wrapper {
    width: 90px;
    height: 90px;
  }
  
  .empty-state-icon {
    font-size: 48px !important;
  }

  .d-flex {
    flex-direction: column;
  }
  
  .action-btn {
    width: 100%;
  }
}
</style>
