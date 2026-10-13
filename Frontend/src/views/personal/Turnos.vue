<template>
  <div>
    <PageHeader eyebrow="Equipo" title="Planificador de turnos" :subtitle="subtitulo">
      <button class="btn btn-soft btn-sm" @click="copiar"><PhCopy :size="16" /> Copiar semana anterior</button>
      <button class="btn btn-soft btn-sm" @click="plantillasOpen = true"><PhStack :size="16" /> Plantillas</button>
      <button class="btn btn-gold btn-sm" :disabled="!d.sin_publicar" @click="publicar"><PhMegaphone :size="16" /> Publicar horario<span v-if="d.sin_publicar" class="num">· {{ d.sin_publicar }}</span></button>
    </PageHeader>

    <!-- Semana + paleta -->
    <div class="flex flex-wrap items-center gap-3 mb-4" data-reveal>
      <div class="flex items-center gap-1 card px-1.5 h-11">
        <button class="btn btn-ghost btn-sm btn-icon" aria-label="Semana anterior" @click="mover(-7)"><PhCaretLeft :size="18" /></button>
        <button class="px-2 text-sm font-bold whitespace-nowrap" @click="semana = weekStart(isoDate()); cargar()">{{ rangoTxt }}</button>
        <button class="btn btn-ghost btn-sm btn-icon" aria-label="Semana siguiente" @click="mover(7)"><PhCaretRight :size="18" /></button>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs text-ink-3 mr-1 hidden sm:inline">Arrastra o elige y toca una celda:</span>
        <button v-for="(p, k) in presets" :key="k" draggable="true" class="h-9 px-3 rounded-xl text-xs font-bold border flex items-center gap-2 transition cursor-grab active:cursor-grabbing"
                :class="pincel === k ? 'ring-2 ring-offset-2 ring-offset-bg' : ''"
                :style="{ background: `rgb(var(--turno-${k === 'personalizado' ? 'custom' : k}) / .18)`, borderColor: `rgb(var(--turno-${k === 'personalizado' ? 'custom' : k}) / .55)`, color: `rgb(var(--turno-${k === 'personalizado' ? 'custom' : k}))`, '--tw-ring-color': `rgb(var(--turno-${k === 'personalizado' ? 'custom' : k}))` }"
                @dragstart="dragPreset = k; dragTurno = null" @click="pincel = pincel === k ? null : k">
          <component :is="p.icon" :size="15" weight="fill" /> {{ p.label }} <span class="num opacity-80">{{ p.ini }}–{{ p.fin }}</span>
        </button>
      </div>
    </div>

    <!-- Grilla -->
    <div class="card overflow-x-auto" data-reveal>
      <table class="w-full min-w-[980px] border-separate border-spacing-0 text-sm">
        <thead>
          <tr>
            <th class="sticky left-0 z-10 bg-surface text-left px-4 py-3 eyebrow border-b border-line/60 w-52">Empleado</th>
            <th v-for="dia in d.dias" :key="dia" class="px-2 py-3 border-b border-line/60 text-center" :class="dia === hoy ? 'text-gold' : ''">
              <p class="text-[11px] uppercase tracking-wider font-bold capitalize">{{ diaCorto(dia) }}</p>
              <p class="num text-lg" :class="dia === hoy ? 'text-gold' : 'text-ink'">{{ dia.slice(8) }}</p>
            </th>
            <th class="px-3 py-3 border-b border-line/60 eyebrow text-right">Horas</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="emp in d.empleados" :key="emp.id" class="group">
            <td class="sticky left-0 z-10 bg-surface px-4 py-2 border-b border-line/40">
              <div class="flex items-center gap-2.5">
                <Avatar :src="emp.foto" :name="emp.nombre" :puesto="emp.puesto" :size="34" />
                <div class="min-w-0"><p class="font-bold truncate text-[13px]">{{ emp.nombre }}</p>
                  <p class="text-[11px] text-ink-3">{{ emp.puesto }}<span v-if="emp.estado !== 'activo'" class="text-warn"> · {{ emp.estado }}</span></p></div>
              </div>
            </td>
            <td v-for="dia in d.dias" :key="dia" class="border-b border-l border-line/40 p-1 align-top h-[74px] transition-colors"
                :class="[dropKey === emp.id + dia ? 'bg-gold/10' : dia === hoy ? 'bg-gold/[.03]' : '', pincel ? 'cursor-copy hover:bg-gold/5' : '']"
                @dragover.prevent="dropKey = emp.id + dia" @dragleave="dropKey = null" @drop="soltar(emp.id, dia)" @click.self="clickCelda(emp.id, dia)">
              <div class="space-y-1" @click.self="clickCelda(emp.id, dia)">
                <button v-for="t in turnosDe(emp.id, dia)" :key="t.id" draggable="true" class="bloque w-full text-left rounded-lg px-2 py-1.5 border transition hover:brightness-110"
                        :class="!t.publicado ? 'border-dashed' : ''" :style="estiloBloque(t)" :title="t.conflictos.join('\n') || (t.publicado ? 'Publicado' : 'Sin publicar')"
                        @dragstart="dragTurno = t; dragPreset = null" @click.stop="editar(t)">
                  <p class="num text-[11px] font-bold leading-tight flex items-center gap-1">
                    <PhWarning v-if="t.conflictos.length" :size="12" weight="fill" class="text-danger shrink-0" />{{ t.hora_inicio }}–{{ t.hora_fin }}
                  </p>
                  <p class="text-[10px] opacity-80 leading-tight">{{ TIPOS[t.tipo] }}</p>
                </button>
              </div>
            </td>
            <td class="border-b border-l border-line/40 px-3 text-right whitespace-nowrap">
              <p class="num font-semibold" :class="emp.excede ? 'text-danger' : 'text-ink'">{{ (emp.minutos / 60).toFixed(1) }} h</p>
              <p v-if="emp.avisos.length" class="text-[10px] text-danger max-w-[120px] ml-auto leading-tight">{{ emp.avisos[0] }}</p>
            </td>
          </tr>
        </tbody>
        <!-- Cobertura -->
        <tfoot>
          <tr>
            <td class="sticky left-0 bg-surface px-4 py-3 align-top">
              <p class="eyebrow">Cobertura</p><p class="text-[11px] text-ink-3 mt-1">Personas por hora · mínimo {{ d.config?.cobertura_minima }}</p>
            </td>
            <td v-for="c in d.cobertura" :key="c.fecha" class="px-1.5 py-3 border-l border-line/40 align-bottom">
              <div class="flex items-end gap-[2px] h-14" :title="c.deficit ? `${c.deficit} hora(s) con falta de personal` : 'Cobertura completa'">
                <div v-for="hh in c.horas" :key="hh.hora" class="flex-1 rounded-t-[3px] min-h-[3px] transition-all"
                     :class="hh.falta ? 'bg-danger' : 'bg-ok/70'" :style="{ height: Math.max(6, hh.personas / maxCob * 100) + '%' }"
                     :title="`${String(hh.hora).padStart(2, '0')}:00 · ${hh.personas} persona(s)`" />
              </div>
              <p class="text-[10px] text-center mt-1" :class="c.deficit ? 'text-danger font-bold' : 'text-ink-3'">{{ c.deficit ? `${c.deficit} h sin cubrir` : 'Completa' }}</p>
            </td>
            <td />
          </tr>
        </tfoot>
      </table>
    </div>
    <div class="flex flex-wrap gap-4 mt-3 text-xs text-ink-3">
      <span class="flex items-center gap-1.5"><span class="w-4 h-3 rounded border border-dashed border-ink-3" /> Sin publicar</span>
      <span class="flex items-center gap-1.5"><PhWarning :size="13" weight="fill" class="text-danger" /> Conflicto (cruce, exceso de horas, falta de descanso o vacaciones)</span>
      <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-danger" /> Falta personal</span>
    </div>

    <!-- Editar / crear turno -->
    <Modal v-model="turnoOpen" :title="turno.id ? 'Editar turno' : 'Nuevo turno'" :subtitle="turno.empleado + ' · ' + (turno.fecha ? fecha(turno.fecha + ' 12:00', { weekday: 'long', day: 'numeric', month: 'long' }) : '')" size="sm">
      <div class="grid grid-cols-3 gap-2 mb-4">
        <button v-for="(p, k) in presets" :key="k" v-show="k !== 'personalizado'" type="button" class="h-14 rounded-xl border text-xs font-bold transition"
                :class="turno.tipo === k ? 'border-gold bg-gold/15 text-gold' : 'border-line text-ink-2'" @click="Object.assign(turno, { tipo: k, hora_inicio: p.ini, hora_fin: p.fin })">
          {{ p.label }}<span class="block num font-normal opacity-80">{{ p.ini }}–{{ p.fin }}</span>
        </button>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="label">Inicio</label><input v-model="turno.hora_inicio" type="time" class="input num" @input="turno.tipo = 'personalizado'" /></div>
        <div><label class="label">Fin</label><input v-model="turno.hora_fin" type="time" class="input num" @input="turno.tipo = 'personalizado'" /></div>
      </div>
      <p class="text-xs text-ink-3 mt-2">Duración: <b class="num">{{ duracion }}</b>{{ cruzaMedianoche ? ' · termina al día siguiente' : '' }}</p>
      <label class="label mt-4">Notas</label>
      <input v-model="turno.notas" class="input" placeholder="Ej. cubre evento privado" />
      <div v-if="turno.conflictos?.length" class="mt-4 rounded-xl bg-danger/10 border border-danger/30 p-3 text-sm text-danger space-y-1">
        <p v-for="c in turno.conflictos" :key="c" class="flex gap-1.5"><PhWarning :size="15" weight="fill" class="shrink-0 mt-0.5" />{{ c }}</p>
      </div>
      <template #footer>
        <button v-if="turno.id" class="btn btn-danger mr-auto" @click="borrar"><PhTrash :size="16" /></button>
        <button class="btn btn-soft" @click="turnoOpen = false">Cancelar</button>
        <button class="btn btn-gold" @click="guardarTurno">Guardar</button>
      </template>
    </Modal>

    <!-- Plantillas -->
    <Modal v-model="plantillasOpen" title="Plantillas de turnos" subtitle="Guarda una semana tipo y aplícala cuando quieras. Las rotativas intercambian los turnos entre las personas cada semana." size="md">
      <ul class="space-y-2 mb-6">
        <li v-for="p in plantillas" :key="p.id" class="flex items-center gap-3 rounded-2xl border border-line p-3">
          <span class="grid place-items-center w-10 h-10 rounded-xl bg-gold/15 text-gold"><component :is="p.rotativa ? PhArrowsClockwise : PhStack" :size="20" /></span>
          <div class="flex-1 min-w-0"><p class="font-bold truncate">{{ p.nombre }}</p><p class="text-xs text-ink-3">{{ p.patron.length }} turnos{{ p.rotativa ? ' · rotativa' : '' }}</p></div>
          <button class="btn btn-gold btn-sm" @click="aplicar(p)">Aplicar</button>
          <button class="btn btn-ghost btn-sm btn-icon text-ink-3 hover:text-danger" aria-label="Eliminar plantilla" @click="borrarPlantilla(p)"><PhTrash :size="15" /></button>
        </li>
        <p v-if="!plantillas.length" class="text-sm text-ink-3">Aún no hay plantillas.</p>
      </ul>
      <div class="rounded-2xl bg-surface-2/60 border border-line/60 p-4 space-y-3">
        <p class="font-bold text-sm">Guardar esta semana como plantilla</p>
        <input v-model="nuevaPlantilla" class="input" placeholder="Nombre: Temporada alta, Rotación cocina…" />
        <label class="flex items-center gap-3 cursor-pointer">
          <button type="button" class="switch" :class="{ on: rotativa }" role="switch" @click="rotativa = !rotativa" />
          <span class="text-sm text-ink-2">Rotativa (los turnos se rotan entre las personas cada semana)</span>
        </label>
        <button class="btn btn-soft w-full" :disabled="!nuevaPlantilla.trim()" @click="guardarPlantilla">Guardar plantilla</button>
      </div>
      <button class="btn btn-ghost btn-sm text-danger mt-4" @click="limpiar"><PhEraser :size="15" /> Vaciar esta semana</button>
    </Modal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { PhCopy, PhStack, PhMegaphone, PhCaretLeft, PhCaretRight, PhSun, PhSunHorizon, PhMoonStars, PhSliders, PhWarning, PhTrash, PhArrowsClockwise, PhEraser } from '@phosphor-icons/vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Avatar from '@/components/ui/Avatar.vue'
