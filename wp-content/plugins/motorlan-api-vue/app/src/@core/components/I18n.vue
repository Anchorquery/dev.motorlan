<script setup lang="ts">
import type { I18nLanguage } from '@layouts/types'
import { cookieRef } from '@layouts/stores/config'

interface Props {
  languages: (I18nLanguage & { url?: string })[]
  location?: any
}

const props = withDefaults(defineProps<Props>(), {
  location: 'bottom end',
})

const { locale } = useI18n({ useScope: 'global' })
const languageCookie = cookieRef<string | null>('language', null)

const changeLanguage = (lang: Props['languages'][number]) => {
  locale.value = lang.i18nLang
  languageCookie.value = lang.i18nLang

  // When WPML/Polylang exposes a per-language URL, follow it so the server
  // re-renders with the matching ICL_LANGUAGE_CODE and Vue picks up the same
  // locale on next load.
  if (lang.url && typeof window !== 'undefined' && lang.url !== window.location.href)
    window.location.href = lang.url
}
</script>

<template>
  <IconBtn>
    <VIcon icon="tabler-language" />

    <!-- Menu -->
    <VMenu
      activator="parent"
      :location="props.location"
      offset="12px"
      width="175"
    >
      <!-- List -->
      <VList
        :selected="[locale]"
        color="primary"
      >
        <!-- List item -->
        <VListItem
          v-for="lang in props.languages"
          :key="lang.i18nLang"
          :value="lang.i18nLang"
          @click="changeLanguage(lang)"
        >
          <!-- Language label -->
          <VListItemTitle>
            {{ lang.label }}
          </VListItemTitle>
        </VListItem>
      </VList>
    </VMenu>
  </IconBtn>
</template>
