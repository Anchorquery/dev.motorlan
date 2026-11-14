// Panel del vendedor (fase 4): Mis publicaciones, alta/edición, ventas, ofertas recibidas
// e interesados. Capturas junto a las de la SPA (publicaciones.png ↔ portal-publicaciones.png…).
//
//   ML_PORTAL_BASE     sitio local con las secciones publicaciones, ventas, ofertas e
//                      interesados encendidas (def. https://dev.motorlan.test). Si no, se omite.
//   ML_PORTAL_USER / ML_PORTAL_PASS   vendedor de prueba de la BD LOCAL (nunca uno real), o
//   ML_PORTAL_COOKIES  ruta a un JSON de cookies de sesión de ese usuario (para un servidor de
//                      pruebas sin formulario de acceso).
//   ML_VENDOR_SPA=1    captura las referencias de la SPA con las secciones APAGADAS.
//   ML_F4_ROOM         room_key de una consulta de un comprador sobre una publicación con stock
//                      (prueba de «Vender» + valoración; sin ella se omite).
//   ML_F4_OFFER_PUB    título de la publicación con una oferta pendiente que se puede rechazar
//                      (sin ella se omite).
//   ML_F4_EDIT_REF     referencia de una publicación publicada del vendedor para editar,
//                      duplicar y borrar la copia (sin ella se omite).
// Escrituras (solo en escritorio y solo en la BD local): alta completa con fotos y PDF (queda en
// revisión), edición con un PDF, duplicar y borrar la copia, rechazo de una oferta y una venta
// manual con su valoración.
import fs from 'node:fs'
import { test, expect } from '@playwright/test'

const BASE = process.env.ML_PORTAL_BASE || 'https://dev.motorlan.test'
const USER = process.env.ML_PORTAL_USER || ''
const PASS = process.env.ML_PORTAL_PASS || ''
const COOKIES = process.env.ML_PORTAL_COOKIES || ''
const SPA_MODE = process.env.ML_VENDOR_SPA === '1'
const ROOM = process.env.ML_F4_ROOM || ''
const OFFER_PUB = process.env.ML_F4_OFFER_PUB || ''
const EDIT_REF = process.env.ML_F4_EDIT_REF || ''
const D = BASE + '/mi-cuenta/dashboard/'
const LIST = D + 'publications/publication/list'
const ADD = D + 'publications/publication/add'
const SALES = D + 'publications/sales'
const OFFERS = D + 'publications/offers-received'
const INQ = D + 'inquiries'

const HIDE = `.xdebug-error, body > b:first-child, body > br { display: none !important; }
  #cookie-law-info-bar, .cky-consent-container, .cky-overlay { display: none !important; }`

// PNG 1×1 y PDF mínimo: suficientes para media_handle_upload.
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64')
const PDF = Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 200 200]/Parent 2 0 R>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n')

async function settle(page) {
  await page.addStyleTag({ content: HIDE })
  await page.waitForLoadState('networkidle', { timeout: 20_000 }).catch(() => {})
  await page.waitForFunction(() => !document.querySelector('.v-skeleton-loader, .v-progress-circular, .htmx-request'), null, { timeout: 15_000 }).catch(() => {})
  await page.evaluate(() => document.fonts.ready)
  await page.waitForTimeout(300)
}

async function auth(page) {
  if (COOKIES) {
    await page.context().addCookies(JSON.parse(fs.readFileSync(COOKIES, 'utf8')))
    return
  }
  await page.goto(BASE + '/wp-login.php')
  await page.fill('#user_login', USER)
  await page.fill('#user_pass', PASS)
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')])
}

const volatile = (page) => [page.locator('img'), page.locator('time'), page.locator('.v-avatar img, .v-img')]
const toastText = (page) => page.locator('.ml-toast, [role="status"]').first()

// Rellena un campo por name, sea input, textarea o select (valor u opción por índice).
async function setField(page, name, value) {
  const el = page.locator(`[name="${name}"]:visible`).first()
  const tag = await el.evaluate((n) => n.tagName)
  if (tag === 'SELECT') {
    if (typeof value === 'number') {
      const v = await el.evaluate((n, i) => n.options[i] && n.options[i].value, value)
      await el.selectOption(v)
    } else await el.selectOption(value)
  } else await el.fill(String(value))
}

test.describe('Referencias de la SPA (vendedor)', () => {
  test.skip(!SPA_MODE, 'Solo con ML_VENDOR_SPA=1')
  test.skip(!COOKIES && (!USER || !PASS), 'Sin credenciales')

  test('capturas de la SPA', async ({ page }) => {
    await auth(page)
    for (const [url, name] of [[LIST, 'publicaciones'], [ADD, 'publicacion-alta'], [SALES, 'ventas'], [OFFERS, 'ofertas-recibidas'], [INQ, 'interesados']]) {
      await page.goto(url)
      await page.waitForSelector('.v-card, .v-data-table, .v-stepper, main', { timeout: 30_000 })
      await settle(page)
      await expect(page).toHaveScreenshot(name + '.png', { fullPage: true, mask: volatile(page) })
    }
  })
})

