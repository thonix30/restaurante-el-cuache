<template>
  <div v-if="!e" class="space-y-4"><div class="skeleton h-40" /><div class="skeleton h-80" /></div>
  <div v-else>
    <router-link to="/personal" class="btn btn-ghost btn-sm -ml-3 mb-3 text-ink-3"><PhArrowLeft :size="16" /> Personal</router-link>

    <!-- Encabezado -->
    <div class="card p-6 sm:p-8 mb-5 relative overflow-hidden" data-reveal>
      <Botanical class="absolute -right-10 -top-16 w-56 text-gold/10 rotate-45" />
      <div class="relative flex flex-col sm:flex-row sm:items-center gap-5">
        <Avatar :src="e.foto" :name="e.nombre_completo" :puesto="e.puesto" :size="112" />
        <div class="flex-1 min-w-0">
          <h1 class="font-serif text-4xl sm:text-5xl leading-none">{{ e.nombre_completo }}</h1>
          <div class="flex flex-wrap items-center gap-2 mt-3">
            <span class="chip" :class="'chip-' + (PUESTO_COLOR[e.puesto] || 'neutral')">{{ e.puesto }}</span>
            <StatusChip :estado="e.estado" />
            <span v-if="e.antiguedad" class="chip chip-neutral"><PhMedal :size="13" /> {{ e.antiguedad }}</span>
            <span v-if="e.acceso_activo" class="chip chip-info"><PhKey :size="13" /> Acceso: {{ e.rol }} · {{ e.usuario }}</span>
          </div>
        </div>
        <div class="flex gap-2">
          <button class="btn btn-soft" @click="editarOpen = true"><PhPencilSimple :size="17" /> Editar</button>
          <button v-if="e.estado !== 'baja'" class="btn btn-danger" @click="darBaja"><PhUserMinus :size="17" /> Dar de baja</button>
        </div>
      </div>
      <p v-if="e.estado === 'baja'" class="relative mt-4 text-sm text-danger">Dado de baja el {{ fecha(e.fecha_baja + ' 12:00', { day: 'numeric', month: 'long', year: 'numeric' }) }}: {{ e.motivo_baja }}</p>
    </div>

    <!-- Pestañas -->
    <div class="flex gap-1 overflow-x-auto scrollbar-none mb-5 border-b border-line/60" data-reveal>
      <button v-for="t in tabs" :key="t.id" class="relative px-4 h-11 text-sm font-bold whitespace-nowrap transition flex items-center gap-2"
              :class="tab === t.id ? 'text-ink' : 'text-ink-3 hover:text-ink-2'" @click="tab = t.id">
        <component :is="t.icon" :size="17" /> {{ t.label }}
        <span v-if="t.id === 'docs' && docsAlerta" class="w-2 h-2 rounded-full bg-warn" />
        <span v-if="tab === t.id" class="absolute bottom-0 left-2 right-2 h-0.5 rounded bg-gold" />
      </button>
    </div>

    <!-- Datos personales -->
    <div v-if="tab === 'datos'" class="grid lg:grid-cols-2 gap-4">
      <div class="card p-5"><p class="panel-title mb-4">Datos personales</p>
        <dl class="dl"><Dato l="DPI" :v="e.documento_identidad" mono /><Dato l="Nacimiento" :v="e.fecha_nacimiento ? fecha(e.fecha_nacimiento + ' 12:00', { day: 'numeric', month: 'long', year: 'numeric' }) + ` (${edad} años)` : null" />
          <Dato l="Teléfono" :v="e.telefono" mono /><Dato l="Correo" :v="e.email" /><Dato l="Dirección" :v="e.direccion" /></dl></div>
      <div class="card p-5"><p class="panel-title mb-4">Contacto de emergencia</p>
        <dl class="dl"><Dato l="Nombre" :v="e.emergencia_nombre" /><Dato l="Parentesco" :v="e.emergencia_parentesco" /><Dato l="Teléfono" :v="e.emergencia_telefono" mono /></dl>
        <p class="panel-title mt-6 mb-4">Uniforme y salud</p>
        <dl class="dl"><Dato l="Tallas" :v="[e.talla_camisa && 'Camisa ' + e.talla_camisa, e.talla_pantalon && 'Pantalón ' + e.talla_pantalon, e.talla_calzado && 'Calzado ' + e.talla_calzado].filter(Boolean).join(' · ')" />
          <Dato l="Alergias / notas" :v="e.notas_medicas" /></dl></div>
    </div>

    <!-- Contrato y pago -->
    <div v-if="tab === 'contrato'" class="grid lg:grid-cols-2 gap-4">
      <div class="card p-5"><p class="panel-title mb-4">Contrato</p>
        <dl class="dl"><Dato l="Puesto" :v="e.puesto" /><Dato l="Ingreso" :v="e.fecha_ingreso ? fecha(e.fecha_ingreso + ' 12:00', { day: 'numeric', month: 'long', year: 'numeric' }) : null" />
          <Dato l="Tipo de contrato" :v="e.tipo_contrato" /><Dato l="Antigüedad" :v="e.antiguedad" /></dl></div>
      <div class="card p-5"><p class="panel-title mb-4">Pago</p>
        <dl class="dl"><Dato l="Forma de pago" :v="e.tipo_pago" />
          <Dato :l="e.tipo_pago === 'Mensual' ? 'Salario mensual' : 'Tarifa por hora'" :v="money(e.tipo_pago === 'Mensual' ? e.salario : e.tarifa_hora)" mono />
          <Dato l="Banco" :v="e.banco" /><Dato l="Cuenta" :v="e.cuenta_bancaria" mono /></dl>
        <div class="mt-5 rounded-2xl bg-surface-2/60 border border-line/60 p-4 grid grid-cols-3 text-center">
          <div><p class="num text-2xl">{{ e.vacaciones.anuales }}</p><p class="text-[11px] text-ink-3">días al año</p></div>
          <div><p class="num text-2xl">{{ e.vacaciones.usados }}</p><p class="text-[11px] text-ink-3">usados</p></div>
          <div><p class="num text-2xl text-gold">{{ e.vacaciones.disponibles }}</p><p class="text-[11px] text-ink-3">disponibles</p></div>
        </div>
      </div>
    </div>

    <!-- Documentos -->
    <div v-if="tab === 'docs'" class="grid lg:grid-cols-[1.4fr_1fr] gap-4">
      <div class="card divide-y divide-line/50">
        <div v-for="d in e.documentos" :key="d.id" class="flex items-center gap-3 p-4">
          <span class="grid place-items-center w-11 h-11 rounded-xl bg-surface-2 text-ink-2"><component :is="d.mime?.startsWith('image') ? PhImage : PhFilePdf" :size="22" /></span>
          <div class="flex-1 min-w-0">
            <p class="font-bold truncate">{{ d.nombre }}</p>
            <div class="flex flex-wrap items-center gap-1.5 mt-1">
              <span class="chip chip-neutral">{{ d.tipo }}</span>
              <span v-if="d.dias_para_vencer !== null" class="chip" :class="d.dias_para_vencer < 0 ? 'chip-danger' : d.dias_para_vencer <= 30 ? 'chip-warn' : 'chip-ok'">
                {{ d.dias_para_vencer < 0 ? `Vencido hace ${-d.dias_para_vencer} d` : d.dias_para_vencer === 0 ? 'Vence hoy' : `Vence en ${d.dias_para_vencer} d` }}
              </span>
            </div>
          </div>
          <button class="btn btn-soft btn-sm" @click="verDoc(d)"><PhEye :size="16" /> Ver</button>
          <button class="btn btn-ghost btn-sm btn-icon text-ink-3 hover:text-danger" aria-label="Eliminar documento" @click="borrarDoc(d)"><PhTrash :size="16" /></button>
        </div>
        <EmptyState v-if="!e.documentos.length" :icon="PhFolderOpen" title="Sin documentos" text="Sube el contrato, identificación o certificado de salud." />
      </div>
      <form class="card p-5 space-y-3 h-fit" @submit.prevent="subirDoc">
        <p class="panel-title">Adjuntar documento</p>
        <div><label class="label">Tipo</label><select v-model="doc.tipo" class="input"><option v-for="t in ['Contrato', 'Identificación', 'Certificado de salud', 'Otro']" :key="t">{{ t }}</option></select></div>
        <div><label class="label">Nombre</label><input v-model="doc.nombre" class="input" :placeholder="doc.tipo" /></div>
        <div><label class="label">Vence el (opcional)</label><input v-model="doc.vence_en" type="date" class="input" /></div>
        <label class="flex items-center gap-3 rounded-xl border border-dashed border-line p-3 cursor-pointer hover:border-gold/50">
          <PhPaperclip :size="20" class="text-ink-3" /><span class="text-sm text-ink-2 truncate flex-1">{{ doc.file?.name || 'Elegir archivo (PDF o imagen, máx. 10 MB)' }}</span>
          <input type="file" accept="application/pdf,image/*" class="hidden" @change="doc.file = $event.target.files[0]" />
        </label>
        <button class="btn btn-gold w-full" :disabled="!doc.file || subiendo">Subir documento</button>
      </form>
    </div>

    <!-- Acceso al sistema -->
    <div v-if="tab === 'acceso'" class="grid lg:grid-cols-2 gap-4">
      <form class="card p-5 space-y-4" @submit.prevent="guardarAcceso">
        <div class="flex items-center justify-between">
          <div><p class="panel-title">Acceso al sistema</p><p class="text-xs text-ink-3">Usuario y contraseña para la app</p></div>
          <button type="button" class="switch switch-lg" :class="{ on: acc.acceso_activo }" role="switch" :aria-checked="acc.acceso_activo" @click="acc.acceso_activo = !acc.acceso_activo" />
        </div>
        <template v-if="acc.acceso_activo">
          <div><label class="label">Usuario</label><input v-model.trim="acc.usuario" class="input" :class="accErr.usuario ? 'input-error' : ''" autocapitalize="none" /><p v-if="accErr.usuario" class="field-error">{{ accErr.usuario }}</p></div>
          <div><label class="label">Rol</label>
            <div class="grid grid-cols-3 gap-2">
              <button v-for="r in roles" :key="r.id" type="button" class="rounded-xl border p-3 text-left transition" :class="acc.rol === r.id ? 'border-gold bg-gold/10' : 'border-line'" @click="acc.rol = r.id">
                <component :is="r.icon" :size="20" :class="acc.rol === r.id ? 'text-gold' : 'text-ink-3'" />
                <p class="text-sm font-bold mt-1">{{ r.label }}</p><p class="text-[10px] text-ink-3 leading-tight">{{ r.desc }}</p>
              </button>
            </div></div>
          <div><label class="label">{{ e.tiene_password ? 'Restablecer contraseña (opcional)' : 'Contraseña inicial' }}</label>
            <input v-model="acc.password" type="text" class="input num" :class="accErr.password ? 'input-error' : ''" placeholder="Mínimo 6 caracteres" autocomplete="new-password" />
            <p v-if="accErr.password" class="field-error">{{ accErr.password }}</p></div>
        </template>
        <p v-else class="text-sm text-ink-3">Sin acceso: solo puede marcar asistencia en el kiosco con su PIN.</p>
        <button class="btn btn-gold w-full">Guardar acceso</button>
      </form>
      <form class="card p-5" @submit.prevent="guardarPin">
        <p class="panel-title">PIN de asistencia</p>
        <p class="text-xs text-ink-3 mb-5">4 dígitos para marcar en el kiosco. {{ e.tiene_pin ? 'Ya tiene un PIN asignado.' : 'Aún no tiene PIN.' }}</p>
        <input v-model="pin" maxlength="4" inputmode="numeric" pattern="\d{4}" class="input h-16 text-center num text-4xl tracking-[.6em]" placeholder="••••" @input="pin = pin.replace(/\D/g, '')" />
        <button class="btn btn-gold w-full mt-4" :disabled="pin.length !== 4">{{ e.tiene_pin ? 'Cambiar PIN' : 'Asignar PIN' }}</button>
        <p class="text-[11px] text-ink-3 mt-3 flex gap-1.5"><PhShieldCheck :size="14" class="shrink-0" /> El PIN se guarda cifrado; nadie puede verlo después.</p>
      </form>
    </div>

    <!-- Asistencia -->
    <div v-if="tab === 'asistencia'" class="grid lg:grid-cols-[1fr_300px] gap-4">
      <div class="card p-5">
        <div class="flex items-center justify-between mb-4">
          <button class="btn btn-ghost btn-sm btn-icon" aria-label="Mes anterior" @click="cambiarMes(-1)"><PhCaretLeft :size="18" /></button>
          <p class="panel-title first-letter:uppercase">{{ mesTxt }}</p>
          <button class="btn btn-ghost btn-sm btn-icon" aria-label="Mes siguiente" @click="cambiarMes(1)"><PhCaretRight :size="18" /></button>
        </div>
        <div class="grid grid-cols-7 gap-1.5 text-center">
          <span v-for="(d, i) in ['L', 'M', 'M', 'J', 'V', 'S', 'D']" :key="i" class="text-[11px] font-bold text-ink-3 pb-1">{{ d }}</span>
          <span v-for="i in offsetMes" :key="'o' + i" />
          <div v-for="d in asis?.dias || []" :key="d.fecha" class="aspect-square rounded-xl grid place-items-center text-sm num relative border"
               :class="colorDia(d)" :title="d.estados.join(', ') || 'Libre'">
            {{ Number(d.fecha.slice(8)) }}
            <span v-if="d.trabajado_min" class="absolute bottom-0.5 text-[9px] opacity-80">{{ Math.round(d.trabajado_min / 6) / 10 }}h</span>
          </div>
        </div>
        <div class="flex flex-wrap gap-3 mt-4 text-[11px] text-ink-2">
          <span v-for="l in leyenda" :key="l.t" class="flex items-center gap-1.5"><span class="w-3 h-3 rounded" :class="l.c" />{{ l.t }}</span>
        </div>
      </div>
      <div class="card p-5 space-y-3 h-fit">
        <p class="panel-title">Resumen del mes</p>
        <div class="flex justify-between text-sm"><span class="text-ink-3">Horas trabajadas</span><b class="num">{{ horasMin(asis?.totales.trabajado_min) }}</b></div>
        <div class="flex justify-between text-sm"><span class="text-ink-3">Horas extra</span><b class="num text-info">{{ horasMin(asis?.totales.extra_min) }}</b></div>
        <div class="flex justify-between text-sm"><span class="text-ink-3">Tardanzas</span><b class="num text-warn">{{ asis?.totales.tardanzas }}</b></div>
        <div class="flex justify-between text-sm"><span class="text-ink-3">Faltas sin justificar</span><b class="num text-danger">{{ asis?.totales.faltas }}</b></div>
        <router-link to="/asistencia" class="btn btn-soft btn-sm w-full mt-2">Ver asistencia del día</router-link>
      </div>
    </div>

    <!-- Propinas y ventas -->
    <div v-if="tab === 'ventas'" class="grid sm:grid-cols-3 gap-4">
      <KpiCard label="Cuentas atendidas (30 días)" :value="e.ventas_30d.cuentas" :icon="PhReceipt" />
      <KpiCard label="Ventas (30 días)" :value="e.ventas_30d.ventas" format="money" :icon="PhCurrencyCircleDollar" tone="info" />
      <KpiCard label="Propinas repartidas (30 días)" :value="e.ventas_30d.propinas_repartidas" format="money" :icon="PhHandCoins" tone="ok" hint="Según su presencia al cobrarse cada propina" />
    </div>

    <SidePanel v-model="editarOpen" title="Editar ficha" :eyebrow="e.nombre_completo" size="lg">
      <EmpleadoForm :empleado="e" @guardado="(r) => { editarOpen = false; aplicar(r) }" @cancelar="editarOpen = false" />
    </SidePanel>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, h } from 'vue'
