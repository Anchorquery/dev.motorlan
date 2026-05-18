<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/@core/stores/user'

defineProps<{
  activeFiltersCount?: number
}>()

const emit = defineEmits(['open-filters'])

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()
const { t } = useI18n()

const isLoggedIn = computed(() => userStore.getIsLoggedIn)

const login = () => {
  router.push({
    name: 'login',
    query: { to: route.fullPath !== '/' ? route.fullPath : undefined },
  })
}
const goDashboard = () => router.push({ name: 'dashboard-purchases-purchases' })
const goProfile = () => router.push({ name: 'dashboard-user-profile' })
const addPublication = () => router.push('/dashboard/publications/publication/add')
</script>

<template>
  <Teleport to="body">
    <nav class="snav d-md-none" :aria-label="t('store.mobile_nav.aria_label')">
      <!-- ── No autenticado: 2 ítems ──────────────────────────────── -->
      <div v-if="!isLoggedIn" class="snav__bar snav__bar--2col">
        <button class="snav__item" type="button" @click="emit('open-filters')">
          <span class="snav__icon">
            <VBadge v-if="activeFiltersCount" :content="activeFiltersCount" color="error" floating>
              <VIcon icon="tabler-adjustments-alt" size="22" />
            </VBadge>
            <VIcon v-else icon="tabler-adjustments-alt" size="22" />
          </span>
          <span class="snav__label">{{ t('store.mobile_nav.filters') }}</span>
        </button>

        <span class="snav__sep" />

        <button class="snav__item" type="button" @click="login">
          <span class="snav__icon">
            <VIcon icon="tabler-login" size="22" />
          </span>
          <span class="snav__label">{{ t('store.mobile_nav.login') }}</span>
        </button>
      </div>

      <!-- ── Autenticado: 4 ítems iguales ──────────────────────────── -->
      <div v-else class="snav__bar snav__bar--4col">
        <button class="snav__item" type="button" @click="emit('open-filters')">
          <span class="snav__icon">
            <VBadge v-if="activeFiltersCount" :content="activeFiltersCount" color="error" floating>
              <VIcon icon="tabler-adjustments-alt" size="22" />
            </VBadge>
            <VIcon v-else icon="tabler-adjustments-alt" size="22" />
          </span>
          <span class="snav__label">{{ t('store.mobile_nav.filters') }}</span>
        </button>

        <button class="snav__item" type="button" @click="goDashboard">
          <span class="snav__icon">
            <VIcon icon="tabler-layout-dashboard" size="22" />
          </span>
          <span class="snav__label">{{ t('store.mobile_nav.dashboard') }}</span>
        </button>

        <button class="snav__item" type="button" :aria-label="t('store.mobile_nav.add_publication')" @click="addPublication">
          <span class="snav__icon">
            <VIcon icon="tabler-plus" size="22" />
          </span>
          <span class="snav__label">{{ t('store.mobile_nav.publish') }}</span>
        </button>

        <button class="snav__item" type="button" @click="goProfile">
          <span class="snav__icon">
            <VIcon icon="tabler-user" size="22" />
          </span>
          <span class="snav__label">{{ t('store.mobile_nav.profile') }}</span>
        </button>
      </div>

      <!-- Safe area iOS -->
      <div class="snav__safe-area" />
    </nav>
  </Teleport>
</template>

<style scoped>
.snav {
  position: fixed;
  inset-inline: 0;
  bottom: 0;
  z-index: 200;
}

.snav__safe-area {
  height: env(safe-area-inset-bottom, 0px);
  background: rgba(255, 255, 255, 0.99);
}

.snav__bar {
  background: rgba(255, 255, 255, 0.98);
  border-top: 1px solid rgba(218, 41, 28, 0.15);
  border-radius: 18px 18px 0 0;
  box-shadow: 0 -4px 20px rgba(15, 23, 42, 0.1);
  min-height: 58px;
}

.snav__bar--2col {
  display: flex;
  align-items: stretch;
}

.snav__bar--4col {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  align-items: stretch;
}

.snav__sep {
  display: block;
  width: 1px;
  background: rgba(0, 0, 0, 0.08);
  margin-block: 12px;
  flex-shrink: 0;
}

.snav__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 3px;
  padding: 8px 4px;
  border: none;
  background: transparent;
  cursor: pointer;
  color: rgba(0, 0, 0, 0.5);
  -webkit-tap-highlight-color: transparent;
  touch-action: manipulation;
  transition: background 0.12s, color 0.12s;
  min-width: 0;
  width: 100%;
}

.snav__item:active {
  background: rgba(0, 0, 0, 0.07);
}

.snav__bar--2col .snav__item {
  flex: 1;
}

.snav__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 28px;
}

.snav__label {
  font-size: 0.59rem;
  font-weight: 700;
  line-height: 1;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  white-space: nowrap;
}

@media (min-width: 960px) {
  .snav {
    display: none !important;
  }
}

@media (max-width: 360px) {
  .snav__label {
    font-size: 0.52rem;
  }
}
</style>
