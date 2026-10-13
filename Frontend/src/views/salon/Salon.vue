<template>
  <div>
    <PageHeader eyebrow="Operación en vivo" title="Salón" :subtitle="`${resumen.ocupadas} de ${mesas.length} mesas ocupadas · ${money(resumen.saldo)} por cobrar`">
      <div class="seg">
        <button :class="{ on: vista === 'plano' }" @click="vista = 'plano'"><PhSquaresFour :size="15" class="inline -mt-0.5" /> Plano</button>
        <button :class="{ on: vista === 'lista' }" @click="vista = 'lista'"><PhList :size="15" class="inline -mt-0.5" /> Lista</button>
      </div>
      <button v-if="auth.isAdmin && vista === 'plano'" class="btn btn-sm" :class="editando ? 'btn-gold' : 'btn-soft'" @click="toggleEditar">
        <component :is="editando ? PhFloppyDisk : PhPencilSimple" :size="16" /> {{ editando ? 'Guardar plano' : 'Editar plano' }}
      </button>
      <router-link to="/pedido" class="btn btn-gold btn-sm"><PhNotePencil :size="16" /> Tomar pedido</router-link>
    </PageHeader>

    <!-- Leyenda / filtros por estado -->
    <div class="flex gap-2 overflow-x-auto scrollbar-none pb-1 mb-5" data-reveal>
      <button v-for="e in estados" :key="e.id" class="chip h-9 px-3.5 text-xs cursor-pointer transition"
              :class="[filtro === e.id ? 'ring-2 ring-gold/60' : '', e.chip]" @click="filtro = filtro === e.id ? null : e.id">
        <span class="w-2 h-2 rounded-full" :class="e.dot" />{{ e.label }} <b class="num">{{ conteo(e.id) }}</b>
      </button>
    </div>

    <div v-if="!rt.cajaAbierta" class="card border-warn/40 p-4 mb-5 flex items-center gap-3" data-reveal>
      <PhLockKey :size="22" class="text-warn" />
      <p class="text-sm text-ink-2 flex-1">La caja está cerrada: no se pueden tomar pedidos ni cobrar.</p>
      <router-link v-if="auth.isAdmin" to="/caja" class="btn btn-gold btn-sm">Abrir caja</router-link>
    </div>

    <!-- ═══ Plano ═══ -->
    <div v-if="vista === 'plano'" class="card p-3 sm:p-4 overflow-x-auto" data-reveal>
      <div ref="plano" class="relative min-w-[760px] aspect-[16/9] rounded-2xl overflow-hidden"
           :class="editando ? 'ring-2 ring-gold/40 ring-dashed' : ''"
           style="background-image: radial-gradient(rgb(var(--line) / .55) 1px, transparent 1px); background-size: 22px 22px; background-color: rgb(var(--surface-2) / .4)">
        <!-- zonas -->
        <span v-for="z in zonas" :key="z.nombre" class="absolute eyebrow text-[10px] text-ink-3/70" :style="{ left: z.x + '%', top: '2%' }">{{ z.nombre }}</span>

        <button v-for="m in mesas" :key="m.id" :data-mesa="m.id"
                class="mesa absolute grid place-items-center text-center border-2 transition-[box-shadow,transform,opacity] duration-300"
                :class="[forma(m), estilo(m.estado).cls, editando ? 'cursor-grab active:cursor-grabbing' : 'hover:-translate-y-1 hover:shadow-lift',
                         filtro && !coincide(m) ? 'opacity-25' : '', sel?.id === m.id ? 'ring-4 ring-gold/50' : '', alertaMesa(m) ? 'pulse-gold' : '']"
                :style="{ left: m.pos_x + '%', top: m.pos_y + '%' }"
                @pointerdown="editando && iniciarArrastre($event, m)" @click="!editando && seleccionar(m)">
          <!-- sillas -->
          <span v-for="k in Math.min(m.capacidad, 8)" :key="k" class="silla" :style="silla(m, k)" />
          <div class="relative leading-none">
            <component v-if="m.llamado" :is="m.llamado.tipo === 'mesero' ? PhBellRinging : PhReceipt" :size="18" weight="fill" class="mx-auto mb-1 text-gold campana" />
            <p class="font-serif text-3xl" :class="estilo(m.estado).num">{{ m.numero }}</p>
            <p v-if="m.llamado" class="num text-[11px] font-bold mt-1" :class="since(m.llamado.desde) > 180 ? 'text-warn' : 'text-gold'">{{ espera(since(m.llamado.desde)) }}</p>
            <p v-else-if="m.cuenta" class="num text-[11px] mt-1 text-ink-2">{{ money(m.cuenta.saldo) }}</p>
            <p v-else class="text-[10px] mt-1 text-ink-3">{{ m.capacidad }} pers.</p>
          </div>
        </button>
      </div>
      <p v-if="editando" class="text-xs text-ink-3 mt-3 flex items-center gap-1.5"><PhHandGrabbing :size="15" /> Arrastra las mesas para acomodar el plano y luego guarda.</p>
    </div>

    <!-- ═══ Lista ═══ -->
    <div v-else class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3">
      <button v-for="m in mesasFiltradas" :key="m.id" class="card card-hover p-4 text-left flex items-center gap-4" data-reveal
              :class="[alertaMesa(m) ? 'border-gold/60' : '', sel?.id === m.id ? 'ring-2 ring-gold/50' : '']" @click="seleccionar(m)">
        <span class="grid place-items-center w-16 h-16 rounded-2xl border-2 shrink-0 font-serif text-3xl" :class="[estilo(m.estado).cls, estilo(m.estado).num, alertaMesa(m) ? 'pulse-gold' : '']">{{ m.numero }}</span>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <StatusChip :estado="m.estado" />
            <span v-if="m.llamado" class="num text-xs font-bold" :class="since(m.llamado.desde) > 180 ? 'text-warn' : 'text-gold'">{{ espera(since(m.llamado.desde)) }}</span>
          </div>
          <p class="text-sm text-ink-3 mt-1.5 truncate">
            <template v-if="m.cuenta">{{ m.cuenta.personas }} pers. · {{ espera(since(m.cuenta.abierta_en)) }}<template v-if="m.cuenta.mesero"> · {{ m.cuenta.mesero }}</template></template>
            <template v-else>{{ m.zona }} · {{ m.capacidad }} personas</template>
          </p>
        </div>
        <span v-if="m.cuenta" class="num text-lg font-semibold text-ink">{{ money(m.cuenta.saldo) }}</span>
      </button>
    </div>

    <SidePanel v-model="panel" :title="sel ? `Mesa ${sel.numero}` : ''" :eyebrow="sel?.zona" size="md">
      <template #header>
        <div v-if="sel" class="flex items-center gap-3">
          <span class="grid place-items-center w-12 h-12 rounded-2xl border-2 font-serif text-2xl" :class="[estilo(sel.estado).cls, estilo(sel.estado).num]">{{ sel.numero }}</span>
          <div>
            <h2 class="font-serif text-2xl leading-none">Mesa {{ sel.numero }}</h2>
            <div class="mt-1.5"><StatusChip :estado="sel.estado" /></div>
          </div>
        </div>
      </template>
      <CuentaPanel :mesa="sel" :mesas="mesas" @changed="onChanged" />
    </SidePanel>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { PhSquaresFour, PhList, PhPencilSimple, PhFloppyDisk, PhNotePencil, PhBellRinging, PhReceipt, PhLockKey, PhHandGrabbing } from '@phosphor-icons/vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SidePanel from '@/components/ui/SidePanel.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import CuentaPanel from '@/components/salon/CuentaPanel.vue'
