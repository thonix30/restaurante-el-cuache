import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authService, salonService } from '@/services'
import { play, vibrate } from '@/utils/sound'
import { useAuthStore } from './auth'
import { useUiStore } from './ui'
import { setServerTime } from '@/composables/useNow'

/**
 * Estado en vivo del personal por sondeo (cada 4 s): avisos de mesas,
 * comandas listas, agotados. Detecta lo NUEVO para sonar/vibrar una sola vez.
 * Sondeo en vez de WebSockets: funciona en cualquier hosting PHP compartido.
 */
export const useRealtimeStore = defineStore('realtime', () => {
    const llamados = ref([])
    const listas = ref([])
    const agotados = ref([])
    const comandasPendientes = ref(0)
    const cajaAbierta = ref(true)
    const stockBajo = ref(0)
    const solicitudesPendientes = ref(0)
    const online = ref(true)
    const listasVistas = ref(new Set())

    let timer = null
    let first = true
    let known = { llamados: new Set(), listas: new Set(), pendientes: 0 }

    const avisosCount = computed(() => llamados.value.length + listas.value.filter(l => !listasVistas.value.has(l.id)).length)

    const poll = async () => {
        const auth = useAuthStore()
        if (!auth.isAuthenticated) return
        try {
            const d = await authService.sync()
            online.value = true
            setServerTime(d.server_time)
            const ui = useUiStore()
            const rol = auth.rol
            const atiendeMesas = rol === 'Admin' || rol === 'Mesero'

            if (!first && atiendeMesas) {
                const nuevos = d.llamados.filter(l => !known.llamados.has(l.id))
                for (const l of nuevos) {
                    ui.toast(l.tipo === 'mesero' ? 'Están llamando al mesero' : `Pidió la cuenta${l.metodo_pago ? ' · ' + l.metodo_pago : ''}`,
                        'call', { titulo: `Mesa ${l.mesa_numero}`, duracion: 6000 })
                }
                if (nuevos.length) { play(nuevos.some(l => l.tipo === 'mesero') ? 'call' : 'bill'); vibrate() }

                const nuevasListas = d.listas.filter(l => !known.listas.has(l.id))
                for (const l of nuevasListas) ui.toast('Listo para servir', 'success', { titulo: `Mesa ${l.mesa_numero ?? '—'}` })
                if (nuevasListas.length) { play('ready'); vibrate([80, 40, 80]) }
            }
            if (!first && (rol === 'Cocinero' || rol === 'Admin') && d.comandas_pendientes > known.pendientes) {
                // En la pantalla de cocina el propio KDS hace sonar el timbre
                if (rol === 'Cocinero' && !location.hash.startsWith('#/cocina')) play('order')
            }

            known = { llamados: new Set(d.llamados.map(l => l.id)), listas: new Set(d.listas.map(l => l.id)), pendientes: d.comandas_pendientes }
            llamados.value = d.llamados
            listas.value = d.listas
            agotados.value = d.agotados
            comandasPendientes.value = d.comandas_pendientes
            cajaAbierta.value = d.caja_abierta
            stockBajo.value = d.stock_bajo ?? 0
            solicitudesPendientes.value = d.solicitudes_pendientes ?? 0
            first = false
        } catch (e) {
            online.value = e.status !== 0
        }
    }

    const start = () => {
        if (timer) return
        first = true
        poll()
        timer = setInterval(() => { if (!document.hidden) poll() }, 4000)
        document.addEventListener('visibilitychange', onVisible)
    }
    const onVisible = () => { if (!document.hidden) poll() }
    const stop = () => {
        clearInterval(timer)
        timer = null
        document.removeEventListener('visibilitychange', onVisible)
    }

    const atender = async (id) => {
        llamados.value = llamados.value.filter(l => l.id !== id)
        await salonService.atender(id)
        poll()
    }
    const marcarListaVista = (id) => { listasVistas.value = new Set([...listasVistas.value, id]) }

    return {
        llamados, listas, agotados, comandasPendientes, cajaAbierta, stockBajo, solicitudesPendientes, online,
        avisosCount, start, stop, poll, atender, marcarListaVista, listasVistas,
    }
})
