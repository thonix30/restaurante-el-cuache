<template>
  <span class="chip chip-dot" :class="[cfg.cls, cfg.pulse ? 'pulse-gold' : '']">{{ label || cfg.label }}</span>
</template>

<script setup>
import { computed } from 'vue'

/**
 * Colores de estado del sistema:
 * pendiente ámbar · listo verde · cancelado rojo apagado · agotado gris ·
 * llamando al mesero dorado con pulso.
 */
const MAP = {
  // mesas
  libre: { label: 'Libre', cls: 'chip-neutral' },
  ocupada: { label: 'Ocupada', cls: 'chip-info' },
  por_cobrar: { label: 'Por cobrar', cls: 'chip-sage' },
  llamando: { label: 'Llamando', cls: 'chip-gold', pulse: true },
  cuenta: { label: 'Pidió la cuenta', cls: 'chip-gold', pulse: true },
  // comandas
  pendiente: { label: 'Pendiente', cls: 'chip-warn' },
  preparacion: { label: 'En preparación', cls: 'chip-info' },
  listo: { label: 'Listo', cls: 'chip-ok' },
  entregado: { label: 'Entregado', cls: 'chip-neutral' },
  cancelado: { label: 'Cancelado', cls: 'chip-danger' },
  // cuentas
  abierta: { label: 'Abierta', cls: 'chip-info' },
  pagada: { label: 'Pagada', cls: 'chip-ok' },
  cancelada: { label: 'Cancelada', cls: 'chip-danger' },
  // productos
  disponible: { label: 'Disponible', cls: 'chip-ok' },
  agotado: { label: 'Agotado', cls: 'chip-muted' },
  sin_stock: { label: 'Sin stock', cls: 'chip-muted' },
  stock_bajo: { label: 'Stock bajo', cls: 'chip-warn' },
  // asistencia
  a_tiempo: { label: 'A tiempo', cls: 'chip-ok' },
  tarde: { label: 'Tarde', cls: 'chip-warn' },
  falta: { label: 'Falta', cls: 'chip-danger' },
  sin_entrada: { label: 'Sin entrada', cls: 'chip-warn' },
  salida_anticipada: { label: 'Salida anticipada', cls: 'chip-warn' },
  horas_extra: { label: 'Horas extra', cls: 'chip-info' },
  en_turno: { label: 'En turno', cls: 'chip-ok' },
  en_descanso: { label: 'En descanso', cls: 'chip-sage' },
  sin_salida: { label: 'Sin salida', cls: 'chip-danger' },
  sin_turno: { label: 'Sin turno', cls: 'chip-neutral' },
  programado: { label: 'Programado', cls: 'chip-neutral' },
  vacaciones: { label: 'Vacaciones', cls: 'chip-sage' },
  permiso: { label: 'Permiso', cls: 'chip-sage' },
  justificada: { label: 'Justificada', cls: 'chip-sage' },
  libre_dia: { label: 'Libre', cls: 'chip-neutral' },
  // empleados
  activo: { label: 'Activo', cls: 'chip-ok' },
  suspendido: { label: 'Suspendido', cls: 'chip-warn' },
  baja: { label: 'De baja', cls: 'chip-danger' },
  // solicitudes
  aprobada: { label: 'Aprobada', cls: 'chip-ok' },
  rechazada: { label: 'Rechazada', cls: 'chip-danger' },
}

const props = defineProps({ estado: String, label: String })
const cfg = computed(() => MAP[props.estado] || { label: props.estado, cls: 'chip-neutral' })
</script>
