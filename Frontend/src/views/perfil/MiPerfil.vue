<template>
  <div v-if="!p" class="space-y-3 max-w-3xl"><div class="skeleton h-44" /><div class="skeleton h-32" /><div class="skeleton h-64" /></div>
  <div v-else class="max-w-5xl">
    <!-- Saludo -->
    <div class="flex items-center gap-4 mb-6" data-reveal>
      <Avatar :src="p.empleado.foto" :name="p.empleado.nombre_completo" :puesto="p.empleado.puesto" :size="64" :dot="enTurno ? 'ok' : null" />
      <div>
        <p class="eyebrow">{{ saludo }}</p>
        <h1 class="font-serif text-3xl sm:text-4xl leading-none mt-1">{{ p.empleado.nombre }}</h1>
        <p class="text-sm text-ink-3 mt-1">{{ p.empleado.puesto }}</p>
      </div>
    </div>

    <div class="grid lg:grid-cols-[1.2fr_1fr] gap-4">
      <div class="space-y-4">
        <!-- Próximo turno -->
        <div class="card p-6 relative overflow-hidden" data-reveal>
          <div class="absolute inset-0 opacity-60 pointer-events-none" style="background: radial-gradient(120% 90% at 100% 0%, rgb(var(--gold) / .22), transparent 60%)" />
          <Botanical class="absolute -right-8 -bottom-10 w-40 text-gold/15" />
          <div class="relative">
            <p class="eyebrow text-gold">{{ enTurno ? 'Estás en turno' : 'Tu próximo turno' }}</p>
            <template v-if="p.proximo_turno">
              <p class="font-serif text-3xl mt-2 first-letter:uppercase">{{ diaTurno }}</p>
              <p class="num text-4xl sm:text-5xl font-semibold gold-text mt-1">{{ p.proximo_turno.hora_inicio }} – {{ p.proximo_turno.hora_fin }}</p>
              <p v-if="faltaTxt" class="text-sm text-ink-2 mt-2 flex items-center gap-1.5"><PhHourglassMedium :size="16" /> {{ faltaTxt }}</p>
            </template>
            <p v-else class="font-serif text-2xl mt-2 text-ink-2">Sin turnos publicados por ahora</p>

            <div class="flex flex-wrap gap-2 mt-6">
              <button v-for="a in acciones" :key="a" class="btn" :class="a === 'entrada' || a === 'fin_descanso' ? 'btn-gold' : 'btn-soft'" :disabled="marcando" @click="marcar(a)">
                <component :is="ICONO_ACC[a]" :size="18" /> {{ ETIQ_ACC[a] }}
              </button>
            </div>
            <p v-if="p.marcacion.ultima" class="text-xs text-ink-3 mt-3">Última marca: {{ ULTIMA[p.marcacion.ultima.tipo] }} a las <span class="num">{{ hora(p.marcacion.ultima.marcado_en) }}</span></p>
          </div>
        </div>

        <!-- Horario -->
        <div class="card p-5" data-reveal>
          <div class="flex items-center justify-between mb-4">
            <p class="panel-title">Mi horario</p>
            <div class="seg"><button :class="{ on: semanaVista === 0 }" @click="semanaVista = 0">Esta semana</button><button :class="{ on: semanaVista === 1 }" @click="semanaVista = 1">Siguiente</button></div>
          </div>
          <ul class="space-y-1.5">
            <li v-for="dia in diasSemana" :key="dia" class="flex items-center gap-3 rounded-xl px-3 py-2.5" :class="dia === hoy ? 'bg-gold/10 border border-gold/40' : 'bg-surface-2/40'">
              <div class="w-14"><p class="text-[11px] uppercase font-bold text-ink-3 capitalize">{{ diaCorto(dia) }}</p><p class="num text-lg leading-none" :class="dia === hoy ? 'text-gold' : ''">{{ dia.slice(8) }}</p></div>
              <template v-if="turnosDia(dia).length">
                <div v-for="t in turnosDia(dia)" :key="t.id" class="flex items-center gap-2">
                  <span class="w-2 h-8 rounded-full" :style="{ background: `rgb(var(--turno-${t.tipo === 'personalizado' ? 'custom' : t.tipo}))` }" />
                  <div><p class="num font-semibold">{{ t.hora_inicio }} – {{ t.hora_fin }}</p><p class="text-[11px] text-ink-3">{{ TIPOS[t.tipo] }}</p></div>
                </div>
              </template>
              <span v-else class="text-sm text-ink-3">Descanso</span>
            </li>
          </ul>
        </div>
      </div>

      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div class="card p-5" data-reveal>
            <p class="eyebrow">Horas esta semana</p>
            <p class="num text-3xl font-semibold mt-2">{{ p.horas_semana }}<span class="text-base text-ink-3"> h</span></p>
          </div>
          <div class="card p-5 flex items-center gap-3" data-reveal>
            <svg viewBox="0 0 36 36" class="w-14 h-14 -rotate-90 shrink-0">
              <circle cx="18" cy="18" r="15" fill="none" stroke="rgb(var(--surface-3))" stroke-width="4" />
              <circle cx="18" cy="18" r="15" fill="none" stroke="rgb(var(--sage))" stroke-width="4" stroke-linecap="round" :stroke-dasharray="`${vacPct * 94.2} 94.2`" />
            </svg>
            <div><p class="num text-2xl font-semibold">{{ p.vacaciones.disponibles }}</p><p class="text-[11px] text-ink-3 leading-tight">días de vacaciones disponibles de {{ p.vacaciones.anuales }}</p></div>
          </div>
        </div>

        <button class="btn btn-gold btn-lg w-full" @click="abrirSolicitud"><PhPaperPlaneTilt :size="20" /> Solicitar permiso / cambio de turno</button>

        <div class="card p-5" data-reveal>
          <p class="panel-title mb-3">Mis solicitudes</p>
          <ul class="space-y-2">
            <li v-for="s in p.solicitudes" :key="s.id" class="flex items-center gap-3 rounded-xl bg-surface-2/40 px-3 py-2.5">
              <div class="flex-1 min-w-0">
                <p class="text-sm font-bold">{{ TIPO_SOL[s.tipo] }}</p>
                <p class="text-xs text-ink-3 truncate">{{ s.tipo === 'cambio_turno' ? `Con ${s.companero} · ${s.turno?.fecha}` : `${s.fecha_inicio}${s.fecha_fin !== s.fecha_inicio ? ' a ' + s.fecha_fin : ''}` }}{{ s.respuesta ? ' · ' + s.respuesta : '' }}</p>
              </div>
              <StatusChip :estado="s.estado === 'cancelada' ? 'cancelado' : s.estado" />
              <button v-if="s.estado === 'pendiente'" class="text-xs text-ink-3 hover:text-danger font-bold" @click="cancelarSol(s)">Cancelar</button>
            </li>
            <p v-if="!p.solicitudes.length" class="text-sm text-ink-3">Aún no has enviado solicitudes.</p>
          </ul>
        </div>

        <div class="card p-5" data-reveal>
          <p class="panel-title mb-3">Mi asistencia del mes</p>
          <div class="grid grid-cols-7 gap-1">
            <span v-for="i in offsetMes" :key="'o' + i" />
            <span v-for="d in p.asistencia_mes.dias" :key="d.fecha" class="aspect-square rounded-lg grid place-items-center text-[11px] num" :class="colorDia(d)" :title="d.estados.join(', ')">{{ Number(d.fecha.slice(8)) }}</span>
          </div>
          <div class="flex justify-between text-xs text-ink-3 mt-3">
            <span>Tardanzas <b class="text-warn num">{{ p.asistencia_mes.totales.tardanzas }}</b></span>
            <span>Faltas <b class="text-danger num">{{ p.asistencia_mes.totales.faltas }}</b></span>
            <span>Extra <b class="text-info num">{{ horasMin(p.asistencia_mes.totales.extra_min) }}</b></span>
          </div>
        </div>

        <button v-if="p.empleado.tiene_password" class="btn btn-ghost btn-sm w-full text-ink-3" @click="passOpen = true"><PhKey :size="15" /> Cambiar mi contraseña</button>
      </div>
    </div>

    <!-- Solicitud -->
    <Modal v-model="solOpen" title="Nueva solicitud" size="md">
      <div class="grid grid-cols-3 gap-2 mb-5">
        <button v-for="(l, k) in TIPO_SOL" :key="k" type="button" class="h-16 rounded-2xl border text-sm font-bold transition" :class="sol.tipo === k ? 'border-gold bg-gold/15 text-gold' : 'border-line text-ink-2'" @click="sol.tipo = k">{{ l }}</button>
      </div>
      <template v-if="sol.tipo !== 'cambio_turno'">
        <div class="grid grid-cols-2 gap-3">
          <div><label class="label">Desde</label><input v-model="sol.fecha_inicio" type="date" :min="hoy" class="input" /></div>
          <div><label class="label">Hasta</label><input v-model="sol.fecha_fin" type="date" :min="sol.fecha_inicio || hoy" class="input" /></div>
        </div>
        <p v-if="sol.tipo === 'vacaciones'" class="text-xs mt-2" :class="diasSol > p.vacaciones.disponibles ? 'text-danger' : 'text-ink-3'">{{ diasSol }} día(s) · disponibles: {{ p.vacaciones.disponibles }}</p>
      </template>
      <template v-else>
        <label class="label">Turno que quieres ceder</label>
        <select v-model="sol.turno_id" class="input mb-3"><option :value="null" disabled>Elige tu turno…</option>
          <option v-for="t in misTurnosFuturos" :key="t.id" :value="t.id">{{ fecha(t.fecha + ' 12:00', { weekday: 'short', day: 'numeric', month: 'short' }) }} · {{ t.hora_inicio }}–{{ t.hora_fin }}</option></select>
        <label class="label">Compañero que lo toma</label>
        <select v-model="sol.companero_id" class="input mb-3"><option :value="null" disabled>Elige…</option><option v-for="c in p.companeros" :key="c.id" :value="c.id">{{ c.nombre }} · {{ c.puesto }}</option></select>
        <label class="label">A cambio de su turno (opcional)</label>
        <select v-model="sol.turno_companero_id" class="input" :disabled="!sol.companero_id"><option :value="null">Sin intercambio</option>
          <option v-for="t in turnosCompanero" :key="t.id" :value="t.id">{{ fecha(t.fecha + ' 12:00', { weekday: 'short', day: 'numeric', month: 'short' }) }} · {{ t.hora_inicio }}–{{ t.hora_fin }}</option></select>
      </template>
      <label class="label mt-4">Motivo {{ sol.tipo === 'permiso' ? '*' : '(opcional)' }}</label>
      <textarea v-model="sol.motivo" rows="2" class="input" placeholder="Cuéntale a administración…" />
      <template #footer>
        <button class="btn btn-soft" @click="solOpen = false">Cancelar</button>
        <button class="btn btn-gold" :disabled="enviando" @click="enviarSolicitud">Enviar solicitud</button>
      </template>
    </Modal>

    <Modal v-model="passOpen" title="Cambiar contraseña" size="sm">
      <label class="label">Contraseña actual</label><input v-model="pass.actual" type="password" class="input mb-3" autocomplete="current-password" />
      <label class="label">Nueva contraseña</label><input v-model="pass.nueva" type="password" class="input" autocomplete="new-password" placeholder="Mínimo 6 caracteres" />
      <template #footer>
        <button class="btn btn-soft" @click="passOpen = false">Cancelar</button>
        <button class="btn btn-gold" :disabled="!pass.actual || pass.nueva.length < 6" @click="cambiarPass">Guardar</button>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { PhHourglassMedium, PhPaperPlaneTilt, PhKey, PhDoorOpen, PhCoffee, PhArrowCounterClockwise, PhSignOut } from '@phosphor-icons/vue'
