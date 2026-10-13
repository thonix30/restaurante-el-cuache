<template>
  <div>
    <PageHeader eyebrow="Equipo" title="Asistencia del día" subtitle="Turno programado contra marcas reales. Las correcciones exigen motivo y quedan en la auditoría.">
      <button class="btn btn-soft" @click="exportar"><PhDownloadSimple :size="18" /> Exportar</button>
      <router-link to="/kiosco" class="btn btn-gold"><PhFingerprint :size="18" /> Kiosco</router-link>
    </PageHeader>

    <div class="flex flex-wrap items-center gap-3 mb-5" data-reveal>
      <div class="flex items-center gap-1 card px-1.5 h-11">
        <button class="btn btn-ghost btn-sm btn-icon" aria-label="Día anterior" @click="cambiar(-1)"><PhCaretLeft :size="18" /></button>
        <input v-model="fechaSel" type="date" class="bg-transparent text-sm font-bold px-1 focus:outline-none" @change="cargar" />
        <button class="btn btn-ghost btn-sm btn-icon" aria-label="Día siguiente" @click="cambiar(1)"><PhCaretRight :size="18" /></button>
      </div>
      <button v-if="fechaSel !== hoy" class="btn btn-ghost btn-sm" @click="fechaSel = hoy; cargar()">Hoy</button>
      <span class="flex-1" />
      <div class="flex flex-wrap gap-2">
        <span class="chip chip-neutral num">{{ r.programados || 0 }} programados</span>
        <span class="chip chip-ok num">{{ r.a_tiempo || 0 }} a tiempo</span>
        <span class="chip chip-warn num">{{ r.tarde || 0 }} tarde</span>
        <span class="chip chip-danger num">{{ r.falta || 0 }} faltas</span>
        <span class="chip chip-info num">{{ r.horas_extra || 0 }} con extra</span>
      </div>
    </div>

    <div class="card overflow-x-auto" data-reveal>
      <table class="tbl">
        <thead><tr><th>Empleado</th><th>Turno programado</th><th>Entrada</th><th>Salida</th><th class="text-right">Horas</th><th>Estado</th><th /></tr></thead>
        <tbody>
          <tr v-for="f in d.filas" :key="f.empleado.id">
            <td><div class="flex items-center gap-3"><Avatar :src="f.empleado.foto" :name="f.empleado.nombre" :puesto="f.empleado.puesto" :size="36" :dot="enTurno(f) ? 'ok' : null" />
              <div><p class="font-bold">{{ f.empleado.nombre }}</p><p class="text-[11px] text-ink-3">{{ f.empleado.puesto }}</p></div></div></td>
            <td class="num text-ink-2 whitespace-nowrap">{{ f.resumen?.turno ? `${hora(f.resumen.turno.inicio)} – ${hora(f.resumen.turno.fin)}` : '—' }}</td>
            <td class="num whitespace-nowrap">
              <span :class="f.resumen?.tarde_min > tol ? 'text-warn font-bold' : ''">{{ f.resumen?.entrada ? hora(f.resumen.entrada) : '—' }}</span>
              <button v-if="fotoDe(f, 'entrada')" class="ml-1 text-ink-3 hover:text-gold align-middle" title="Ver foto" @click="verFoto(fotoDe(f, 'entrada'))"><PhCamera :size="14" /></button>
              <PhPencilSimpleLine v-if="corregida(f)" :size="13" class="inline ml-1 text-info" title="Corregida" />
            </td>
            <td class="num whitespace-nowrap">{{ f.resumen?.salida ? hora(f.resumen.salida) : '—' }}
              <span v-if="f.resumen?.descanso_min" class="text-[11px] text-ink-3 block">descanso {{ horasMin(f.resumen.descanso_min) }}</span></td>
            <td class="text-right num">{{ f.resumen?.trabajado_min ? horasMin(f.resumen.trabajado_min) : '—' }}
              <span v-if="f.resumen?.extra_min" class="block text-[11px] text-info">+{{ horasMin(f.resumen.extra_min) }} extra</span></td>
            <td>
              <div class="flex flex-wrap gap-1">
                <template v-if="f.resumen">
                  <span v-for="s in f.resumen.estados" :key="s" class="chip chip-dot" :class="chipCls(s)">{{ etiquetaEstado(s, f.resumen) }}</span>
                </template>
                <StatusChip v-else :estado="f.empleado.estado === 'activo' ? 'libre_dia' : f.empleado.estado" :label="f.empleado.estado === 'activo' ? 'Libre' : undefined" />
              </div>
            </td>
            <td class="text-right whitespace-nowrap">
              <button v-if="justificable(f)" class="btn btn-ghost btn-sm" @click="abrirJustificar(f)"><PhNote :size="15" /> Justificar</button>
              <button class="btn btn-ghost btn-sm" @click="abrirCorregir(f)"><PhPencilSimple :size="15" /> Corregir</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Corregir -->
    <Modal v-model="corrOpen" title="Corregir asistencia" :subtitle="`${sel?.empleado.nombre} · ${fecha(fechaSel + ' 12:00', { weekday: 'long', day: 'numeric', month: 'long' })}`" size="sm">
      <div class="grid grid-cols-2 gap-3">
        <div><label class="label">Entrada *</label><input v-model="corr.entrada" type="time" class="input num" /></div>
        <div><label class="label">Salida</label><input v-model="corr.salida" type="time" class="input num" /></div>
        <div><label class="label">Inicio descanso</label><input v-model="corr.descanso_inicio" type="time" class="input num" /></div>
        <div><label class="label">Fin descanso</label><input v-model="corr.descanso_fin" type="time" class="input num" /></div>
      </div>
      <label class="label mt-4">Motivo de la corrección *</label>
      <textarea v-model="corr.motivo" rows="2" class="input" placeholder="Ej. olvidó marcar la salida, falla del kiosco…" />
      <p class="text-[11px] text-ink-3 mt-2 flex gap-1.5"><PhShieldCheck :size="14" class="shrink-0" /> Reemplaza las marcas del día y queda registrado en Auditoría con los valores anteriores.</p>
      <template #footer>
        <button class="btn btn-soft" @click="corrOpen = false">Cancelar</button>
        <button class="btn btn-gold" :disabled="!corr.entrada || !corr.motivo.trim()" @click="guardarCorreccion">Guardar corrección</button>
      </template>
    </Modal>

    <!-- Justificar -->
    <Modal v-model="justOpen" title="Justificar" :subtitle="sel?.empleado.nombre" size="sm">
      <div class="seg w-full mb-4">
        <button v-for="t in tiposJust" :key="t.id" class="flex-1" :class="{ on: just.tipo === t.id }" @click="just.tipo = t.id">{{ t.l }}</button>
      </div>
      <label class="label">Motivo *</label>
      <textarea v-model="just.motivo" rows="3" class="input" placeholder="Ej. cita médica, emergencia familiar…" />
      <label class="mt-4 flex items-center gap-3 rounded-xl border border-dashed border-line p-3 cursor-pointer hover:border-gold/50">
        <PhPaperclip :size="20" class="text-ink-3" /><span class="text-sm text-ink-2 truncate flex-1">{{ just.file?.name || 'Adjuntar comprobante (opcional)' }}</span>
        <input type="file" accept="application/pdf,image/*" class="hidden" @change="just.file = $event.target.files[0]" />
      </label>
      <template #footer>
        <button class="btn btn-soft" @click="justOpen = false">Cancelar</button>
        <button class="btn btn-gold" :disabled="!just.motivo.trim()" @click="guardarJustificacion">Guardar</button>
      </template>
    </Modal>

    <Modal :model-value="!!fotoUrl" @update:model-value="fotoUrl = null" title="Foto de la marcación" size="sm">
      <img :src="fotoUrl" alt="Foto de marcación" class="w-full rounded-2xl" />
    </Modal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import {
  PhDownloadSimple, PhFingerprint, PhCaretLeft, PhCaretRight, PhCamera, PhPencilSimpleLine, PhNote, PhPencilSimple, PhShieldCheck, PhPaperclip,
} from '@phosphor-icons/vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Avatar from '@/components/ui/Avatar.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import Modal from '@/components/ui/Modal.vue'
import { personalService } from '@/services'
import { fileUrl } from '@/services/api'
import { useUiStore } from '@/stores/ui'
import { hora, fecha, horasMin, isoDate, addDays, downloadCsv } from '@/utils/format'

