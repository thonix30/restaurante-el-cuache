<template>
  <div ref="root" class="flex flex-col h-[calc(100dvh-64px-2rem)] lg:h-[calc(100dvh-64px-1.5rem)] min-h-[520px] bg-bg">
    <!-- Barra -->
    <div class="flex flex-wrap items-center gap-3 mb-3 px-1">
      <h1 class="font-serif text-3xl">Cocina</h1>
      <span class="chip chip-warn num">{{ por('pendiente').length }} pendientes</span>
      <span class="chip chip-info num">{{ por('preparacion').length }} en preparación</span>
      <span class="chip chip-ok num">{{ por('listo').length }} listas</span>
      <div class="flex-1" />
      <span class="text-xs text-ink-3 hidden md:flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-warn" />10 min <span class="w-2 h-2 rounded-full bg-danger ml-2" />20 min</span>
      <button class="btn btn-soft btn-sm" @click="toggleSound"><component :is="soundEnabled ? PhSpeakerHigh : PhSpeakerSlash" :size="16" /> {{ soundEnabled ? 'Sonido activo' : 'Sin sonido' }}</button>
      <button class="btn btn-soft btn-sm" @click="pantallaCompleta"><PhCornersOut :size="16" /> Pantalla completa</button>
    </div>

    <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3 min-h-0 overflow-x-auto">
      <section v-for="col in columnas" :key="col.id" class="rounded-3xl border border-line/60 bg-surface/40 flex flex-col min-h-[280px] min-w-0">
        <header class="flex items-center gap-2 px-4 h-14 border-b border-line/50 shrink-0">
          <span class="w-2.5 h-2.5 rounded-full" :class="col.dot" />
          <h2 class="font-bold text-ink">{{ col.label }}</h2>
          <span class="num text-sm text-ink-3">{{ por(col.id).length }}</span>
        </header>
        <TransitionGroup name="list" tag="div" class="flex-1 overflow-y-auto p-3 space-y-3 relative">
          <article v-for="c in por(col.id)" :key="c.id" :data-comanda="c.id" class="card overflow-hidden"
                   :class="[nivel(c) === 'rojo' ? 'border-danger/70' : nivel(c) === 'ambar' ? 'border-warn/60' : '', col.id === 'listo' ? 'border-ok/50' : '']">
            <div class="flex items-center gap-3 px-4 pt-3.5 pb-2">
              <div class="flex-1 min-w-0">
                <p class="font-serif text-3xl leading-none">{{ c.mesa_numero ? 'Mesa ' + c.mesa_numero : 'Para llevar' }}</p>
                <p class="text-xs text-ink-3 mt-1 truncate">#{{ c.id }} · {{ c.origen === 'cliente' ? 'Pedido por QR' : c.mesero || 'Mesero' }}</p>
              </div>
              <div class="text-right">
                <p class="num text-2xl font-semibold" :class="{ verde: 'text-ok', ambar: 'text-warn', rojo: 'text-danger' }[nivel(c)]">{{ crono(segs(c)) }}</p>
                <p class="text-[10px] text-ink-3">{{ col.id === 'listo' ? 'esperando mesero' : 'desde el pedido' }}</p>
              </div>
            </div>
            <!-- barra de tiempo -->
            <div class="h-1 mx-4 rounded-full bg-surface-3 overflow-hidden"><div class="h-full rounded-full transition-all duration-1000"
                 :class="{ verde: 'bg-ok', ambar: 'bg-warn', rojo: 'bg-danger' }[nivel(c)]" :style="{ width: Math.min(100, segs(c) / 1200 * 100) + '%' }" /></div>

            <ul class="px-4 py-3 space-y-2.5">
              <li v-for="it in c.items" :key="it.id" class="flex items-start gap-3 group">
                <span class="num text-xl font-bold text-gold w-8 shrink-0">{{ it.cantidad }}×</span>
                <div class="flex-1 min-w-0">
                  <p class="text-lg font-bold text-ink leading-tight" :class="agotados.has(it.producto_id) ? 'line-through text-ink-3' : ''">{{ it.nombre }}</p>
                  <p v-if="it.notas" class="mt-1.5 rounded-lg bg-warn/15 border border-warn/40 text-warn text-sm font-bold px-2.5 py-1.5 flex gap-1.5">
                    <PhWarning :size="16" weight="fill" class="shrink-0 mt-0.5" /> {{ it.notas }}
                  </p>
                </div>
                <button v-if="it.producto_id && !agotados.has(it.producto_id)" class="btn btn-ghost btn-sm btn-icon text-ink-3/60 hover:text-danger hover:bg-danger/10 shrink-0"
                        title="Marcar agotado" :aria-label="'Marcar agotado: ' + it.nombre" @click="marcarAgotado(it)">
                  <PhProhibit :size="17" />
                </button>
                <span v-else-if="agotados.has(it.producto_id)" class="chip chip-muted">Agotado</span>
              </li>
              <li v-if="c.cancelados" class="text-xs text-danger">{{ c.cancelados }} platillo(s) cancelado(s)</li>
            </ul>

            <div class="flex gap-2 px-3 pb-3">
              <button v-if="col.id !== 'pendiente'" class="btn btn-soft btn-icon h-14 w-14 shrink-0" title="Regresar" @click="regresar(c)"><PhArrowUUpLeft :size="20" /></button>
              <button class="btn btn-lg h-14 flex-1 text-base" :class="col.btn" :disabled="moviendo.has(c.id)" @click="avanzar(c)">
                <component :is="col.icon" :size="22" weight="fill" /> {{ col.accion }}
              </button>
            </div>
          </article>
        </TransitionGroup>
        <div v-if="!por(col.id).length" class="flex-1 grid place-items-center text-center p-6 text-ink-3">
          <div><component :is="col.vacioIcon" :size="38" class="mx-auto mb-2 opacity-40" /><p class="text-sm">{{ col.vacio }}</p></div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import {
  PhSpeakerHigh, PhSpeakerSlash, PhCornersOut, PhWarning, PhProhibit, PhArrowUUpLeft, PhFire, PhCheckCircle, PhHandPalm,
  PhCoffee, PhCookingPot, PhBellSimple,
} from '@phosphor-icons/vue'
import { salonService, catalogoService } from '@/services'
import { useUiStore } from '@/stores/ui'
import { useRealtimeStore } from '@/stores/realtime'
import { useNow, setServerTime } from '@/composables/useNow'
import { crono } from '@/utils/format'
import { soundEnabled, toggleSound, play } from '@/utils/sound'
import { gsap, reduced } from '@/composables/useMotion'
import { confirmar } from '@/plugins/swal'

