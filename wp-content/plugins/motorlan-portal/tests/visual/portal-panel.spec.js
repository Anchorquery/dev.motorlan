// Panel (fase 2): layout, campana, Mi perfil, favoritos y chat. Capturas junto a las de
// la SPA (cuenta.png ↔ portal-cuenta.png, favoritos.png ↔ portal-favoritos.png).
//
//   ML_PORTAL_BASE   sitio local con las secciones panel, cuenta y favoritos encendidas
//                    (def. https://dev.motorlan.test). Si no están encendidas, se omite.
//   ML_PORTAL_USER / ML_PORTAL_PASS  usuario de prueba de la BD LOCAL (nunca uno real).
//                    Sin ellas se omite. Los tests quitan y vuelven a poner un favorito y
//                    marcan notificaciones; no envían mensajes de chat ni guardan el perfil
//                    con cambios (el guardado se prueba con los mismos datos).
//   ML_PANEL_SPA=1   captura las referencias de la SPA (cuenta.png, favoritos.png) con las
//                    secciones APAGADAS, mismo usuario.
import { test, expect } from '@playwright/test'

const BASE = process.env.ML_PORTAL_BASE || 'https://dev.motorlan.test'
const USER = process.env.ML_PORTAL_USER || ''
const PASS = process.env.ML_PORTAL_PASS || ''
const SPA_MODE = process.env.ML_PANEL_SPA === '1'
const ACCOUNT = BASE + '/mi-cuenta/dashboard/user/account'
const FAVS = BASE + '/mi-cuenta/dashboard/favorites/list'

const HIDE = `.xdebug-error, body > b:first-child, body > br { display: none !important; }
  #cookie-law-info-bar, .cky-consent-container, .cky-overlay { display: none !important; }`

async function settle(page) {
  await page.addStyleTag({ content: HIDE })
  await page.waitForLoadState('networkidle', { timeout: 20_000 }).catch(() => {})
  await page.waitForFunction(() => !document.querySelector('.v-skeleton-loader, .v-progress-circular'), null, { timeout: 15_000 }).catch(() => {})
  await page.evaluate(() => document.fonts.ready)
  await page.waitForTimeout(300)
}

async function login(page) {
  await page.goto(BASE + '/wp-login.php')
  await page.fill('#user_login', USER)
  await page.fill('#user_pass', PASS)
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')])
}

const volatile = (page) => [page.locator('img'), page.locator('.ml-profile__img'), page.locator('.v-avatar img, .v-img')]