import Modal from '@/components/ui/Modal.vue'
import { personalService } from '@/services'
import { useUiStore } from '@/stores/ui'
import { isoDate, weekStart, addDays, diaCorto, fecha } from '@/utils/format'
import { confirmar } from '@/plugins/swal'

const route = useRoute()
const ui = useUiStore()
const hoy = isoDate()
const semana = ref(weekStart(typeof route.query.semana === 'string' ? route.query.semana : hoy))
const d = ref({ dias: [], empleados: [], turnos: [], cobertura: [] })
const pincel = ref(null)
const dragPreset = ref(null)
const dragTurno = ref(null)
const dropKey = ref(null)
const turnoOpen = ref(false)
const turno = reactive({})
const plantillasOpen = ref(false)
const plantillas = ref([])
const nuevaPlantilla = ref('')
const rotativa = ref(false)

const TIPOS = { manana: 'Mañana', tarde: 'Tarde', noche: 'Noche', personalizado: 'Personalizado' }
const presets = {
  manana: { label: 'Mañana', ini: '07:00', fin: '15:00', icon: PhSun },
  tarde: { label: 'Tarde', ini: '15:00', fin: '23:00', icon: PhSunHorizon },
  noche: { label: 'Noche', ini: '18:00', fin: '02:00', icon: PhMoonStars },
  personalizado: { label: 'Personalizado', ini: '10:00', fin: '18:00', icon: PhSliders },
}
const rangoTxt = computed(() => d.value.desde ? `${fecha(d.value.desde + ' 12:00')} – ${fecha(d.value.hasta + ' 12:00', { day: 'numeric', month: 'short', year: 'numeric' })}` : '')
const subtitulo = computed(() => {
  const total = d.value.turnos.length
  const conf = d.value.turnos.filter(t => t.conflictos.length).length
  return `${total} turnos esta semana${conf ? ` · ${conf} con conflicto` : ''}${d.value.sin_publicar ? ` · ${d.value.sin_publicar} sin publicar` : ' · todo publicado'}`
})
const maxCob = computed(() => Math.max(1, ...d.value.cobertura.flatMap(c => c.horas.map(h => h.personas))))
const turnosDe = (eid, dia) => d.value.turnos.filter(t => t.empleado_id === eid && t.fecha === dia)
const varTipo = (t) => `--turno-${t === 'personalizado' ? 'custom' : t}`
const estiloBloque = (t) => ({
  background: `rgb(var(${varTipo(t.tipo)}) / ${t.publicado ? .22 : .1})`,
  borderColor: t.conflictos.length ? 'rgb(var(--danger))' : `rgb(var(${varTipo(t.tipo)}) / .6)`,
  color: 'rgb(var(--ink))',
})
const minutos = (a, b) => { const [h1, m1] = a.split(':').map(Number), [h2, m2] = b.split(':').map(Number); let x = (h2 * 60 + m2) - (h1 * 60 + m1); if (x <= 0) x += 1440; return x }
const duracion = computed(() => turno.hora_inicio && turno.hora_fin ? `${(minutos(turno.hora_inicio, turno.hora_fin) / 60).toFixed(1)} h` : '—')
const cruzaMedianoche = computed(() => turno.hora_fin && turno.hora_inicio && turno.hora_fin <= turno.hora_inicio)

