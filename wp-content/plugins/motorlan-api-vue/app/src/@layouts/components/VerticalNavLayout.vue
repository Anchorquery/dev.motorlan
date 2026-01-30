<script lang="ts" setup>
import { VerticalNav } from '@layouts/components'
import { useLayoutConfigStore } from '@layouts/stores/config'
import type { VerticalNavItems } from '@layouts/types'
import { useRouter } from 'vue-router'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const { isToastVisible, toastMessage, toastColor } = useToast()

const logout = () => {
  // Remove "userData" from cookie
  useCookie('userData').value = null
  useCookie('accessToken').value = null
  useCookie('userAbilityRules').value = null

  // Redirect to login page
  router.push('/login')
}
interface Props {
  navItems: VerticalNavItems
  verticalNavAttrs?: {
    wrapper?: string
    wrapperProps?: Record<string, unknown>
  }
}

const props = withDefaults(defineProps<Props>(), {
  verticalNavAttrs: () => ({}),
})

const { width: windowWidth } = useWindowSize()
const configStore = useLayoutConfigStore()

const isOverlayNavActive = ref(false)
const isLayoutOverlayVisible = ref(false)
const toggleIsOverlayNavActive = useToggle(isOverlayNavActive)

// ℹ️ This is alternative to below two commented watcher
// We want to show overlay if overlay nav is visible and want to hide overlay if overlay is hidden and vice versa.
syncRef(isOverlayNavActive, isLayoutOverlayVisible)

// 👉 Lock scroll when overlay nav is active
watch(isOverlayNavActive, val => {
  if (val)
    document.body.classList.add('layout-vertical-nav-overlay-active')
  else
    document.body.classList.remove('layout-vertical-nav-overlay-active')
})

// watch(isOverlayNavActive, value => {
//   // Sync layout overlay with overlay nav
//   isLayoutOverlayVisible.value = value
// })

// watch(isLayoutOverlayVisible, value => {
//   // If overlay is closed via click, close hide overlay nav
//   if (!value) isOverlayNavActive.value = false
// })

// ℹ️ Hide overlay if user open overlay nav in <md and increase the window width without closing overlay nav
watch(windowWidth, () => {
  if (!configStore.isLessThanOverlayNavBreakpoint && isLayoutOverlayVisible.value)
    isLayoutOverlayVisible.value = false
})

const verticalNavAttrs = computed(() => {
  const vNavAttrs = toRef(props, 'verticalNavAttrs')

  const { wrapper: verticalNavWrapper, wrapperProps: verticalNavWrapperProps, ...additionalVerticalNavAttrs } = vNavAttrs.value

  return {
    verticalNavWrapper,
    verticalNavWrapperProps,
    additionalVerticalNavAttrs,
  }
})
</script>

<template>
  <div
    class="layout-wrapper"
    data-allow-mismatch
    :class="configStore._layoutClasses"
  >
    <component
      :is="verticalNavAttrs.verticalNavWrapper ? verticalNavAttrs.verticalNavWrapper : 'div'"
      v-bind="verticalNavAttrs.verticalNavWrapperProps"
      class="vertical-nav-wrapper"
    >
      <VerticalNav
        :is-overlay-nav-active="isOverlayNavActive"
        :toggle-is-overlay-nav-active="toggleIsOverlayNavActive"
        :nav-items="props.navItems"
        v-bind="{ ...verticalNavAttrs.additionalVerticalNavAttrs }"
      >
        <template #nav-header>
          <slot name="vertical-nav-header" />
        </template>
        <template #before-nav-items>
          <slot name="before-vertical-nav-items" />
        </template>
        <template #after-nav-items>
          <div class="pa-4 mt-auto border-t">
            <UserProfile />
          </div>
        </template>
      </VerticalNav>
    </component>
    <div class="layout-content-wrapper">
      <header
        class="layout-navbar"
        :class="[{ 'navbar-blur': true }]"
      >
        <div class="navbar-content-container">
          <div class="d-flex align-center h-100 px-4 flex-grow-1">
            <template v-if="configStore.isLessThanOverlayNavBreakpoint">
              <VBtn
                icon
                variant="text"
                color="default"
                class="ms-n3"
                size="large"
                aria-label="Toggle Navigation"
                @click="toggleIsOverlayNavActive(true)"
              >
                <VIcon
                  size="28"
                  icon="tabler-menu-2"
                />
              </VBtn>
            </template>
            <VSpacer />
            
            <slot name="navbar" />

            <div class="d-flex align-center gap-x-2">
              <slot name="navbar-right" />
            </div>
          </div>
        </div>
      </header>
      <main class="layout-page-content">
        <div class="page-content-container">
          <slot />
        </div>
      </main>
      <footer class="layout-footer">
        <div class="footer-content-container">
          <slot name="footer" />
        </div>
      </footer>
    </div>
    <div
      class="layout-overlay"
      :class="[{ visible: isLayoutOverlayVisible }]"
      @click="() => { isLayoutOverlayVisible = !isLayoutOverlayVisible }"
    />
    <VSnackbar
      v-model="isToastVisible"
      :color="toastColor"
      location="top end"
      variant="tonal"
    >
      {{ toastMessage }}
    </VSnackbar>
  </div>
