import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'
import { fileURLToPath, URL } from 'node:url'

// https://vitejs.dev/config/
// base './' + rutas con hash (#/salon): la app funciona en cualquier carpeta
// (la del proyecto tiene espacios) y en cualquier hosting sin reglas de
// reescritura; los QR de mesa apuntan a .../#/m/<token>.
export default defineConfig({
    base: './',
    plugins: [
        vue(),
        VitePWA({
            // 'prompt': al publicar una versión nueva se muestra un aviso "Actualizar"
            // (src/stores/pwa.js) en lugar de recargar en medio de un pedido.
            registerType: 'prompt',
            injectRegister: false,
            includeAssets: ['favicon.png', 'apple-touch-icon.png', 'img/logo-cream.png', 'img/logo-ink.png'],
            workbox: {
                globPatterns: ['**/*.{js,css,html,png,svg,webp,woff2}'],
                globIgnores: ['img/*.jpg', 'screenshots/**'],
                cleanupOutdatedCaches: true,
                clientsClaim: true, // controla la app desde la primera visita
                navigateFallback: 'index.html',
                navigateFallbackDenylist: [/\/Backend\//],
                runtimeCaching: [
                    {
                        // Carta: siempre la más reciente; sin internet se muestra la última guardada
                        urlPattern: ({ url, request }) => request.method === 'GET' && /\/api\/v1\/public\/menu$/.test(url.pathname),
                        handler: 'NetworkFirst',
                        options: { cacheName: 'carta', networkTimeoutSeconds: 5, expiration: { maxEntries: 5, maxAgeSeconds: 60 * 60 * 24 * 7 }, cacheableResponse: { statuses: [200] } },
                    },
                    {
                        // Fotos de platillos: primero caché, se renuevan en segundo plano
                        urlPattern: ({ request }) => request.destination === 'image',
                        handler: 'StaleWhileRevalidate',
                        options: { cacheName: 'imagenes', expiration: { maxEntries: 150, maxAgeSeconds: 60 * 60 * 24 * 30 } },
                    },
                    {
                        urlPattern: /^https:\/\/fonts\.(googleapis|gstatic)\.com\/.*/,
                        handler: 'CacheFirst',
                        options: { cacheName: 'fuentes', expiration: { maxEntries: 20, maxAgeSeconds: 60 * 60 * 24 * 365 } },
                    },
                ],
            },
            manifest: {
                id: './',
                name: 'El Cuache · Café y Restaurante',
                short_name: 'El Cuache',
                description: 'Menú digital y gestión del restaurante El Cuache',
                theme_color: '#14110F',
                background_color: '#14110F',
                display: 'standalone',
                orientation: 'any',
                start_url: './',
                scope: './',
                lang: 'es',
                dir: 'ltr',
                categories: ['food', 'business', 'productivity'],
                screenshots: [
                    { src: 'screenshots/carta-movil.webp', sizes: '824x1830', type: 'image/webp', form_factor: 'narrow', label: 'Carta digital del restaurante' },
                    { src: 'screenshots/panel-movil.webp', sizes: '824x1830', type: 'image/webp', form_factor: 'narrow', label: 'Panel del administrador' },
                    { src: 'screenshots/panel-escritorio.webp', sizes: '1440x900', type: 'image/webp', form_factor: 'wide', label: 'Panel del administrador en computadora' },
                ],
                icons: [
                    { src: 'pwa-192x192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
                    { src: 'pwa-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
                    { src: 'pwa-maskable-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
                shortcuts: [
                    { name: 'Salón', short_name: 'Salón', url: './#/salon', icons: [{ src: 'pwa-192x192.png', sizes: '192x192' }] },
                    { name: 'Tomar pedido', short_name: 'Pedido', url: './#/pedido', icons: [{ src: 'pwa-192x192.png', sizes: '192x192' }] },
                    { name: 'Cocina', short_name: 'Cocina', url: './#/cocina', icons: [{ src: 'pwa-192x192.png', sizes: '192x192' }] },
                    { name: 'Kiosco de asistencia', short_name: 'Kiosco', url: './#/kiosco', icons: [{ src: 'pwa-192x192.png', sizes: '192x192' }] },
                ],
            },
        }),
    ],
    resolve: {
        alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
    },
    build: {
        chunkSizeWarningLimit: 900,
    },
})