const cargar = async () => {
  try { d.value = await personalService.semana(semana.value) } catch (e) { ui.error(e) }
}
const mover = (n) => { semana.value = addDays(semana.value, n); cargar() }

const crear = async (eid, dia, tipo) => {
  const p = presets[tipo]
  try {
    const t = await personalService.crearTurno({ empleado_id: eid, fecha: dia, hora_inicio: p.ini, hora_fin: p.fin, tipo })
    await cargar()
    if (t.conflictos?.length) ui.toast(t.conflictos[0], 'warn', { titulo: 'Turno creado con conflicto' })
  } catch (e) { ui.error(e) }
}
const soltar = async (eid, dia) => {
  dropKey.value = null
  if (dragPreset.value) return crear(eid, dia, dragPreset.value)
  const t = dragTurno.value
  if (!t || (t.empleado_id === eid && t.fecha === dia)) return
  try {
    const r = await personalService.editarTurno(t.id, { empleado_id: eid, fecha: dia, hora_inicio: t.hora_inicio, hora_fin: t.hora_fin, tipo: t.tipo, notas: t.notas })
    await cargar()
    if (r.conflictos?.length) ui.toast(r.conflictos[0], 'warn', { titulo: 'Turno movido con conflicto' })
  } catch (e) { ui.error(e) }
  dragTurno.value = null
}
const clickCelda = (eid, dia) => {
  if (pincel.value) return crear(eid, dia, pincel.value)
  const emp = d.value.empleados.find(x => x.id === eid)
  Object.keys(turno).forEach(k => delete turno[k])
  Object.assign(turno, { empleado_id: eid, empleado: emp.nombre, fecha: dia, tipo: 'manana', hora_inicio: '07:00', hora_fin: '15:00', notas: '' })
  turnoOpen.value = true
}
const editar = (t) => {
  Object.keys(turno).forEach(k => delete turno[k])
  Object.assign(turno, { ...t, empleado: d.value.empleados.find(x => x.id === t.empleado_id)?.nombre || '' })
  turnoOpen.value = true
}
const guardarTurno = async () => {
  const data = { empleado_id: turno.empleado_id, fecha: turno.fecha, hora_inicio: turno.hora_inicio, hora_fin: turno.hora_fin, tipo: turno.tipo, notas: turno.notas }
  try {
    const r = turno.id ? await personalService.editarTurno(turno.id, data) : await personalService.crearTurno(data)
    await cargar()
    if (r.conflictos?.length) { Object.assign(turno, r); ui.toast('Guardado, pero revisa los conflictos', 'warn') } else turnoOpen.value = false
  } catch (e) { ui.error(e) }
}
const borrar = async () => {
  try { await personalService.borrarTurno(turno.id); turnoOpen.value = false; cargar() } catch (e) { ui.error(e) }
}
const copiar = async () => {
  if (d.value.turnos.length && !await confirmar('¿Copiar la semana anterior?', 'Los turnos de esta semana se reemplazarán por los de la semana pasada.', { confirmar: 'Copiar' })) return
  try { d.value = await personalService.copiarSemana(semana.value); ui.toast('Semana copiada · recuerda publicarla') } catch (e) { ui.error(e) }
}
const publicar = async () => {
  try { d.value = await personalService.publicar(semana.value); ui.toast('Horario publicado: el equipo ya lo ve en su perfil') } catch (e) { ui.error(e) }
}
const limpiar = async () => {
  if (!await confirmar('¿Vaciar esta semana?', 'Se eliminan todos sus turnos.', { confirmar: 'Vaciar', peligro: true })) return
  try { d.value = await personalService.limpiar(semana.value); plantillasOpen.value = false } catch (e) { ui.error(e) }
}
const cargarPlantillas = async () => { try { plantillas.value = await personalService.plantillas() } catch { /* opcional */ } }
const aplicar = async (p) => {
  if (!await confirmar(`¿Aplicar «${p.nombre}»?`, 'Reemplaza los turnos de la semana que estás viendo.', { confirmar: 'Aplicar' })) return
  try { d.value = await personalService.aplicarPlantilla(p.id, semana.value); plantillasOpen.value = false; ui.toast('Plantilla aplicada') } catch (e) { ui.error(e) }
}
const guardarPlantilla = async () => {
  try { await personalService.guardarPlantilla(nuevaPlantilla.value, semana.value, rotativa.value); nuevaPlantilla.value = ''; cargarPlantillas(); ui.toast('Plantilla guardada') } catch (e) { ui.error(e) }
}
const borrarPlantilla = async (p) => {
  try { await personalService.borrarPlantilla(p.id); cargarPlantillas() } catch (e) { ui.error(e) }
}

onMounted(() => { cargar(); cargarPlantillas() })
</script>
