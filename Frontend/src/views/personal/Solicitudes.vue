<template>
  <div>
    <PageHeader eyebrow="Equipo" title="Solicitudes y horas" subtitle="Aprueba vacaciones, permisos y cambios de turno; revisa horas, puntualidad, propinas y pago estimado del periodo." />

    <div class="grid 2xl:grid-cols-[420px_1fr] gap-4">
      <!-- ═══ Bandeja ═══ -->
      <section class="card p-5 h-fit no-print" data-reveal>
        <div class="flex items-center justify-between mb-4">
          <p class="panel-title">Bandeja</p>
          <div class="seg"><button :class="{ on: filtro === 'pendiente' }" @click="filtro = 'pendiente'; cargarSol()">Pendientes</button><button :class="{ on: !filtro }" @click="filtro = ''; cargarSol()">Todas</button></div>
        </div>
        <TransitionGroup name="list" tag="ul" class="space-y-3 relative">
          <li v-for="s in solicitudes" :key="s.id" class="rounded-2xl border border-line p-4" :class="s.estado === 'pendiente' ? 'bg-surface-2/40' : 'opacity-75'">
            <div class="flex items-start gap-3">
              <Avatar :src="s.foto" :name="s.empleado" :puesto="s.puesto" :size="40" />
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <p class="font-bold">{{ s.empleado }}</p>
                  <span class="chip" :class="tipo(s.tipo).cls"><component :is="tipo(s.tipo).icon" :size="12" /> {{ tipo(s.tipo).l }}</span>
                </div>
                <p class="text-sm text-ink-2 mt-1">
                  <template v-if="s.tipo === 'cambio_turno'">
                    Cede su turno del <b>{{ fecha(s.turno?.fecha + ' 12:00', { weekday: 'short', day: 'numeric', month: 'short' }) }}</b> <span class="num">{{ s.turno?.inicio }}–{{ s.turno?.fin }}</span> a <b>{{ s.companero }}</b>
                    <template v-if="s.turno_companero"> a cambio del {{ fecha(s.turno_companero.fecha + ' 12:00', { weekday: 'short', day: 'numeric' }) }}</template>
                  </template>
                  <template v-else>
                    {{ fecha(s.fecha_inicio + ' 12:00', { day: 'numeric', month: 'short' }) }}<template v-if="s.fecha_fin !== s.fecha_inicio"> – {{ fecha(s.fecha_fin + ' 12:00', { day: 'numeric', month: 'short' }) }}</template>
                    · <b class="num">{{ s.dias }} día{{ s.dias === 1 ? '' : 's' }}</b>
                  </template>
                </p>
                <p v-if="s.motivo" class="text-xs text-ink-3 mt-1 italic">“{{ s.motivo }}”</p>
                <p v-if="s.estado !== 'pendiente'" class="text-xs mt-2"><StatusChip :estado="s.estado" /> <span class="text-ink-3">{{ s.resuelto_por }}{{ s.respuesta ? ': ' + s.respuesta : '' }}</span></p>
              </div>
            </div>
            <div v-if="s.estado === 'pendiente'" class="grid grid-cols-2 gap-2 mt-3">
              <button class="btn btn-soft btn-sm" @click="resolver(s, false)"><PhX :size="15" /> Rechazar</button>
              <button class="btn btn-gold btn-sm" @click="resolver(s, true)"><PhCheck :size="15" weight="bold" /> Aprobar</button>
            </div>
          </li>
        </TransitionGroup>
        <EmptyState v-if="!solicitudes.length" :icon="PhTray" title="Bandeja vacía" text="No hay solicitudes por revisar." />
      </section>

      <!-- ═══ Reporte ═══ -->
      <section class="space-y-4 min-w-0">
        <div class="card p-4 flex flex-wrap items-center gap-2 no-print" data-reveal>
          <div class="seg">
            <button v-for="p in periodos" :key="p.id" :class="{ on: periodo === p.id }" @click="setPeriodo(p.id)">{{ p.l }}</button>
          </div>
          <input v-model="desde" type="date" class="input h-9 w-auto text-xs" @change="periodo = 'rango'; cargarRep()" />
          <input v-model="hasta" type="date" class="input h-9 w-auto text-xs" @change="periodo = 'rango'; cargarRep()" />
          <span class="flex-1" />
          <button class="btn btn-soft btn-sm" @click="excel"><PhFileXls :size="16" /> Excel</button>
          <button class="btn btn-soft btn-sm" @click="pdf"><PhFilePdf :size="16" /> PDF</button>
        </div>

        <div id="reporte" class="space-y-4">
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="card p-4" data-reveal><p class="eyebrow">Horas trabajadas</p><p class="num text-2xl mt-2">{{ rep.totales?.horas_trabajadas ?? 0 }}</p></div>
            <div class="card p-4" data-reveal><p class="eyebrow">Horas extra</p><p class="num text-2xl mt-2 text-info">{{ rep.totales?.horas_extra ?? 0 }}</p></div>
            <div class="card p-4" data-reveal><p class="eyebrow">Faltas / tardanzas</p><p class="num text-2xl mt-2"><span class="text-danger">{{ rep.totales?.faltas ?? 0 }}</span> / <span class="text-warn">{{ rep.totales?.tardanzas ?? 0 }}</span></p></div>
            <div class="card p-4" data-reveal><p class="eyebrow">Pago estimado</p><p class="num text-2xl mt-2 text-gold">{{ money(rep.totales?.pago_estimado || 0) }}</p></div>
          </div>

          <div class="card overflow-x-auto" data-reveal>
            <div class="px-5 pt-4 pb-2"><p class="panel-title">Reporte por empleado</p><p class="text-xs text-ink-3">{{ fecha(rep.desde + ' 12:00') }} – {{ fecha(rep.hasta + ' 12:00') }}</p></div>
            <table class="tbl">
              <thead><tr><th>Empleado</th><th class="text-right">Días</th><th class="text-right">Normales</th><th class="text-right">Extra</th><th class="text-right">Faltas</th><th class="text-right">Tarde</th><th class="text-right">Puntualidad</th><th class="text-right">Propinas</th><th class="text-right">Pago estimado</th></tr></thead>
              <tbody>
                <tr v-for="f in rep.filas" :key="f.empleado.id">
                  <td><div class="flex items-center gap-2.5"><Avatar :src="f.empleado.foto" :name="f.empleado.nombre" :puesto="f.empleado.puesto" :size="30" /><div><p class="font-bold text-[13px]">{{ f.empleado.nombre }}</p><p class="text-[10px] text-ink-3">{{ f.empleado.tipo_pago }}</p></div></div></td>
                  <td class="text-right num">{{ f.dias_trabajados }}</td>
                  <td class="text-right num">{{ f.horas_normales }}</td>
                  <td class="text-right num text-info">{{ f.horas_extra || '—' }}</td>
                  <td class="text-right num" :class="f.faltas ? 'text-danger font-bold' : 'text-ink-3'">{{ f.faltas }}</td>
                  <td class="text-right num" :class="f.tardanzas ? 'text-warn' : 'text-ink-3'">{{ f.tardanzas }}</td>
                  <td class="text-right">
                    <span v-if="f.puntualidad != null" class="num font-bold" :class="f.puntualidad >= 90 ? 'text-ok' : f.puntualidad >= 70 ? 'text-warn' : 'text-danger'">{{ f.puntualidad }}%</span><span v-else class="text-ink-3">—</span>
                  </td>
                  <td class="text-right num text-ok">{{ money(f.propinas) }}</td>
                  <td class="text-right num font-semibold" :title="`Base ${money(f.pago_base)} + extra ${money(f.pago_extra)} + propinas ${money(f.propinas)}`">{{ money(f.pago_estimado) }}</td>
                </tr>
              </tbody>
            </table>
            <p class="text-[11px] text-ink-3 px-5 py-3 border-t border-line/50">Pago estimado = salario prorrateado (o horas × tarifa) + horas extra al 150% + propinas repartidas según quién estaba en turno al cobrarse. Referencia, no sustituye la planilla.</p>
          </div>

          <div class="card p-5" data-reveal>
            <p class="panel-title mb-1">Puntualidad del equipo</p>
            <p class="text-xs text-ink-3 mb-4">Llegadas a tiempo, tarde y faltas por día</p>
            <StackedColumns v-if="rep.puntualidad?.length" :cols="cols" :series="series" aria-label="Puntualidad del equipo por día" />
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { PhX, PhCheck, PhTray, PhFileXls, PhFilePdf, PhAirplaneTilt, PhNote, PhSwap } from '@phosphor-icons/vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Avatar from '@/components/ui/Avatar.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StackedColumns from '@/components/charts/StackedColumns.vue'
import { personalService } from '@/services'
import { useUiStore } from '@/stores/ui'
import { useRealtimeStore } from '@/stores/realtime'
import { money, fecha, isoDate, addDays, weekStart, diaCorto, downloadCsv } from '@/utils/format'
import { pedirMotivo } from '@/plugins/swal'

