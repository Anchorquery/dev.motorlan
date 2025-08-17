import { createApp } from 'vue'

import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'

// Styles
import vuetifyStyles from 'vuetify/styles?inline'
import coreStyles from '@core/scss/template/index.scss?inline'
import customStyles from '@styles/styles.scss?inline'

// Create vue app
const app = createApp(App)

// Register plugins
registerPlugins(app)

// Mount vue app
const host = document.querySelector('#app')
if (host) {
  const shadowRoot = host.attachShadow({ mode: 'open' })

  const appContainer = document.createElement('div')

  const styleEl = document.createElement('style')
  styleEl.textContent = vuetifyStyles + coreStyles + customStyles

  shadowRoot.appendChild(styleEl)
  shadowRoot.appendChild(appContainer)

  app.mount(appContainer)
} else {
  console.error('Could not find host element #app to mount the Vue app.')
}
