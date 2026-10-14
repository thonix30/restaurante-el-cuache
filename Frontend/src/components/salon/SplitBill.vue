<template>
  <Modal :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" title="Dividir cuenta"
         :eyebrow="'Mesa ' + (cuenta?.mesa_numero ?? '')" size="xl">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
      <div class="seg">
        <button :class="{ on: modo === 'platillo' }" @click="modo = 'platillo'">Por platillo</button>
        <button :class="{ on: modo === 'iguales' }" @click="modo = 'iguales'">Partes iguales</button>
      </div>
      <div class="flex items-center gap-2 text-sm">
        <span class="text-ink-3">Personas</span>
        <QtyStepper :value="personas" @inc="personas = Math.min(12, personas + 1)" @dec="personas = Math.max(2, personas - 1)" />
      </div>
    </div>

    <!-- ═══ Por platillo ═══ -->
    <div v-if="modo === 'platillo'" class="grid grid-cols-1 lg:grid-cols-[1fr_1.3fr] gap-5">
      <div>
        <p class="eyebrow mb-2">Platillos sin asignar · toca o arrastra</p>
        <div class="rounded-2xl border border-dashed border-line p-2 min-h-[120px] space-y-1.5" @dragover.prevent @drop="soltar(null)">
          <button v-for="u in sinAsignar" :key="u.key" draggable="true" @dragstart="arrastrando = u.key"
                  class="w-full flex items-center gap-2 rounded-xl px-3 h-11 bg-surface-2 border border-line/70 text-sm text-left hover:border-gold/50 active:scale-[.98] transition"
                  @click="asignar(u.key, personaSel)">
            <PhDotsSixVertical :size="16" class="text-ink-3 shrink-0" />
            <span class="flex-1 truncate">{{ u.nombre }}</span>
            <span class="num text-ink-2">{{ money(u.precio) }}</span>
          </button>
          <p v-if="!sinAsignar.length" class="text-center text-sm text-ink-3 py-8">Todo asignado ✓</p>
        </div>
        <p class="text-xs text-ink-3 mt-2">Al tocar un platillo se asigna a <b class="text-gold">{{ nombreDe(personaSel) }}</b>.</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 content-start">
        <div v-for="n in personas" :key="n" class="rounded-2xl border p-3 transition cursor-pointer"
             :class="[personaSel === n ? 'border-gold bg-gold/[.06]' : 'border-line bg-surface-2/40', pagadas.has(n) ? 'opacity-60' : '']"
             @click="personaSel = n" @dragover.prevent @drop="soltar(n)">
          <div class="flex items-center justify-between mb-2">
            <div class="font-bold text-ink flex items-center gap-2 min-w-0 flex-1">
              <span class="grid place-items-center w-7 h-7 rounded-full text-xs num shrink-0" :style="{ background: `var(--cat-${((n - 1) % 5) + 1})`, color: '#fff' }">{{ n }}</span>
              <input v-model.trim="nombres[n]" :placeholder="`Persona ${n}`" maxlength="40" :disabled="pagadas.has(n)" @click.stop
                     class="nombre-persona" :aria-label="`Nombre de la persona ${n}`" />
            </div>
            <span v-if="pagadas.has(n)" class="chip chip-ok">Pagado</span>
          </div>
          <ul class="space-y-1 min-h-[40px]">
            <li v-for="u in deLaPersona(n)" :key="u.key" class="flex items-center gap-2 text-sm">
              <span class="flex-1 truncate text-ink-2">{{ u.nombre }}</span>
              <span class="num text-ink-3 text-xs">{{ money(u.precio) }}</span>
              <button v-if="!pagadas.has(n)" class="text-ink-3 hover:text-danger" :aria-label="'Quitar ' + u.nombre" @click.stop="asignar(u.key, null)"><PhX :size="14" /></button>
            </li>
          </ul>
          <div class="flex items-center justify-between mt-3 pt-2 border-t border-line/60">
            <span class="num font-semibold text-ink">{{ money(subtotal(n)) }}</span>
            <button class="btn btn-gold btn-sm" :disabled="!subtotal(n) || pagadas.has(n)" @click.stop="cobrarPersona(n)">Cobrar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ Partes iguales ═══ -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
      <div v-for="n in personas" :key="n" class="rounded-2xl border border-line bg-surface-2/40 p-4" :class="pagadasIguales.has(n) ? 'opacity-60' : ''">
        <input v-model.trim="nombres[n]" :placeholder="`Persona ${n}`" maxlength="40" :disabled="pagadasIguales.has(n)"
               class="nombre-persona font-bold" :aria-label="`Nombre de la persona ${n}`" />
        <p class="num text-3xl font-semibold text-ink mt-2">{{ money(parteIgual(n)) }}</p>
        <button class="btn btn-gold w-full mt-3" :disabled="pagadasIguales.has(n) || parteIgual(n) <= 0" @click="cobrarIgual(n)">
          {{ pagadasIguales.has(n) ? 'Pagado' : 'Cobrar' }}
        </button>
      </div>
    </div>

    <template #footer>
      <div class="w-full flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
        <span class="text-ink-3">Total <b class="num text-ink">{{ money(cuenta?.total || 0) }}</b></span>
        <span class="text-ink-3">Pagado <b class="num text-ok">{{ money(cuenta?.pagado || 0) }}</b></span>
        <span class="text-ink-3 ml-auto">Falta por pagar</span>
        <b class="num text-2xl text-gold">{{ money(cuenta?.saldo || 0) }}</b>
      </div>
    </template>
  </Modal>

  <CobroModal v-model="cobro.open" :cuenta-id="cuenta?.id" :maximo="cuenta?.saldo || 0" :monto-inicial="cobro.monto"
              :etiqueta="cobro.etiqueta" :items="cobro.items" :titulo="'Cobrar · ' + cobro.etiqueta" @cobrado="onCobrado" />
