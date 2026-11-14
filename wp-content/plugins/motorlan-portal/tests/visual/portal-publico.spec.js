// Pantallas públicas migradas (fase 1): tienda, ficha y acceso, servidas por el portal.
// Las capturas se guardan junto a las de la SPA (tienda.png ↔ portal-tienda.png,
// ficha.png ↔ portal-ficha.png, login.png ↔ portal-login.png) para compararlas a ojo.
//
//   ML_PORTAL_BASE  sitio con las secciones "tienda" y "acceso" encendidas
//                   (def. https://dev.motorlan.test). Si no responde o no están
//                   encendidas, se omite. Solo hace GET y envíos que no crean nada
//                   (login con credenciales falsas, formularios vacíos).
import { test, expect } from '@playwright/test'

const BASE = process.env.ML_PORTAL_BASE || 'https://dev.motorlan.test'
const STORE = BASE + '/marketplace-motorlan/'
const ACCOUNT = BASE + '/mi-cuenta/'

// Fotos y avisos de depuración de WordPress fuera de la comparación.
const HIDE = `.xdebug-error, body > b:first-child, body > br { display: none !important; }`
const volatile = (page) => [page.locator('img'), page.locator('.ml-pcard__media')]

async function settle(page) {
  await page.addStyleTag({ content: HIDE })
  await page.waitForLoadState('networkidle', { timeout: 20_000 }).catch(() => {})
  await page.evaluate(() => document.fonts.ready)
}

test.describe('Portal: pantallas públicas', () => {
  test.beforeAll(async ({ request }) => {
    const res = await request.get(STORE, { timeout: 10_000, ignoreHTTPSErrors: true }).catch(() => null)
    const ok = !!res && res.ok() && (await res.text()).includes('ml-store')
    test.skip(!ok, `Tienda del portal no disponible en ${STORE} (¿sección "tienda" apagada?)`)
  })

  test('tienda: listado con SEO y filtros por HTMX', async ({ page }) => {
    await page.goto(STORE)
    await settle(page)

    await expect(page.locator('.ml-store-hero h1')).toBeVisible()
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', /marketplace-motorlan\/?$/)
    const ld = await page.locator('script[type="application/ld+json"]').allTextContents()
    expect(ld.some((t) => t.includes('"ItemList"'))).toBeTruthy()
    await expect(page.locator('.ml-pcard').first()).toBeVisible()

    await expect(page).toHaveScreenshot('portal-tienda.png', { mask: volatile(page) })

    // Filtro por tipo: HTMX cambia solo los resultados y fija la URL.
    if (await page.locator('.ml-filters-panel').isVisible()) {
      const swap = page.waitForResponse((r) => r.request().headers()['hx-request'] === 'true')
      await page.locator('label[for="ml-f-ptype-motor-ac"]').click()
      await swap
      await expect(page).toHaveURL(/tipo=motor.*tech=ac/) // mismos parámetros que la SPA
      await expect(page.locator('#ml-f-par')).toBeHidden() // con Motor AC no hay PAR
    }
  })

  test('ficha: datos, JSON-LD y chat de invitado', async ({ page }) => {
    await page.goto(STORE)
    const href = await page.locator('a.ml-pcard').first().getAttribute('href')
    test.skip(!href, 'No hay publicaciones en la tienda')
    await page.goto(href)
    await settle(page)

    await expect(page.locator('h1')).toBeVisible()
    const ld = await page.locator('script[type="application/ld+json"]').allTextContents()
    expect(ld.some((t) => t.includes('"Product"'))).toBeTruthy()
    await expect(page).toHaveScreenshot('portal-ficha.png', { mask: volatile(page) })

    const consult = page.locator('.ml-ficha button', { hasText: /consultar precio/i }).first()
    if (await consult.isVisible()) {
      await consult.click()
      await expect(page.locator('#ml-chat-name')).toBeVisible()
    }
  })

  test('login: error de credenciales sin recargar', async ({ page }) => {
    await page.goto(ACCOUNT + 'login')
    await settle(page)
    await expect(page.locator('.ml-auth-card h1')).toBeVisible()
    await expect(page).toHaveScreenshot('portal-login.png')

    await page.fill('#username', 'no-existe@example.com')
    await page.fill('#password', 'Incorrecta#1')
    await page.click('button[type="submit"]')
    await expect(page.locator('.ml-alert.ml-c-error')).toBeVisible()
    await expect(page).toHaveURL(/\/login\/?$/)
  })

  test('registro: el asistente valida cada paso', async ({ page }) => {
    await page.goto(ACCOUNT + 'register')
    await settle(page)
    await expect(page).toHaveScreenshot('portal-register.png')

    await page.click('fieldset:visible >> text=Continuar')
    await expect(page.locator('fieldset:visible .ml-hint--error')).toBeVisible()

    await page.fill('#first_name', 'Ana')
    await page.fill('#last_name', 'Prueba')
    await page.fill('#email', 'ana@example.com')
    await page.click('fieldset:visible >> text=Continuar')
    await expect(page.locator('#password')).toBeVisible()

    // Contraseña débil: no deja continuar; las 5 reglas se marcan al cumplirse.
    await page.fill('#password', 'abc')
    await expect(page.locator('fieldset:visible >> text=Continuar')).toBeDisabled()
    await page.fill('#password', 'Segura#2026x')
    await page.fill('#password_confirm', 'Segura#2026x')
    await page.click('fieldset:visible >> text=Continuar')
    await expect(page.locator('#ml-privacy')).toBeVisible()
    await expect(page.locator('.ml-auth-summary')).toContainText('ana@example.com')
  })

  test('recuperar contraseña', async ({ page }) => {
    await page.goto(ACCOUNT + 'forgot-password')
    await settle(page)
    await expect(page).toHaveScreenshot('portal-forgot-password.png')
  })

  test('restablecer y verificar sin token: enlace inválido', async ({ page }) => {
    await page.goto(ACCOUNT + 'reset-password')
    await expect(page.getByText('Solicitar un enlace nuevo')).toBeVisible()

    await page.goto(ACCOUNT + 'verify-email')
    await expect(page.locator('.ml-auth-card .text-error')).toBeVisible()
  })
})
