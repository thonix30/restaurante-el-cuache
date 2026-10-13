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
            registerType: 'autoUpdate',
            injectRegister: false, // se registra en main.js para recargar sola al haber versión nueva
            includeAssets: ['favicon.png', 'apple-touch-icon.png', 'img/logo-cream.png', 'img/logo-ink.png'],
            workbox: {
                globPatterns: ['**/*.{js,css,html,png,svg,webp,woff2}'],
                globIgnores: ['img/*.jpg'],
                cleanupOutdatedCaches: true,
                navigateFallback: 'index.html',
                navigateFallbackDenylist: [/\/Backend\//],
                runtimeCaching: [
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
                icons: [
                    { src: 'pwa-192x192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
                    { src: 'pwa-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
                    { src: 'pwa-maskable-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
                shortcuts: [
                    { name: 'Salón', short_name: 'Salón', url: './#/salon' },
                    { name: 'Tomar pedido', short_name: 'Pedido', url: './#/pedido' },
                    { name: 'Cocina', short_name: 'Cocina', url: './#/cocina' },
                    { name: 'Kiosco de asistencia', short_name: 'Kiosco', url: './#/kiosco' },
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