</template>

<script setup>
import { ref, computed, reactive, watch } from 'vue'
import { PhDotsSixVertical, PhX } from '@phosphor-icons/vue'
import Modal from '@/components/ui/Modal.vue'
import QtyStepper from '@/components/ui/QtyStepper.vue'
import CobroModal from './CobroModal.vue'
import { money } from '@/utils/format'

const props = defineProps({ modelValue: Boolean, cuenta: Object })
const emit = defineEmits(['update:modelValue', 'cobrado'])
const modo = ref('platillo')
const personas = ref(2)
const personaSel = ref(1)
// Nombres de cada persona (opcional): si se deja vacío queda "Persona N"
const nombres = ref({})
const nombreDe = (n) => nombres.value[n] || `Persona ${n}`
const asignacion = ref({})       // key de unidad → persona
const pagadas = ref(new Set())
const pagadasIguales = ref(new Set())
const arrastrando = ref(null)
const cobro = reactive({ open: false, monto: 0, etiqueta: '', items: null, persona: null, modo: null })

watch(() => props.modelValue, (v) => {
  if (!v) return
  personas.value = Math.max(2, Math.min(12, props.cuenta?.personas || 2))
  asignacion.value = {}; pagadas.value = new Set(); pagadasIguales.value = new Set(); personaSel.value = 1; nombres.value = {}
  unidades.value = calcularUnidades()
})

// Cada unidad pendiente de pago es un elemento asignable (2× Café → 2 unidades).
// Se toma una foto al abrir: si se recalculara tras cada cobro, los índices se
// correrían y los platillos de otras personas cambiarían de dueño.
const unidades = ref([])
const calcularUnidades = () => {
  const out = []
  for (const it of props.cuenta?.items || []) {
    if (it.estado !== 'activo') continue
    const pendientes = Math.max(0, Math.floor(it.cantidad - (it.pagado_cantidad || 0) + 1e-6))
    for (let k = 0; k < pendientes; k++) out.push({ key: `${it.id}-${k}`, item_id: it.id, nombre: it.nombre, precio: it.precio })
  }
  return out
}
// Los descuentos se reparten proporcionalmente
const factor = computed(() => (props.cuenta?.subtotal ? props.cuenta.total / props.cuenta.subtotal : 1))
const sinAsignar = computed(() => unidades.value.filter(u => !asignacion.value[u.key]))
const deLaPersona = (n) => unidades.value.filter(u => asignacion.value[u.key] === n)
const subtotal = (n) => Math.round(deLaPersona(n).reduce((s, u) => s + u.precio, 0) * factor.value * 100) / 100
const asignar = (key, n) => { asignacion.value = { ...asignacion.value, [key]: n } }
const soltar = (n) => { if (arrastrando.value) asignar(arrastrando.value, n); arrastrando.value = null }

const cobrarPersona = (n) => {
  const items = {}
  deLaPersona(n).forEach(u => { items[u.item_id] = (items[u.item_id] || 0) + 1 })
  Object.assign(cobro, {
    open: true, monto: Math.min(subtotal(n), props.cuenta.saldo), etiqueta: nombreDe(n),
    items: Object.entries(items).map(([item_id, cantidad]) => ({ item_id: Number(item_id), cantidad })), persona: n, modo: 'platillo',
  })
}
const pendientesIguales = computed(() => personas.value - pagadasIguales.value.size)
const parteIgual = (n) => {
  if (pagadasIguales.value.has(n)) return 0
  const saldo = props.cuenta?.saldo || 0
  const base = Math.floor(saldo / Math.max(1, pendientesIguales.value) * 100) / 100
  // La última persona pendiente absorbe los centavos del redondeo
  const ultima = [...Array(personas.value).keys()].map(i => i + 1).filter(i => !pagadasIguales.value.has(i)).pop()
  return n === ultima ? Math.round((saldo - base * (pendientesIguales.value - 1)) * 100) / 100 : base
}
const cobrarIgual = (n) => Object.assign(cobro, { open: true, monto: parteIgual(n), etiqueta: nombreDe(n), items: null, persona: n, modo: 'iguales' })

const onCobrado = (r) => {
  if (cobro.modo === 'platillo') pagadas.value = new Set([...pagadas.value, cobro.persona])
  else pagadasIguales.value = new Set([...pagadasIguales.value, cobro.persona])
  emit('cobrado', r)
  if (r.completo) emit('update:modelValue', false)
}
</script>

<style scoped>
.nombre-persona { min-width: 0; width: 100%; background: transparent; border: 0; border-bottom: 1px dashed rgb(var(--line)); padding: 2px 0; color: rgb(var(--ink)); outline: none; }
.nombre-persona:focus { border-bottom-color: rgb(var(--gold)); }
.nombre-persona::placeholder { color: rgb(var(--ink)); opacity: 1; }
.nombre-persona:focus::placeholder { opacity: .35; }
</style>
