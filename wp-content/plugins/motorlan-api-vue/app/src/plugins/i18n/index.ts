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

const getServerLanguage = () => {
  if (typeof window === 'undefined')
    return null

  const wpData = (window as any)?.wpData
  return wpData?.language ?? wpData?.language_locale ?? null
}

export const getI18n = () => {
  if (_i18n === null) {
    const serverLanguage = getServerLanguage()
    const defaultLocale = serverLanguage ?? themeConfig.app.i18n.defaultLocale

    _i18n = createI18n({
      legacy: false,
      locale: cookieRef('language', defaultLocale).value,
      fallbackLocale: 'en',
      messages,
    })
  }

  return _i18n
}

export default function (app: App) {
  app.use(getI18n())
}