import { useRoute } from 'vue-router'
import {
  PhArrowLeft, PhMedal, PhKey, PhPencilSimple, PhUserMinus, PhIdentificationCard, PhBriefcase, PhFolderOpen, PhLockKey, PhCalendarCheck, PhHandCoins,
  PhImage, PhFilePdf, PhEye, PhTrash, PhPaperclip, PhShieldCheck, PhCaretLeft, PhCaretRight, PhReceipt, PhCurrencyCircleDollar,
  PhCrown, PhCallBell, PhChefHat,
} from '@phosphor-icons/vue'
import Botanical from '@/components/ui/Botanical.vue'
import Avatar from '@/components/ui/Avatar.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import KpiCard from '@/components/ui/KpiCard.vue'
import SidePanel from '@/components/ui/SidePanel.vue'
import EmpleadoForm from '@/components/personal/EmpleadoForm.vue'
import { personalService } from '@/services'
import { openFile } from '@/services/api'
import { useUiStore } from '@/stores/ui'
import { money, fecha, horasMin, PUESTO_COLOR } from '@/utils/format'
import { pedirMotivo, confirmar } from '@/plugins/swal'

// Fila "etiqueta: valor" de las fichas
const Dato = (p) => h('div', { class: 'flex justify-between gap-4 py-2 border-b border-line/40 last:border-0 text-sm' }, [
  h('dt', { class: 'text-ink-3 shrink-0' }, p.l),
  h('dd', { class: ['text-right text-ink', p.mono ? 'num' : '', !p.v ? 'text-ink-3' : ''] }, p.v || '—'),
])
Dato.props = ['l', 'v', 'mono']