test.describe('Panel del portal', () => {
  test.skip(!USER || !PASS, 'Sin ML_PORTAL_USER / ML_PORTAL_PASS')
  test.skip(SPA_MODE, 'Modo referencia de la SPA')

  test.beforeEach(async ({ page }) => {
    await login(page)
    const res = await page.goto(FAVS)
    const html = res ? await res.text() : ''
    test.skip(!html.includes('id="ml-favs"'), 'Secciones del panel apagadas')
  })

  test('layout: menú, barra, campana y menú del usuario', async ({ page, isMobile }) => {
    await settle(page)
    if (isMobile) {
      await expect(page.locator('.ml-dnav')).toBeVisible()
      await expect(page.locator('.ml-nav__burger')).toBeVisible()
      await page.click('.ml-nav__burger')
      await expect(page.locator('.ml-nav')).toBeInViewport()
      await page.click('.ml-nav-scrim')
    } else {
      await expect(page.locator('.ml-nav')).toBeVisible()
      await expect(page.locator('.ml-dnav')).toBeHidden()
      await expect(page.locator('.ml-nav__link.is-active')).toContainText(/Favoritos/i)
      // Sin «Administración» para un usuario normal.
      await expect(page.locator('.ml-nav__section', { hasText: /Administración/ })).toHaveCount(0)
    }

    // Campana: lista de la API, contador y marcar todas.
    const bell = page.locator('button[aria-label="Notificaciones"]')
    await bell.click()
    const items = page.locator('.ml-notif__item')
    await expect(items.first()).toBeVisible()
    await expect(page).toHaveScreenshot('portal-notificaciones.png', { mask: volatile(page) })
    const toggled = page.waitForResponse((r) => /\/notifications\/(read|unread)$/.test(r.url()))
    await page.locator('.ml-notif button[aria-label^="Marcar todas"]').click()
    expect((await toggled).ok()).toBeTruthy()
    await page.keyboard.press('Escape')

    // Menú del usuario con Mi perfil y cerrar sesión.
    await page.locator('button[aria-label="Cuenta"]').click()
    await expect(page.getByRole('menuitem', { name: 'Mi perfil' })).toBeVisible()
    await expect(page.locator('.ml-menu a', { hasText: 'Cerrar sesión' })).toBeVisible()
  })

  test('notificación con vue_path lleva a la pantalla y se marca leída', async ({ page }) => {
    await settle(page)
    await page.locator('button[aria-label="Notificaciones"]').click()
    const item = page.locator('.ml-notif__item', { hasText: 'Oferta recibida' })
    test.skip(!(await item.count()), 'Falta la notificación de prueba')
    await Promise.all([page.waitForURL(/dashboard\/user\/account\?tab=1&offer_id=99/), item.click()])
  })

  test('favoritos: filtros por HTMX, quitar con confirmación', async ({ page }) => {
    await settle(page)
    await expect(page.locator('#ml-favs tbody tr').first()).toBeVisible()
    await expect(page).toHaveScreenshot('portal-favoritos.png', { mask: volatile(page) })

    // Elementos por página: solo se cambia la tabla.
    const swap = page.waitForResponse((r) => r.request().headers()['hx-request'] === 'true')
    await page.selectOption('#ml-favs-per-page', '5')
    await swap
    await expect(page.locator('#ml-favs tbody tr')).toHaveCount(5)

    // Quitar el primero (y volver a ponerlo por la API para dejar los datos igual).
    const first = page.locator('#ml-favs tbody tr').first()
    const name = (await first.locator('td').first().innerText()).trim()
    await first.locator('button[aria-label="Eliminar de favoritos"]').click()
    const dialog = page.locator('.ml-dialog', { hasText: '¿Seguro que quieres quitar' })
    await expect(dialog).toBeVisible()
    const removed = page.waitForResponse((r) => r.request().method() === 'POST' && r.url().includes('favorites/list'))
    await dialog.locator('button[type="submit"]').click()
    const res = await removed
    expect(res.ok()).toBeTruthy()
    await expect(page.locator('.ml-toast')).toContainText('Eliminado de favoritos')
    await expect(dialog).toBeHidden()

    const body = (await res.request().postData()) || ''
    const id = new URLSearchParams(body).get('id')
    expect(id).toBeTruthy()
    const nonce = await page.evaluate(() => window.motorlanPortal.nonce)
    const add = await page.request.post(BASE + '/wp-json/motorlan/v1/favorites', { headers: { 'X-WP-Nonce': nonce }, data: { publicacion_id: Number(id) } })
    expect(add.ok(), `volver a añadir ${name}`).toBeTruthy()
  })

  test('mi perfil: una sola pantalla, validación y email con contraseña', async ({ page }) => {
    // user/profile redirige a user/account.
    await page.goto(BASE + '/mi-cuenta/dashboard/user/profile')
    await expect(page).toHaveURL(/dashboard\/user\/account\/?$/)
    await settle(page)
    await expect(page.locator('.ml-profile__avatar')).toBeVisible()
    await expect(page).toHaveScreenshot('portal-cuenta.png', { mask: volatile(page), fullPage: true })

    // Guardar sin cambios: lo valida y guarda la API (mismos datos).
    const saved = page.waitForResponse((r) => r.request().method() === 'POST' && r.url().includes('user/account'))
    await page.click('button:has-text("Guardar cambios")')
    expect((await saved).ok()).toBeTruthy()
    await expect(page.locator('.ml-toast')).toContainText(/guardados/i)

    // Obligatorio vacío: error del servidor junto al campo.
    const nombre = await page.inputValue('#nombre')
    await page.fill('#nombre', '')
    const invalid = page.waitForResponse((r) => r.request().method() === 'POST' && r.url().includes('user/account'))
    await page.click('button:has-text("Guardar cambios")')
    await invalid
    await expect(page.locator('#nombre-hint')).toBeVisible()
    await page.fill('#nombre', nombre)

    // Email nuevo: pide la contraseña ANTES de enviar nada.
    let posted = false
    page.on('request', (r) => { if (r.method() === 'POST' && r.url().includes('user/account')) posted = true })
    await page.fill('#email', 'otro-email@example.test')
    await page.click('button:has-text("Guardar cambios")')
    const dialog = page.locator('.ml-dialog', { hasText: 'Contraseña actual' })
    await expect(dialog).toBeVisible()
    expect(posted).toBeFalsy()
    await dialog.locator('button', { hasText: 'Cancelar' }).click()
    await expect(dialog).toBeHidden()

    // Cambiar contraseña: el primer paso exige las 5 reglas (no se envía nada).
    await page.click('button:has-text("Cambiar contraseña")')
    const pw = page.locator('.ml-dialog', { hasText: 'Nueva contraseña' })
    await expect(pw).toBeVisible()
    await pw.locator('#ml-password-new').fill('abc')
    await pw.locator('#ml-password-confirm').fill('abc')
    await pw.locator('button', { hasText: 'Enviar código' }).click()
    await expect(pw.locator('.ml-alert.ml-c-error')).toBeVisible()
  })

  test('chat del kit en la ficha (con sesión): carga la conversación y marca leído', async ({ page }) => {
    await page.goto(BASE + '/marketplace-motorlan/')
    const href = await page.locator('a.ml-pcard').first().getAttribute('href').catch(() => null)
    test.skip(!href, 'Sin tienda del portal')
    await page.goto(href)
    await settle(page)
    const consult = page.locator('.ml-ficha button', { hasText: /consultar precio/i }).first()
    test.skip(!(await consult.isVisible()), 'Publicación propia o sin chat')
    const loaded = page.waitForResponse((r) => /\/products\/\d+\/messages/.test(r.url()) && r.request().method() === 'GET')
    await consult.click()
    expect((await loaded).status()).toBeLessThan(500)
    await expect(page.locator('#ml-chat-messages')).toBeVisible()
    await expect(page.locator('#ml-chat-messages-text')).toBeVisible()
  })
})

// Referencias de la SPA (secciones apagadas): ML_PANEL_SPA=1.
test.describe('Panel de la SPA (referencia)', () => {
  test.skip(!USER || !PASS || !SPA_MODE, 'Solo con ML_PANEL_SPA=1 y credenciales')

  test('mi perfil (SPA)', async ({ page }) => {
    await login(page)
    await page.goto(ACCOUNT)
    await settle(page)
    await expect(page).toHaveScreenshot('cuenta.png', { mask: volatile(page), fullPage: true })
  })

  test('favoritos (SPA)', async ({ page }) => {
    await login(page)
    await page.goto(FAVS)
    await settle(page)
    await expect(page).toHaveScreenshot('favoritos.png', { mask: volatile(page) })
  })
})
