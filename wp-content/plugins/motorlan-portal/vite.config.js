import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import { resolve } from 'path'

// Salida con nombres FIJOS y sin trocear: dist/js/app.js + dist/css/app.css (+ icons.json y
// css/public.css que generan los scripts de `npm run build`). Con la SPA eran ~207 chunks
// y un despliegue a medias dejaba la web con 404 de chunks. La caché se invalida con
// ?v=filemtime (Support\Assets::url), no con hashes en el nombre.
export default defineConfig({
  plugins: [tailwindcss()],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: false,
    cssCodeSplit: false,
    // Todo lo pequeño (SVG, imágenes) dentro del CSS/JS: sin archivos sueltos en dist/assets.
    assetsInlineLimit: 100_000,
    rollupOptions: {
      input: { app: resolve(__dirname, 'assets/js/app.js') },
      output: {
        inlineDynamicImports: true,
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name].js',
        assetFileNames: (info) =>
          (info.names?.[0] || info.name || '').endsWith('.css') ? 'css/app.css' : 'assets/[name][extname]',
      },
    },
  },
})
