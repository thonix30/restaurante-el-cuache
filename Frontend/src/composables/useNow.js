import { ref, onUnmounted } from 'vue'
import { toDate } from '@/utils/format'

/**
 * Reloj compartido sincronizado con la hora del servidor: así el cronómetro
 * de una comanda marca lo mismo en la tablet de cocina y en el celular del
 * mesero aunque sus relojes estén desfasados.
 */
const offset = ref(0)
const now = ref(Date.now())
let ticker = null
let users = 0

export function setServerTime(s) {
    const d = toDate(s)
    if (d) offset.value = d.getTime() - Date.now()
}

export function useNow() {
    users++
    if (!ticker) ticker = setInterval(() => { now.value = Date.now() + offset.value }, 1000)
    now.value = Date.now() + offset.value
    onUnmounted(() => {
        users--
        if (!users) { clearInterval(ticker); ticker = null }
    })
    const since = (s) => { const d = toDate(s); return d ? Math.max(0, (now.value - d.getTime()) / 1000) : 0 }
    return { now, since }
}
