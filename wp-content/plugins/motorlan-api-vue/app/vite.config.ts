import { fileURLToPath } from 'node:url'
import vue from '@vitejs/plugin-vue'
import vueJsx from '@vitejs/plugin-vue-jsx'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { VueRouterAutoImports, getPascalCaseRouteName } from 'unplugin-vue-router'
import VueRouter from 'unplugin-vue-router/vite'
import { defineConfig, loadEnv } from 'vite'
import VueDevTools from 'vite-plugin-vue-devtools'
import MetaLayouts from 'vite-plugin-vue-meta-layouts'
import vuetify from 'vite-plugin-vuetify'
import svgLoader from 'vite-svg-loader'

// https://vitejs.dev/config/
export default ({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  return defineConfig({
  plugins: [
    // Docs: https://github.com/posva/unplugin-vue-router
    // ℹ️ This plugin should be placed before vue plugin
    VueRouter({
      getRouteName: routeNode => {
        // Convert pascal case to kebab case
        return getPascalCaseRouteName(routeNode)
          .replace(/([a-z\d])([A-Z])/g, '$1-$2')
          .toLowerCase()
      },

    }),
    vue({
      template: {
        compilerOptions: {
          isCustomElement: tag => tag === 'swiper-container' || tag === 'swiper-slide',
        },
      },
    }),
    VueDevTools(),
    vueJsx(),

    // Docs: https://github.com/vuetifyjs/vuetify-loader/tree/master/packages/vite-plugin
    vuetify({
      styles: {
        configFile: 'src/assets/styles/variables/_vuetify.scss',
      },
    }),

    // Docs: https://github.com/dishait/vite-plugin-vue-meta-layouts?tab=readme-ov-file
    MetaLayouts({
      target: './src/layouts',
      defaultLayout: 'default',
    }),

    // Docs: https://github.com/antfu/unplugin-vue-components#unplugin-vue-components
    Components({
      dirs: ['src/@core/components', 'src/views/demos', 'src/components'],
      dts: true,
      resolvers: [
        componentName => {
          // Auto import `VueApexCharts`
          if (componentName === 'VueApexCharts')
            return { name: 'default', from: 'vue3-apexcharts', as: 'VueApexCharts' }
        },
      ],
    }),

    // Docs: https://github.com/antfu/unplugin-auto-import#unplugin-auto-import
    AutoImport({
      imports: ['vue', VueRouterAutoImports, '@vueuse/core', '@vueuse/math', 'vue-i18n', 'pinia'],
      dirs: [
        './src/@core/utils',
        './src/@core/composable/',
        './src/composables/',
        './src/utils/',
        './src/plugins/*/composables/*',
      ],
      vueTemplate: true,

      // ℹ️ Disabled to avoid confusion & accidental usage
      ignore: ['useStorage'],
    }),

    svgLoader(),

  ],
  define: {
    'process.env': {},
    '__APP_ENV__': JSON.stringify(env),
  },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
      '@themeConfig': fileURLToPath(new URL('./themeConfig.ts', import.meta.url)),
      '@core': fileURLToPath(new URL('./src/@core', import.meta.url)),
      '@layouts': fileURLToPath(new URL('./src/@layouts', import.meta.url)),
      '@images': fileURLToPath(new URL('./src/assets/images/', import.meta.url)),
      '@styles': fileURLToPath(new URL('./src/assets/styles/', import.meta.url)),
      '@configured-variables': fileURLToPath(new URL('./src/assets/styles/variables/_template.scss', import.meta.url)),
      '@db': fileURLToPath(new URL('./src/plugins/fake-api/handlers/', import.meta.url)),
      '@api-utils': fileURLToPath(new URL('./src/plugins/fake-api/utils/', import.meta.url)),
    },
  },
  base: '/',

  // 2. Configuración del servidor de desarrollo para solucionar CORS y HMR.
  server: {
    // (EDITADO) Escucha en todas las interfaces de red, no solo localhost.
    // Esto es crucial para que tu sitio en dev.motorlan.test pueda acceder a él.
    host: '0.0.0.0',

    // Escucha en el puerto 5173
    port: 5173,

    // Habilita CORS para permitir conexiones desde tu sitio de WordPress.
    cors: true,

    // Configuración para que el HMR (Hot Module Replacement) se conecte correctamente.
    hmr: {
      host: 'localhost',
    },
    proxy: {
      '/wp-json': {
        target: 'http://d88s88wskkksoswg0kscckow.213.130.147.89.sslip.io',
        changeOrigin: true,
        secure: false,
      },
    },
  },
  build: {
    // 2. Directorio de salida, que en tu caso es 'dist'.
    outDir: 'dist',

    // 3. Desactiva los sourcemaps en producción para reducir el tamaño de los archivos.
    sourcemap: false,

    // 4. Límite de advertencia para el tamaño de los chunks (ya lo tenías).
    chunkSizeWarningLimit: 5000,

    // 5. Configuración de Rollup para controlar los archivos de salida.
    rollupOptions: {
      // 6. Define el punto de entrada principal de tu aplicación.
      // Asegúrate de que la ruta sea correcta (ej. 'src/main.js' o 'src/main.ts').
      input: {
        app: 'src/main.ts',
      },
      output: {
        // 7. Elimina los hashes de los nombres de archivo para tener nombres estáticos.
        // Esto es CRUCIAL para poder encolar los scripts en WordPress.
        entryFileNames: 'js/app.js', // Archivo JS principal
        chunkFileNames: 'js/[name].js', // Otros chunks de JS (si los hay)
        assetFileNames: assetInfo => { // Archivos de assets (CSS, imágenes, etc.)
          if (assetInfo.name.endsWith('.css'))
            return 'css/style.css' // Nombre estático para el archivo CSS

          return 'assets/[name].[ext]' // Otros assets
        },
       inlineDynamicImports: true,
      },
    },
  },

  optimizeDeps: {
    exclude: ['vuetify'],
    entries: [
      './src/**/*.vue',
    ],
  },
  })
}
