<template>
  <Modal :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" :title="titulo" :eyebrow="etiqueta || 'Cobro'" size="sm">
    <div class="text-center py-2">
      <p class="eyebrow">A cobrar</p>
      <div class="flex items-center justify-center gap-2 mt-1">
        <span class="num text-2xl text-ink-3">Q</span>
        <input v-model.number="monto" type="number" step="0.01" min="0.01" :max="maximo" inputmode="decimal"
               class="num text-5xl font-semibold text-ink bg-transparent w-48 text-center focus:outline-none border-b-2 border-transparent focus:border-gold/60" aria-label="Monto" />
      </div>
      <p class="text-xs text-ink-3 mt-1">Saldo pendiente <span class="num">{{ money(maximo) }}</span></p>
    </div>

    <p class="label mt-4">Método de pago</p>
    <div class="grid grid-cols-3 gap-2">
      <button v-for="m in metodos" :key="m.id" type="button" class="h-20 rounded-2xl border flex flex-col items-center justify-center gap-1.5 text-sm font-bold transition"
              :class="metodo === m.id ? 'border-gold bg-gold/15 text-gold' : 'border-line bg-surface-2 text-ink-2'" @click="metodo = m.id">
        <component :is="m.icon" :size="24" weight="duotone" /> {{ m.id }}
      </button>
    </div>

    <p class="label mt-5">Propina</p>
    <div class="grid grid-cols-5 gap-1.5">
      <button v-for="p in [0, 5, 10, 15]" :key="p" type="button" class="h-11 rounded-xl border text-sm font-bold transition"
              :class="propPct === p ? 'border-gold bg-gold/15 text-gold' : 'border-line bg-surface-2 text-ink-2'" @click="setPct(p)">
        {{ p ? p + '%' : 'No' }}
      </button>
      <input v-model.number="propina" type="number" min="0" step="0.5" class="input h-11 num text-center px-1" placeholder="Q" aria-label="Propina personalizada" @input="propPct = null" />
    </div>

    <template v-if="metodo === 'Efectivo'">
      <p class="label mt-5">Efectivo recibido</p>
      <div class="flex gap-2">
        <input v-model.number="recibido" type="number" min="0" step="0.5" inputmode="decimal" class="input num h-12 text-lg flex-1" :placeholder="money(totalCobro)" />
        <button v-for="b in billetes" :key="b" type="button" class="btn btn-soft h-12 px-3 num" @click="recibido = b">{{ b }}</button>
      </div>
      <div v-if="recibido" class="mt-3 rounded-2xl p-3 flex items-center justify-between" :class="cambio >= 0 ? 'bg-ok/10 border border-ok/30' : 'bg-danger/10 border border-danger/30'">
        <span class="text-sm font-bold" :class="cambio >= 0 ? 'text-ok' : 'text-danger'">{{ cambio >= 0 ? 'Cambio' : 'Falta' }}</span>
        <span class="num text-2xl font-semibold text-ink">{{ money(Math.abs(cambio)) }}</span>
      </div>
    </template>

    <template #footer>
      <div class="w-full">
        <div class="flex justify-between text-sm text-ink-3 mb-3">
          <span>Total con propina</span><b class="num text-ink text-base">{{ money(totalCobro) }}</b>
        </div>
        <button class="btn btn-gold btn-lg w-full" :disabled="!valido || enviando" @click="cobrar">
          <span v-if="enviando" class="w-5 h-5 border-2 border-on-gold/40 border-t-on-gold rounded-full animate-spin" />
          <template v-else><PhCheckCircle :size="20" weight="fill" /> Cobrar {{ money(monto || 0) }}</template>
        </button>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { PhMoney, PhCreditCard, PhBank, PhCheckCircle } from '@phosphor-icons/vue'
import Modal from '@/components/ui/Modal.vue'
import { salonService } from '@/services'
import { useUiStore } from '@/stores/ui'
import { money } from '@/utils/format'

const props = defineProps({
  modelValue: Boolean, cuentaId: Number, maximo: Number, montoInicial: Number,
  etiqueta: String, items: Array, titulo: { type: String, default: 'Cobrar' }, metodoSugerido: String,
})
const emit = defineEmits(['update:modelValue', 'cobrado'])
const ui = useUiStore()
const metodos = [{ id: 'Efectivo', icon: PhMoney }, { id: 'Tarjeta', icon: PhCreditCard }, { id: 'Transferencia', icon: PhBank }]
const monto = ref(0), metodo = ref('Efectivo'), propina = ref(0), propPct = ref(0), recibido = ref(null), enviando = ref(false)

watch(() => props.modelValue, (v) => {
  if (!v) return
  monto.value = Math.round((props.montoInicial ?? props.maximo) * 100) / 100
  metodo.value = props.metodoSugerido === 'tarjeta' ? 'Tarjeta' : 'Efectivo'
  propina.value = 0; propPct.value = 0; recibido.value = null
})
const setPct = (p) => { propPct.value = p; propina.value = Math.round((monto.value || 0) * p) / 100 }
watch(monto, () => { if (propPct.value) setPct(propPct.value) })

const totalCobro = computed(() => (Number(monto.value) || 0) + (Number(propina.value) || 0))
const cambio = computed(() => Math.round(((Number(recibido.value) || 0) - totalCobro.value) * 100) / 100)
const billetes = computed(() => {
  const t = totalCobro.value
  return [...new Set([50, 100, 200].map(b => Math.ceil(t / b) * b))].filter(b => b >= t).slice(0, 2)
})
const valido = computed(() => monto.value > 0 && monto.value <= props.maximo + 0.005 && (metodo.value !== 'Efectivo' || !recibido.value || cambio.value >= 0))

const cobrar = async () => {
  enviando.value = true
  try {
    const r = await salonService.cobrar(props.cuentaId, {
      monto: Math.round(monto.value * 100) / 100, propina: Number(propina.value) || 0, metodo: metodo.value,
      recibido: metodo.value === 'Efectivo' && recibido.value ? Number(recibido.value) : null,
      etiqueta: props.etiqueta || null, items: props.items || null,
    })
    ui.toast(r.completo ? 'Cuenta saldada. ¡Mesa libre!' : `Pago registrado${r.cambio ? ' · cambio ' + money(r.cambio) : ''}`)
    emit('cobrado', r)
    emit('update:modelValue', false)
  } catch (e) { ui.error(e) } finally { enviando.value = false }
}
</script>