const ui = useUiStore()
const rt = useRealtimeStore()
const solicitudes = ref([])
const filtro = ref('pendiente')
const rep = ref({})
const hoy = isoDate()
const periodo = ref('semana')
const desde = ref(weekStart(hoy))
const hasta = ref(hoy)
const periodos = [{ id: 'semana', l: 'Esta semana' }, { id: 'quincena', l: '15 días' }, { id: 'mes', l: 'Este mes' }]
const tipo = (t) => ({ vacaciones: { l: 'Vacaciones', cls: 'chip-sage', icon: PhAirplaneTilt }, permiso: { l: 'Permiso', cls: 'chip-info', icon: PhNote }, cambio_turno: { l: 'Cambio de turno', cls: 'chip-gold', icon: PhSwap } }[t])
const series = [{ key: 'a_tiempo', label: 'A tiempo', color: 'ok' }, { key: 'tarde', label: 'Tarde', color: 'warn' }, { key: 'falta', label: 'Falta', color: 'danger' }]
const cols = computed(() => (rep.value.puntualidad || []).map(p => ({
  label: rep.value.puntualidad.length > 14 ? p.fecha.slice(8) : diaCorto(p.fecha),
  tooltip: fecha(p.fecha + ' 12:00', { weekday: 'long', day: 'numeric', month: 'short' }),
  values: { a_tiempo: p.a_tiempo, tarde: p.tarde, falta: p.falta },
  footer: p.puntualidad != null ? `Puntualidad ${p.puntualidad}%` : 'Sin registros',
})))

