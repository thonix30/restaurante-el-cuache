<template>
  <div>
    <PageHeader eyebrow="Equipo" title="Personal" subtitle="Fichas, acceso al sistema y estado de cada persona del equipo.">
      <router-link to="/kiosco" class="btn btn-soft"><PhFingerprint :size="18" /> Kiosco</router-link>
      <button class="btn btn-gold" @click="nuevo = true"><PhUserPlus :size="18" /> Nuevo empleado</button>
    </PageHeader>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4 mb-6">
      <KpiCard label="Empleados activos" :value="kpis.activos || 0" :icon="PhUsersThree" />
      <KpiCard label="En turno ahora" :value="kpis.en_turno || 0" :icon="PhClock" tone="ok" />
      <KpiCard label="Tardanzas hoy" :value="kpis.tardanzas_hoy || 0" :icon="PhAlarm" tone="warn" />
      <KpiCard label="Faltas esta semana" :value="kpis.faltas_semana || 0" :icon="PhCalendarX" tone="danger" />
    </div>

    <div class="grid xl:grid-cols-[1fr_320px] gap-4">
      <div>
        <div class="flex flex-wrap items-center gap-2 mb-4" data-reveal>
          <div class="relative flex-1 min-w-[200px]"><PhMagnifyingGlass :size="17" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
            <input v-model="q" type="search" class="input h-10 pl-9" placeholder="Buscar por nombre, teléfono o usuario…" /></div>
          <select v-model="puesto" class="input h-10 w-auto"><option value="">Todos los puestos</option><option v-for="p in puestos" :key="p">{{ p }}</option></select>
          <select v-model="estado" class="input h-10 w-auto"><option value="">Activos y otros</option><option value="activo">Activos</option><option value="vacaciones">De vacaciones</option><option value="suspendido">Suspendidos</option><option value="baja">Dados de baja</option></select>
          <div class="seg"><button :class="{ on: vista === 'tarjetas' }" aria-label="Tarjetas" @click="vista = 'tarjetas'"><PhSquaresFour :size="16" /></button><button :class="{ on: vista === 'tabla' }" aria-label="Tabla" @click="vista = 'tabla'"><PhRows :size="16" /></button></div>
        </div>

        <div v-if="vista === 'tarjetas'" class="grid sm:grid-cols-2 2xl:grid-cols-3 gap-3">
          <router-link v-for="e in filtrados" :key="e.id" :to="`/personal/${e.id}`" class="card card-hover p-4 flex items-center gap-4">
            <Avatar :src="e.foto" :name="e.nombre_completo" :puesto="e.puesto" :size="56" :dot="e.en_turno ? (e.en_descanso ? 'sage' : 'ok') : null" />
            <div class="flex-1 min-w-0">
              <p class="font-bold truncate">{{ e.nombre_completo }}</p>
              <div class="flex flex-wrap items-center gap-1.5 mt-1">
                <span class="chip" :class="'chip-' + (PUESTO_COLOR[e.puesto] || 'neutral')">{{ e.puesto }}</span>
                <StatusChip v-if="e.estado !== 'activo'" :estado="e.estado" />
                <span v-if="e.en_turno" class="chip chip-ok chip-dot">{{ e.en_descanso ? 'En descanso' : 'En turno' }}</span>
              </div>
              <p class="text-xs text-ink-3 mt-1.5 num">{{ e.telefono || 'Sin teléfono' }}<template v-if="e.acceso_activo"> · <PhKey :size="11" class="inline" /> {{ e.usuario }}</template></p>
            </div>
            <PhCaretRight :size="18" class="text-ink-3" />
          </router-link>
        </div>
        <div v-else class="card overflow-x-auto">
          <table class="tbl">
            <thead><tr><th>Empleado</th><th>Puesto</th><th>Estado</th><th>Teléfono</th><th>Acceso</th><th>Ingreso</th></tr></thead>
            <tbody>
              <tr v-for="e in filtrados" :key="e.id" class="cursor-pointer" @click="router.push(`/personal/${e.id}`)">
                <td><div class="flex items-center gap-3"><Avatar :src="e.foto" :name="e.nombre_completo" :puesto="e.puesto" :size="36" :dot="e.en_turno ? 'ok' : null" /><span class="font-bold">{{ e.nombre_completo }}</span></div></td>
                <td><span class="chip" :class="'chip-' + (PUESTO_COLOR[e.puesto] || 'neutral')">{{ e.puesto }}</span></td>
                <td><StatusChip :estado="e.estado" /></td>
                <td class="num text-ink-2">{{ e.telefono || '—' }}</td>
                <td class="text-ink-2">{{ e.acceso_activo ? `${e.rol} · ${e.usuario}` : 'Solo kiosco' }}</td>
                <td class="text-ink-3">{{ e.fecha_ingreso ? fecha(e.fecha_ingreso + ' 12:00', { month: 'short', year: 'numeric' }) : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <EmptyState v-if="!cargando && !filtrados.length" :icon="PhUsersThree" title="Sin resultados" text="Ajusta los filtros o registra a alguien nuevo." />
      </div>

      <!-- Alertas del equipo -->
      <aside class="card p-5 h-fit" data-reveal>
        <p class="panel-title mb-1">Alertas del equipo</p>
        <p class="text-xs text-ink-3 mb-4">Documentos, cumpleaños, salidas y cobertura</p>
        <ul class="space-y-2.5">
          <li v-for="(a, i) in alertasEquipo" :key="i">
            <router-link :to="a.ruta || '#'" class="flex gap-3 rounded-xl p-2.5 -mx-2.5 hover:bg-surface-2 transition">
              <span class="grid place-items-center w-9 h-9 rounded-xl shrink-0" :class="nivel(a.nivel)"><component :is="icono(a.tipo)" :size="18" weight="duotone" /></span>
              <div class="min-w-0"><p class="text-sm font-bold">{{ a.titulo }}</p><p class="text-xs text-ink-3">{{ a.mensaje }}</p></div>
            </router-link>
          </li>
        </ul>
        <p v-if="!alertasEquipo.length" class="text-sm text-ink-3">Todo en orden ✓</p>
      </aside>
    </div>

    <SidePanel v-model="nuevo" title="Nuevo empleado" eyebrow="Personal" size="lg">
      <EmpleadoForm :empleado="null" @guardado="(e) => { nuevo = false; router.push(`/personal/${e.id}`) }" @cancelar="nuevo = false" />
    </SidePanel>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  PhFingerprint, PhUserPlus, PhUsersThree, PhClock, PhAlarm, PhCalendarX, PhMagnifyingGlass, PhSquaresFour, PhRows, PhCaretRight, PhKey,
  PhFileText, PhCake, PhSignOut, PhTray, PhBell,
} from '@phosphor-icons/vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import KpiCard from '@/components/ui/KpiCard.vue'
import Avatar from '@/components/ui/Avatar.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SidePanel from '@/components/ui/SidePanel.vue'
import EmpleadoForm from '@/components/personal/EmpleadoForm.vue'
import { personalService } from '@/services'
import { useUiStore } from '@/stores/ui'
import { PUESTO_COLOR, fecha } from '@/utils/format'

const router = useRouter()
const ui = useUiStore()
const empleados = ref([])
const kpis = ref({})
const alertas = ref([])
const q = ref('')
const puesto = ref('')
const estado = ref('')
const vista = ref('tarjetas')
const nuevo = ref(false)
const cargando = ref(true)
const puestos = ['Mesero', 'Cocinero', 'Bartender', 'Cajero', 'Ayudante', 'Administrador']

const norm = (s) => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const filtrados = computed(() => empleados.value.filter(e => !q.value || norm(`${e.nombre_completo} ${e.telefono} ${e.usuario}`).includes(norm(q.value))))
const alertasEquipo = computed(() => alertas.value.filter(a => a.tipo !== 'stock_bajo'))
const nivel = (n) => ({ alta: 'bg-danger/15 text-danger', media: 'bg-warn/15 text-warn', baja: 'bg-info/15 text-info', info: 'bg-gold/15 text-gold' }[n])
const icono = (t) => ({ documento: PhFileText, cumpleanos: PhCake, sin_salida: PhSignOut, falta_personal: PhUsersThree, solicitud: PhTray }[t] || PhBell)

const cargar = async () => {
  try {
    const d = await personalService.empleados({ puesto: puesto.value || undefined, estado: estado.value || undefined })
    empleados.value = d.empleados
    kpis.value = d.kpis
  } catch (e) { ui.error(e) }
  cargando.value = false
}
watch([puesto, estado], cargar)
onMounted(async () => {
  cargar()
  try { alertas.value = await personalService.alertas() } catch { /* opcional */ }
})
</script>
