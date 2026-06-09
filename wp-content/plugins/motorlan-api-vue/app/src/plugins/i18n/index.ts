import type { App } from 'vue'
import { createI18n } from 'vue-i18n'
import { cookieRef } from '@layouts/stores/config'
import { themeConfig } from '@themeConfig'

const messages = Object.fromEntries(
  Object.entries(
    import.meta.glob<{ default: any }>('./locales/*.json', { eager: true }))
    .map(([key, value]) => [key.slice(10, -5), value.default]),
)

let _i18n: any = null

const supportedLocales = Object.keys(messages)

const normalizeLocale = (locale: string | null | undefined) => {
  if (!locale)
    return themeConfig.app.i18n.defaultLocale

  const code = locale.toLowerCase().split(/[-_]/)[0]
  return supportedLocales.includes(code) ? code : themeConfig.app.i18n.defaultLocale
}

const deepMerge = (target: Record<string, any>, source: Record<string, any>) => {
  const output: Record<string, any> = { ...target }

  for (const [key, value] of Object.entries(source || {})) {
    if (value && typeof value === 'object' && !Array.isArray(value) && output[key] && typeof output[key] === 'object' && !Array.isArray(output[key])) {
      output[key] = deepMerge(output[key], value)
    }
    else {
      output[key] = value
    }
  }

  return output
}

const mergeMessages = () => {
  const wpMessages = (window as any)?.wpData?.i18n_messages ?? {}
  const merged: Record<string, any> = {}

  for (const locale of supportedLocales) {
    merged[locale] = deepMerge(messages[locale] ?? {}, wpMessages[locale] ?? {})
  }

  return merged
}

const getServerLanguage = () => {
  if (typeof window === 'undefined')
    return null

  const wpData = (window as any)?.wpData
  const raw = wpData?.language ?? wpData?.language_locale ?? null
  if (!raw)
    return null

  return normalizeLocale(raw)
}

export const getI18n = () => {
  if (_i18n === null) {
    const serverLanguage = getServerLanguage()
    const cookie = cookieRef<string | null>('language', null)
    const cookieLocale = cookie.value ? normalizeLocale(cookie.value) : null

    // Server (WPML/Polylang) is authoritative. Cookie only used when server has no language.
    const initialLocale = serverLanguage ?? cookieLocale ?? themeConfig.app.i18n.defaultLocale

    // Keep cookie in sync so subsequent SPA navigations remember the choice.
    if (cookie.value !== initialLocale)
      cookie.value = initialLocale

    _i18n = createI18n({
      legacy: false,
      locale: initialLocale,
      fallbackLocale: 'en',
      messages: mergeMessages(),
    })
  }

  return _i18n
}

export default function (app: App) {
  app.use(getI18n())
}
