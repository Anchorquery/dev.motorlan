// Administración (fase 5): publicaciones, aprobaciones y vista previa. Capturas junto a las
// de la SPA (admin-publicaciones.png ↔ portal-admin-publicaciones.png…).
//
//   ML_PORTAL_BASE       sitio LOCAL con la sección admin encendida (def. https://dev.motorlan.test).
//   ML_ADMIN_USER/PASS   administrador de prueba de la BD local (nunca uno real).
//   ML_NOADMIN_USER/PASS usuario sin manage_options (comprueba el 403).
//   Hacen falta publicaciones pendientes de prueba cuyo título contenga «prueba fase 5» (de
//   otro usuario), una de ellas con marca propuesta (_pending_brand_name), y otra con
//   «prueba fase 5 borrar» en el título: el test de eliminar la BORRA de verdad (DELETE es
//   definitivo); sin ella, esa parte se omite. El resto de cambios se deshacen: cambian el
//   estado (también a la papelera) y lo devuelven a pendiente, rechazan una y la devuelven a
//   pendiente, y corrigen la marca y la restauran. No aprueban (crearía la marca).
//   ML_ADMIN_SPA=1       captura las referencias de la SPA con la sección admin APAGADA.
import { test, expect } from '@playwright/test'

const BASE = process.env.ML_PORTAL_BASE || 'https://dev.motorlan.test'
const ADMIN = [process.env.ML_ADMIN_USER || '', process.env.ML_ADMIN_PASS || '']
const NOADMIN = [process.env.ML_NOADMIN_USER || '', process.env.ML_NOADMIN_PASS || '']
const SPA_MODE = process.env.ML_ADMIN_SPA === '1'
const PUBS = BASE + '/mi-cuenta/dashboard/admin/publications'
const APPROVALS = BASE + '/mi-cuenta/dashboard/admin/approvals'
const SEED = 'prueba fase 5'

const HIDE = `.xdebug-error, body > b:first-child, body > br { display: none !important; }
  #cookie-law-info-bar, .cky-consent-container, .cky-overlay { display: none !important; }`

async function settle(page) {
  await page.addStyleTag({ content: HIDE })
  await page.waitForLoadState('networkidle', { timeout: 20_000 }).catch(() => {})
  await page.waitForFunction(() => !document.querySelector('.v-skeleton-loader, .v-progress-circular'), null, { timeout: 15_000 }).catch(() => {})
  await page.evaluate(() => document.fonts.ready)
  await page.waitForTimeout(300)
}

async function login(page, [user, pass]) {
  await page.goto(BASE + '/wp-login.php')
  await page.fill('#user_login', user)
  await page.fill('#user_pass', pass)
  await page.click('#wp-submit')
  // A un admin WordPress le puede pedir que confirme el email del sitio: ya hay sesión, se sigue.
  await page.waitForURL((u) => !u.pathname.includes('wp-login') || u.search.includes('confirm_admin_email'), { timeout: 45_000 })
}

const volatile = (page) => [page.locator('img'), page.locator('.v-avatar img, .v-img')]

/** Respuesta HTMX a un POST de la pantalla con su aviso (HX-Trigger). */
const posted = (page, url) => page.waitForResponse((r) => r.request().method() === 'POST' && r.url().startsWith(url))

/** Filtra la tabla de publicaciones por las de prueba y un estado. */
async function filterPubs(page, estado, q = SEED) {
  const done = page.waitForResponse((r) => r.url().startsWith(PUBS) && r.request().method() === 'GET' && r.url().includes('q='))
  await page.fill('#ml-admin-pubs-q', q)
  if (estado !== undefined) await page.selectOption('#ml-admin-pubs-estado', estado)
  await done
  await page.waitForTimeout(400) // último cambio (retardo 350 ms)
  await page.waitForLoadState('networkidle')
}

async function setStatus(page, id, status) {
  await page.locator(`#ml-admin-pubs tr[data-id="${id}"] button[aria-label="Cambiar estado"]`).click()
  const dlg = page.locator('#ml-overlays .ml-dialog').filter({ hasText: 'Cambiar estado' })
  await expect(dlg).toBeVisible()
  await dlg.locator('select[name="status"]').selectOption(status)
  const res = posted(page, PUBS)
  await dlg.locator('button[type="submit"]').click()
  const r = await res
  expect(r.headers()['x-ml-done']).toBe('1')
  await expect(dlg).toBeHidden()
}

