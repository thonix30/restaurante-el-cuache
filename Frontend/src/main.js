import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import './style.css'
import App from './App.vue'
import { registerSW } from 'virtual:pwa-register'

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')

// PWA: al publicar una versión nueva la app se recarga sola (sin limpiar caché a mano)
registerSW({
    immediate: true,
    onNeedRefresh() { location.reload() },
})
