// Capturas de referencia de la SPA actual (solo lectura) y del catálogo del portal.
//
//   npm run test:visual                  compara con las referencias guardadas
//   npm run test:visual:update           regenera las referencias
//
// Variables:
//   ML_E2E_BASE      sitio de la SPA (def. https://www.motorlan.es). Solo se hacen GET y, si hay
//                    credenciales, un inicio de sesión: nada se publica ni se modifica.
//   ML_E2E_USER/PASS cuenta para la captura del panel (sin ellas se omite).
//   ML_PREVIEW_BASE  vista previa del catálogo (def. http://127.0.0.1:8765, `php -S` con
//                    scripts/preview-ui-kit.php). Sin servidor se omite.
//   ML_E2E_CHANNEL   navegador (def. chrome = Chrome instalado; usa "chromium" tras `npx playwright install`).
import { defineConfig, devices } from '@playwright/test'

const channel = process.env.ML_E2E_CHANNEL || 'chrome'
const browser = channel === 'chromium' ? {} : { channel }

export default defineConfig({
  testDir: 'tests/visual',
  outputDir: 'test-results',
  snapshotPathTemplate: '{testDir}/__screenshots__/{projectName}/{arg}{ext}',
  timeout: 60_000,
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
  expect: {
    // Las páginas tienen datos reales (anuncios, precios): margen amplio, la referencia es para el ojo.
    toHaveScreenshot: { maxDiffPixelRatio: 0.08, animations: 'disabled', caret: 'hide' },
  },
  use: {
    baseURL: process.env.ML_E2E_BASE || 'https://www.motorlan.es',
    locale: 'es-ES',
    timezoneId: 'Europe/Madrid',
    ignoreHTTPSErrors: true,
    ...browser,
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'], ...browser, viewport: { width: 1440, height: 900 } } },
    { name: 'mobile', use: { ...devices['Pixel 7'], ...browser } },
  ],
})
