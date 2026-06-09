<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/@core/stores/user'
import { useI18n } from 'vue-i18n'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const { t } = useI18n()

const isAdmin = computed(() => userStore.isAdmin)

// Focused detail/form pages — these have their own action buttons, hide nav
const HIDE_NAV_PREFIXES = [
  '/dashboard/admin/approvals/preview',
  '/dashboard/publications/sales/', // individual sale detail (not the list)
  '/dashboard/publications/publication/questions/',
]

// purchases/[uuid].vue — uuid pattern, not a named sub-route
const PURCHASES_LIST_SLUGS = ['purchases', 'inquiries', 'questions', 'opinions', 'favorites', 'offers-sent', 'chat', 'list']

const shouldHide = computed(() => {
  const p = route.path
  if (HIDE_NAV_PREFIXES.some(prefix => p.startsWith(prefix))) return true
  // purchases/[uuid] — single purchase detail page
  const purchaseDetailMatch = p.match(/^\/dashboard\/purchases\/([^/]+)$/)
  if (purchaseDetailMatch && !PURCHASES_LIST_SLUGS.includes(purchaseDetailMatch[1])) return true
  return false
})

const isActive = (paths: string[]) =>
  paths.some(p => route.path.startsWith(p))

const adminItems = computed(() => [
  {
    icon: 'tabler-list-check',
    label: t('dashboard_mobile_nav.admin_pubs', 'Anuncios'),
    paths: ['/dashboard/admin/publications'],
    action: () => router.push('/dashboard/admin/publications'),
  },
  {
    icon: 'tabler-shield-check',
    label: t('dashboard_mobile_nav.approvals', 'Aprobar'),
    paths: ['/dashboard/admin/approvals'],
    action: () => router.push('/dashboard/admin/approvals'),
  },
  {
    icon: 'tabler-circle-plus',
    label: t('dashboard_mobile_nav.publish', 'Publicar'),
    paths: ['/dashboard/publications/publication/add'],
    action: () => router.push('/dashboard/publications/publication/add'),
    isPrimary: true,
  },
  {
    icon: 'tabler-currency-euro',
    label: t('dashboard_mobile_nav.sales', 'Ventas'),
    paths: ['/dashboard/publications/sales'],
    action: () => router.push('/dashboard/publications/sales'),
  },
  {
    icon: 'tabler-user-circle',
    label: t('dashboard_mobile_nav.profile', 'Perfil'),
    paths: ['/dashboard/user'],
    action: () => router.push('/dashboard/user/account'),
  },
])

const userItems = computed(() => [
  {
    icon: 'tabler-list',
    label: t('dashboard_mobile_nav.my_pubs', 'Anuncios'),
    paths: ['/dashboard/publications/publication/list', '/dashboard/publications/publication/edit'],
    action: () => router.push('/dashboard/publications/publication/list'),
  },
  {
    icon: 'tabler-shopping-bag',
    label: t('dashboard_mobile_nav.purchases', 'Compras'),
    paths: ['/dashboard/purchases/purchases', '/dashboard/purchases/list', '/dashboard/purchases/favorites', '/dashboard/purchases/opinions', '/dashboard/purchases/questions', '/dashboard/purchases/offers-sent'],
    action: () => router.push('/dashboard/purchases/purchases'),
  },
  {
    icon: 'tabler-circle-plus',
    label: t('dashboard_mobile_nav.publish', 'Publicar'),
    paths: ['/dashboard/publications/publication/add'],
    action: () => router.push('/dashboard/publications/publication/add'),
    isPrimary: true,
  },
  {
    icon: 'tabler-message-share',
    label: t('dashboard_mobile_nav.inquiries', 'Consultas'),
    paths: ['/dashboard/purchases/inquiries', '/dashboard/inquiries'],
    action: () => router.push('/dashboard/purchases/inquiries'),
  },
  {
    icon: 'tabler-user-circle',
    label: t('dashboard_mobile_nav.profile', 'Perfil'),
    paths: ['/dashboard/user'],
    action: () => router.push('/dashboard/user/account'),
  },
])

const navItems = computed(() => isAdmin.value ? adminItems.value : userItems.value)
</script>

<template>
  <Teleport to="body">
    <nav v-if="!shouldHide" class="dnav d-md-none" aria-label="Dashboard navigation">
      <div class="dnav__bar">
        <button
          v-for="item in navItems"
          :key="item.icon"
          class="dnav__item"
          :class="{
            'dnav__item--active': isActive(item.paths),
            'dnav__item--primary': item.isPrimary,
          }"
          type="button"
          @click="item.action"
        >
          <span class="dnav__icon-wrap" :class="{ 'dnav__icon-wrap--primary': item.isPrimary }">
            <VIcon :icon="item.icon" :size="item.isPrimary ? 26 : 22" />
          </span>
          <span class="dnav__label">{{ item.label }}</span>
        </button>
      </div>
      <div class="dnav__safe-area" />
    </nav>
  </Teleport>
</template>

<style scoped>
.dnav {
  position: fixed;
  inset-inline: 0;
  bottom: 0;
  z-index: 200;
}

.dnav__safe-area {
  height: env(safe-area-inset-bottom, 0px);
  background: rgba(255, 255, 255, 0.99);
}

.dnav__bar {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  align-items: stretch;
  background: rgba(255, 255, 255, 0.98);
  border-top: 1px solid rgba(var(--v-theme-primary), 0.15);
  border-radius: 18px 18px 0 0;
  box-shadow: 0 -4px 20px rgba(15, 23, 42, 0.1);
  min-height: 58px;
}

.dnav__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 3px;
  padding: 8px 4px;
  border: none;
  background: transparent;
  cursor: pointer;
  color: rgba(0, 0, 0, 0.45);
  -webkit-tap-highlight-color: transparent;
  touch-action: manipulation;
  transition: color 0.12s, background 0.12s;
  min-width: 0;
  width: 100%;
  position: relative;
}

.dnav__item:active {
  background: rgba(0, 0, 0, 0.06);
}

.dnav__item--active {
  color: rgb(var(--v-theme-primary));
}

.dnav__item--primary {
  color: rgb(var(--v-theme-primary));
}

.dnav__icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  transition: background 0.15s, transform 0.15s;
}

.dnav__icon-wrap--primary {
  background: rgb(var(--v-theme-primary));
  color: #fff;
  width: 44px;
  height: 44px;
  margin-top: -10px;
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.4);
}

.dnav__item--primary .dnav__icon-wrap--primary {
  color: #fff;
}

.dnav__item:active .dnav__icon-wrap--primary {
  transform: scale(0.93);
}

.dnav__item--active .dnav__icon-wrap:not(.dnav__icon-wrap--primary) {
  background: rgba(var(--v-theme-primary), 0.1);
}

.dnav__label {
  font-size: 0.6rem;
  font-weight: 600;
  line-height: 1.1;
  letter-spacing: 0;
  white-space: normal;
  text-align: center;
  max-width: 100%;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  margin-top: 2px;
}

.dnav__item--primary .dnav__label {
  color: rgba(0, 0, 0, 0.5);
}

@media (min-width: 960px) {
  .dnav {
    display: none !important;
  }
}

@media (max-width: 360px) {
  .dnav__label {
    font-size: 0.55rem;
  }

  .dnav__icon-wrap--primary {
    width: 38px;
    height: 38px;
  }
}
</style>