import Avatar from '@/components/ui/Avatar.vue'
import Botanical from '@/components/ui/Botanical.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import Modal from '@/components/ui/Modal.vue'
import { personalService } from '@/services'
import { useUiStore } from '@/stores/ui'
import { useNow } from '@/composables/useNow'
import { hora, fecha, isoDate, addDays, weekStart, diaCorto, horasMin, toDate, espera } from '@/utils/format'

const ui = useUiStore()
const { now } = useNow()
const p = ref(null)
const hoy = isoDate()
const semanaVista = ref(0)
const marcando = ref(false)
const solOpen = ref(false)
const enviando = ref(false)
const sol = reactive({})
const passOpen = ref(false)
const pass = reactive({ actual: '', nueva: '' })

const TIPOS = { manana: 'Mañana', tarde: 'Tarde', noche: 'Noche', personalizado: 'Turno' }
const TIPO_SOL = { vacaciones: 'Vacaciones', permiso: 'Permiso', cambio_turno: 'Cambio de turno' }
const ETIQ_ACC = { entrada: 'Marcar entrada', inicio_descanso: 'Iniciar descanso', fin_descanso: 'Terminar descanso', salida: 'Marcar salida' }
const ULTIMA = { entrada: 'Entrada', inicio_descanso: 'Inicio de descanso', fin_descanso: 'Fin de descanso', salida: 'Salida' }
const ICONO_ACC = { entrada: PhDoorOpen, inicio_descanso: PhCoffee, fin_descanso: PhArrowCounterClockwise, salida: PhSignOut }
const h = new Date().getHours()
const saludo = h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches'

