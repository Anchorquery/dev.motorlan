/**
 * This is an advanced example for creating icon bundles for Iconify SVG Framework.
 *
 * It creates a bundle from:
 * - All SVG files in a directory.
 * - Custom JSON files.
 * - Iconify icon sets.
 * - SVG framework.
 *
 * This example uses Iconify Tools to import and clean up icons.
 * For Iconify Tools documentation visit https://docs.iconify.design/tools/tools2/
 */
import { promises as fs } from 'node:fs'
import { createRequire } from 'node:module'
import { basename, dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const require = createRequire(import.meta.url)
const __dirname = dirname(fileURLToPath(import.meta.url))

// Installation: npm install --save-dev @iconify/tools @iconify/utils @iconify/json @iconify/iconify
import { cleanupSVG, importDirectory, isEmptyColor, parseColors, runSVGO } from '@iconify/tools'
import type { IconifyJSON } from '@iconify/types'
import { getIcons, getIconsCSS, stringToIcon } from '@iconify/utils'

/**
 * Script configuration
 */
interface BundleScriptCustomSVGConfig {

  // Path to SVG files
  dir: string

  // True if icons should be treated as monotone: colors replaced with currentColor
  monotone: boolean

  // Icon set prefix
  prefix: string
}

interface BundleScriptCustomJSONConfig {

  // Path to JSON file
  filename: string

  // List of icons to import. If missing, all icons will be imported
  icons?: string[]
}

interface BundleScriptConfig {

  // Custom SVG to import and bundle
  svg?: BundleScriptCustomSVGConfig[]

  // Icons to bundled from @iconify/json packages
  icons?: string[]

  // List of JSON files to bundled
  // Entry can be a string, pointing to filename or a BundleScriptCustomJSONConfig object (see type above)
  // If entry is a string or object without 'icons' property, an entire JSON file will be bundled
  json?: (string | BundleScriptCustomJSONConfig)[]
}

const sources: BundleScriptConfig = {

  svg: [
    // {
    //   dir: 'src/assets/images/iconify-svg',
    //   monotone: true,
    //   prefix: 'custom',
    // },

    // {
    //   dir: 'emojis',
    //   monotone: false,
    //   prefix: 'emoji',
    // },
  ],

  icons: [
    // 'mdi:home',
    // 'mdi:account',
    // 'mdi:login',
    // 'mdi:logout',
    // 'octicon:book-24',
    // 'octicon:code-square-24',
  ],

  json: [
    // Custom JSON file
    // 'json/gg.json',

    // Iconify JSON file (@iconify/json is a package name, /json/ is directory where files are, then filename)
    require.resolve('@iconify-json/tabler/icons.json'),
    {
      filename: require.resolve('@iconify-json/mdi/icons.json'),
      icons: [
        'close-circle',
        'language-javascript',
        'language-typescript',
      ],
    },
    {
      filename: require.resolve('@iconify-json/fa/icons.json'),
      icons: [
        'circle',
      ],
    },

    // Custom file with only few icons
    // {
    //   filename: require.resolve('@iconify-json/line-md/icons.json'),
    //   icons: [
    //     'home-twotone-alt',
    //     'github',
    //     'document-list',
    //     'document-code',
    //     'image-twotone',
    //   ],
    // },
  ],
}

// File to save bundle to
const target = join(__dirname, 'icons.css')

// Production bundle: only the tabler icons referenced in these directories
const usedTarget = join(__dirname, 'icons.used.css')
const usedIconsScanDirs = [
  join(__dirname, '../..'), // app/src
  join(__dirname, '../../../themeConfig.ts'),
  join(__dirname, '../../../../includes'), // PHP (iconos enviados por la API)
]

/**
 * Do stuff!
 */

;(async function () {
  // Create directory for output if missing
  const dir = dirname(target)
  try {
    await fs.mkdir(dir, {
      recursive: true,
    })
  }
  catch (err) {
    //
  }

  const allIcons: IconifyJSON[] = []

  /**
   * Convert sources.icons to sources.json
   */
  if (sources.icons) {
    const sourcesJSON = sources.json ? sources.json : (sources.json = [])

    // Sort icons by prefix
    const organizedList = organizeIconsList(sources.icons)

    for (const prefix in organizedList) {
      const filename = require.resolve(`@iconify/json/json/${prefix}.json`)

      sourcesJSON.push({
        filename,
        icons: organizedList[prefix],
      })
    }
  }

  /**
   * Bundle JSON files and collect icons
   */
  if (sources.json) {
    for (let i = 0; i < sources.json.length; i++) {
      const item = sources.json[i]

      // Load icon set
      const filename = typeof item === 'string' ? item : item.filename
      const content = JSON.parse(await fs.readFile(filename, 'utf8')) as IconifyJSON

      for (const key in content) {
        if (key === 'prefix' && content.prefix === 'tabler') {
          for (const k in content.icons)
            content.icons[k].body = content.icons[k].body.replace(/stroke-width="2"/g, 'stroke-width="1.5"')
        }
      }

      // Filter icons
      if (typeof item !== 'string' && item.icons?.length) {
        const filteredContent = getIcons(content, item.icons)

        if (!filteredContent)
          throw new Error(`Cannot find required icons in ${filename}`)

        // Collect filtered icons
        allIcons.push(filteredContent)
      }
      else {
        // Collect all icons from the JSON file
        allIcons.push(content)
      }
    }
  }

  /**
   * Bundle custom SVG icons and collect icons
   */
  if (sources.svg) {
    for (let i = 0; i < sources.svg.length; i++) {
      const source = sources.svg[i]

      // Import icons
      const iconSet = await importDirectory(source.dir, {
        prefix: source.prefix,
      })

      // Validate, clean up, fix palette, etc.
      await iconSet.forEach(async (name, type) => {
        if (type !== 'icon')
          return

        // Get SVG instance for parsing
        const svg = iconSet.toSVG(name)

        if (!svg) {
          // Invalid icon
          iconSet.remove(name)

          return
        }

        // Clean up and optimise icons
        try {
          // Clean up icon code
          await cleanupSVG(svg)

          if (source.monotone) {
            // Replace color with currentColor, add if missing
            // If icon is not monotone, remove this code
            await parseColors(svg, {
              defaultColor: 'currentColor',
              callback: (attr, colorStr, color) => {
                return !color || isEmptyColor(color) ? colorStr : 'currentColor'
              },
            })
          }

          // Optimise
          await runSVGO(svg)
        }
        catch (err) {
          // Invalid icon
          console.error(`Error parsing ${name} from ${source.dir}:`, err)
          iconSet.remove(name)

          return
        }

        // Update icon from SVG instance
        iconSet.fromSVG(name, svg)
      })

      // Collect the SVG icon
      allIcons.push(iconSet.export())
    }
  }

  // Generate CSS from collected icons
  const cssContent = allIcons
    .map(iconSet => getIconsCSS(
      iconSet,
      Object.keys(iconSet.icons),
      {
        iconSelector: '.{prefix}-{name}',
        mode: 'mask',
      },
    ))
    .join('\n')

  // Save the CSS to a file
  await fs.writeFile(target, cssContent, 'utf8')

  console.log(`Saved CSS to ${target}!`)

  // RENDIMIENTO: el set completo de tabler (~6000 iconos, 2,8 MB) era el 84 % de
  // style.css. La build de producción usa icons.used.css (alias en vite.config.ts)
  // con solo los iconos tabler que aparecen en el código; dev sigue con el set completo.
  const usedTabler = await collectUsedIconNames('tabler', usedIconsScanDirs)

  const usedCssContent = allIcons
    .map(iconSet => getIconsCSS(
      iconSet,
      Object.keys(iconSet.icons).filter(name => iconSet.prefix !== 'tabler' || usedTabler.has(name)),
      {
        iconSelector: '.{prefix}-{name}',
        mode: 'mask',
      },
    ))
    .join('\n')

  await fs.writeFile(usedTarget, usedCssContent, 'utf8')

  console.log(`Saved CSS with ${usedTabler.size} used tabler icons to ${usedTarget}!`)
})().catch(err => {
  console.error(err)
  process.exitCode = 1
})

/**
 * Collect `<prefix>-<name>` icon names referenced anywhere in the given directories.
 * Icons built dynamically (e.g. `tabler-${name}`) are not detected: write them literally.
 */
async function collectUsedIconNames(prefix: string, dirs: string[]): Promise<Set<string>> {
  const used = new Set<string>()
  const pattern = new RegExp(`\\b${prefix}-([a-z0-9]+(?:-[a-z0-9]+)*)`, 'g')

  const scanFile = async (path: string, name: string) => {
    if (!/\.(vue|ts|tsx|js|jsx|scss|css|json|php)$/.test(name) || /^icons(\.used)?\.css$/.test(name))
      return

    const content = await fs.readFile(path, 'utf8')
    for (const match of content.matchAll(pattern))
      used.add(match[1])
  }

  const walk = async (dir: string): Promise<void> => {
    for (const entry of await fs.readdir(dir, { withFileTypes: true })) {
      const path = join(dir, entry.name)

      if (entry.isDirectory()) {
        if (entry.name !== 'node_modules' && entry.name !== 'dist')
          await walk(path)
      }
      else {
        await scanFile(path, entry.name)
      }
    }
  }

  for (const path of dirs) {
    const stat = await fs.stat(path)
    if (stat.isDirectory())
      await walk(path)
    else
      await scanFile(path, basename(path))
  }

  return used
}

/**
 * Sort icon names by prefix
 */
function organizeIconsList(icons: string[]): Record<string, string[]> {
  const sorted: Record<string, string[]> = Object.create(null)

  icons.forEach(icon => {
    const item = stringToIcon(icon)

    if (!item)
      return

    const prefix = item.prefix
    const prefixList = sorted[prefix] ? sorted[prefix] : (sorted[prefix] = [])

    const name = item.name

    if (!prefixList.includes(name))
      prefixList.push(name)
  })

  return sorted
}