const route = useRoute()
const ui = useUiStore()
const id = Number(route.params.id)
const e = ref(null)
const tab = ref('datos')
const editarOpen = ref(false)
const doc = reactive({ tipo: 'Contrato', nombre: '', vence_en: '', file: null })
const subiendo = ref(false)
const acc = reactive({})
const accErr = ref({})
const pin = ref('')
const asis = ref(null)
const mes = ref(new Date().toISOString().slice(0, 7))

const tabs = [
  { id: 'datos', label: 'Datos personales', icon: PhIdentificationCard },
  { id: 'contrato', label: 'Contrato y pago', icon: PhBriefcase },
  { id: 'docs', label: 'Documentos', icon: PhFolderOpen },
  { id: 'acceso', label: 'Acceso al sistema', icon: PhLockKey },
  { id: 'asistencia', label: 'Asistencia', icon: PhCalendarCheck },
  { id: 'ventas', label: 'Propinas y ventas', icon: PhHandCoins },
]
const roles = [
  { id: 'Admin', label: 'Admin', desc: 'Acceso total', icon: PhCrown },
  { id: 'Mesero', label: 'Mesero', desc: 'Salón, pedidos y cobro', icon: PhCallBell },
  { id: 'Cocinero', label: 'Cocina', desc: 'Tablero y agotados', icon: PhChefHat },
]
const leyenda = [
  { t: 'A tiempo', c: 'bg-ok/30' }, { t: 'Tarde', c: 'bg-warn/30' }, { t: 'Falta', c: 'bg-danger/30' },
  { t: 'Vacaciones / permiso', c: 'bg-sage/30' }, { t: 'Programado', c: 'bg-surface-3' },
]
const edad = computed(() => e.value?.fecha_nacimiento ? Math.floor((Date.now() - new Date(e.value.fecha_nacimiento)) / 31557600000) : null)
const docsAlerta = computed(() => e.value?.documentos.some(d => d.dias_para_vencer !== null && d.dias_para_vencer <= 30))
const mesTxt = computed(() => new Date(mes.value + '-15').toLocaleDateString('es-GT', { month: 'long', year: 'numeric' }))
const offsetMes = computed(() => (new Date(mes.value + '-01T12:00').getDay() + 6) % 7)
const colorDia = (d) => {
  const s = d.estados
  if (s.includes('falta') && !s.includes('justificada')) return 'bg-danger/20 border-danger/40 text-danger'
  if (s.includes('tarde')) return 'bg-warn/20 border-warn/40 text-warn'
  if (s.includes('vacaciones') || s.includes('permiso') || s.includes('justificada')) return 'bg-sage/20 border-sage/40 text-sage'
  if (s.includes('a_tiempo') || s.includes('sin_turno') || s.includes('en_turno')) return 'bg-ok/20 border-ok/40 text-ok'
  if (s.includes('programado')) return 'bg-surface-3 border-line text-ink-2'
  return 'border-transparent text-ink-3'
}