const acciones = computed(() => p.value?.marcacion.permitidas || [])
const enTurno = computed(() => ['entrada', 'fin_descanso', 'inicio_descanso'].includes(p.value?.marcacion.ultima?.tipo))
const diaTurno = computed(() => {
  const f = p.value?.proximo_turno?.fecha
  if (!f) return ''
  if (f === hoy) return 'Hoy'
  if (f === addDays(hoy, 1)) return 'Mañana'
  return fecha(f + ' 12:00', { weekday: 'long', day: 'numeric', month: 'long' })
})
const faltaTxt = computed(() => {
  const t = p.value?.proximo_turno
  if (!t || enTurno.value) return ''
  const seg = (toDate(`${t.fecha} ${t.hora_inicio}:00`).getTime() - now.value) / 1000
  return seg > 0 ? `Empieza en ${espera(seg)}` : 'Ya comenzó: marca tu entrada'
})
const diasSemana = computed(() => { const l = addDays(weekStart(hoy), semanaVista.value * 7); return [...Array(7).keys()].map(i => addDays(l, i)) })
const turnosDia = (d) => (p.value?.horario || []).filter(t => t.fecha === d)
const vacPct = computed(() => p.value ? p.value.vacaciones.disponibles / Math.max(1, p.value.vacaciones.anuales) : 0)
const offsetMes = computed(() => (new Date(hoy.slice(0, 8) + '01T12:00').getDay() + 6) % 7)
const colorDia = (d) => {
  const s = d.estados
  if (d.fecha === hoy) return 'ring-1 ring-gold text-gold'
  if (s.includes('falta') && !s.includes('justificada')) return 'bg-danger/20 text-danger'
  if (s.includes('tarde')) return 'bg-warn/20 text-warn'
  if (s.includes('a_tiempo') || s.includes('sin_turno')) return 'bg-ok/20 text-ok'
  if (s.includes('vacaciones') || s.includes('permiso')) return 'bg-sage/20 text-sage'
  if (s.includes('programado')) return 'bg-surface-3 text-ink-2'
  return 'text-ink-3'
}
const misTurnosFuturos = computed(() => (p.value?.horario || []).filter(t => t.fecha >= hoy))
const turnosCompanero = computed(() => (p.value?.turnos_companeros || []).filter(t => t.empleado_id === sol.companero_id))
const diasSol = computed(() => sol.fecha_inicio && sol.fecha_fin ? Math.round((toDate(sol.fecha_fin + ' 12:00') - toDate(sol.fecha_inicio + ' 12:00')) / 86400000) + 1 : 0)

