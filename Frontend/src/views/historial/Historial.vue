<template>
  <div>
    <PageHeader eyebrow="Gestión" title="Historial de pedidos" subtitle="Cuentas cobradas y canceladas con su detalle completo.">
      <button class="btn btn-soft" @click="exportar"><PhDownloadSimple :size="18" /> Exportar</button>
    </PageHeader>

    <!-- Filtros -->
    <div class="card p-3 mb-4 flex flex-wrap items-center gap-2" data-reveal>
      <input v-model="f.desde" type="date" class="input h-10 w-auto" />
      <span class="text-ink-3">—</span>
      <input v-model="f.hasta" type="date" class="input h-10 w-auto" />
      <select v-model="f.mesa" class="input h-10 w-auto"><option value="">Todas las mesas</option><option v-for="m in mesas" :key="m.id" :value="m.numero">Mesa {{ m.numero }}</option></select>
      <select v-model="f.mesero_id" class="input h-10 w-auto"><option value="">Todos los meseros</option><option v-for="e in meseros" :key="e.id" :value="e.id">{{ e.nombre_completo }}</option></select>
      <div class="relative flex-1 min-w-[180px]"><PhMagnifyingGlass :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
        <input v-model="f.q" type="search" class="input h-10 pl-9" placeholder="# de cuenta o platillo…" @keyup.enter="cargar(1)" /></div>
    </div>
    <div class="flex flex-wrap items-center gap-2 mb-5" data-reveal>
      <button v-for="e in [{ id: '', l: 'Todas' }, { id: 'pagada', l: 'Pagadas' }, { id: 'cancelada', l: 'Canceladas' }]" :key="e.id"
              class="chip h-8 px-3.5 cursor-pointer" :class="f.estado === e.id ? 'chip-gold' : 'chip-neutral'" @click="f.estado = e.id">{{ e.l }}</button>
      <span class="flex-1" />
      <span class="text-sm text-ink-3">{{ d.total || 0 }} cuentas · ventas <b class="num text-ink">{{ money(d.resumen?.ventas || 0) }}</b> · propinas <b class="num text-ok">{{ money(d.resumen?.propinas || 0) }}</b></span>
    </div>

    <div class="card overflow-x-auto" data-reveal>
      <table class="tbl">
        <thead><tr><th>Cuenta</th><th>Fecha</th><th>Mesa</th><th>Mesero</th><th class="text-right">Platillos</th><th>Pago</th><th class="text-right">Total</th><th>Estado</th></tr></thead>
        <tbody>
          <tr v-for="c in d.cuentas" :key="c.id" class="cursor-pointer" @click="ver(c)">
            <td class="num font-bold">#{{ c.id }}</td>
            <td class="whitespace-nowrap text-ink-2">{{ fechaHora(c.cerrada_en || c.abierta_en) }}</td>
            <td>{{ c.mesa_numero ?? '—' }}</td>
            <td class="text-ink-2">{{ c.mesero || 'QR' }}</td>
            <td class="text-right num">{{ c.platillos }}</td>
            <td><span class="text-xs text-ink-2">{{ c.metodos.join(' + ') || '—' }}</span> <span v-if="c.dividida" class="chip chip-info ml-1">Dividida</span></td>
            <td class="text-right num font-semibold">{{ money(c.total) }}<span v-if="c.propina" class="block text-[11px] text-ok">+{{ money(c.propina) }}</span></td>
            <td><StatusChip :estado="c.estado" /></td>
          </tr>
        </tbody>
      </table>
      <EmptyState v-if="d.cuentas && !d.cuentas.length" :icon="PhReceipt" title="Sin cuentas" text="No hay cuentas con esos filtros." />
      <div v-if="paginas > 1" class="flex items-center justify-between px-4 py-3 border-t border-line/60 text-sm">
        <button class="btn btn-soft btn-sm" :disabled="d.page <= 1" @click="cargar(d.page - 1)">Anterior</button>
        <span class="text-ink-3">Página {{ d.page }} de {{ paginas }}</span>
        <button class="btn btn-soft btn-sm" :disabled="d.page >= paginas" @click="cargar(d.page + 1)">Siguiente</button>
      </div>
    </div>

    <!-- Detalle -->
    <SidePanel v-model="detalleOpen" :title="det ? `Cuenta #${det.id}` : ''" :eyebrow="det ? `Mesa ${det.mesa_numero ?? '—'} · ${det.mesero || 'QR'}` : ''" size="lg">
      <template v-if="det">
        <div class="flex flex-wrap gap-2 mb-5">
          <StatusChip :estado="det.estado" />
          <span class="chip chip-neutral">{{ det.personas }} personas</span>
          <span class="chip chip-neutral">{{ fechaHora(det.abierta_en) }} → {{ hora(det.cerrada_en) }}</span>
        </div>
        <p v-if="det.motivo_cancelacion" class="rounded-xl bg-danger/10 border border-danger/30 p-3 text-sm text-danger mb-4">Cancelada: {{ det.motivo_cancelacion }}</p>
        <p class="eyebrow mb-2">Platillos</p>
        <ul class="divide-y divide-line/50 mb-5">
          <li v-for="i in det.items" :key="i.id" class="flex items-center gap-3 py-2.5 text-sm" :class="i.estado === 'cancelado' ? 'opacity-50' : ''">
            <span class="num w-7 text-ink-3">{{ i.cantidad }}×</span>
            <div class="flex-1"><p :class="i.estado === 'cancelado' ? 'line-through' : ''">{{ i.nombre }}</p>
              <p v-if="i.notas" class="text-xs text-warn">“{{ i.notas }}”</p><p v-if="i.motivo_cancelacion" class="text-xs text-danger">{{ i.motivo_cancelacion }}</p></div>
            <span class="num">{{ money(i.precio * i.cantidad) }}</span>
          </li>
        </ul>
        <div class="rounded-2xl bg-surface-2/60 border border-line/60 p-4 space-y-1.5 text-sm mb-5">
          <div class="flex justify-between"><span class="text-ink-3">Subtotal</span><span class="num">{{ money(det.subtotal) }}</span></div>
          <div v-if="det.descuento" class="flex justify-between"><span class="text-ink-3">Descuento · {{ det.descuento_motivo }}</span><span class="num text-ok">− {{ money(det.descuento) }}</span></div>
          <div class="flex justify-between font-bold"><span>Total</span><span class="num">{{ money(det.total) }}</span></div>
          <div class="flex justify-between"><span class="text-ink-3">Propina</span><span class="num text-ok">{{ money(det.propina) }}</span></div>
        </div>
        <p class="eyebrow mb-2">Pagos</p>
        <ul class="space-y-1.5 mb-5">
          <li v-for="p in det.pagos" :key="p.id" class="flex items-center gap-2 text-sm rounded-xl px-3 py-2 bg-surface-2/50" :class="p.anulado ? 'opacity-50 line-through' : ''">
            <span class="flex-1">{{ p.etiqueta || 'Pago' }} · {{ p.metodo }} · {{ hora(p.creado_en) }} · {{ p.cobrado_por_nombre }}</span>
            <span class="num">{{ money(p.monto) }}</span>
          </li>
        </ul>
        <p class="eyebrow mb-2">Comandas a cocina</p>
        <ul class="space-y-1.5">
          <li v-for="c in det.comandas" :key="c.id" class="flex items-center gap-2 text-sm">
            <span class="num text-ink-3">#{{ c.id }}</span><StatusChip :estado="c.estado" />
            <span class="text-ink-3 text-xs">{{ c.origen === 'cliente' ? 'QR' : 'Mesero' }} · {{ hora(c.creado_en) }}<template v-if="c.listo_en"> → lista {{ hora(c.listo_en) }}</template></span>
          </li>
        </ul>
      </template>
    </SidePanel>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { PhDownloadSimple, PhMagnifyingGlass, PhReceipt } from '@phosphor-icons/vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SidePanel from '@/components/ui/SidePanel.vue'
