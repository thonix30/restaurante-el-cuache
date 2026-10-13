<template>
  <div>
    <PageHeader eyebrow="Seguridad" title="Auditoría" subtitle="Bitácora de acciones sensibles: cancelaciones, descuentos, precios, accesos, agotados y correcciones de asistencia." />

    <!-- Chips por tipo de evento -->
    <div class="flex gap-2 overflow-x-auto scrollbar-none pb-1 mb-4" data-reveal>
      <button v-for="c in categorias" :key="c.id" class="chip h-9 px-3.5 cursor-pointer shrink-0" :class="f.categoria === c.id ? 'chip-gold' : 'chip-neutral'" @click="f.categoria = f.categoria === c.id ? '' : c.id">
        <component :is="c.icon" :size="14" /> {{ c.label }} <b class="num">{{ d.por_categoria?.[c.id] || 0 }}</b>
      </button>
    </div>
    <div class="card p-3 mb-5 flex flex-wrap items-center gap-2" data-reveal>
      <input v-model="f.desde" type="date" class="input h-10 w-auto" /><span class="text-ink-3">—</span><input v-model="f.hasta" type="date" class="input h-10 w-auto" />
      <select v-model="f.usuario_id" class="input h-10 w-auto"><option value="">Todos los usuarios</option><option v-for="u in d.usuarios" :key="u.id" :value="u.id">{{ u.nombre }}</option></select>
      <select v-model="f.accion" class="input h-10 w-auto"><option value="">Todas las acciones</option><option v-for="a in d.acciones" :key="a" :value="a">{{ nombreAccion(a) }}</option></select>
      <div class="relative flex-1 min-w-[180px]"><PhMagnifyingGlass :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
        <input v-model="f.q" type="search" class="input h-10 pl-9" placeholder="Buscar en la descripción…" @keyup.enter="cargar(1)" /></div>
    </div>

    <div class="card overflow-hidden" data-reveal>
      <ul class="divide-y divide-line/50">
        <li v-for="e in d.eventos" :key="e.id" class="flex items-start gap-4 px-5 py-3.5 hover:bg-surface-2/50 cursor-pointer transition" @click="sel = e">
          <span class="grid place-items-center w-10 h-10 rounded-xl shrink-0" :class="estiloCat(e.categoria).cls"><component :is="estiloCat(e.categoria).icon" :size="19" weight="duotone" /></span>
          <div class="flex-1 min-w-0">
            <p class="text-sm"><b class="text-ink">{{ nombreAccion(e.accion) }}</b> <span class="text-ink-3">· {{ e.usuario_nombre || 'Sistema' }}</span></p>
            <p class="text-sm text-ink-2 mt-0.5 line-clamp-2">{{ e.descripcion }}</p>
          </div>
          <div class="text-right shrink-0">
            <p class="text-xs text-ink-2 num">{{ hora(e.creado_en) }}</p>
            <p class="text-[11px] text-ink-3">{{ fecha(e.creado_en) }}</p>
          </div>
        </li>
      </ul>
      <EmptyState v-if="d.eventos && !d.eventos.length" :icon="PhShieldCheck" title="Sin eventos" text="No hay acciones registradas con esos filtros." />
      <div v-if="paginas > 1" class="flex items-center justify-between px-4 py-3 border-t border-line/60 text-sm">
        <button class="btn btn-soft btn-sm" :disabled="d.page <= 1" @click="cargar(d.page - 1)">Anterior</button>
        <span class="text-ink-3">Página {{ d.page }} de {{ paginas }}</span>
        <button class="btn btn-soft btn-sm" :disabled="d.page >= paginas" @click="cargar(d.page + 1)">Siguiente</button>
      </div>
    </div>

    <Modal :model-value="!!sel" @update:model-value="sel = null" :title="sel ? nombreAccion(sel.accion) : ''" :eyebrow="sel?.categoria" size="md">
      <dl v-if="sel" class="space-y-3 text-sm">
        <div class="flex justify-between gap-4"><dt class="text-ink-3">Fecha</dt><dd class="num">{{ fechaHora(sel.creado_en) }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-ink-3">Usuario</dt><dd>{{ sel.usuario_nombre || 'Sistema' }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-ink-3">Entidad</dt><dd>{{ sel.entidad || '—' }} {{ sel.entidad_id ? '#' + sel.entidad_id : '' }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-ink-3">IP</dt><dd class="num">{{ sel.ip }}</dd></div>
        <div><dt class="text-ink-3 mb-1">Descripción</dt><dd class="text-ink">{{ sel.descripcion }}</dd></div>
        <div v-if="sel.detalle"><dt class="text-ink-3 mb-1">Detalle</dt>
          <dd><pre class="num text-xs bg-surface-2 border border-line rounded-xl p-3 overflow-x-auto whitespace-pre-wrap">{{ JSON.stringify(sel.detalle, null, 2) }}</pre></dd></div>
      </dl>
    </Modal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { PhMagnifyingGlass, PhShieldCheck, PhReceipt, PhForkKnife, PhKey, PhUsersThree, PhCashRegister, PhPackage, PhGear } from '@phosphor-icons/vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Modal from '@/components/ui/Modal.vue'
import { adminService } from '@/services'
import { useUiStore } from '@/stores/ui'
import { hora, fecha, fechaHora, isoDate, addDays } from '@/utils/format'

const ui = useUiStore()
const d = ref({})
const sel = ref(null)
const f = reactive({ desde: addDays(isoDate(), -6), hasta: isoDate(), categoria: '', accion: '', usuario_id: '', q: '' })
const paginas = computed(() => Math.ceil((d.value.total || 0) / (d.value.limit || 50)))

const categorias = [
  { id: 'venta', label: 'Ventas', icon: PhReceipt, cls: 'bg-gold/15 text-gold' },
  { id: 'producto', label: 'Carta', icon: PhForkKnife, cls: 'bg-warn/15 text-warn' },
  { id: 'acceso', label: 'Accesos', icon: PhKey, cls: 'bg-info/15 text-info' },
  { id: 'personal', label: 'Personal', icon: PhUsersThree, cls: 'bg-sage/15 text-sage' },
  { id: 'caja', label: 'Caja', icon: PhCashRegister, cls: 'bg-ok/15 text-ok' },
  { id: 'inventario', label: 'Inventario', icon: PhPackage, cls: 'bg-surface-3 text-ink-2' },
  { id: 'sistema', label: 'Sistema', icon: PhGear, cls: 'bg-surface-3 text-ink-3' },
]
const estiloCat = (c) => categorias.find(x => x.id === c) || categorias[6]
const NOMBRES = {
  pedido_cancelado: 'Pedido cancelado', platillo_cancelado: 'Platillo cancelado', cuenta_cancelada: 'Cuenta cancelada', descuento_aplicado: 'Descuento aplicado',
  pago_registrado: 'Cobro registrado', pago_anulado: 'Pago anulado', cuenta_movida: 'Cuenta movida de mesa', precio_cambiado: 'Cambio de precio',
  producto_creado: 'Platillo creado', producto_editado: 'Platillo editado', producto_eliminado: 'Platillo eliminado', producto_agotado: 'Marcado como agotado',
  producto_reactivado: 'Platillo reactivado', inicio_sesion: 'Inicio de sesión', inicio_sesion_fallido: 'Intento de acceso fallido', cierre_sesion: 'Cierre de sesión',
  acceso_modificado: 'Acceso modificado', pin_asignado: 'PIN asignado', password_cambiado: 'Contraseña cambiada', empleado_creado: 'Empleado registrado',
  empleado_editado: 'Ficha editada', empleado_baja: 'Baja de empleado', documento_subido: 'Documento agregado', documento_eliminado: 'Documento eliminado',
  asistencia_corregida: 'Corrección de asistencia', asistencia_justificada: 'Falta justificada', solicitud_aprobada: 'Solicitud aprobada', solicitud_rechazada: 'Solicitud rechazada',
  horario_publicado: 'Horario publicado', turnos_copiados: 'Turnos copiados', turnos_eliminados: 'Turnos eliminados', plantilla_aplicada: 'Plantilla aplicada',
  caja_abierta: 'Caja abierta', caja_cerrada: 'Caja cerrada', compra_registrada: 'Compra registrada', stock_ajustado: 'Ajuste de stock',
  mesa_eliminada: 'Mesa eliminada', qr_regenerado: 'QR regenerado', configuracion_cambiada: 'Configuración cambiada',
}
const nombreAccion = (a) => NOMBRES[a] || a

const cargar = async (page = 1) => {
  try { d.value = await adminService.auditoria({ ...f, page }) } catch (e) { ui.error(e) }
}
watch(() => [f.desde, f.hasta, f.categoria, f.accion, f.usuario_id], () => cargar(1))
onMounted(() => cargar())
</script>
