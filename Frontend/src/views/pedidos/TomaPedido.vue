<template>
  <div class="max-w-5xl mx-auto">
    <!-- Mesa -->
    <div class="flex items-center gap-3 mb-4" data-reveal>
      <button class="card flex items-center gap-3 pl-3 pr-4 h-16 flex-1 sm:flex-none sm:min-w-[260px] text-left hover:border-gold/50 transition" @click="selMesa = true">
        <span class="grid place-items-center w-11 h-11 rounded-xl font-serif text-2xl border-2"
              :class="mesa ? 'border-gold bg-gold/15 text-gold' : 'border-dashed border-line text-ink-3'">{{ mesa?.numero ?? '?' }}</span>
        <div class="flex-1 min-w-0">
          <p class="eyebrow text-[10px]">Mesa</p>
          <p class="font-bold text-ink truncate">{{ mesa ? `Mesa ${mesa.numero} · ${mesa.zona}` : 'Elige la mesa' }}</p>
        </div>
        <PhCaretDown :size="18" class="text-ink-3" />
      </button>
      <div v-if="mesa && !mesa.cuenta" class="hidden sm:flex items-center gap-2 text-sm text-ink-2">
        Personas <QtyStepper :value="personas" @inc="personas++" @dec="personas = Math.max(1, personas - 1)" />
      </div>
      <span v-if="mesa?.cuenta" class="hidden sm:inline-flex chip chip-info">Cuenta abierta · {{ money(mesa.cuenta.saldo) }}</span>
    </div>

    <!-- Buscador + categorías -->
    <div class="sticky top-16 z-10 -mx-4 sm:mx-0 px-4 sm:px-0 py-2 bg-bg/90 backdrop-blur">
      <div class="relative">
        <PhMagnifyingGlass :size="18" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-3" />
        <input v-model="q" class="input pl-10 h-12" placeholder="Buscar platillo…" type="search" />
      </div>
      <div class="flex gap-2 overflow-x-auto scrollbar-none mt-2.5 pb-1">
        <button class="shrink-0 h-9 px-4 rounded-full text-xs font-bold border transition" :class="!cat ? 'bg-gold text-on-gold border-gold' : 'bg-surface border-line text-ink-2'" @click="cat = null">Todo</button>
        <button v-for="c in categorias" :key="c.id" class="shrink-0 h-9 px-4 rounded-full text-xs font-bold border transition flex items-center gap-1.5"
                :class="cat === c.id ? 'bg-gold text-on-gold border-gold' : 'bg-surface border-line text-ink-2'" @click="cat = c.id">
          <CatIcon :name="c.icono" :size="14" /> {{ c.nombre }}
        </button>
      </div>
    </div>

    <!-- Platillos -->
    <div v-if="cargando" class="space-y-2 mt-3"><div v-for="i in 6" :key="i" class="skeleton h-20" /></div>
    <ul class="mt-3 space-y-2 pb-36">
      <li v-for="p in filtrados" :key="p.id" class="card p-2.5 pr-3 flex items-center gap-3 transition" :class="agotado(p) ? 'opacity-55' : ''">
        <img :src="p.imagen || './img/almuerzo.jpg'" alt="" class="w-14 h-14 sm:w-16 sm:h-16 rounded-xl object-cover shrink-0" :class="agotado(p) ? 'grayscale-soft' : ''" :style="{ objectPosition: p.imagen_posicion }" />
        <div class="flex-1 min-w-0">
          <p class="font-bold text-ink leading-tight line-clamp-2 text-[15px]">{{ p.nombre }}</p>
          <div class="flex items-center gap-2 mt-1">
            <span class="num text-sm text-gold">{{ money(p.precio) }}</span>
            <StatusChip v-if="agotado(p)" :estado="p.agotado_motivo === 'stock' ? 'sin_stock' : 'agotado'" />
            <span v-else-if="p.controla_stock && p.stock <= p.stock_minimo" class="chip chip-warn">Quedan {{ p.stock }}</span>
          </div>
          <button v-if="cart.qtyOf(p.id) && !agotado(p)" class="text-[11px] text-ink-3 hover:text-gold font-bold mt-1 flex items-center gap-1" @click="notaDe(p)">
            <PhNotePencil :size="12" /> {{ lineas(p)[0]?.notas ? '“' + lineas(p)[0].notas + '”' : 'Agregar nota' }}
          </button>
        </div>
        <QtyStepper big :value="cart.qtyOf(p.id)" :disabled="agotado(p)" @inc="sumar(p)" @dec="restar(p)" />
      </li>
      <EmptyState v-if="!cargando && !filtrados.length" :icon="PhMagnifyingGlass" title="Sin resultados" text="Prueba con otro nombre o categoría." />
    </ul>

    <!-- Resumen inferior -->
    <div class="fixed z-30 inset-x-0 bottom-[68px] lg:bottom-0 lg:left-auto lg:right-0 lg:w-[calc(100%-var(--sb,16rem))] glass border-t border-line/60 safe-bottom">
      <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-3">
        <button class="flex-1 min-w-0 text-left" :disabled="!cart.count" @click="resumenOpen = true">
          <p class="text-xs text-ink-3">{{ cart.count }} platillo{{ cart.count === 1 ? '' : 's' }} <span v-if="cart.count" class="text-gold font-bold">· ver detalle</span></p>
          <p class="num text-2xl font-semibold text-ink leading-tight">{{ money(cart.total) }}</p>
        </button>
        <button v-if="cart.count" class="btn btn-ghost btn-icon" title="Vaciar" @click="cart.clear()"><PhTrash :size="20" /></button>
        <button class="btn btn-gold btn-lg" :disabled="!cart.count || !mesa || enviando || cart.bloqueado" @click="enviar">
          <span v-if="enviando" class="w-5 h-5 border-2 border-on-gold/40 border-t-on-gold rounded-full animate-spin" />
          <template v-else><PhCookingPot :size="20" weight="fill" /> Enviar a cocina</template>
        </button>
      </div>
    </div>

    <!-- Elegir mesa -->
    <Modal v-model="selMesa" title="¿Para qué mesa?" size="lg">
      <div class="grid grid-cols-3 sm:grid-cols-5 gap-2.5">
        <button v-for="m in mesas" :key="m.id" class="aspect-square rounded-2xl border-2 flex flex-col items-center justify-center gap-1 transition active:scale-95"
                :class="[mesa?.id === m.id ? 'border-gold bg-gold/15' : m.cuenta ? 'border-info/50 bg-info/10' : 'border-line bg-surface-2']"
                @click="elegirMesa(m)">
          <span class="font-serif text-3xl" :class="mesa?.id === m.id ? 'text-gold' : 'text-ink'">{{ m.numero }}</span>
          <span class="text-[10px] font-bold" :class="m.cuenta ? 'text-info' : 'text-ink-3'">{{ m.cuenta ? money(m.cuenta.saldo) : 'Libre' }}</span>
        </button>
      </div>
    </Modal>

    <!-- Detalle del pedido -->
    <Modal v-model="resumenOpen" :title="'Pedido · Mesa ' + (mesa?.numero ?? '')" size="md">
      <div v-if="mesa && !mesa.cuenta" class="flex items-center justify-between mb-4 sm:hidden">
        <span class="text-sm text-ink-2">Personas en la mesa</span>
        <QtyStepper :value="personas" @inc="personas++" @dec="personas = Math.max(1, personas - 1)" />
      </div>
      <ul class="space-y-2.5">
        <li v-for="it in cart.items" :key="it.uid" class="rounded-2xl border p-3" :class="it.no_disponible ? 'border-danger/50 bg-danger/[.07]' : 'border-line'">
          <div class="flex items-center gap-3">
            <div class="flex-1 min-w-0">
              <p class="font-bold" :class="it.no_disponible ? 'text-danger' : ''">{{ it.nombre }}</p>
              <p class="num text-xs text-ink-3">{{ money(it.precio) }} c/u</p>
            </div>
            <button v-if="it.no_disponible" class="btn btn-danger btn-sm" @click="cart.remove(it)">Quitar</button>
            <QtyStepper v-else :value="it.cantidad" @inc="cart.inc(it)" @dec="cart.dec(it)" />
          </div>
          <input v-if="!it.no_disponible" v-model="it.notas" maxlength="200" class="input h-9 mt-2 text-xs" placeholder="Nota para cocina…" />
        </li>
      </ul>
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { PhCaretDown, PhMagnifyingGlass, PhNotePencil, PhTrash, PhCookingPot } from '@phosphor-icons/vue'
import QtyStepper from '@/components/ui/QtyStepper.vue'
import Modal from '@/components/ui/Modal.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import CatIcon from '@/components/ui/CatIcon.vue'
import { catalogoService, salonService } from '@/services'
import { useCartStore } from '@/stores/cart'
import { useUiStore } from '@/stores/ui'
import { useRealtimeStore } from '@/stores/realtime'
import { money } from '@/utils/format'
import { pedirMotivo } from '@/plugins/swal'

