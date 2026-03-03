import { createApp } from 'vue'

import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'

// Styles
import '@core/scss/template/index.scss'
import '@styles/styles.scss'
import '@/assets/styles/design-improvements.css'

// Create vue app
const app = createApp(App)

// Register plugins
registerPlugins(app)

// Mount vue app
app.mount('#motorlan-app')

// Nota: La transición del skeleton ahora se maneja en los layouts
// mediante el composable useAppLoading cuando Suspense resuelve.
// Esto garantiza que el skeleton solo desaparezca cuando el contenido esté listo.