const aplicar = (r) => {
  e.value = r
  Object.assign(acc, { acceso_activo: r.acceso_activo, usuario: r.usuario || (r.nombre || '').toLowerCase().normalize('NFD').replace(/[^a-z]/g, ''), rol: r.rol || 'Mesero', password: '' })
}
const cargar = async () => { try { aplicar(await personalService.empleado(id)) } catch (er) { ui.error(er) } }
const cargarAsis = async () => { try { asis.value = await personalService.asistenciaMes(id, mes.value) } catch (er) { ui.error(er) } }
const cambiarMes = (n) => {
  const d = new Date(mes.value + '-15'); d.setMonth(d.getMonth() + n)
  mes.value = d.toISOString().slice(0, 7); cargarAsis()
}

const darBaja = async () => {
  const motivo = await pedirMotivo(`¿Dar de baja a ${e.value.nombre}?`, 'Pierde el acceso al sistema y sus turnos futuros se eliminan. Su historial se conserva.', { confirmar: 'Dar de baja' })
  if (!motivo) return
  try { aplicar(await personalService.baja(id, motivo)); ui.toast('Empleado dado de baja', 'info') } catch (er) { ui.error(er) }
}
const guardarAcceso = async () => {
  accErr.value = {}
  try { aplicar(await personalService.acceso(id, { ...acc, password: acc.password || undefined })); ui.toast('Acceso actualizado') } catch (er) { accErr.value = er.errors; ui.error(er) }
}
const guardarPin = async () => {
  try { await personalService.pin(id, pin.value); pin.value = ''; e.value.tiene_pin = true; ui.toast('PIN guardado') } catch (er) { ui.error(er) }
}
const subirDoc = async () => {
  subiendo.value = true
  try {
    e.value.documentos = await personalService.subirDocumento(id, doc.file, { tipo: doc.tipo, nombre: doc.nombre || doc.tipo, vence_en: doc.vence_en || null })
    Object.assign(doc, { nombre: '', vence_en: '', file: null })
    ui.toast('Documento agregado')
  } catch (er) { ui.error(er) }
  subiendo.value = false
}
const verDoc = async (d) => { try { await openFile(`/empleados/${id}/documentos/${d.id}`) } catch (er) { ui.error(er) } }
const borrarDoc = async (d) => {
  if (!await confirmar(`¿Eliminar «${d.nombre}»?`, 'El archivo se borra definitivamente.', { confirmar: 'Eliminar', peligro: true })) return
  try { e.value.documentos = await personalService.borrarDocumento(id, d.id) } catch (er) { ui.error(er) }
}

onMounted(() => { cargar(); cargarAsis() })
</script>

<style scoped>
.dl { display: block; }
</style>
