type WPData = {
  vue_page_urls?: Record<string, string>
  vue_base?: string
}

const wp = (): WPData => (window as unknown as { wpData?: WPData }).wpData || {}

type PageKey = 'mi-cuenta' | 'marketplace-motorlan' | 'store' | 'login' | 'register' | 'forgot-password' | 'reset-password' | 'verify-email' | 'register-success'

export function pageUrl(key: PageKey, sub = ''): string {
  const map = wp().vue_page_urls || {}
  const base = map[key] || `/${key}/`
  const clean = sub.replace(/^\//, '')

  return base + clean
}

export const storeUrl = (slug: string) => pageUrl('marketplace-motorlan', `${slug}/`)
export const accountUrl = (sub = '') => pageUrl('mi-cuenta', sub)

export function isAccountBase(base: string): boolean {
  const accountPath = wp().vue_page_urls?.['mi-cuenta']
  if (accountPath)
    return base === accountPath || base.replace(/\/$/, '') === accountPath.replace(/\/$/, '')

  return base.includes('mi-cuenta')
}
