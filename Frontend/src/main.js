import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import './style.css'
import App from './App.vue'
import { usePwaStore } from './stores/pwa'

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')

// PWA: service worker, aviso de versión nueva e instalación
usePwaStore().iniciar()
