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
  return normalizeLocale(wpData?.language ?? wpData?.language_locale ?? null)
}

export const getI18n = () => {
  if (_i18n === null) {
    const serverLanguage = getServerLanguage()
    const defaultLocale = serverLanguage ?? themeConfig.app.i18n.defaultLocale
    const persistedLocale = normalizeLocale(cookieRef('language', defaultLocale).value)

    _i18n = createI18n({
      legacy: false,
      locale: persistedLocale,
      fallbackLocale: 'en',
      messages: mergeMessages(),
    })
  }

  return _i18n
}

export default function (app: App) {
  app.use(getI18n())
}
