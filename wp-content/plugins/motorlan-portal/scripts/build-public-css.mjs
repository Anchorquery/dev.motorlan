/**
 * dist/css/app.css → dist/css/public.css
 *
 * El CSS del portal para las pantallas públicas que van dentro de la cabecera y el pie
 * del tema (layout/public.twig), donde conviven con GeneratePress:
 *
 *  1. Sin @layer. El tema no usa capas y una regla sin capa gana SIEMPRE a una con capa:
 *     con las capas de Tailwind, `button:hover { background: … }` del tema pisaría las
 *     utilidades. Las capas se aplanan en su orden y lo que ya iba sin capa queda al final.
 *  2. Todo bajo #ml-portal. El preflight de Tailwind solo afecta al portal, no al tema, y
 *     el ID sube la especificidad para ganar a las reglas por elemento del tema
 *     (input[type=text], table td, ul { margin-left: 3em }…), lo mismo que corrige
 *     theme-isolation.css en la SPA.
 */
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import postcss from 'postcss'

const SCOPE = '#ml-portal'
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const input = path.join(root, 'dist/css/app.css')
const output = path.join(root, 'dist/css/public.css')

const css = postcss.parse(fs.readFileSync(input, 'utf8'))

// ── 1. Aplanar capas en su orden de cascada ──
const ORDER = ['properties', 'theme', 'base', 'components', 'utilities']
const buckets = new Map(ORDER.map((n) => [n, []]))
const unlayered = []
const collect = (nodes, layer) => {
  for (const node of nodes) {
    if (node.type === 'atrule' && node.name === 'layer') {
      if (!node.nodes) continue // `@layer a, b;` solo declara el orden
      const name = node.params.trim()
      if (!buckets.has(name)) buckets.set(name, [])
      collect(node.nodes, name)
      continue
    }
    ;(layer ? buckets.get(layer) : unlayered).push(node.clone())
  }
}
collect(css.nodes, null)
const out = postcss.root()
for (const nodes of buckets.values()) out.append(nodes)
out.append(unlayered)

// ── 2. Prefijar selectores ──
const ROOTISH = /^(?:html|body|:root|:host)\b/
const scopeSelector = (sel) => {
  const s = sel.trim()
  if (!s) return s
  if (ROOTISH.test(s)) {
    const m = s.match(/^((?:html|body|:root|:host)[^\s>+~]*)(?:\s*([>+~])?\s*(.*))?$/)
    const [, compound, , rest] = m || []
    // `:root`, `html`, `body` → el contenedor (las variables --ml-* y el fondo viven ahí).
    if (!rest) return /^(?:html|body|:root|:host)$/.test(compound) ? SCOPE : (/^html[.:[]/.test(compound) ? compound : `${compound} ${SCOPE}`)
    return `${compound} ${SCOPE} ${rest}`
  }
  return `${SCOPE} ${s}`
}
out.walkRules((rule) => {
  let parent = rule.parent
  while (parent && parent.type !== 'root') {
    if (parent.type === 'atrule' && /keyframes$/.test(parent.name)) return
    parent = parent.parent
  }
  rule.selectors = [...new Set(rule.selectors.map(scopeSelector))]
})

// El contenedor no pinta el fondo de la app (va dentro de la página del tema), pero el
// teleport de los diálogos y avisos se monta en <body>: sus clases ml-* siguen funcionando
// porque las variables también se declaran en :root para ellos.
const vars = []
css.walkRules((rule) => {
  if (rule.selectors.includes(':root') && rule.nodes.some((n) => n.prop && n.prop.startsWith('--ml-'))) {
    vars.push(...rule.nodes.filter((n) => n.prop && n.prop.startsWith('--ml-')).map((n) => n.clone()))
  }
})
const rootVars = postcss.rule({ selector: ':root' })
rootVars.append(vars)
out.prepend(rootVars)

out.append(postcss.parse(`html.overflow-hidden{overflow:hidden}${SCOPE}{box-sizing:border-box;background:transparent;font-family:var(--font-sans)}${SCOPE} :is(h1,h2,h3,h4,h5,h6){font-family:inherit;letter-spacing:inherit;text-transform:none;margin:0}${SCOPE} :is(ul,ol){margin:0;padding:0}${SCOPE} :is(table,td,th){border:0}`))

fs.writeFileSync(output, out.toString())
console.log(`public.css: ${(fs.statSync(output).size / 1024).toFixed(1)} kB`)