test.describe('Administración del portal', () => {
  test.skip(!ADMIN[0] || !ADMIN[1], 'Sin ML_ADMIN_USER / ML_ADMIN_PASS')
  test.skip(SPA_MODE, 'Modo referencia de la SPA')

  test.beforeEach(async ({ page }) => {
    await login(page, ADMIN)
    const res = await page.goto(PUBS)
    const html = res ? await res.text() : ''
    test.skip(!html.includes('id="ml-admin-pubs"'), 'Sección admin apagada')
  })

  test('publicaciones: menú, filtros, captura', async ({ page, isMobile }) => {
    await settle(page)
    if (!isMobile) {
      await expect(page.locator('.ml-nav__section', { hasText: 'Administración' })).toBeVisible()
      await expect(page.locator('.ml-nav__link.is-active')).toContainText('Publicaciones')
    } else {
      await expect(page.locator('.ml-dnav')).toBeVisible()
      await expect(page.locator('.ml-dnav a[aria-current="page"], .ml-dnav .is-active').first()).toContainText(/Anuncios/)
    }
    await expect(page.locator('#ml-admin-pubs tbody tr[data-id]').first()).toBeVisible()
    await expect(page).toHaveScreenshot('portal-admin-publicaciones.png', { mask: volatile(page) })

    await page.evaluate(() => { window.__mlNoReload = 1 })
    await filterPubs(page, 'pending')
    const rows = page.locator('#ml-admin-pubs tbody tr[data-id]')
    expect(await rows.count()).toBeGreaterThan(0)
    for (const chip of await rows.locator('td[data-label="Estado"]').allInnerTexts()) expect(chip.trim()).toBe('Pendiente')
    expect(await page.evaluate(() => window.__mlNoReload)).toBe(1) // filtros por HTMX, sin recargar
  })

  test('publicaciones: estado, eliminar y contactar', async ({ page, isMobile }) => {
    test.skip(isMobile, 'Las escrituras se prueban una vez (escritorio)')
    await settle(page)
    await filterPubs(page, 'pending')
    const id = await page.locator('#ml-admin-pubs tbody tr[data-id]').last().getAttribute('data-id')

    // Estado → Borrador y de vuelta a Pendiente.
    await setStatus(page, id, 'draft')
    await page.selectOption('#ml-admin-pubs-estado', '')
    await page.waitForLoadState('networkidle')
    await expect(page.locator(`#ml-admin-pubs tr[data-id="${id}"] td[data-label="Estado"]`)).toHaveText(/Borrador/)
    await setStatus(page, id, 'pending')
    await expect(page.locator(`#ml-admin-pubs tr[data-id="${id}"] td[data-label="Estado"]`)).toHaveText(/Pendiente/)

    // Papelera (recuperable) por el estado y de vuelta.
    await setStatus(page, id, 'trash')
    await page.selectOption('#ml-admin-pubs-estado', 'trash')
    await page.waitForLoadState('networkidle')
    await expect(page.locator(`#ml-admin-pubs tr[data-id="${id}"] td[data-label="Estado"]`)).toHaveText(/Papelera/)
    await setStatus(page, id, 'pending')

    // Eliminar pide confirmación y avisa de que es definitivo; cancelar no borra.
    await page.selectOption('#ml-admin-pubs-estado', 'pending')
    await page.waitForLoadState('networkidle')
    const del = page.locator('#ml-overlays .ml-dialog').filter({ hasText: '¿Eliminar esta publicación?' })
    await page.locator(`#ml-admin-pubs tr[data-id="${id}"] button[aria-label="Eliminar"]`).click()
    await expect(del).toBeVisible()
    await expect(del).toContainText('definitivamente')
    await del.getByRole('button', { name: 'Cancelar' }).click()
    await expect(del).toBeHidden()
    await expect(page.locator(`#ml-admin-pubs tr[data-id="${id}"]`)).toHaveCount(1)

    // Borrado real solo de la publicación desechable («prueba fase 5 borrar»).
    await filterPubs(page, '', SEED + ' borrar')
    const victim = page.locator('#ml-admin-pubs tbody tr[data-id]')
    if (await victim.count() === 1) {
      const vid = await victim.getAttribute('data-id')
      await victim.locator('button[aria-label="Eliminar"]').click()
      const res = posted(page, PUBS)
      await del.locator('button[type="submit"]').click()
      expect((await res).headers()['x-ml-done']).toBe('1')
      await expect(del).toBeHidden()
      await expect(page.locator('.ml-toast')).toContainText(/eliminada/i)
      await expect(page.locator(`#ml-admin-pubs tr[data-id="${vid}"]`)).toHaveCount(0)
    }
    await filterPubs(page, 'pending')

    // Contactar: asunto por defecto, mensaje obligatorio. En local wp_mail puede fallar: el
    // diálogo solo se cierra si salió bien; en ambos casos llega un aviso.
    await page.locator(`#ml-admin-pubs tr[data-id="${id}"] button[aria-label="Contactar"]`).click()
    const contact = page.locator('#ml-overlays .ml-dialog').filter({ hasText: 'Contactar al anunciante' })
    await expect(contact).toBeVisible()
    await expect(contact.locator('input[name="subject"]')).toHaveValue('Mensaje del Administrador - Motorlan')
    await expect(contact.locator('button[type="submit"]')).toBeDisabled()
    await contact.locator('textarea[name="message"]').fill('Mensaje de prueba (local) de la fase 5.')
    const sent = posted(page, PUBS)
    await contact.locator('button[type="submit"]').click()
    const r = await sent
    if (r.headers()['x-ml-done'] === '1') await expect(contact).toBeHidden()
    else await expect(contact).toBeVisible()
    expect(r.headers()['hx-trigger']).toContain('ml:toast')
  })

  test('aprobaciones: listado, rechazar con motivo', async ({ page, isMobile }) => {
    await page.goto(APPROVALS)
    await settle(page)
    const rows = page.locator('#ml-admin-pending tbody tr[data-id]')
    await expect(rows.first()).toBeVisible()
    await expect(page.locator('#ml-admin-pending .ml-chip', { hasText: /MOTORES PRUEBA/i }).first()).toBeVisible()
    await expect(page).toHaveScreenshot('portal-admin-aprobaciones.png', { mask: volatile(page) })
    if (isMobile) return

    // Rechazar: el botón no se activa sin motivo; al confirmar, sale de la lista (a borrador).
    const id = await rows.last().getAttribute('data-id')
    await rows.last().getByRole('button', { name: 'Rechazar' }).click()
    const dlg = page.locator('#ml-overlays .ml-dialog').filter({ hasText: 'Rechazar publicación' })
    await expect(dlg).toBeVisible()
    await expect(dlg.locator('button[type="submit"]')).toBeDisabled()
    await dlg.locator('textarea[name="reason"]').fill('Prueba local: faltan datos técnicos.')
    const res = posted(page, APPROVALS)
    await dlg.locator('button[type="submit"]').click()
    expect((await res).headers()['x-ml-done']).toBe('1')
    await expect(dlg).toBeHidden()
    await expect(page.locator(`#ml-admin-pending tr[data-id="${id}"]`)).toHaveCount(0)

    // Se devuelve a pendiente desde Publicaciones.
    await page.goto(PUBS + '?estado=draft&q=' + encodeURIComponent(SEED))
    await settle(page)
    await setStatus(page, id, 'pending')
  })

  test('vista previa: ficha, marca propuesta, sin barra móvil', async ({ page, isMobile }) => {
    await page.goto(APPROVALS)
    const link = page.locator('#ml-admin-pending tr', { has: page.locator('.ml-chip', { hasText: /MOTORES PRUEBA/i }) }).locator('a[aria-label="Previsualizar"]').first()
    await link.click()
    await page.waitForURL(/\/approvals\/preview\//)
    await settle(page)
    await expect(page.locator('.ml-ficha__title')).toBeVisible()
    await expect(page.locator('.ml-gallery')).toBeVisible()
    await expect(page.locator('.ml-detail')).toBeVisible()
    await expect(page.getByText('Solicitante de la publicación')).toBeVisible()
    await expect(page.getByText('Nueva marca pendiente de aprobación')).toBeVisible()
    await expect(page.locator('.ml-dnav')).toHaveCount(0)
    await expect(page).toHaveScreenshot('portal-admin-vista-previa.png', { mask: volatile(page), fullPage: true })
    if (isMobile) return

    // Corregir la marca y restaurarla.
    const original = (await page.locator('#ml-admin-brand .ml-chip').innerText()).trim()
    for (const name of ['MOTORES PRUEBA EDITADA', original]) {
      await page.getByRole('button', { name: 'Editar nombre' }).click()
      await page.fill('#ml-admin-brand-name', name.toLowerCase())
      await expect(page.locator('#ml-admin-brand-name')).toHaveValue(name) // se escribe en mayúsculas
      await Promise.all([page.waitForURL(/ok=brand|\/preview\/[^?]+$/), page.locator('#ml-admin-brand button[type="submit"]').click()])
      await expect(page.locator('#ml-admin-brand .ml-chip')).toHaveText(name)
    }
    // Rechazar sin motivo no se puede; Volver lleva al listado.
    await page.getByRole('button', { name: 'Rechazar' }).first().click()
    const dlg = page.locator('#ml-overlays .ml-dialog').filter({ hasText: 'Rechazar publicación' })
    await expect(dlg.locator('button[type="submit"]')).toBeDisabled()
    await dlg.getByRole('button', { name: 'Cancelar' }).click()
    await page.getByRole('link', { name: 'Volver' }).click()
    await expect(page).toHaveURL(/\/admin\/approvals\/?$/)
  })

  test('vista previa: uuid inexistente', async ({ page }) => {
    const res = await page.goto(APPROVALS + '/preview/00000000-0000-0000-0000-000000000000')
    expect(res.status()).toBe(404)
    await expect(page.getByText('Publicación no encontrada')).toBeVisible()
  })
})

test.describe('Administración: sin permiso', () => {
  test.skip(!NOADMIN[0] || !NOADMIN[1] || SPA_MODE, 'Sin ML_NOADMIN_USER / ML_NOADMIN_PASS')

  test('un usuario sin manage_options recibe 403', async ({ page, isMobile }) => {
    await login(page, NOADMIN)
    for (const url of [PUBS, APPROVALS, APPROVALS + '/preview/00000000-0000-0000-0000-000000000000']) {
      const res = await page.goto(url)
      expect(res.status(), url).toBe(403)
    }
    // Un POST directo (sin pasar por la pantalla) también.
    const post = await page.request.post(APPROVALS, { form: { action: 'approve', id: '1' } })
    expect(post.status()).toBe(403)
    if (!isMobile) {
      await page.goto(BASE + '/mi-cuenta/dashboard/user/account')
      await expect(page.locator('.ml-nav__section', { hasText: 'Administración' })).toHaveCount(0)
    }
  })
})

// Referencias de la SPA (sección admin apagada): ML_ADMIN_SPA=1.
test.describe('Administración de la SPA (referencia)', () => {
  test.skip(!ADMIN[0] || !ADMIN[1] || !SPA_MODE, 'Solo con ML_ADMIN_SPA=1 y credenciales')

  test('publicaciones, aprobaciones y vista previa (SPA)', async ({ page }) => {
    test.setTimeout(180_000) // tres pantallas de la SPA, cada una con su carga del bundle
    await login(page, ADMIN)
    await page.goto(PUBS)
    await settle(page)
    await expect(page).toHaveScreenshot('admin-publicaciones.png', { mask: volatile(page) })
    await page.goto(APPROVALS)
    await settle(page)
    await expect(page).toHaveScreenshot('admin-aprobaciones.png', { mask: volatile(page) })
    await page.locator('.v-data-table tbody tr').filter({ hasText: /MOTORES PRUEBA/i }).locator('button').last().dispatchEvent('click')
    await page.waitForURL(/\/approvals\/preview\//)
    await settle(page)
    await expect(page).toHaveScreenshot('admin-vista-previa.png', { mask: volatile(page), fullPage: true })
  })
})
