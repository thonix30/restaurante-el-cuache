<template>
  <div>
    <PageHeader eyebrow="Arqueo" title="Caja" subtitle="Abre el turno con el fondo inicial; al cerrar, cuenta el efectivo y el sistema calcula la diferencia.">
      <button class="btn btn-soft" @click="verHistorial"><PhClockCounterClockwise :size="18" /> Turnos anteriores</button>
    </PageHeader>

    <div v-if="cargando" class="grid grid-cols-1 md:grid-cols-2 gap-4"><div class="skeleton h-64" /><div class="skeleton h-64" /></div>

    <!-- ═══ Caja cerrada: abrir ═══ -->
    <div v-else-if="!estado.abierto" class="max-w-xl mx-auto card p-8 text-center" data-reveal>
      <span class="grid place-items-center w-20 h-20 mx-auto rounded-3xl bg-warn/15 text-warn mb-5"><PhLockKey :size="38" weight="duotone" /></span>
      <h2 class="font-serif text-3xl">La caja está cerrada</h2>
      <p class="text-ink-3 mt-2">Mientras esté cerrada no se pueden tomar pedidos y los clientes verán «Aún no abrimos».</p>
      <div class="mt-7 text-left">
        <label class="label">Fondo inicial (efectivo en caja)</label>
        <div class="relative"><span class="absolute left-4 top-1/2 -translate-y-1/2 num text-ink-3 text-lg">Q</span>
          <input v-model.number="fondo" type="number" min="0" step="0.5" class="input h-14 pl-10 num text-2xl" placeholder="500.00" /></div>
        <label class="label mt-4">Notas (opcional)</label>
        <input v-model="notasApertura" class="input" placeholder="Turno de la mañana…" />
      </div>
      <button class="btn btn-gold btn-lg w-full mt-6" :disabled="fondo === null || fondo === '' || ocupado" @click="abrir"><PhLockKeyOpen :size="20" /> Abrir caja</button>
    </div>

    <!-- ═══ Turno abierto ═══ -->
    <div v-else class="grid grid-cols-1 xl:grid-cols-[1.5fr_1fr] gap-4">
      <div class="space-y-4">
        <div class="card p-5" data-reveal>
          <div class="flex flex-wrap items-center gap-3">
            <span class="chip chip-ok chip-dot">Turno abierto</span>
            <span class="text-sm text-ink-3">desde {{ fechaHora(t.abierto_en) }} · {{ t.abierto_por }}</span>
            <span class="flex-1" />
            <span class="text-sm text-ink-3">Fondo inicial <b class="num text-ink">{{ money(t.monto_inicial) }}</b></span>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div v-for="m in t.metodos" :key="m.metodo" class="card p-5" data-reveal>
            <div class="flex items-center justify-between">
              <p class="eyebrow">{{ m.metodo }}</p>
              <component :is="iconoMetodo(m.metodo)" :size="20" class="text-gold" />
            </div>
            <p class="num text-2xl font-semibold mt-3">{{ money(m.monto) }}</p>
            <p class="text-xs text-ink-3 mt-1">{{ m.movimientos }} cobros · propinas <span class="num">{{ money(m.propina) }}</span></p>
          </div>
        </div>

        <div class="card p-5" data-reveal>
          <p class="panel-title mb-4">Desglose del turno</p>
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-ink-3">Cuentas cobradas</dt><dd class="num">{{ t.cuentas_cobradas }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-3">Ventas netas</dt><dd class="num">{{ money(t.ventas) }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-3">Total cobrado</dt><dd class="num">{{ money(t.cobrado) }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-3">Propinas</dt><dd class="num text-ok">{{ money(t.propinas) }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-3">Descuentos ({{ t.descuentos.n }})</dt><dd class="num text-warn">− {{ money(t.descuentos.monto) }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-3">Cancelaciones ({{ t.cancelaciones.items + t.cancelaciones.cuentas }})</dt><dd class="num text-danger">{{ money(t.cancelaciones.monto) }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-3">Pagos anulados ({{ t.pagos_anulados.n }})</dt><dd class="num">{{ money(t.pagos_anulados.monto) }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-3">Propinas con tarjeta/transf.</dt><dd class="num">{{ money(t.propinas_no_efectivo) }}</dd></div>
          </dl>
        </div>
      </div>

      <!-- Cierre -->
      <div class="card p-6 h-fit xl:sticky xl:top-24" data-reveal>
        <p class="panel-title">Cerrar turno</p>
        <p class="text-sm text-ink-3 mt-1">Cuenta el efectivo de la gaveta (incluye fondo y propinas en efectivo).</p>
        <div class="rounded-2xl bg-surface-2/60 border border-line/60 p-4 mt-5 flex justify-between items-center">
          <span class="text-sm text-ink-2">Efectivo esperado</span>
          <span class="num text-2xl font-semibold">{{ money(t.efectivo_esperado) }}</span>
        </div>
        <label class="label mt-5">Efectivo contado</label>
        <div class="relative"><span class="absolute left-4 top-1/2 -translate-y-1/2 num text-ink-3 text-lg">Q</span>
          <input v-model.number="contado" type="number" min="0" step="0.01" class="input h-14 pl-10 num text-2xl" placeholder="0.00" /></div>

        <Transition name="fade">
          <div v-if="contado !== null && contado !== ''" class="mt-4 rounded-2xl p-4 flex items-center gap-3 border"
               :class="cuadra ? 'bg-ok/10 border-ok/40' : 'bg-danger/10 border-danger/40'">
            <component :is="cuadra ? PhCheckCircle : PhWarningCircle" :size="26" weight="fill" :class="cuadra ? 'text-ok' : 'text-danger'" />
            <div class="flex-1">
              <p class="font-bold" :class="cuadra ? 'text-ok' : 'text-danger'">{{ cuadra ? 'La caja cuadra' : diferencia > 0 ? 'Sobra efectivo' : 'Falta efectivo' }}</p>
              <p class="text-xs text-ink-3">Diferencia entre lo contado y lo esperado</p>
            </div>
            <span class="num text-2xl font-semibold" :class="cuadra ? 'text-ok' : 'text-danger'">{{ diferencia > 0 ? '+' : '' }}{{ money(diferencia) }}</span>
          </div>
        </Transition>
        <div v-if="!cuadra && contado !== null && contado !== ''" class="mt-4">
          <label class="label">Explica la diferencia <span class="text-danger">*</span></label>
          <textarea v-model="notasCierre" rows="2" class="input" placeholder="Ej. se dio cambio de más en la mesa 4…" />
        </div>
        <p v-if="t.cuentas_abiertas" class="text-xs text-warn mt-4 flex gap-1.5"><PhWarning :size="15" class="shrink-0" /> Hay {{ t.cuentas_abiertas }} cuenta(s) abiertas: cóbralas o cancélalas antes de cerrar.</p>
        <button class="btn btn-gold btn-lg w-full mt-5" :disabled="contado === null || contado === '' || (!cuadra && !notasCierre.trim()) || ocupado" @click="cerrar">
          <PhLockKey :size="20" /> Cerrar turno
        </button>
      </div>
    </div>

    <!-- Historial de turnos -->
    <Modal v-model="historialOpen" title="Turnos anteriores" size="xl">
      <div class="overflow-x-auto">
        <table class="tbl">
          <thead><tr><th>Cierre</th><th>Abrió / Cerró</th><th class="text-right">Fondo</th><th class="text-right">Esperado</th><th class="text-right">Contado</th><th class="text-right">Diferencia</th></tr></thead>
          <tbody>
            <tr v-for="h in historial" :key="h.id">
              <td class="whitespace-nowrap">{{ fechaHora(h.cerrado_en) }}</td>
              <td class="text-ink-2 text-xs">{{ h.abierto_por_nombre }}<br />{{ h.cerrado_por_nombre }}</td>
              <td class="text-right num">{{ money(h.monto_inicial) }}</td>
              <td class="text-right num">{{ money(h.efectivo_esperado) }}</td>
              <td class="text-right num">{{ money(h.efectivo_contado) }}</td>
              <td class="text-right num" :class="Math.abs(h.diferencia) < .01 ? 'text-ok' : 'text-danger'" :title="h.notas_cierre">{{ money(h.diferencia) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-if="!historial.length" class="text-sm text-ink-3 py-6 text-center">Aún no hay turnos cerrados.</p>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { PhClockCounterClockwise, PhLockKey, PhLockKeyOpen, PhCheckCircle, PhWarningCircle, PhWarning, PhMoney, PhCreditCard, PhBank } from '@phosphor-icons/vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Modal from '@/components/ui/Modal.vue'
import { adminService } from '@/services'
import { useUiStore } from '@/stores/ui'
import { useRealtimeStore } from '@/stores/realtime'
import { money, fechaHora } from '@/utils/format'
import { confirmar } from '@/plugins/swal'

const ui = useUiStore()
const rt = useRealtimeStore()
const estado = ref({})
const cargando = ref(true)
const ocupado = ref(false)
const fondo = ref(null)
const notasApertura = ref('')
const contado = ref(null)
const notasCierre = ref('')
const historialOpen = ref(false)
const historial = ref([])
let timer = null

const t = computed(() => estado.value.turno || {})
const diferencia = computed(() => Math.round(((Number(contado.value) || 0) - (t.value.efectivo_esperado || 0)) * 100) / 100)
const cuadra = computed(() => Math.abs(diferencia.value) < 0.01)
const iconoMetodo = (m) => ({ Efectivo: PhMoney, Tarjeta: PhCreditCard, Transferencia: PhBank }[m])

const cargar = async () => {
  try { estado.value = await adminService.caja() } catch (e) { ui.error(e) }
  cargando.value = false
}
const abrir = async () => {
  ocupado.value = true
  try { estado.value = await adminService.abrirCaja(fondo.value, notasApertura.value); ui.toast('Caja abierta. ¡Buen servicio!'); rt.poll() } catch (e) { ui.error(e) }
  ocupado.value = false
}
const cerrar = async () => {
  const msg = cuadra.value ? 'La caja cuadra exactamente.' : `Se registrará una diferencia de ${money(diferencia.value)}.`
  if (!await confirmar('¿Cerrar el turno de caja?', msg + ' Después no se podrán tomar pedidos hasta volver a abrir.', { confirmar: 'Cerrar turno' })) return
  ocupado.value = true
  try {
    await adminService.cerrarCaja(contado.value, notasCierre.value)
    ui.toast('Turno cerrado')
    contado.value = null; notasCierre.value = ''
    await cargar(); rt.poll()
  } catch (e) { ui.error(e) }
  ocupado.value = false
}
const verHistorial = async () => {
  historialOpen.value = true
  try { historial.value = await adminService.historialCaja() } catch (e) { ui.error(e) }
}

onMounted(() => { cargar(); timer = setInterval(() => { if (estado.value.abierto && !document.hidden) cargar() }, 15000) })
onUnmounted(() => clearInterval(timer))
</script>
