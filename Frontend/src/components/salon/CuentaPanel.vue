<template>
  <div v-if="!mesa" />
  <div v-else class="space-y-5">
    <!-- Llamado activo -->
    <div v-if="mesa.llamado" class="rounded-2xl border border-gold/50 bg-gold/10 p-3.5 flex items-center gap-3 pulse-gold">
      <component :is="mesa.llamado.tipo === 'mesero' ? PhBellRinging : PhReceipt" :size="26" weight="fill" class="text-gold" />
      <div class="flex-1">
        <p class="font-bold text-ink">{{ mesa.llamado.tipo === 'mesero' ? 'Están llamando al mesero' : 'Pidieron la cuenta' }}</p>
        <p class="text-xs text-ink-2 num">Esperando {{ espera(since(mesa.llamado.desde)) }}
          <span v-if="mesa.llamado.metodo_pago" class="capitalize"> · paga con {{ mesa.llamado.metodo_pago }}</span></p>
      </div>
      <button class="btn btn-gold btn-sm" @click="atender">Atender</button>
    </div>

    <!-- Mesa libre -->
    <div v-if="!cuenta && !cargando" class="text-center py-6">
      <span class="grid place-items-center w-20 h-20 mx-auto rounded-3xl bg-surface-2 border border-line mb-4"><PhArmchair :size="36" class="text-ink-3" /></span>
      <p class="font-serif text-2xl">Mesa libre</p>
      <p class="text-sm text-ink-3 mt-1">Capacidad para {{ mesa.capacidad }} personas · {{ mesa.zona }}</p>
      <div class="flex items-center justify-center gap-3 mt-6">
        <span class="text-sm text-ink-2">Personas</span>
        <QtyStepper big :value="personas" @inc="personas++" @dec="personas = Math.max(1, personas - 1)" />
      </div>
      <div class="grid grid-cols-2 gap-2 mt-6">
        <button class="btn btn-soft btn-lg" :disabled="ocupado" @click="abrir"><PhDoorOpen :size="20" /> Abrir mesa</button>
        <button class="btn btn-gold btn-lg" @click="agregar"><PhPlus :size="20" weight="bold" /> Tomar pedido</button>
      </div>
    </div>

    <div v-if="cargando" class="space-y-2"><div v-for="i in 4" :key="i" class="skeleton h-12" /></div>

    <!-- Cuenta abierta -->
    <template v-if="cuenta">
      <div class="flex flex-wrap items-center gap-2 text-xs text-ink-3">
        <span class="chip chip-neutral"><PhUsers :size="13" /> {{ cuenta.personas }} personas</span>
        <span class="chip chip-neutral num"><PhClock :size="13" /> {{ espera(since(cuenta.abierta_en)) }}</span>
        <span v-if="cuenta.mesero" class="chip chip-neutral"><PhUser :size="13" /> {{ cuenta.mesero }}</span>
        <button class="chip chip-neutral hover:border-gold/50" @click="moverMesa"><PhArrowsLeftRight :size="13" /> Cambiar de mesa</button>
      </div>

      <ul class="divide-y divide-line/50 -mx-1">
        <li v-for="it in itemsVisibles" :key="it.id" class="flex items-start gap-3 py-3 px-1 group" :class="it.estado === 'cancelado' ? 'opacity-50' : ''">
          <span class="num w-7 text-ink-2 font-bold pt-0.5">{{ it.cantidad }}×</span>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-ink" :class="it.estado === 'cancelado' ? 'line-through' : ''">{{ it.nombre }}</p>
            <p v-if="it.notas" class="text-xs text-warn mt-0.5">“{{ it.notas }}”</p>
            <p v-if="it.estado === 'cancelado'" class="text-xs text-danger mt-0.5">Cancelado: {{ it.motivo_cancelacion }}</p>
            <div v-else class="flex items-center gap-1.5 mt-1">
              <StatusChip :estado="it.comanda_estado" />
              <span v-if="it.pagado_cantidad" class="chip chip-ok">Pagado {{ it.pagado_cantidad }}</span>
              <span v-if="it.origen === 'cliente'" class="chip chip-neutral"><PhQrCode :size="11" /> QR</span>
            </div>
          </div>
          <div class="text-right">
            <p class="num text-sm text-ink">{{ money(it.precio * it.cantidad) }}</p>
            <button v-if="it.estado === 'activo'" class="text-[11px] text-ink-3 hover:text-danger mt-1 font-bold" @click="cancelarItem(it)">Cancelar</button>
          </div>
        </li>
      </ul>
      <button v-if="cuenta.items.some(i => i.estado === 'cancelado')" class="text-xs text-ink-3 underline" @click="verCancelados = !verCancelados">
        {{ verCancelados ? 'Ocultar' : 'Ver' }} cancelados
      </button>

      <!-- Totales -->
      <div class="rounded-2xl bg-surface-2/60 border border-line/60 p-4 space-y-2 text-sm">
        <div class="flex justify-between"><span class="text-ink-3">Subtotal</span><span class="num">{{ money(cuenta.subtotal) }}</span></div>
        <div class="flex justify-between items-center">
          <span class="text-ink-3 flex items-center gap-2">Descuento
            <button v-if="auth.isAdmin" class="text-gold text-xs font-bold" @click="descOpen = !descOpen">{{ cuenta.descuento ? 'Cambiar' : 'Aplicar' }}</button>
          </span>
          <span class="num" :class="cuenta.descuento ? 'text-ok' : ''">− {{ money(cuenta.descuento) }}</span>
        </div>
        <p v-if="cuenta.descuento_motivo" class="text-xs text-ink-3 -mt-1">{{ cuenta.descuento_motivo }}</p>
        <div v-if="descOpen" class="grid grid-cols-[auto_1fr] gap-2 pt-1">
          <div class="seg"><button :class="{ on: desc.tipo === 'porcentaje' }" @click="desc.tipo = 'porcentaje'">%</button><button :class="{ on: desc.tipo === 'monto' }" @click="desc.tipo = 'monto'">Q</button></div>
          <input v-model.number="desc.valor" type="number" min="0" class="input h-10 num" :placeholder="desc.tipo === 'porcentaje' ? '10' : '25.00'" />
          <input v-model="desc.motivo" class="input h-10 col-span-2" placeholder="Motivo (obligatorio): cliente frecuente, cortesía…" />
          <button class="btn btn-gold btn-sm col-span-2" :disabled="!desc.valor && desc.valor !== 0 || !desc.motivo" @click="aplicarDescuento">Aplicar descuento</button>
        </div>
        <div v-if="cuenta.pagado" class="flex justify-between"><span class="text-ink-3">Pagado</span><span class="num text-ok">− {{ money(cuenta.pagado) }}</span></div>
        <div class="flex justify-between items-end pt-2 border-t border-line/60">
          <span class="font-bold text-ink">{{ cuenta.pagado ? 'Falta por pagar' : 'Total' }}</span>
          <span class="num text-3xl font-semibold text-gold">{{ money(cuenta.saldo) }}</span>
        </div>
      </div>

      <!-- Pagos registrados -->
      <div v-if="cuenta.pagos.length">
        <p class="eyebrow mb-2">Pagos</p>
        <ul class="space-y-1.5">
          <li v-for="p in cuenta.pagos" :key="p.id" class="flex items-center gap-2 text-sm rounded-xl px-3 py-2 bg-surface-2/50" :class="p.anulado ? 'opacity-50 line-through' : ''">
            <span class="flex-1">{{ p.etiqueta || 'Pago' }} · {{ p.metodo }}</span>
            <span v-if="p.propina" class="text-xs text-ink-3 num">+{{ money(p.propina) }} prop.</span>
            <span class="num">{{ money(p.monto) }}</span>
            <button v-if="auth.isAdmin && !p.anulado" class="text-ink-3 hover:text-danger" title="Anular pago" @click="anularPago(p)"><PhArrowCounterClockwise :size="15" /></button>
          </li>
        </ul>
      </div>

      <div class="grid grid-cols-2 gap-2">
        <button class="btn btn-soft btn-lg" @click="agregar"><PhPlus :size="18" weight="bold" /> Agregar platillos</button>
        <button class="btn btn-soft btn-lg" :disabled="cuenta.saldo <= 0" @click="splitOpen = true"><PhUsersThree :size="18" /> Dividir cuenta</button>
        <button class="btn btn-gold btn-lg col-span-2" :disabled="cuenta.saldo <= 0" @click="cobroOpen = true"><PhCashRegister :size="20" weight="fill" /> Cobrar {{ money(cuenta.saldo) }}</button>
        <button v-if="auth.isAdmin" class="btn btn-ghost btn-sm col-span-2 text-danger" @click="cancelarCuenta"><PhProhibit :size="16" /> Cancelar cuenta</button>
      </div>
    </template>

    <CobroModal v-model="cobroOpen" :cuenta-id="cuenta?.id" :maximo="cuenta?.saldo || 0" titulo="Cobrar cuenta" etiqueta="Cuenta completa"
                :metodo-sugerido="mesa.llamado?.metodo_pago" @cobrado="onCobrado" />
    <SplitBill v-model="splitOpen" :cuenta="cuenta" @cobrado="onCobrado" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  PhBellRinging, PhReceipt, PhArmchair, PhDoorOpen, PhPlus, PhUsers, PhClock, PhUser, PhArrowsLeftRight, PhQrCode,
  PhArrowCounterClockwise, PhUsersThree, PhCashRegister, PhProhibit,
} from '@phosphor-icons/vue'
import QtyStepper from '@/components/ui/QtyStepper.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import CobroModal from './CobroModal.vue'
import SplitBill from './SplitBill.vue'
import { salonService } from '@/services'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useRealtimeStore } from '@/stores/realtime'
import { useNow } from '@/composables/useNow'
import { money, espera } from '@/utils/format'
import { pedirMotivo } from '@/plugins/swal'

