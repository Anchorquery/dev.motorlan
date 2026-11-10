// Catálogo del portal en la vista previa sin WordPress (scripts/preview-ui-kit.php), para
// ponerlo al lado de las referencias de la SPA y detectar regresiones de los componentes.
//   php -S 127.0.0.1:8765 scripts/preview-ui-kit.php   (en otra terminal)
import { test, expect } from '@playwright/test'

const BASE = process.env.ML_PREVIEW_BASE || 'http://127.0.0.1:8765'

test.describe('Catálogo del portal (vista previa)', () => {
  test.beforeAll(async ({ request }) => {
    const ok = await request.get(BASE + '/', { timeout: 3_000 }).then((r) => r.ok()).catch(() => false)
    test.skip(!ok, `Vista previa no disponible en ${BASE}`)
  })

  for (const [name, path] of [['ui-kit', '/'], ['ui-kit-auth', '/auth/'], ['ui-kit-public', '/public/']]) {
    test(name, async ({ page }) => {
      await page.goto(BASE + path)
      await page.waitForLoadState('networkidle')
      await page.evaluate(() => document.fonts.ready)
      await expect(page).toHaveScreenshot(`${name}.png`, { fullPage: true })
    })
  }
})
