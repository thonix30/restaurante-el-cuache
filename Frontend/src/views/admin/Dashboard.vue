<template>
  <div>
    <PageHeader :eyebrow="saludo" :title="`Hola, ${auth.usuario?.nombre_corto || ''}`" subtitle="Así va El Cuache. Los números se comparan con el periodo anterior de la misma duración.">
      <div class="seg">
        <button v-for="p in periodos" :key="p.id" :class="{ on: periodo === p.id }" @click="setPeriodo(p.id)">{{ p.label }}</button>
      </div>
      <div v-if="periodo === 'rango'" class="flex items-center gap-2">
        <input v-model="desde" type="date" class="input h-9 w-auto text-xs" @change="cargar" />
        <span class="text-ink-3">—</span>
        <input v-model="hasta" type="date" class="input h-9 w-auto text-xs" @change="cargar" />
      </div>
    </PageHeader>

    <!-- KPIs -->
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4 mb-5">
      <KpiCard label="Ventas" :value="d.kpis?.ventas || 0" format="money" :icon="PhCurrencyCircleDollar" :delta="d.cambio?.ventas" :hint="`Utilidad ${money(d.kpis?.utilidad || 0)}`" />
      <KpiCard label="Ticket promedio" :value="d.kpis?.ticket_promedio || 0" format="money" :icon="PhReceipt" :delta="d.cambio?.ticket_promedio" tone="info" :hint="`${d.kpis?.comensales || 0} comensales`" />
      <KpiCard label="Cuentas cobradas" :value="d.kpis?.cuentas || 0" :icon="PhCheckSquareOffset" :delta="d.cambio?.cuentas" tone="sage" :hint="`${d.kpis?.platillos || 0} platillos`" />
      <KpiCard label="Propinas" :value="d.kpis?.propinas || 0" format="money" :icon="PhHandCoins" :delta="d.cambio?.propinas" tone="ok" :hint="`Descuentos ${money(d.kpis?.descuentos || 0)}`" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[1.7fr_1fr] gap-4 mb-4">
      <!-- Ingresos -->
      <div class="card p-5" data-reveal>
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
          <div>
            <p class="panel-title">Ingresos por {{ d.agrupado === 'hora' ? 'hora' : 'día' }}</p>
            <p class="text-xs text-ink-3 mt-0.5">{{ rangoTxt }}</p>
          </div>
          <div class="flex items-center gap-4 text-xs text-ink-2">
            <span class="flex items-center gap-1.5"><span class="w-4 h-0.5 bg-gold rounded" />Este periodo</span>
            <span class="flex items-center gap-1.5"><span class="w-4 border-t border-dashed border-ink-3" />Anterior</span>
            <button class="btn btn-ghost btn-sm" @click="tabla = !tabla"><PhTable :size="15" /> {{ tabla ? 'Gráfica' : 'Tabla' }}</button>
          </div>
        </div>
        <AreaChart v-if="!tabla && d.serie" :values="d.serie.map(s => s.ventas)" :prev="d.serie_anterior" :labels="etiquetasX" :tooltip-labels="etiquetasTip"
                   :fmt="money" :fmt-axis="moneyShort" aria-label="Ingresos del periodo comparados con el anterior" />
        <div v-else class="max-h-72 overflow-y-auto">
          <table class="tbl"><thead><tr><th>{{ d.agrupado === 'hora' ? 'Hora' : 'Día' }}</th><th class="text-right">Cuentas</th><th class="text-right">Ventas</th><th class="text-right">Anterior</th></tr></thead>
            <tbody><tr v-for="(s, i) in d.serie" :key="s.clave"><td>{{ etiquetasTip[i] }}</td><td class="text-right num">{{ s.cuentas }}</td><td class="text-right num">{{ money(s.ventas) }}</td><td class="text-right num text-ink-3">{{ money(d.serie_anterior?.[i] || 0) }}</td></tr></tbody>
          </table>
        </div>
      </div>

      <!-- Categorías -->
      <div class="card p-5" data-reveal>
        <p class="panel-title mb-1">Ingresos por categoría</p>
        <p class="text-xs text-ink-3 mb-5">Participación en las ventas</p>
        <DonutChart v-if="d.categorias?.length" :rows="d.categorias.map(c => ({ label: c.categoria, value: c.ventas }))" :fmt="money" :fmt-centro="moneyShort" aria-label="Ventas por categoría" />
        <EmptyState v-else :icon="PhChartPie" title="Sin ventas" text="Aún no hay cuentas cobradas en este periodo." />
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
      <div class="card p-5" data-reveal>
        <p class="panel-title mb-4">Top platillos</p>
        <BarList v-if="d.top_platillos?.length" :rows="d.top_platillos.map(p => ({ label: p.nombre, value: p.unidades, extra: money(p.ventas) }))" :fmt="(v) => v + ' u.'" />
        <p v-else class="text-sm text-ink-3">Sin datos.</p>
      </div>
      <div class="card p-5" data-reveal>
        <p class="panel-title mb-4">Ventas por mesero</p>
        <ul v-if="d.meseros?.length" class="space-y-3">
          <li v-for="(m, i) in d.meseros" :key="m.mesero" class="flex items-center gap-3">
            <span class="num text-xs text-ink-3 w-4">{{ i + 1 }}</span>
            <Avatar :name="m.mesero" :size="36" puesto="Mesero" />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-bold truncate">{{ m.mesero }}</p>
              <p class="text-[11px] text-ink-3">{{ m.cuentas }} cuentas · ticket {{ money(m.ticket) }}</p>
            </div>
            <div class="text-right">
              <p class="num text-sm font-semibold">{{ money(m.ventas) }}</p>
              <p class="num text-[11px] text-ok">+{{ money(m.propinas) }}</p>
            </div>
          </li>
        </ul>
        <p v-else class="text-sm text-ink-3">Sin datos.</p>
      </div>
      <div class="card p-5" data-reveal>
        <p class="panel-title mb-4">Métodos de pago</p>
        <BarList v-if="d.metodos?.length" :rows="d.metodos.map(m => ({ label: m.metodo, value: m.monto, extra: m.n + ' cobros' }))" :fmt="money" />
        <p v-else class="text-sm text-ink-3">Sin datos.</p>
        <div class="grid grid-cols-2 gap-2 mt-5 pt-4 border-t border-line/60 text-sm">
          <div><p class="text-ink-3 text-xs">Costo de lo vendido</p><p class="num">{{ money(d.kpis?.costo || 0) }}</p></div>
          <div><p class="text-ink-3 text-xs">Compras del periodo</p><p class="num">{{ money(d.kpis?.compras || 0) }}</p></div>
        </div>
      </div>
    </div>

    <!-- Alertas -->
    <div v-if="alertas.length" class="card p-5" data-reveal>
      <div class="flex items-center justify-between mb-3">
        <p class="panel-title">Alertas</p>
        <span class="chip chip-neutral num">{{ alertas.length }}</span>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2">
        <router-link v-for="(a, i) in alertas.slice(0, 9)" :key="i" :to="a.ruta" class="flex items-start gap-3 rounded-xl p-3 border border-line/60 hover:border-gold/50 transition">
          <span class="grid place-items-center w-9 h-9 rounded-xl shrink-0" :class="nivelCls(a.nivel)"><component :is="iconoAlerta(a.tipo)" :size="18" weight="duotone" /></span>
          <div class="min-w-0"><p class="text-sm font-bold">{{ a.titulo }}</p><p class="text-xs text-ink-3 line-clamp-2">{{ a.mensaje }}</p></div>
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  PhCurrencyCircleDollar, PhReceipt, PhCheckSquareOffset, PhHandCoins, PhTable, PhChartPie, PhFileText, PhCake,
  PhSignOut, PhUsersThree, PhTray, PhPackage, PhBell,
} from '@phosphor-icons/vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import KpiCard from '@/components/ui/KpiCard.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Avatar from '@/components/ui/Avatar.vue'
import AreaChart from '@/components/charts/AreaChart.vue'
import BarList from '@/components/charts/BarList.vue'
import DonutChart from '@/components/charts/DonutChart.vue'
import { adminService, personalService } from '@/services'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { money, moneyShort, isoDate, addDays, fecha, diaCorto } from '@/utils/format'