const route = useRoute()
const router = useRouter()
const cart = useCartStore()
const ui = useUiStore()
const rt = useRealtimeStore()

const productos = ref([])
const categorias = ref([])
const mesas = ref([])
const mesa = ref(null)
const q = ref('')
const cat = ref(null)
const personas = ref(2)
const cargando = ref(true)
const enviando = ref(false)
const selMesa = ref(false)
const resumenOpen = ref(false)

// Agotado en vivo: el sondeo global trae los ids no disponibles cada 4 s
const agotado = (p) => !p.disponible || rt.agotados.includes(p.id)
watch(() => rt.agotados, (ids) => {
  const recien = cart.marcarAgotados(ids)
  recien.forEach(it => ui.toast(`${it.nombre} se acaba de agotar`, 'warn'))
})

const norm = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const filtrados = computed(() => productos.value.filter(p =>
  (!cat.value || p.categoria_id === cat.value) && (!q.value || norm(p.nombre).includes(norm(q.value)))))
const lineas = (p) => cart.items.filter(i => i.producto_id === p.id)

const sumar = (p) => { const l = lineas(p)[0]; l ? cart.inc(l) : cart.add(p) }
const restar = (p) => { const l = lineas(p).at(-1); if (l) cart.dec(l) }
const notaDe = async (p) => {
  const l = lineas(p)[0]
  const n = await pedirMotivo(`Nota para ${p.nombre}`, 'Se mostrará resaltada en cocina.', { placeholder: 'Sin cebolla, término medio…', confirmar: 'Guardar nota', peligro: false })
  if (n !== null && l) l.notas = n
}