</template>

<style lang="scss">
@use "@configured-variables" as variables;
@use "@layouts/styles/placeholders";
@use "@layouts/styles/mixins";

.layout-wrapper.layout-nav-type-vertical {
  // TODO(v2): Check why we need height in vertical nav & min-height in horizontal nav
  position: relative;
  block-size: 100%;

  .layout-content-wrapper {
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    min-block-size: 100dvh;
    transition: padding-inline-start 0.2s ease-in-out;
    will-change: padding-inline-start;

    @media screen and (min-width: 1280px) {
      padding-inline-start: variables.$layout-vertical-nav-width;
    }
  }

  .layout-navbar {
    z-index: variables.$layout-vertical-nav-layout-navbar-z-index;

    .navbar-content-container {
      block-size: variables.$layout-vertical-nav-navbar-height;
    }

    @at-root {
      .layout-wrapper.layout-nav-type-vertical {
        .layout-navbar {
          @if variables.$layout-vertical-nav-navbar-is-contained {
            @include mixins.boxed-content;
          }
          /* stylelint-disable-next-line @stylistic/indentation */
          @else {
            .navbar-content-container {
              @include mixins.boxed-content;
            }
          }
        }
      }
    }
  }

  &.layout-navbar-sticky .layout-navbar {
    @extend %layout-navbar-sticky;
  }

  &.layout-navbar-hidden .layout-navbar {
    @extend %layout-navbar-hidden;
  }

  // 👉 Footer
  .layout-footer {
    @include mixins.boxed-content;
  }

  // 👉 Layout overlay
  .layout-overlay {
    position: fixed;
    z-index: variables.$layout-overlay-z-index;
    background-color: rgb(0 0 0 / 60%);
    cursor: pointer;
    inset: 0;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s ease-in-out;
    will-change: opacity;

    &.visible {
      opacity: 1;
      pointer-events: auto;
    }
  }

  // 👉 Navbar styling
  .layout-navbar {
    position: sticky;
    top: 0;
    width: 100%;
    z-index: variables.$layout-vertical-nav-layout-navbar-z-index;
    background-color: rgb(var(--v-theme-surface));
    transition: transform 0.2s ease-in-out;

    .navbar-content-container {
      height: variables.$layout-vertical-nav-navbar-height;
      display: flex;
      align-items: center;
    }

    &.navbar-blur {
      backdrop-filter: blur(8px);
      background-color: rgba(var(--v-theme-surface), 0.85);
    }
  }

  // Adjust right column pl when vertical nav is collapsed
  &.layout-vertical-nav-collapsed .layout-content-wrapper {
    @media screen and (min-width: 1280px) {
      padding-inline-start: variables.$layout-vertical-nav-collapsed-width;
    }
  }

  // 👉 Content height fixed
  &.layout-content-height-fixed {
    .layout-content-wrapper {
      max-block-size: 100dvh;
    }

    .layout-page-content {
      display: flex;
      overflow: hidden;

      .page-content-container {
        inline-size: 100%;

        > :first-child {
          max-block-size: 100%;
          overflow-y: auto;
        }
      }
    }
  }
}
</style>