const auth = useAuthStore()
const ui = useUiStore()
const d = ref({})
const alertas = ref([])
const tabla = ref(false)
const periodo = ref('7d')
const hoy = isoDate()
const desde = ref(addDays(hoy, -6))
const hasta = ref(hoy)
const periodos = [{ id: 'hoy', label: 'Hoy' }, { id: '7d', label: '7 días' }, { id: '30d', label: '30 días' }, { id: 'mes', label: 'Este mes' }, { id: 'rango', label: 'Rango' }]
const h = new Date().getHours()
const saludo = h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches'

const setPeriodo = (p) => {
  periodo.value = p
  if (p === 'hoy') { desde.value = hoy; hasta.value = hoy }
  if (p === '7d') { desde.value = addDays(hoy, -6); hasta.value = hoy }
  if (p === '30d') { desde.value = addDays(hoy, -29); hasta.value = hoy }
  if (p === 'mes') { desde.value = hoy.slice(0, 8) + '01'; hasta.value = hoy }
  if (p !== 'rango') cargar()
}
const cargar = async () => {
  try { d.value = await adminService.estadisticas({ desde: desde.value, hasta: hasta.value }) } catch (e) { ui.error(e) }
}
const rangoTxt = computed(() => d.value.desde === d.value.hasta ? fecha(d.value.desde + ' 12:00', { weekday: 'long', day: 'numeric', month: 'long' }) : `${fecha(d.value.desde + ' 12:00')} – ${fecha(d.value.hasta + ' 12:00')}`)
const etiquetasX = computed(() => (d.value.serie || []).map(s => d.value.agrupado === 'hora' ? s.etiqueta.slice(0, 2) + 'h' : (d.value.serie.length > 10 ? s.etiqueta.slice(8) : diaCorto(s.etiqueta))))
const etiquetasTip = computed(() => (d.value.serie || []).map(s => d.value.agrupado === 'hora' ? s.etiqueta : fecha(s.etiqueta + ' 12:00', { weekday: 'short', day: 'numeric', month: 'short' })))
const nivelCls = (n) => ({ alta: 'bg-danger/15 text-danger', media: 'bg-warn/15 text-warn', baja: 'bg-info/15 text-info', info: 'bg-gold/15 text-gold' }[n])
const iconoAlerta = (t) => ({ documento: PhFileText, cumpleanos: PhCake, sin_salida: PhSignOut, falta_personal: PhUsersThree, solicitud: PhTray, stock_bajo: PhPackage }[t] || PhBell)

onMounted(async () => {
  cargar()
  try { alertas.value = await personalService.alertas() } catch { /* opcional */ }
})
</script>
