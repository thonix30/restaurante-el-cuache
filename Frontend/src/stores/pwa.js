import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { registerSW } from 'virtual:pwa-register'

// Aplicación instalable (PWA):
//  · registra el service worker (la app abre aunque no haya internet)
//  · avisa cuando hay una versión nueva en lugar de recargar de golpe
//    (no se pierde un pedido a medio tomar)
//  · ofrece "Instalar app" en Android/escritorio y explica cómo en iPhone
export const usePwaStore = defineStore('pwa', () => {
    const hayVersionNueva = ref(false)
    const listaSinConexion = ref(false)
    const promptInstalar = ref(null)
    const instalada = ref(false)
    let actualizarSW = null

    const ua = navigator.userAgent || ''
    const esIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    const modoApp = () => window.matchMedia?.('(display-mode: standalone)').matches || navigator.standalone === true
    instalada.value = modoApp()

    // Android / Chrome / Edge: el navegador permite mostrar su ventana de instalación
    const puedeInstalar = computed(() => !instalada.value && !!promptInstalar.value)
    // iPhone / iPad: no hay ventana automática; se instala desde "Compartir"
    const instruccionesIOS = computed(() => !instalada.value && esIOS)

    function iniciar() {
        window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); promptInstalar.value = e })
        window.addEventListener('appinstalled', () => { instalada.value = true; promptInstalar.value = null })
        window.matchMedia?.('(display-mode: standalone)').addEventListener?.('change', () => { instalada.value = modoApp() })

        actualizarSW = registerSW({
            immediate: true,
            onNeedRefresh() { hayVersionNueva.value = true },
            onOfflineReady() { listaSinConexion.value = true },
            onRegisteredSW(url, reg) {
                // Buscar versiones nuevas cada 30 min y al volver a la app
                if (!reg) return
                setInterval(() => reg.update().catch(() => {}), 30 * 60 * 1000)
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') reg.update().catch(() => {})
                })
            },
        })
    }

    async function instalar() {
        const e = promptInstalar.value
        if (!e) return false
        e.prompt()
        const { outcome } = await e.userChoice.catch(() => ({ outcome: 'dismissed' }))
        promptInstalar.value = null
        if (outcome === 'accepted') instalada.value = true
        return outcome === 'accepted'
    }

    const actualizar = async () => {
        hayVersionNueva.value = false
        const controlada = !!navigator.serviceWorker?.controller
        await actualizarSW?.(true)
        // Si la página aún no estaba controlada por el service worker no llega
        // el evento que recarga: se recarga a mano.
        setTimeout(() => location.reload(), controlada ? 3000 : 400)
    }
    const despues = () => { hayVersionNueva.value = false }

    return { hayVersionNueva, listaSinConexion, instalada, puedeInstalar, instruccionesIOS, esIOS, iniciar, instalar, actualizar, despues }
})