const setPeriodo = (p) => {
  periodo.value = p
  if (p === 'semana') desde.value = weekStart(hoy)
  if (p === 'quincena') desde.value = addDays(hoy, -14)
  if (p === 'mes') desde.value = hoy.slice(0, 8) + '01'
  hasta.value = hoy
  cargarRep()
}
const cargarSol = async () => { try { solicitudes.value = await personalService.solicitudes(filtro.value || undefined) } catch (e) { ui.error(e) } }
const cargarRep = async () => { try { rep.value = await personalService.reporteHoras(desde.value, hasta.value) } catch (e) { ui.error(e) } }

const resolver = async (s, aprobar) => {
  let respuesta = null
  if (!aprobar) {
    respuesta = await pedirMotivo('¿Rechazar la solicitud?', `Le avisaremos a ${s.empleado} el motivo.`, { confirmar: 'Rechazar' })
    if (!respuesta) return
  }
  try {
    await personalService.resolver(s.id, aprobar, respuesta)
    ui.toast(aprobar ? (s.tipo === 'cambio_turno' ? 'Cambio aplicado al horario' : 'Aprobada · sus turnos de esos días se liberaron') : 'Solicitud rechazada', aprobar ? 'success' : 'info')
    cargarSol(); rt.poll()
  } catch (e) { ui.error(e) }
}

const excel = () => downloadCsv(`reporte-horas-${rep.value.desde}_${rep.value.hasta}.csv`,
  ['Empleado', 'Puesto', 'Tipo de pago', 'Días trabajados', 'Horas trabajadas', 'Horas normales', 'Horas extra', 'Faltas', 'Tardanzas', 'Puntualidad %', 'Propinas', 'Pago base', 'Pago extra', 'Pago estimado'],
  rep.value.filas.map(f => [f.empleado.nombre, f.empleado.puesto, f.empleado.tipo_pago, f.dias_trabajados, f.horas_trabajadas, f.horas_normales, f.horas_extra, f.faltas, f.tardanzas, f.puntualidad ?? '', f.propinas, f.pago_base, f.pago_extra, f.pago_estimado]))

// PDF: ventana con el reporte en estilo claro, lista para "Guardar como PDF"
const pdf = () => {
  const w = window.open('', '_blank')
  if (!w) return ui.toast('Permite las ventanas emergentes', 'warn')
  const filas = rep.value.filas.map(f => `<tr><td>${f.empleado.nombre}<br><small>${f.empleado.puesto}</small></td><td>${f.dias_trabajados}</td><td>${f.horas_normales}</td><td>${f.horas_extra}</td><td>${f.faltas}</td><td>${f.tardanzas}</td><td>${f.puntualidad ?? '—'}${f.puntualidad != null ? '%' : ''}</td><td>${money(f.propinas)}</td><td><b>${money(f.pago_estimado)}</b></td></tr>`).join('')
  w.document.write(`<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Reporte de horas</title>
  <style>body{font-family:Manrope,Arial,sans-serif;color:#231F1C;margin:28px}h1{font-family:Georgia,serif;font-weight:500;margin:0}
  table{width:100%;border-collapse:collapse;margin-top:18px;font-size:12px}th,td{padding:7px 8px;border-bottom:1px solid #ddd;text-align:right}th:first-child,td:first-child{text-align:left}
  th{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#7a6d5e}small{color:#888}.tot{display:flex;gap:28px;margin-top:14px;font-size:13px}</style></head><body>
  <div style="display:flex;align-items:center;gap:14px"><img src="${new URL('./img/logo-ink.png', location.href.split('#')[0]).href}" width="64"><div><h1>Reporte de horas</h1><div>El Cuache · ${rep.value.desde} a ${rep.value.hasta}</div></div></div>
  <div class="tot"><span>Horas: <b>${rep.value.totales.horas_trabajadas}</b></span><span>Extra: <b>${rep.value.totales.horas_extra}</b></span><span>Faltas: <b>${rep.value.totales.faltas}</b></span><span>Propinas: <b>${money(rep.value.totales.propinas)}</b></span><span>Pago estimado: <b>${money(rep.value.totales.pago_estimado)}</b></span></div>
  <table><thead><tr><th>Empleado</th><th>Días</th><th>Normales</th><th>Extra</th><th>Faltas</th><th>Tarde</th><th>Puntualidad</th><th>Propinas</th><th>Pago est.</th></tr></thead><tbody>${filas}</tbody></table>
  <script>window.onload=()=>setTimeout(()=>window.print(),300)<\/script></body></html>`)
  w.document.close()
}

onMounted(() => { cargarSol(); cargarRep() })
</script>