import { salonService, catalogoService } from '@/services'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useRealtimeStore } from '@/stores/realtime'
import { useNow, setServerTime } from '@/composables/useNow'
import { money, espera } from '@/utils/format'

const auth = useAuthStore()
const ui = useUiStore()
const rt = useRealtimeStore()
const route = useRoute()
const router = useRouter()
const { since } = useNow()

const mesas = ref([])
const vista = ref(window.innerWidth < 768 ? 'lista' : 'plano')
const filtro = ref(null)
const sel = ref(null)
const panel = ref(false)
const editando = ref(false)
const plano = ref(null)
let timer = null

const estados = [
  { id: 'libre', label: 'Libre', chip: 'chip-neutral', dot: 'bg-ink-3' },
  { id: 'ocupada', label: 'Ocupada', chip: 'chip-info', dot: 'bg-info' },
  { id: 'por_cobrar', label: 'Por cobrar', chip: 'chip-sage', dot: 'bg-sage' },
  { id: 'llamando', label: 'Llamando', chip: 'chip-gold', dot: 'bg-gold animate-pulse' },
  { id: 'cuenta', label: 'Pidió la cuenta', chip: 'chip-gold', dot: 'bg-gold animate-pulse' },
]
const ESTILO = {
  libre: { cls: 'bg-surface border-line', num: 'text-ink-3' },
  ocupada: { cls: 'bg-info/10 border-info/50', num: 'text-ink' },
  por_cobrar: { cls: 'bg-sage/15 border-sage/60', num: 'text-ink' },
  llamando: { cls: 'bg-gold/15 border-gold', num: 'text-gold' },
  cuenta: { cls: 'bg-gold/15 border-gold', num: 'text-gold' },
}
const estilo = (e) => ESTILO[e] || ESTILO.libre
const alertaMesa = (m) => m.estado === 'llamando' || m.estado === 'cuenta'
const forma = (m) => ({ redonda: 'w-24 h-24 rounded-full', cuadrada: 'w-24 h-24 rounded-2xl', larga: 'w-44 h-24 rounded-2xl' }[m.forma])
const conteo = (id) => mesas.value.filter(m => m.estado === id).length
const coincide = (m) => !filtro.value || m.estado === filtro.value
const mesasFiltradas = computed(() => mesas.value.filter(coincide).sort((a, b) => (alertaMesa(b) - alertaMesa(a)) || a.numero - b.numero))
const zonas = computed(() => {
  const z = {}
  mesas.value.forEach(m => { z[m.zona] = Math.min(z[m.zona] ?? 100, m.pos_x) })
  return Object.entries(z).map(([nombre, x]) => ({ nombre, x }))
})
const resumen = computed(() => ({
  ocupadas: mesas.value.filter(m => m.cuenta).length,
  saldo: mesas.value.reduce((s, m) => s + (m.cuenta?.saldo || 0), 0),
}))

