// Panel del comprador (fase 3): Mis compras, detalle, consultas, ofertas enviadas y Mis
// valoraciones. Capturas junto a las de la SPA (compras.png ↔ portal-compras.png, …).
//
//   ML_PORTAL_BASE     sitio local con la sección `comprador` encendida (def. https://dev.motorlan.test).
//                      Si no está encendida, se omite.
//   Sesión (BD LOCAL, nunca una cuenta real), una de las dos:
//     ML_PORTAL_COOKIES  ruta a un JSON [{ name, value }] con las cookies de WordPress del usuario de prueba
//     ML_PORTAL_USER / ML_PORTAL_PASS  inicio de sesión en wp-login.php
//   Datos que espera (comprador con compras, consultas y ofertas; ver README):
//     ML_COMPRA_UUID     compra por oferta del usuario, sin valorar (detalle y chat)
//   ML_COMPRADOR_CONFIRM=1  prueba también confirmar una oferta con doble clic (crea UNA compra:
//                      hay que devolver la oferta a «aceptada» después). Sin ella, no se confirma nada.
//   ML_COMPRADOR_SPA=1 captura las referencias de la SPA (compras.png, compra.png, consultas.png,
//                      ofertas.png) con la sección APAGADA (p. ej. ML_PORTAL_BASE=https://dev.motorlan.test).
// Los tests no envían valoraciones ni mensajes, y retirar una oferta se cancela en el diálogo.
import { test, expect } from '@playwright/test'
import fs from 'node:fs'

const BASE = process.env.ML_PORTAL_BASE || 'https://dev.motorlan.test'
const COOKIES = process.env.ML_PORTAL_COOKIES || ''
const USER = process.env.ML_PORTAL_USER || ''
const PASS = process.env.ML_PORTAL_PASS || ''
const UUID = process.env.ML_COMPRA_UUID || ''
const SPA_MODE = process.env.ML_COMPRADOR_SPA === '1'
const CONFIRM = process.env.ML_COMPRADOR_CONFIRM === '1'
const D = BASE + '/mi-cuenta/dashboard/'
const URLS = {
  compras: D + 'purchases/purchases',
  consultas: D + 'purchases/inquiries',
  ofertas: D + 'purchases/offers-sent',
  valoraciones: D + 'purchases/opinions',
}

const HIDE = `.xdebug-error, body > b:first-child, body > br { display: none !important; }
  #cookie-law-info-bar, .cky-consent-container, .cky-overlay { display: none !important; }`

async function settle(page) {
  await page.addStyleTag({ content: HIDE })
  await page.waitForLoadState('networkidle', { timeout: 20_000 }).catch(() => {})
  await page.waitForFunction(() => !document.querySelector('.v-skeleton-loader, .v-progress-circular, .ml-table-loading.htmx-request'), null, { timeout: 15_000 }).catch(() => {})
  await page.evaluate(() => document.fonts.ready)
  await page.waitForTimeout(300)
}

async function signIn(context, page) {
  if (COOKIES) {
    const url = new URL(BASE)
    const list = JSON.parse(fs.readFileSync(COOKIES, 'utf8'))
    await context.addCookies(list.map((c) => ({ name: c.name, value: c.value, domain: url.hostname, path: '/', secure: url.protocol === 'https:', httpOnly: true })))
    return
  }
  await page.goto(BASE + '/wp-login.php')
  await page.fill('#user_login', USER)
  await page.fill('#user_pass', PASS)
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')])
}

const volatile = (page) => [page.locator('img'), page.locator('.v-avatar img, .v-img')]
const isHx = (r) => r.request().headers()['hx-request'] === 'true'

// ── Referencias de la SPA (sección apagada) ──────────────────────────────────────────────
test.describe('SPA: referencias del comprador', () => {
  test.skip(!SPA_MODE, 'Solo con ML_COMPRADOR_SPA=1')
  test.skip(!COOKIES && (!USER || !PASS), 'Sin sesión de prueba')

  test.beforeEach(async ({ context, page }) => { await signIn(context, page) })

  for (const [name, url] of Object.entries({ compras: URLS.compras, consultas: URLS.consultas, ofertas: URLS.ofertas, compra: UUID ? D + 'purchases/' + UUID : '' })) {
    test(name, async ({ page }) => {
      test.skip(!url, 'Sin ML_COMPRA_UUID')
      await page.goto(url)
      await settle(page)
      await page.waitForTimeout(1500)
      await expect(page).toHaveScreenshot(name + '.png', { fullPage: true, mask: volatile(page) })
    })
  }
})