const props = defineProps({ mesa: Object, mesas: Array })
const emit = defineEmits(['changed'])
const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()
const rt = useRealtimeStore()
const { since } = useNow()

const cuenta = ref(null)
const cargando = ref(false)
const ocupado = ref(false)
const personas = ref(2)
const verCancelados = ref(false)
const cobroOpen = ref(false)
const splitOpen = ref(false)
const descOpen = ref(false)
const desc = reactive({ tipo: 'porcentaje', valor: null, motivo: '' })

const itemsVisibles = computed(() => (cuenta.value?.items || []).filter(i => verCancelados.value || i.estado === 'activo'))

const cargar = async () => {
  if (!props.mesa) return
  cargando.value = !cuenta.value
  try { cuenta.value = props.mesa.cuenta ? await salonService.cuentaMesa(props.mesa.id) : null } catch (e) { ui.error(e) }
  cargando.value = false
}
watch(() => props.mesa?.id, () => { cuenta.value = null; descOpen.value = false; cargar() }, { immediate: true })
watch(() => props.mesa?.cuenta?.saldo, cargar)

const refrescar = async () => { await cargar(); emit('changed') }
const abrir = async () => {
  ocupado.value = true
  try { cuenta.value = await salonService.abrirMesa(props.mesa.id, personas.value); emit('changed'); ui.toast(`Mesa ${props.mesa.numero} abierta`) } catch (e) { ui.error(e) }
  ocupado.value = false
}
const agregar = () => router.push({ path: '/pedido', query: { mesa: props.mesa.id } })
const atender = async () => { try { await rt.atender(props.mesa.llamado.id); emit('changed') } catch (e) { ui.error(e) } }