const elegirMesa = (m) => {
  mesa.value = m
  selMesa.value = false
  cart.load('mesero-' + m.id)
  cart.marcarAgotados(rt.agotados)
  personas.value = Math.min(m.capacidad, 2)
  router.replace({ query: { mesa: m.id } })
}

const enviar = async () => {
  enviando.value = true
  try {
    await salonService.pedir(mesa.value.id, cart.payload, mesa.value.cuenta ? null : personas.value)
    ui.toast(`Pedido de la mesa ${mesa.value.numero} enviado a cocina`)
    cart.clear()
    resumenOpen.value = false
    rt.poll()
    router.push({ path: '/salon', query: { mesa: mesa.value.id } })
  } catch (e) {
    if (e.payload?.code === 'NO_DISPONIBLE') cart.marcarAgotados([...rt.agotados, ...e.payload.no_disponibles.map(x => x.producto_id)])
    ui.error(e)
  } finally { enviando.value = false }
}

let pollProductos = null
const recargarProductos = async () => {
  try {
    const [p, c] = await Promise.all([catalogoService.productos(), catalogoService.categorias()])
    productos.value = p
    categorias.value = c.filter(x => x.activa && p.some(y => y.categoria_id === x.id))
  } catch { /* se reintenta en el siguiente ciclo */ }
}
onUnmounted(() => clearInterval(pollProductos))

onMounted(async () => {
  pollProductos = setInterval(() => { if (!document.hidden) recargarProductos() }, 30000)
  try {
    const [p, c, m] = await Promise.all([catalogoService.productos(), catalogoService.categorias(), catalogoService.mesas()])
    productos.value = p
    categorias.value = c.filter(x => x.activa && p.some(y => y.categoria_id === x.id))
    mesas.value = m
    const pre = m.find(x => x.id === Number(route.query.mesa))
    if (pre) elegirMesa(pre)
    else selMesa.value = true
  } catch (e) { ui.error(e) }
  cargando.value = false
})
</script>