// ── Portal ───────────────────────────────────────────────────────────────────────────────
test.describe('Panel del comprador (portal)', () => {
  test.skip(SPA_MODE, 'Modo referencia de la SPA')
  test.skip(!COOKIES && (!USER || !PASS), 'Sin sesión de prueba')

  test.beforeEach(async ({ context, page }) => {
    await signIn(context, page)
    const res = await page.goto(URLS.compras)
    const html = res ? await res.text() : ''
    test.skip(!html.includes('id="ml-compras"'), 'Sección comprador apagada')
  })

  test('Mis compras: tabla, búsqueda por HTMX y diálogo de valorar', async ({ page, isMobile }) => {
    await settle(page)
    const rows = page.locator('#ml-compras tbody tr[data-uuid]')
    await expect(rows.first()).toBeVisible()
    await expect(page).toHaveScreenshot('portal-compras.png', { fullPage: true, mask: volatile(page) })

    // Búsqueda sin resultados → estado vacío, solo se cambia la tabla.
    const swap = page.waitForResponse(isHx)
    await page.fill('#ml-compras-q', 'zzzz-no-existe')
    await swap
    await expect(page.locator('#ml-compras')).toContainText('Sin resultados')
    const back = page.waitForResponse(isHx)
    await page.fill('#ml-compras-q', '')
    await back
    await expect(rows.first()).toBeVisible()

    // Valorar: el envío solo se habilita con estrellas y comentario. Se cancela (no se valora).
    const rate = page.locator('#ml-compras tbody').getByRole('button', { name: 'Valorar vendedor' }).locator('visible=true').first()
    test.skip(!(await rate.count()), 'Ninguna compra por valorar')
    await rate.click()
    const dialog = page.locator('.ml-dialog', { hasText: 'Valora al vendedor' })
    await expect(dialog).toBeVisible()
    await expect(dialog).toContainText(/¿Cómo fue tu experiencia con .+\?/)
    const send = dialog.getByRole('button', { name: 'Enviar' })
    await expect(send).toBeDisabled()
    await dialog.locator('label').nth(3).click() // 4 estrellas
    await expect(send).toBeDisabled()
    await dialog.locator('textarea[name="comment"]').fill('Prueba')
    await expect(send).toBeEnabled()
    if (!isMobile) await expect(page).toHaveScreenshot('portal-valorar.png', { mask: volatile(page) })
    await dialog.getByRole('button', { name: 'Cancelar' }).click()
    await expect(dialog).toBeHidden()
  })

  test('Mis valoraciones: menú y tabla', async ({ page, isMobile }) => {
    if (!isMobile) {
      const link = page.locator('.ml-nav a', { hasText: 'Mis valoraciones' })
      await expect(link).toBeVisible()
      await link.click()
    } else {
      await page.goto(URLS.valoraciones)
    }
    await expect(page).toHaveURL(/purchases\/opinions/)
    await settle(page)
    await expect(page.locator('#ml-opiniones')).toBeVisible()
    await expect(page).toHaveScreenshot('portal-valoraciones.png', { fullPage: true, mask: volatile(page) })
  })

  test('Detalle: resumen, chat y ?openChat=true', async ({ page }) => {
    test.skip(!UUID, 'Sin ML_COMPRA_UUID')
    await page.goto(D + 'purchases/' + UUID)
    await settle(page)
    await expect(page.locator('[data-test="purchase-summary"]')).toContainText(UUID)
    await expect(page.locator('[data-test="purchase-total"]')).toContainText('€')
    await expect(page).toHaveScreenshot('portal-compra.png', { fullPage: true, mask: volatile(page) })

    const thread = page.waitForResponse((r) => r.url().includes('/purchases/' + UUID + '/messages'))
    await page.locator('[data-test="open-chat"]').click()
    expect((await thread).ok()).toBeTruthy()
    const chat = page.locator('.ml-dialog', { hasText: 'Por tu seguridad' })
    await expect(chat).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(chat).toBeHidden()

    await page.goto(D + 'purchases/' + UUID + '?openChat=true')
    await expect(page.locator('.ml-dialog', { hasText: 'Por tu seguridad' })).toBeVisible()

    const missing = await page.goto(D + 'purchases/00000000-0000-4000-8000-000000000000')
    expect(missing.status()).toBe(404)
    await expect(page.locator('main')).toContainText('No encontramos la compra')
  })

  test('Consultas enviadas: bandeja, no leídos y chat del producto', async ({ page }) => {
    await page.goto(URLS.consultas)
    await settle(page)
    const rows = page.locator('#ml-inq tbody tr[data-room]')
    await expect(rows.first()).toBeVisible()
    await expect(page).toHaveScreenshot('portal-consultas.png', { fullPage: true, mask: volatile(page) })

    const row = rows.first()
    const name = (await row.locator('td').nth(1).locator('p').first().innerText()).trim()
    const msgs = page.waitForResponse((r) => /\/messages/.test(r.url()) && r.request().method() === 'GET')
    await row.locator('[data-test="inq-chat"]').click()
    expect((await msgs).ok()).toBeTruthy()
    const chat = page.locator('.ml-dialog', { hasText: 'Por tu seguridad' })
    await expect(chat).toContainText(name)
    await page.keyboard.press('Escape')

    // ?room_key= abre esa conversación al cargar.
    const room = await rows.nth(1).getAttribute('data-room').catch(() => null)
    if (room) {
      await page.goto(URLS.consultas + '?room_key=' + encodeURIComponent(room))
      await expect(page.locator('.ml-dialog', { hasText: 'Por tu seguridad' })).toBeVisible()
    }
  })

  test('Ofertas enviadas: filtros, detalle y retirar con confirmación', async ({ page }) => {
    await page.goto(URLS.ofertas)
    await settle(page)
    const rows = page.locator('#ml-ofertas tbody tr[data-offer]')
    await expect(rows.first()).toBeVisible()
    // La SPA mostraba la clave «offers.sent» como título.
    await expect(page.locator('h1')).toHaveText('Ofertas enviadas')
    await expect(page).toHaveScreenshot('portal-ofertas.png', { fullPage: true, mask: volatile(page) })

    // Filtro por estado (HTMX).
    const swap = page.waitForResponse(isHx)
    await page.selectOption('#ml-ofertas-estado', 'pending')
    await swap
    await expect(page.locator('#ml-ofertas tbody tr[data-offer]:not([data-status="pending"])')).toHaveCount(0)

    // Detalle.
    const pending = page.locator('#ml-ofertas tbody tr[data-status="pending"]').first()
    test.skip(!(await pending.count()), 'Sin ofertas pendientes')
    await pending.getByRole('button', { name: 'Ver detalle' }).locator('visible=true').first().click()
    const detail = page.locator('.ml-dialog', { hasText: 'Detalles de la oferta' })
    await expect(detail).toBeVisible()
    await expect(detail).toContainText('Pendiente')

    // Retirar pide confirmación (la SPA no preguntaba); se cancela.
    await detail.getByRole('button', { name: 'Retirar oferta' }).click()
    const ask = page.locator('.ml-dialog', { hasText: '¿Seguro que quieres retirar' })
    await expect(ask).toBeVisible()
    await ask.getByRole('button', { name: 'Cancelar' }).click()
    await expect(ask).toBeHidden()
  })

  test('Ofertas enviadas: confirmar con doble clic crea una sola compra', async ({ page }) => {
    test.skip(!CONFIRM, 'Solo con ML_COMPRADOR_CONFIRM=1 (crea una compra)')
    await page.goto(URLS.ofertas + '?estado=accepted_pending_confirmation')
    await settle(page)
    const btn = page.locator('#ml-ofertas tbody tr[data-status="accepted_pending_confirmation"]').first()
      .getByRole('button', { name: 'Confirmar compra' }).locator('visible=true').first()
    test.skip(!(await btn.count()), 'Sin ofertas aceptadas por confirmar')
    let posts = 0
    page.on('request', (r) => { if (r.method() === 'POST' && r.url().includes('offers-sent')) posts++ })
    await Promise.all([
      page.waitForURL(/dashboard\/purchases\/[0-9a-f-]{36}$/),
      btn.dblclick(),
    ])
    expect(posts).toBe(1)
    await settle(page)
    await expect(page.locator('[data-test="purchase-offer"]')).toContainText('Confirmada')
  })

  test('URLs antiguas del comprador redirigen', async ({ page }) => {
    for (const [from, to] of [['purchases', /purchases\/purchases$/], ['purchases/list', /purchases\/purchases$/], ['purchases/questions', /purchases\/inquiries$/]]) {
      await page.goto(D + from)
      await expect(page).toHaveURL(to)
    }
  })
})