import { adminService, salonService, catalogoService, personalService } from '@/services'
import { useUiStore } from '@/stores/ui'
import { money, fechaHora, hora, isoDate, addDays, downloadCsv } from '@/utils/format'

const ui = useUiStore()
const d = ref({})
const mesas = ref([])
const meseros = ref([])
const f = reactive({ desde: addDays(isoDate(), -6), hasta: isoDate(), estado: '', mesa: '', mesero_id: '', q: '' })
const det = ref(null)
const detalleOpen = ref(false)
const paginas = computed(() => Math.ceil((d.value.total || 0) / (d.value.limit || 50)))

const cargar = async (page = 1) => {
  try { d.value = await adminService.historial({ ...f, page }) } catch (e) { ui.error(e) }
}
watch(() => [f.desde, f.hasta, f.estado, f.mesa, f.mesero_id], () => cargar(1))
const ver = async (c) => {
  detalleOpen.value = true
  det.value = null
  try { det.value = await salonService.cuenta(c.id) } catch (e) { ui.error(e) }
}
const exportar = async () => {
  const all = await adminService.historial({ ...f, page: 1, limit: 200 })
  downloadCsv(`historial-elcuache-${f.desde}_${f.hasta}.csv`,
    ['Cuenta', 'Fecha', 'Mesa', 'Mesero', 'Platillos', 'Subtotal', 'Descuento', 'Total', 'Propina', 'Métodos', 'Estado'],
    all.cuentas.map(c => [c.id, c.cerrada_en || c.abierta_en, c.mesa_numero, c.mesero || 'QR', c.platillos, c.subtotal, c.descuento, c.total, c.propina, c.metodos.join(' + '), c.estado]))
}

onMounted(async () => {
  cargar()
  try {
    mesas.value = await catalogoService.mesas(true)
    meseros.value = (await personalService.empleados({ puesto: 'Mesero' })).empleados
  } catch { /* filtros opcionales */ }
})
</script>