const route = useRoute()
const ui = useUiStore()
const hoy = isoDate()
const fechaSel = ref(typeof route.query.fecha === 'string' ? route.query.fecha : hoy)
const d = ref({ filas: [], resumen: {} })
const tol = 5
const sel = ref(null)
const corrOpen = ref(false)
const corr = reactive({ entrada: '', salida: '', descanso_inicio: '', descanso_fin: '', motivo: '' })
const justOpen = ref(false)
const just = reactive({ tipo: 'falta', motivo: '', file: null })
const fotoUrl = ref(null)
const tiposJust = [{ id: 'falta', l: 'Falta' }, { id: 'tardanza', l: 'Tardanza' }, { id: 'salida_anticipada', l: 'Salida antes' }]

const r = computed(() => d.value.resumen || {})
const CHIP = { a_tiempo: 'chip-ok', tarde: 'chip-warn', falta: 'chip-danger', sin_entrada: 'chip-warn', salida_anticipada: 'chip-warn', horas_extra: 'chip-info', en_turno: 'chip-ok', en_descanso: 'chip-sage', sin_salida: 'chip-danger', sin_turno: 'chip-neutral', programado: 'chip-neutral', vacaciones: 'chip-sage', permiso: 'chip-sage', justificada: 'chip-sage' }
const NOMBRE = { a_tiempo: 'A tiempo', tarde: 'Tarde', falta: 'Falta', sin_entrada: 'Sin entrada', salida_anticipada: 'Salida anticipada', horas_extra: 'Horas extra', en_turno: 'En turno', en_descanso: 'En descanso', sin_salida: 'Sin salida', sin_turno: 'Sin turno', programado: 'Programado', vacaciones: 'Vacaciones', permiso: 'Permiso', justificada: 'Justificada' }
const chipCls = (s) => CHIP[s] || 'chip-neutral'
const etiquetaEstado = (s, res) => s === 'tarde' ? `Tarde +${res.tarde_min} min` : s === 'horas_extra' ? `Extra +${horasMin(res.extra_min)}` : s === 'salida_anticipada' ? `Salió ${res.anticipada_min} min antes` : NOMBRE[s] || s
const enTurno = (f) => f.resumen?.estados.some(s => s === 'en_turno' || s === 'en_descanso')
const corregida = (f) => f.resumen?.marcaciones.some(m => m.origen === 'correccion')
const fotoDe = (f, tipo) => f.resumen?.marcaciones.find(m => m.tipo === tipo && m.tiene_foto)?.id
const justificable = (f) => f.resumen && ['falta', 'tarde', 'salida_anticipada'].some(s => f.resumen.estados.includes(s)) && !f.resumen.estados.includes('justificada')

