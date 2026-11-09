/**
 * dist/icons.json: solo los iconos Tabler que usa el portal (nombre => interior del <svg>).
 *
 * Recorre views/**.twig y src/**.php y se queda con toda cadena literal entre comillas
 * ('heart', "tabler-heart") que sea el nombre de un icono outline de @tabler/icons. Así
 * entran los de icon('x'), { icon: 'x' }, 'icon' => 'x' y los mapas de color => icono,
 * sin lista a mano. Un nombre construido en tiempo de ejecución ('a' ~ b) no entra:
 * escríbelo literal o añádelo a EXTRA.
 */
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const iconsDir = path.join(root, 'node_modules/@tabler/icons/icons/outline')
const EXTRA = []

const walk = (dir, exts, out = []) => {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name)
    if (entry.isDirectory()) walk(full, exts, out)
    else if (exts.some((e) => entry.name.endsWith(e))) out.push(full)
  }
  return out
}

const files = [...walk(path.join(root, 'views'), ['.twig']), ...walk(path.join(root, 'src'), ['.php'])]
const names = new Set(EXTRA)
const literal = /['"](?:tabler-)?([a-z0-9]+(?:-[a-z0-9]+)*)['"]/g
for (const file of files) {
  const text = fs.readFileSync(file, 'utf8')
  for (const m of text.matchAll(literal)) {
    if (fs.existsSync(path.join(iconsDir, `${m[1]}.svg`))) names.add(m[1])
  }
}

const map = {}
for (const name of [...names].sort()) {
  const svg = fs.readFileSync(path.join(iconsDir, `${name}.svg`), 'utf8')
  // Interior del <svg>, sin el <path stroke="none" d="M0 0h24v24H0z"/> de relleno.
  const inner = svg
    .replace(/^[\s\S]*?<svg[^>]*>/, '')
    .replace(/<\/svg>\s*$/, '')
    .replace(/<path stroke="none" d="M0 0h24v24H0z" fill="none"\s*\/>/, '')
    .replace(/\s+/g, ' ')
    .trim()
  map[name] = inner
}

fs.mkdirSync(path.join(root, 'dist'), { recursive: true })
const out = path.join(root, 'dist/icons.json')
fs.writeFileSync(out, JSON.stringify(map))
console.log(`icons.json: ${Object.keys(map).length} iconos, ${(fs.statSync(out).size / 1024).toFixed(1)} kB`)
