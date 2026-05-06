import { mkdir, rm, writeFile } from 'node:fs/promises'
import path from 'node:path'

const API_BASE = process.env.MOTORLAN_API_BASE || 'https://www.motorlan.es'
const ROUTE_PREFIX = 'public-store'
const OUTPUT_DIR = path.resolve(process.cwd(), 'public-store')
const ASSET_BASE = process.env.MOTORLAN_ASSET_BASE || '/wp-content/plugins/motorlan-api-vue/app/dist'

const renderTemplate = (routeHash) => {
  const hash = `#/` + routeHash.replace(/^\/+/, '')
  return `<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Tienda Motorlan</title>
  <link rel="stylesheet" href="${ASSET_BASE}/css/style.css" />
  <link rel="stylesheet" href="${ASSET_BASE}/loader.css" />
</head>
<body>
  <div id="motorlan-app" class="motorlan-app-root"></div>
  <script>
    if (!window.location.hash || window.location.hash === '#/' || window.location.hash === '') {
      window.location.hash = '${hash}'
    }
  </script>
  <script type="module" src="${ASSET_BASE}/js/app.js"></script>
</body>
</html>`
}

const fetchJson = async (url) => {
  const response = await fetch(url)
  if (!response.ok) {
    throw new Error(`Failed to fetch ${url} (${response.status})`)
  }
  return response.json()
}

const collectSlugs = async () => {
  const firstPage = await fetchJson(`${API_BASE}/wp-json/motorlan/v1/store/publicaciones?per_page=30&page=1`)
  const totalPages = firstPage.pagination?.totalPages || 1
  const slugs = new Set(firstPage.data?.map(item => item.slug) || [])

  for (let page = 2; page <= totalPages; page += 1) {
    const pageData = await fetchJson(`${API_BASE}/wp-json/motorlan/v1/store/publicaciones?per_page=30&page=${page}`)
      (pageData.data || []).forEach(entry => entry.slug && slugs.add(entry.slug))
  }

  return Array.from(slugs)
}

const main = async () => {
  await rm(OUTPUT_DIR, { recursive: true, force: true })
  await mkdir(OUTPUT_DIR, { recursive: true })

  await writeFile(path.join(OUTPUT_DIR, 'index.html'), renderTemplate(ROUTE_PREFIX))

  const slugs = await collectSlugs()
  await Promise.all(slugs.map(slug => {
    const slugDir = path.join(OUTPUT_DIR, slug)
    return mkdir(slugDir, { recursive: true }).then(() =>
      writeFile(path.join(slugDir, 'index.html'), renderTemplate(`${ROUTE_PREFIX}/${slug}`))
    )
  }))
}

main().catch(error => {
  console.error(error)
  process.exit(1)
})
