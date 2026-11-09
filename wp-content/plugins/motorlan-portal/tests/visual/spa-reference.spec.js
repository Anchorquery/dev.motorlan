// Referencias visuales de la SPA actual (Vue/Vuetify) para comparar a ojo con las pantallas
// migradas al portal. SOLO LECTURA: navega y captura; el único POST posible es el login del
// panel, y solo si se dan ML_E2E_USER / ML_E2E_PASS.
import { test, expect } from '@playwright/test'

const STORE = '/marketplace-motorlan/'
const LOGIN = '/mi-cuenta/login'
const DASHBOARD = '/mi-cuenta/dashboard/publications/publication/list'

// Banner de cookies, chat y similares: fuera de la captura para que no la ensucien.
const HIDE = `
  #cookie-law-info-bar, .cky-consent-container, .cky-overlay, #cmplz-cookiebanner-container,
  .cookie-notice-container, #moove_gdpr_cookie_info_bar, iframe[src*="chat"], #chatwoot_live_chat_widget,
  .woot-widget-holder, .woot--bubble-holder { display: none !important; }
`

async function settle(page) {
  await page.addStyleTag({ content: HIDE })
  await page.waitForLoadState('networkidle', { timeout: 20_000 }).catch(() => {})
  // Vuetify pinta skeletons mientras llegan los datos: espera a que desaparezcan.
  await page.waitForFunction(() => !document.querySelector('.v-skeleton-loader, .v-progress-circular'), null, { timeout: 15_000 }).catch(() => {})
  await page.waitForTimeout(500)
}

// Precios, fechas, contadores y fotos cambian a diario: se tapan para que la captura compare estructura.
const volatile = (page) => [
  page.locator('img'),
  page.locator('.price, [class*="price"], [class*="precio"]'),
  page.locator('time, [class*="date"], [class*="fecha"]'),
]

test.describe('SPA de referencia', () => {
  test('tienda', async ({ page }) => {
    await page.goto(STORE)
    await settle(page)
    await expect(page).toHaveScreenshot('tienda.png', { fullPage: false, mask: volatile(page) })
  })

  test('ficha de producto', async ({ page }) => {
    await page.goto(STORE)
    await settle(page)
    // Primer anuncio que enlace a una ficha /marketplace-motorlan/{slug}/
    const href = await page.locator(`a[href*="${STORE}"]`).evaluateAll((as, store) =>
      as.map((a) => a.getAttribute('href'))
        .find((h) => h && new RegExp(store.replace(/\//g, '\/') + '[^/?#]+\/?$').test(new URL(h, location.href).pathname)), STORE)
    test.skip(!href, 'No se encontró ningún anuncio en la tienda')
    await page.goto(href)
    await settle(page)
    await expect(page).toHaveScreenshot('ficha.png', { fullPage: false, mask: volatile(page) })
  })

  test('login', async ({ page }) => {
    await page.goto(LOGIN)
    await settle(page)
    await expect(page).toHaveScreenshot('login.png', { fullPage: false })
  })

  test('panel (dashboard)', async ({ page }) => {
    const user = process.env.ML_E2E_USER
    const pass = process.env.ML_E2E_PASS
    test.skip(!user || !pass, 'Define ML_E2E_USER y ML_E2E_PASS para capturar el panel')
    await page.goto(`${LOGIN}?to=${encodeURIComponent(DASHBOARD)}`)
    await settle(page)
    await page.locator('input[type="email"], input[autocomplete="username"], input[name="email"]').first().fill(user)
    await page.locator('input[type="password"]').first().fill(pass)
    await page.locator('button[type="submit"]').first().click()
    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30_000 })
    if (!page.url().includes('/dashboard')) await page.goto(DASHBOARD)
    await settle(page)
    await expect(page).toHaveScreenshot('panel.png', { fullPage: false, mask: volatile(page) })
  })
})