// Sillas alrededor de la mesa (decorativas, según capacidad)
const silla = (m, k) => {
  const n = Math.min(m.capacidad, 8)
  if (m.forma === 'larga') {
    const porLado = Math.ceil(n / 2), lado = k <= porLado ? 0 : 1, i = lado ? k - porLado : k
    return { left: `${(i / (porLado + 1)) * 100}%`, top: lado ? 'calc(100% + 5px)' : '-11px', transform: 'translateX(-50%)' }
  }
  const a = (k / n) * Math.PI * 2 - Math.PI / 2
  return { left: `calc(50% + ${Math.cos(a) * 62}px)`, top: `calc(50% + ${Math.sin(a) * 62}px)`, transform: 'translate(-50%,-50%)' }
}

const cargar = async () => {
  if (editando.value) return
  try {
    const d = await salonService.salon()
    setServerTime(d.server_time)
    mesas.value = d.mesas
    if (sel.value) sel.value = d.mesas.find(m => m.id === sel.value.id) || null
  } catch (e) { if (e.status !== 0) ui.error(e) }
}
const seleccionar = (m) => { sel.value = m; panel.value = true; router.replace({ query: { mesa: m.id } }) }
watch(panel, (v) => { if (!v) router.replace({ query: {} }) })
const onChanged = () => { cargar(); rt.poll() }

// ── Edición del plano (arrastrar) ──
let drag = null
const iniciarArrastre = (e, m) => {
  const r = plano.value.getBoundingClientRect()
  drag = { m, r, dx: e.clientX - (r.left + m.pos_x / 100 * r.width), dy: e.clientY - (r.top + m.pos_y / 100 * r.height) }
  e.target.setPointerCapture?.(e.pointerId)
  window.addEventListener('pointermove', mover)
  window.addEventListener('pointerup', soltar, { once: true })
}
const mover = (e) => {
  if (!drag) return
  const { r, m } = drag
  m.pos_x = Math.max(0, Math.min(92, (e.clientX - drag.dx - r.left) / r.width * 100))
  m.pos_y = Math.max(0, Math.min(84, (e.clientY - drag.dy - r.top) / r.height * 100))
}
const soltar = () => { drag = null; window.removeEventListener('pointermove', mover) }
const toggleEditar = async () => {
  if (!editando.value) { editando.value = true; panel.value = false; return }
  try {
    await catalogoService.posiciones(mesas.value.map(m => ({ id: m.id, pos_x: Math.round(m.pos_x * 10) / 10, pos_y: Math.round(m.pos_y * 10) / 10 })))
    ui.toast('Plano guardado')
  } catch (e) { ui.error(e) }
  editando.value = false
}

onMounted(async () => {
  await cargar()
  if (route.query.mesa) { const m = mesas.value.find(x => x.id === Number(route.query.mesa)); if (m) seleccionar(m) }
  timer = setInterval(() => { if (!document.hidden) cargar() }, 4000)
})
onUnmounted(() => clearInterval(timer))
</script>

<style scoped>
.silla { position: absolute; width: 14px; height: 14px; border-radius: 6px; background: rgb(var(--surface-3)); border: 1px solid rgb(var(--line)); pointer-events: none; }
.campana { animation: ring 1.6s ease-in-out infinite; transform-origin: 50% 0; }
@keyframes ring { 0%, 50%, 100% { transform: rotate(0); } 10%, 30% { transform: rotate(16deg); } 20%, 40% { transform: rotate(-16deg); } }
</style>