const cargar = async () => {
  try { d.value = await personalService.asistencia(fechaSel.value) } catch (e) { ui.error(e) }
}
const cambiar = (n) => { fechaSel.value = addDays(fechaSel.value, n); cargar() }

const hhmm = (s) => (s ? hora(s) : '')
const abrirCorregir = (f) => {
  sel.value = f
  const res = f.resumen
  const desc = res?.descansos?.[0]
  Object.assign(corr, {
    entrada: hhmm(res?.entrada) || (res?.turno ? hhmm(res.turno.inicio) : ''),
    salida: hhmm(res?.salida) || (res?.turno && !res?.entrada ? hhmm(res.turno.fin) : ''),
    descanso_inicio: hhmm(desc?.inicio), descanso_fin: hhmm(desc?.fin), motivo: '',
  })
  corrOpen.value = true
}
const guardarCorreccion = async () => {
  try {
    await personalService.corregir({ empleado_id: sel.value.empleado.id, fecha: fechaSel.value, ...corr })
    corrOpen.value = false; ui.toast('Asistencia corregida'); cargar()
  } catch (e) { ui.error(e) }
}
const abrirJustificar = (f) => {
  sel.value = f
  const s = f.resumen.estados
  Object.assign(just, { tipo: s.includes('falta') ? 'falta' : s.includes('tarde') ? 'tardanza' : 'salida_anticipada', motivo: '', file: null })
  justOpen.value = true
}
const guardarJustificacion = async () => {
  try {
    await personalService.justificar({ empleado_id: sel.value.empleado.id, fecha: fechaSel.value, tipo: just.tipo, motivo: just.motivo }, just.file)
    justOpen.value = false; ui.toast('Justificación registrada'); cargar()
  } catch (e) { ui.error(e) }
}
const verFoto = async (id) => { try { fotoUrl.value = await fileUrl(`/asistencia/marcaciones/${id}/foto`) } catch (e) { ui.error(e) } }
const exportar = () => downloadCsv(`asistencia-${fechaSel.value}.csv`,
  ['Empleado', 'Puesto', 'Turno inicio', 'Turno fin', 'Entrada', 'Salida', 'Horas trabajadas', 'Horas extra', 'Minutos tarde', 'Estado'],
  d.value.filas.map(f => [f.empleado.nombre, f.empleado.puesto, hhmm(f.resumen?.turno?.inicio), hhmm(f.resumen?.turno?.fin), hhmm(f.resumen?.entrada), hhmm(f.resumen?.salida),
    ((f.resumen?.trabajado_min || 0) / 60).toFixed(2), ((f.resumen?.extra_min || 0) / 60).toFixed(2), f.resumen?.tarde_min || 0,
    (f.resumen?.estados || ['libre']).map(s => NOMBRE[s] || s).join(' / ')]))

onMounted(cargar)
</script>