const cargar = async () => { try { p.value = await personalService.perfil() } catch (e) { ui.error(e) } }
const marcar = async (tipo) => {
  marcando.value = true
  try { const r = await personalService.marcar(tipo); ui.toast(`${r.accion} a las ${hora(r.marcado_en)}${r.detalle ? ' · ' + r.detalle : ''}`); await cargar() } catch (e) { ui.error(e) }
  marcando.value = false
}
const abrirSolicitud = () => {
  Object.assign(sol, { tipo: 'permiso', fecha_inicio: addDays(hoy, 1), fecha_fin: addDays(hoy, 1), turno_id: null, companero_id: null, turno_companero_id: null, motivo: '' })
  solOpen.value = true
}
const enviarSolicitud = async () => {
  enviando.value = true
  try { await personalService.solicitar({ ...sol }); solOpen.value = false; ui.toast('Solicitud enviada a administración'); cargar() } catch (e) { ui.error(e) }
  enviando.value = false
}
const cancelarSol = async (s) => { try { await personalService.cancelarSolicitud(s.id); cargar() } catch (e) { ui.error(e) } }
const cambiarPass = async () => {
  try { await personalService.cambiarPassword(pass.actual, pass.nueva); passOpen.value = false; pass.actual = ''; pass.nueva = ''; ui.toast('Contraseña actualizada') } catch (e) { ui.error(e) }
}

onMounted(cargar)
</script>