const ui = useUiStore()
const rt = useRealtimeStore()
const { since } = useNow()
const root = ref(null)
const comandas = ref([])
const moviendo = ref(new Set())
const agotados = computed(() => new Set(rt.agotados))
let timer = null, conocidas = null

const columnas = [
  { id: 'pendiente', label: 'Pendiente', dot: 'bg-warn', accion: 'Empezar', icon: PhFire, btn: 'btn-soft border-warn/50 text-warn', vacio: 'Sin comandas nuevas', vacioIcon: PhCoffee },
  { id: 'preparacion', label: 'En preparación', dot: 'bg-info', accion: 'Listo', icon: PhCheckCircle, btn: 'btn-gold', vacio: 'Nada en el fuego', vacioIcon: PhCookingPot },
  { id: 'listo', label: 'Listo', dot: 'bg-ok', accion: 'Entregado', icon: PhHandPalm, btn: 'btn-ok', vacio: 'Nada esperando al mesero', vacioIcon: PhBellSimple },
]
const por = (e) => comandas.value.filter(c => c.estado === e)
const segs = (c) => since(c.estado === 'listo' ? c.listo_en : c.creado_en)
const nivel = (c) => {
  if (c.estado === 'listo') return segs(c) > 300 ? 'ambar' : 'verde'
  const m = segs(c) / 60
  return m >= 20 ? 'rojo' : m >= 10 ? 'ambar' : 'verde'
}

const cargar = async () => {
  try {
    const d = await salonService.comandas()
    setServerTime(d.server_time)
    const nuevas = conocidas ? d.comandas.filter(c => !conocidas.has(c.id)) : []
    comandas.value = d.comandas
    conocidas = new Set(d.comandas.map(c => c.id))
    if (nuevas.length) {
      play('order')
      ui.toast(nuevas.length === 1 ? `Mesa ${nuevas[0].mesa_numero ?? ''}` : `${nuevas.length} comandas nuevas`, 'call', { titulo: 'Nueva comanda' })
      await nextTick()
      if (!reduced()) nuevas.forEach(c => {
        const el = root.value?.querySelector(`[data-comanda="${c.id}"]`)
        if (el) gsap.fromTo(el, { scale: .9, boxShadow: '0 0 0 4px rgb(214 167 94 / .8)' }, { scale: 1, boxShadow: '0 0 0 0 rgb(214 167 94 / 0)', duration: 1.2, ease: 'elastic.out(1,.5)' })
      })
    }
  } catch (e) { if (e.status !== 0) ui.error(e) }
}

const mover = async (c, estado) => {
  moviendo.value = new Set([...moviendo.value, c.id])
  const previo = c.estado
  if (estado !== 'entregado') c.estado = estado // optimista
  else comandas.value = comandas.value.filter(x => x.id !== c.id)
  try { await salonService.estadoComanda(c.id, estado); rt.poll() } catch (e) { c.estado = previo; ui.error(e); cargar() }
  moviendo.value.delete(c.id)
  moviendo.value = new Set(moviendo.value)
}
const SIG = { pendiente: 'preparacion', preparacion: 'listo', listo: 'entregado' }
const ANT = { preparacion: 'pendiente', listo: 'preparacion' }
const avanzar = (c) => mover(c, SIG[c.estado])
const regresar = (c) => mover(c, ANT[c.estado])

const marcarAgotado = async (it) => {
  if (!await confirmar(`¿Marcar «${it.nombre}» como agotado?`, 'Desaparecerá al instante del menú del cliente y de la toma de pedidos.', { confirmar: 'Marcar agotado', peligro: true })) return
  try { await catalogoService.disponibilidad(it.producto_id, false); rt.poll(); ui.toast(`${it.nombre} marcado como agotado`, 'info') } catch (e) { ui.error(e) }
}
const pantallaCompleta = () => {
  if (document.fullscreenElement) document.exitFullscreen()
  else document.documentElement.requestFullscreen?.()
}

onMounted(() => { cargar(); timer = setInterval(() => { if (!document.hidden) cargar() }, 4000) })
onUnmounted(() => clearInterval(timer))
</script>
