<template>
  <div class="max-w-3xl">
    <PageHeader eyebrow="Sistema" title="Ajustes" subtitle="Datos del restaurante y reglas que usa el sistema para turnos, asistencia y alertas." />

    <form class="space-y-4" @submit.prevent="guardar">
      <div class="card p-5 space-y-4" data-reveal>
        <p class="panel-title">Restaurante</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div><label class="label">Nombre</label><input v-model="c.restaurante_nombre" class="input" /></div>
          <div><label class="label">Lema</label><input v-model="c.restaurante_lema" class="input" /></div>
          <div><label class="label">Propina sugerida (%)</label><input v-model="c.propina_sugerida" type="number" min="0" max="30" class="input num" /></div>
          <div><label class="label">URL base del menú (QR)</label><input v-model="c.url_base_menu" class="input num text-xs" placeholder="Automática" /></div>
        </div>
      </div>

      <div class="card p-5 space-y-4" data-reveal>
        <p class="panel-title">Turnos y asistencia</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div><label class="label">Horario de apertura</label><input v-model="c.horario_apertura" type="time" class="input num" /></div>
          <div><label class="label">Horario de cierre</label><input v-model="c.horario_cierre" type="time" class="input num" /></div>
          <div><label class="label">Máximo de horas por semana</label><input v-model="c.horas_max_semana" type="number" min="1" class="input num" /></div>
          <div><label class="label">Descanso mínimo entre turnos (h)</label><input v-model="c.descanso_min_horas" type="number" min="0" class="input num" /></div>
          <div><label class="label">Tolerancia de llegada (min)</label><input v-model="c.tolerancia_minutos" type="number" min="0" class="input num" /></div>
          <div><label class="label">Personal mínimo por hora</label><input v-model="c.cobertura_minima" type="number" min="0" class="input num" /></div>
          <div><label class="label">Avisar documentos que vencen en (días)</label><input v-model="c.alerta_doc_dias" type="number" min="1" class="input num" /></div>
        </div>
      </div>

      <div class="flex justify-end"><button class="btn btn-gold btn-lg" :disabled="guardando"><PhFloppyDisk :size="18" /> Guardar ajustes</button></div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { PhFloppyDisk } from '@phosphor-icons/vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import { catalogoService } from '@/services'
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()
const c = ref({})
const guardando = ref(false)
const guardar = async () => {
  guardando.value = true
  try { c.value = await catalogoService.guardarConfiguracion(c.value); ui.toast('Ajustes guardados') } catch (e) { ui.error(e) }
  guardando.value = false
}
onMounted(async () => { try { c.value = await catalogoService.configuracion() } catch (e) { ui.error(e) } })
</script>