test.describe('Panel del vendedor en el portal', () => {
  test.skip(SPA_MODE, 'Modo referencia de la SPA')
  test.skip(!COOKIES && (!USER || !PASS), 'Sin ML_PORTAL_USER / ML_PORTAL_PASS ni ML_PORTAL_COOKIES')

  test.beforeEach(async ({ page }) => {
    await auth(page)
    const res = await page.goto(LIST)
    const html = res ? await res.text() : ''
    test.skip(!html.includes('id="ml-pubs"'), 'Sección publicaciones apagada')
  })

  test('Mis publicaciones: tabla, filtros, menú de fila y exportar', async ({ page, isMobile }) => {
    await settle(page)
    await expect(page.locator('#ml-pubs tbody tr').first()).toBeVisible()
    await expect(page).toHaveScreenshot('portal-publicaciones.png', { fullPage: true, mask: volatile(page) })

    // Filtro por estado con HTMX (reemplaza la tabla sin recargar).
    const swapped = page.waitForResponse((r) => r.url().includes('publication/list') && r.request().headers()['hx-request'] === 'true')
    await page.selectOption('#ml-pubs-filters [name="estado"]', 'publish')
    expect((await swapped).ok()).toBeTruthy()
    await settle(page)
    await expect(page.locator('#ml-pubs .ml-chip', { hasText: /Pausad|Borrador/ })).toHaveCount(0)

    // Menú «⋮» de la primera fila.
    const menu = page.locator('#ml-pubs details.ml-row-menu').first()
    await menu.locator('summary').click()
    await expect(menu.locator('button', { hasText: 'Duplicar' })).toBeVisible()
    await page.keyboard.press('Escape')

    // CSV con los filtros en pantalla.
    if (!isMobile) {
      const href = await page.locator('[data-ml-export]').evaluate((a) => { a.click(); return a.href })
      const csv = await page.request.get(href)
      expect(csv.headers()['content-type']).toContain('text/csv')
      expect((await csv.text()).split('\n')[0]).toContain('Publicación')
    }
  })

  test('alta completa con fotos y PDF', async ({ page, isMobile }) => {
    await page.goto(ADD)
    await settle(page)
    await expect(page.locator('.ml-pubform')).toBeVisible()
    await expect(page).toHaveScreenshot('portal-publicacion-alta.png', { fullPage: true, mask: volatile(page) })
    if (isMobile) return // las escrituras, solo en escritorio
    page.on('dialog', (d) => d.accept())

    const ref = 'F4-E2E-' + Date.now().toString(36).toUpperCase()
    // 0 · Tipo (avanza solo).
    await page.locator('input[name="tipo_ui"][value="motor-ac"]').check({ force: true })
    // 1 · Básico: Siguiente sin datos → errores del paso.
    await page.getByRole('button', { name: 'Siguiente' }).click()
    await expect(page.locator('.ml-hint--error:visible').first()).toBeVisible()
    await setField(page, 'acf[marca]', 2)
    await setField(page, 'acf[tipo_o_referencia]', ref)
    await page.getByRole('button', { name: 'Siguiente' }).click()
    // 2 · Especificaciones.
    await setField(page, 'acf[potencia]', 7.5)
    await setField(page, 'acf[velocidad]', 1500)
    await page.getByRole('button', { name: 'Siguiente' }).click()
    // 3 · Fotos: principal + 2 de galería (selector JS, sin name).
    const pickers = page.locator('.ml-pubform input[type="file"][accept="image/*"]:not([name])')
    await pickers.nth(0).setInputFiles({ name: 'principal.png', mimeType: 'image/png', buffer: PNG })
    await pickers.nth(1).setInputFiles([
      { name: 'galeria-1.png', mimeType: 'image/png', buffer: PNG },
      { name: 'galeria-2.png', mimeType: 'image/png', buffer: PNG },
    ])
    await page.getByRole('button', { name: 'Siguiente' }).click()
    // 4 · Detalles.
    await setField(page, 'acf[descripcion]', 'Motor de prueba del portal (fase 4).')
    await setField(page, 'acf[estado_del_articulo]', 1)
    await setField(page, 'acf[pais]', 1)
    await setField(page, 'acf[provincia]', 'Madrid')
    await setField(page, 'acf[precio_de_venta]', 1234)
    await setField(page, 'acf[stock]', 2)
    await page.getByRole('button', { name: 'Siguiente' }).click()
    // 5 · Documentación: un PDF.
    await page.getByRole('button', { name: 'Añadir documento' }).click()
    await page.locator('.ml-pubform input[type="text"][id^="ml-pf-doc-"]').last().fill('Ficha técnica')
    await page.locator('.ml-pubform input[type="file"][accept^=".pdf"]:not([name])').last().setInputFiles({ name: 'ficha.pdf', mimeType: 'application/pdf', buffer: PDF })
    await expect(page).toHaveScreenshot('portal-publicacion-alta-docs.png', { mask: volatile(page) })

    const created = page.waitForResponse((r) => r.url().includes('publication/add') && r.request().method() === 'POST')
    await page.locator('.ml-pubform button[type="submit"]:visible').click()
    const res = await created
    const out = await res.json()
    expect(out.ok, out.message).toBeTruthy()
    await page.waitForURL(/publication\/list/)
    await settle(page)
    const row = page.locator('#ml-pubs tbody tr', { hasText: ref })
    await expect(row).toBeVisible()
    await expect(row.locator('.ml-chip')).toContainText(/revisión|Publicad/i)

    // Pendiente de revisión: el vendedor no la edita ni la borra (como la SPA).
    await expect(row.locator('a[href*="publication/edit/"]')).toHaveCount(0)
  })

  test('edición (pestañas, PDF, precio), duplicar y borrar la copia', async ({ page, isMobile }) => {
    test.skip(!EDIT_REF, 'Sin ML_F4_EDIT_REF')
    await page.goto(LIST + '?estado=publish&per_page=50')
    await settle(page)
    const row = page.locator('#ml-pubs tbody tr', { hasText: EDIT_REF }).first()
    await row.locator('a[href*="publication/edit/"]').click()
    await page.waitForURL(/publication\/edit\//)
    await settle(page)
    await expect(page.locator('input[name="acf[tipo_o_referencia]"]')).toHaveValue(EDIT_REF)
    await expect(page).toHaveScreenshot('portal-publicacion-editar.png', { fullPage: true, mask: volatile(page) })
    if (isMobile) return // las escrituras, solo en escritorio

    const price = String(700 + Math.floor(Math.random() * 99))
    await page.getByRole('tab', { name: 'Información básica' }).click()
    await setField(page, 'acf[precio_de_venta]', price)
    await page.getByRole('tab', { name: 'Documentación' }).click()
    await page.getByRole('button', { name: 'Añadir documento' }).click()
    await page.locator('.ml-pubform input[type="text"][id^="ml-pf-doc-"]').last().fill('Ficha ' + price)
    await page.locator('.ml-pubform input[type="file"][accept^=".pdf"]:not([name])').last().setInputFiles({ name: 'ficha-' + price + '.pdf', mimeType: 'application/pdf', buffer: PDF })
    const saved = page.waitForResponse((r) => r.url().includes('publication/edit/') && r.request().method() === 'POST')
    // Tras guardar se recarga el mismo editor (misma URL): esperar a esa carga.
    const reloaded = page.waitForEvent('load')
    await page.getByRole('button', { name: 'Actualizar publicación' }).click()
    const out = await (await saved).json()
    expect(out.ok, out.message).toBeTruthy()
    await reloaded
    await settle(page)
    await expect(page.locator('input[name="acf[precio_de_venta]"]')).toHaveValue(price)
    await page.getByRole('tab', { name: 'Documentación' }).click()
    await expect(page.locator('.ml-pubform input[type="text"][id^="ml-pf-doc-"]').last()).toHaveValue('Ficha ' + price)

    // Duplicar → editor de la copia (borrador) → borrarla desde el listado.
    await page.goto(LIST + '?estado=publish&per_page=50')
    await settle(page)
    const r1 = page.locator('#ml-pubs tbody tr', { hasText: EDIT_REF }).first()
    await r1.locator('details.ml-row-menu summary').click()
    await r1.locator('details.ml-row-menu button', { hasText: 'Duplicar' }).click()
    const dialog = page.locator('#ml-overlays .ml-dialog:visible')
    await expect(dialog).toContainText('Duplicar publicación')
    await Promise.all([page.waitForURL(/publication\/edit\//), dialog.locator('button[type="submit"]').click()])
    await page.goto(LIST + '?estado=draft&per_page=50')
    await settle(page)
    const copies = page.locator('#ml-pubs tbody tr', { hasText: EDIT_REF })
    const before = await copies.count()
    expect(before).toBeGreaterThan(0)
    await copies.first().locator('details.ml-row-menu summary').click()
    await copies.first().locator('details.ml-row-menu button', { hasText: 'Eliminar' }).click()
    await expect(dialog).toContainText('Eliminar publicación')
    await dialog.locator('button[type="submit"]').click()
    await expect(toastText(page)).toBeVisible()
    await expect(page.locator('#ml-pubs tbody tr', { hasText: EDIT_REF })).toHaveCount(before - 1)
  })

  test('ventas: listado, detalle y chat', async ({ page }) => {
    await page.goto(SALES)
    await settle(page)
    await expect(page).toHaveScreenshot('portal-ventas.png', { fullPage: true, mask: volatile(page) })
    const first = page.locator('#ml-sales tbody tr a[href*="/sales/"]').first()
    test.skip(!(await first.count()), 'Sin ventas de prueba')
    await first.click()
    await page.waitForURL(/publications\/sales\/[a-f0-9-]+/)
    await settle(page)
    await expect(page.getByText('Información del comprador')).toBeVisible()
    await expect(page).toHaveScreenshot('portal-venta.png', { fullPage: true, mask: volatile(page) })
    await page.getByRole('button', { name: 'Ver chat con el comprador' }).click()
    await expect(page.locator('#ml-overlays .ml-dialog:visible')).toContainText('Chat con el comprador')
  })

  test('ofertas recibidas: ?offer_id abre el detalle y rechazar', async ({ page, isMobile }) => {
    await page.goto(OFFERS)
    await settle(page)
    await expect(page).toHaveScreenshot('portal-ofertas-recibidas.png', { fullPage: true, mask: volatile(page) })
    const eye = page.locator('#ml-offers tbody tr button[aria-label="Ver detalle"]').first()
    test.skip(!(await eye.count()), 'Sin ofertas de prueba')
    const id = JSON.parse(await eye.getAttribute('@click').then((s) => s.slice(s.indexOf('{'), s.lastIndexOf('}') + 1))).id

    await page.goto(OFFERS + '?offer_id=' + id)
    await settle(page)
    const dialog = page.locator('#ml-overlays .ml-dialog:visible')
    await expect(dialog).toContainText('Detalle de la oferta')
    expect(page.url()).not.toContain('offer_id')
    await expect(page).toHaveScreenshot('portal-oferta-detalle.png', { mask: volatile(page) })
    await page.keyboard.press('Escape')

    if (isMobile || !OFFER_PUB) return // rechazar: solo en escritorio y con ML_F4_OFFER_PUB
    const row = page.locator('#ml-offers tbody tr', { hasText: OFFER_PUB }).filter({ hasText: 'Pendiente' }).first()
    await row.locator('button[aria-label="Rechazar"]').click()
    await expect(toastText(page)).toBeVisible()
    await settle(page)
    await expect(page.locator('#ml-offers tbody tr', { hasText: OFFER_PUB }).filter({ hasText: 'Rechazada' }).first()).toBeVisible()
  })

  test('interesados: ?room_key abre el chat; vender y valorar', async ({ page, isMobile }) => {
    await page.goto(INQ)
    await settle(page)
    await expect(page).toHaveScreenshot('portal-interesados.png', { fullPage: true, mask: volatile(page) })
    test.skip(!ROOM, 'Sin ML_F4_ROOM')

    await page.goto(INQ + '?room_key=' + encodeURIComponent(ROOM))
    await settle(page)
    const chat = page.locator('#ml-overlays .ml-dialog:visible')
    await expect(chat).toContainText('(prueba F4)')
    expect(page.url()).not.toContain('room_key')
    await expect(page).toHaveScreenshot('portal-interesados-chat.png', { mask: volatile(page) })
    if (isMobile) return // la venta, solo en escritorio

    await chat.getByRole('button', { name: 'Vender' }).click()
    const sale = page.locator('#ml-overlays .ml-dialog:visible', { hasText: 'Registrar venta' })
    await sale.locator('input[name="amount"]').fill('abc')
    await sale.locator('button[type="submit"]').click()
    await expect(sale.locator('.ml-hint--error')).toHaveText('Importe no válido')
    await sale.locator('input[name="amount"]').fill('1.234,50')
    const sold = page.waitForResponse((r) => r.url().includes('/dashboard/inquiries') && r.request().method() === 'POST')
    await sale.locator('button[type="submit"]').click()
    const out = await (await sold).json()
    expect(out.ok, out.message).toBeTruthy()

    const rate = page.locator('#ml-overlays .ml-dialog:visible', { hasText: 'Valora al comprador' })
    await expect(rate).toBeVisible()
    await rate.locator('label:has(input[name="rating"][value="5"])').click()
    await rate.locator('textarea[name="comment"]').fill('Comprador de prueba muy correcto (F4).')
    await Promise.all([page.waitForURL(/dashboard\/inquiries/), rate.locator('button[type="submit"]').click()])
    await expect(toastText(page)).toBeVisible()
  })
})
