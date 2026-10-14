<template>
  <div>
    <PageHeader eyebrow="Gestión" title="Inventario y compras" subtitle="Stock de los productos controlados. Al registrar una compra el stock sube y, si estaba agotado por stock, vuelve solo a la carta.">
      <button class="btn btn-gold" @click="abrirCompra()"><PhShoppingCartSimple :size="18" /> Registrar compra</button>
    </PageHeader>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
      <KpiCard label="Productos controlados" :value="inv.resumen?.controlados || 0" :icon="PhPackage" />
      <KpiCard label="Stock bajo" :value="inv.resumen?.stock_bajo || 0" :icon="PhWarning" tone="warn" />
      <KpiCard label="Sin stock" :value="inv.resumen?.sin_stock || 0" :icon="PhProhibit" tone="danger" />
      <KpiCard label="Valor del inventario" :value="inv.resumen?.valor || 0" format="money" :icon="PhCoins" tone="ok" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[1.4fr_1fr] gap-4">
      <div class="card overflow-hidden" data-reveal>
        <div class="flex items-center justify-between px-5 py-4 border-b border-line/60">
          <p class="panel-title">Stock</p>
          <div class="seg"><button :class="{ on: !soloBajo }" @click="soloBajo = false">Todo</button><button :class="{ on: soloBajo }" @click="soloBajo = true">Stock bajo</button></div>
        </div>
        <div class="overflow-x-auto">
          <table class="tbl">
            <thead><tr><th>Producto</th><th class="w-48">Nivel</th><th class="text-right">Stock</th><th class="text-right">Costo</th><th /></tr></thead>
            <tbody>
              <tr v-for="p in productos" :key="p.id">
                <td>
                  <div class="flex items-center gap-3">
                    <img :src="p.imagen || './img/almuerzo.jpg'" class="w-9 h-9 rounded-lg object-cover" :style="{ objectPosition: p.imagen_posicion }" alt="" />
                    <div><p class="font-bold">{{ p.nombre }}</p>
                      <StatusChip v-if="p.stock <= 0" estado="sin_stock" /><StatusChip v-else-if="p.stock_bajo" estado="stock_bajo" /></div>
                  </div>
                </td>
                <td>
                  <div class="h-2 rounded-full bg-surface-3 overflow-hidden" :title="`Mínimo ${p.stock_minimo}`">
                    <div class="h-full rounded-full" :class="p.stock <= 0 ? 'bg-danger' : p.stock_bajo ? 'bg-warn' : 'bg-ok'" :style="{ width: Math.min(100, p.stock / Math.max(1, p.stock_minimo * 3) * 100) + '%' }" />
                  </div>
                  <p class="text-[10px] text-ink-3 mt-1">mínimo {{ p.stock_minimo }}</p>
                </td>
                <td class="text-right num text-lg font-semibold">{{ p.stock }}</td>
                <td class="text-right num text-ink-3">{{ money(p.costo) }}</td>
                <td class="text-right whitespace-nowrap">
                  <button class="btn btn-soft btn-sm" @click="abrirCompra(p)"><PhPlus :size="14" /> Compra</button>
                  <button class="btn btn-ghost btn-sm" title="Ajustar por conteo" @click="ajustar(p)"><PhSliders :size="15" /></button>
                </td>
              </tr>
            </tbody>
          </table>
          <EmptyState v-if="!productos.length" :icon="PhPackage" title="Nada por aquí" text="Activa «Controlar stock» en un platillo o registra una compra." />
        </div>
      </div>

      <div class="card overflow-hidden" data-reveal>
        <div class="px-5 py-4 border-b border-line/60 flex flex-wrap items-center gap-2">
          <p class="panel-title flex-1">Compras</p>
          <input v-model="desde" type="date" class="input h-9 w-auto text-xs" @change="cargarCompras" />
          <input v-model="hasta" type="date" class="input h-9 w-auto text-xs" @change="cargarCompras" />
        </div>
        <ul class="divide-y divide-line/50 max-h-[520px] overflow-y-auto">
          <li v-for="c in compras.compras" :key="c.id" class="px-5 py-3 flex items-center gap-3">
            <span class="grid place-items-center w-10 h-10 rounded-xl bg-surface-2 text-ink-3"><PhTruck :size="18" /></span>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-bold truncate">{{ c.cantidad }} × {{ c.producto }}</p>
              <p class="text-xs text-ink-3 truncate">{{ c.proveedor }} · {{ fechaHora(c.fecha) }}</p>
            </div>
            <span class="num text-sm">{{ money(c.total) }}</span>
          </li>
        </ul>
        <p v-if="!compras.compras?.length" class="text-sm text-ink-3 p-5">Sin compras en el periodo.</p>
        <div class="px-5 py-3 border-t border-line/60 flex justify-between text-sm"><span class="text-ink-3">Total del periodo</span><b class="num">{{ money(compras.total || 0) }}</b></div>
      </div>
    </div>

    <!-- Registrar compra -->
    <SidePanel v-model="compraOpen" title="Registrar compra" eyebrow="Inventario" size="md">
      <form class="space-y-4" @submit.prevent="guardarCompra">
        <div>
          <label class="label">Producto</label>
          <select v-model="compra.producto_id" class="input" :class="err.producto_id ? 'input-error' : ''">
            <option :value="null" disabled>Elige un producto…</option>
            <option v-for="p in todos" :key="p.id" :value="p.id">{{ p.nombre }}{{ p.controla_stock ? ` (stock ${p.stock})` : '' }}</option>
          </select>
          <p v-if="err.producto_id" class="field-error">{{ err.producto_id }}</p>
        </div>
        <div>
          <label class="label">Proveedor</label>
          <input v-model="compra.proveedor" class="input" :class="err.proveedor ? 'input-error' : ''" list="provs" placeholder="Distribuidora…" />
          <datalist id="provs"><option v-for="p in compras.proveedores" :key="p" :value="p" /></datalist>
          <p v-if="err.proveedor" class="field-error">{{ err.proveedor }}</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="label">Cantidad</label><input v-model.number="compra.cantidad" type="number" min="1" class="input num" :class="err.cantidad ? 'input-error' : ''" /></div>
          <div><label class="label">Costo unitario</label><input v-model.number="compra.costo_unitario" type="number" min="0" step="0.01" class="input num" /></div>
        </div>
        <div class="rounded-2xl bg-surface-2/60 border border-line/60 p-4 flex justify-between items-center">
          <span class="text-sm text-ink-3">Total de la compra</span>
          <span class="num text-2xl font-semibold">{{ money((compra.cantidad || 0) * (compra.costo_unitario || 0)) }}</span>
        </div>
        <div><label class="label">Notas</label><textarea v-model="compra.notas" rows="2" class="input" placeholder="Factura, lote…" /></div>
        <p class="text-xs text-ink-3 flex gap-1.5"><PhInfo :size="14" class="shrink-0 mt-0.5" /> El costo del producto se actualiza al de esta compra.</p>
        <button class="btn btn-gold btn-lg w-full" :disabled="guardando">Registrar compra</button>
      </form>
    </SidePanel>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { PhShoppingCartSimple, PhPackage, PhWarning, PhProhibit, PhCoins, PhPlus, PhSliders, PhTruck, PhInfo } from '@phosphor-icons/vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import KpiCard from '@/components/ui/KpiCard.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SidePanel from '@/components/ui/SidePanel.vue'
