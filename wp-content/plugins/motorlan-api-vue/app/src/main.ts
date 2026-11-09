import { createApp } from 'vue'

import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'

// Styles
import '@core/scss/template/index.scss'
import '@styles/styles.scss'
import '@/assets/styles/design-improvements.css'
// Última: neutraliza los estilos globales del tema de WordPress dentro de la app
import '@/assets/styles/theme-isolation.css'

// Create vue app
const app = createApp(App)

// Register plugins
registerPlugins(app)

// Mount vue app
app.mount('#motorlan-app')