const cancelarItem = async (it) => {
  const motivo = await pedirMotivo(`¿Cancelar ${it.nombre}?`, it.cantidad > 1 ? `Se cancelarán las ${it.cantidad} unidades y se devolverá el stock.` : 'Se devolverá el stock y quedará en la auditoría.',
    { placeholder: 'Ej. el cliente cambió de opinión, se demoró…', confirmar: 'Cancelar platillo' })
  if (!motivo) return
  try { cuenta.value = await salonService.cancelarItem(it.id, null, motivo); emit('changed'); ui.toast('Platillo cancelado', 'info') } catch (e) { ui.error(e) }
}
const aplicarDescuento = async () => {
  try {
    cuenta.value = await salonService.descuento(cuenta.value.id, { ...desc })
    descOpen.value = false; desc.valor = null; desc.motivo = ''
    emit('changed'); ui.toast('Descuento aplicado')
  } catch (e) { ui.error(e) }
}
const anularPago = async (p) => {
  const motivo = await pedirMotivo('¿Anular este pago?', `${p.etiqueta || 'Pago'} de ${money(p.monto)} (${p.metodo}). La cuenta volverá a tener saldo.`, { confirmar: 'Anular pago' })
  if (!motivo) return
  try { cuenta.value = await salonService.anularPago(p.id, motivo); emit('changed'); ui.toast('Pago anulado', 'info') } catch (e) { ui.error(e) }
}
const cancelarCuenta = async () => {
  const motivo = await pedirMotivo(`¿Cancelar la cuenta de la mesa ${props.mesa.numero}?`, 'Se cancelan todos los platillos y se devuelve el stock. Queda registrado en auditoría.', { confirmar: 'Cancelar cuenta' })
  if (!motivo) return
  try { await salonService.cancelarCuenta(cuenta.value.id, motivo); cuenta.value = null; emit('changed'); ui.toast('Cuenta cancelada', 'info') } catch (e) { ui.error(e) }
}
const moverMesa = async () => {
  const libres = (props.mesas || []).filter(m => m.estado === 'libre')
  if (!libres.length) return ui.toast('No hay mesas libres', 'warn')
  const destino = await pedirMotivo('Cambiar de mesa', 'Elige la mesa a la que se pasan los clientes.', {
    opciones: Object.fromEntries(libres.map(m => [m.id, `Mesa ${m.numero} · ${m.zona}`])), confirmar: 'Mover', peligro: false,
  })
  if (!destino) return
  try { await salonService.mover(cuenta.value.id, Number(destino)); emit('changed'); ui.toast('Cuenta movida') } catch (e) { ui.error(e) }
}
const onCobrado = async (r) => {
  if (r.completo) { cuenta.value = null; emit('changed', { cerrada: true }) } else await refrescar()
}
defineExpose({ refrescar })
</script>