import { adminService, catalogoService } from '@/services'
import { useUiStore } from '@/stores/ui'
import { useRealtimeStore } from '@/stores/realtime'
import { money, fechaHora, isoDate } from '@/utils/format'
import { pedirMotivo, pedirNumero } from '@/plugins/swal'

const ui = useUiStore()
const rt = useRealtimeStore()
const inv = ref({})
const compras = ref({})
const todos = ref([])
const soloBajo = ref(false)
const desde = ref(isoDate().slice(0, 8) + '01')
const hasta = ref(isoDate())
const compraOpen = ref(false)
const compra = reactive({})
const err = ref({})
const guardando = ref(false)

const productos = computed(() => (inv.value.productos || []).filter(p => !soloBajo.value || p.stock_bajo || p.stock <= 0))

const cargar = async () => {
  try { inv.value = await adminService.inventario() } catch (e) { ui.error(e) }
}
const cargarCompras = async () => {
  try { compras.value = await adminService.compras({ desde: desde.value, hasta: hasta.value }) } catch (e) { ui.error(e) }
}
const abrirCompra = async (p = null) => {
  if (!todos.value.length) todos.value = await catalogoService.productos()
  Object.assign(compra, { producto_id: p?.id ?? null, proveedor: '', cantidad: null, costo_unitario: p?.costo ?? null, notas: '' })
  err.value = {}
  compraOpen.value = true
}
const guardarCompra = async () => {
  guardando.value = true
  try {
    await adminService.registrarCompra({ ...compra })
    compraOpen.value = false
    ui.toast('Compra registrada y stock actualizado')
    cargar(); cargarCompras(); rt.poll()
  } catch (e) { err.value = e.errors; ui.error(e) }
  guardando.value = false
}
const ajustar = async (p) => {
  const n = await pedirNumero(`Ajustar stock de ${p.nombre}`, `Stock actual en sistema: ${p.stock}. Escribe lo que contaste.`, { min: 0, valor: p.stock })
  if (n === null) return
  const motivo = await pedirMotivo('Motivo del ajuste', 'Queda registrado en la auditoría.', { placeholder: 'Conteo físico, merma, vencido…', confirmar: 'Ajustar', peligro: false })
  if (!motivo) return
  try { await adminService.ajustar(p.id, n, motivo); ui.toast('Stock ajustado'); cargar(); rt.poll() } catch (e) { ui.error(e) }
}

onMounted(() => { cargar(); cargarCompras() })
</script>
