<template>
  <form class="space-y-6" @submit.prevent="guardar">
    <!-- Foto -->
    <div class="flex items-center gap-4">
      <div class="relative">
        <Avatar :src="f.foto" :name="`${f.nombre} ${f.apellido}`" :puesto="f.puesto" :size="84" />
        <button type="button" class="absolute -bottom-1 -right-1 btn btn-gold btn-sm btn-icon rounded-full" aria-label="Cambiar foto" @click="fileEl.click()">
          <span v-if="subiendo" class="w-4 h-4 border-2 border-on-gold/40 border-t-on-gold rounded-full animate-spin" /><PhCamera v-else :size="15" />
        </button>
        <input ref="fileEl" type="file" accept="image/*" class="hidden" @change="subirFoto" />
      </div>
      <div class="grid grid-cols-2 gap-3 flex-1">
        <div><label class="label">Nombre *</label><input v-model.trim="f.nombre" class="input" :class="err.nombre ? 'input-error' : ''" /><p v-if="err.nombre" class="field-error">{{ err.nombre }}</p></div>
        <div><label class="label">Apellido</label><input v-model.trim="f.apellido" class="input" /></div>
      </div>
    </div>

    <fieldset>
      <legend class="eyebrow mb-3">Datos personales</legend>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div><label class="label">Documento de identidad (DPI)</label><input v-model="f.documento_identidad" class="input num" /></div>
        <div><label class="label">Fecha de nacimiento</label><input v-model="f.fecha_nacimiento" type="date" class="input" :class="err.fecha_nacimiento ? 'input-error' : ''" /></div>
        <div><label class="label">Teléfono</label><input v-model="f.telefono" type="tel" class="input num" /></div>
        <div><label class="label">Correo</label><input v-model="f.email" type="email" class="input" :class="err.email ? 'input-error' : ''" /></div>
        <div class="sm:col-span-2"><label class="label">Dirección</label><input v-model="f.direccion" class="input" /></div>
      </div>
    </fieldset>

    <fieldset>
      <legend class="eyebrow mb-3">Contacto de emergencia</legend>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div><label class="label">Nombre</label><input v-model="f.emergencia_nombre" class="input" /></div>
        <div><label class="label">Parentesco</label><input v-model="f.emergencia_parentesco" class="input" list="parentescos" />
          <datalist id="parentescos"><option v-for="p in ['Madre', 'Padre', 'Esposo(a)', 'Hermano(a)', 'Hijo(a)', 'Otro']" :key="p" :value="p" /></datalist></div>
        <div><label class="label">Teléfono</label><input v-model="f.emergencia_telefono" type="tel" class="input num" /></div>
      </div>
    </fieldset>

    <fieldset>
      <legend class="eyebrow mb-3">Contrato y pago</legend>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div><label class="label">Puesto</label>
          <select v-model="f.puesto" class="input"><option v-for="p in puestos" :key="p">{{ p }}</option></select></div>
        <div><label class="label">Fecha de ingreso</label><input v-model="f.fecha_ingreso" type="date" class="input" /></div>
        <div><label class="label">Tipo de contrato</label>
          <select v-model="f.tipo_contrato" class="input"><option v-for="t in ['Indefinido', 'Temporal', 'Por horas', 'Prueba']" :key="t">{{ t }}</option></select></div>
        <div><label class="label">Forma de pago</label>
          <div class="seg w-full"><button type="button" class="flex-1" :class="{ on: f.tipo_pago === 'Mensual' }" @click="f.tipo_pago = 'Mensual'">Salario mensual</button>
            <button type="button" class="flex-1" :class="{ on: f.tipo_pago === 'Por hora' }" @click="f.tipo_pago = 'Por hora'">Por hora</button></div></div>
        <div v-if="f.tipo_pago === 'Mensual'"><label class="label">Salario mensual (Q)</label><input v-model.number="f.salario" type="number" min="0" step="50" class="input num" /></div>
        <div v-else><label class="label">Tarifa por hora (Q)</label><input v-model.number="f.tarifa_hora" type="number" min="0" step="0.5" class="input num" /></div>
        <div><label class="label">Días de vacaciones al año</label><input v-model.number="f.dias_vacaciones" type="number" min="0" class="input num" /></div>
        <div><label class="label">Banco</label><input v-model="f.banco" class="input" list="bancos" />
          <datalist id="bancos"><option v-for="b in ['Banco Industrial', 'Banrural', 'BAM', 'G&T Continental', 'BAC Credomatic', 'Promerica']" :key="b" :value="b" /></datalist></div>
        <div><label class="label">Cuenta bancaria</label><input v-model="f.cuenta_bancaria" class="input num" /></div>
      </div>
    </fieldset>

    <fieldset>
      <legend class="eyebrow mb-3">Uniforme y salud</legend>
      <div class="grid grid-cols-3 gap-3">
        <div><label class="label">Camisa</label><select v-model="f.talla_camisa" class="input"><option :value="null">—</option><option v-for="t in ['XS', 'S', 'M', 'L', 'XL', 'XXL']" :key="t">{{ t }}</option></select></div>
        <div><label class="label">Pantalón</label><input v-model="f.talla_pantalon" class="input num" placeholder="30" /></div>
        <div><label class="label">Calzado</label><input v-model="f.talla_calzado" class="input num" placeholder="40" /></div>
        <div class="col-span-3"><label class="label">Alergias o notas médicas</label><textarea v-model="f.notas_medicas" rows="2" class="input" placeholder="Información confidencial" /></div>
      </div>
    </fieldset>

    <fieldset v-if="f.id">
      <legend class="eyebrow mb-3">Estado</legend>
      <div class="grid grid-cols-3 gap-2">
        <button v-for="e in ['activo', 'vacaciones', 'suspendido']" :key="e" type="button" class="h-11 rounded-xl border text-sm font-bold capitalize transition"
                :class="f.estado === e ? 'border-gold bg-gold/15 text-gold' : 'border-line text-ink-2'" @click="f.estado = e">{{ e }}</button>
      </div>
    </fieldset>

    <div class="flex gap-2 pt-2 sticky bottom-0 bg-surface py-3 -mb-5">
      <button type="button" class="btn btn-soft flex-1" @click="$emit('cancelar')">Cancelar</button>
      <button type="submit" class="btn btn-gold flex-[2]" :disabled="guardando">{{ f.id ? 'Guardar ficha' : 'Registrar empleado' }}</button>
    </div>
  </form>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { PhCamera } from '@phosphor-icons/vue'
import Avatar from '@/components/ui/Avatar.vue'
import { personalService, catalogoService } from '@/services'
import { useUiStore } from '@/stores/ui'
import { comprimirImagen } from '@/utils/image'
import { isoDate } from '@/utils/format'

const props = defineProps({ empleado: Object })
const emit = defineEmits(['guardado', 'cancelar'])
const ui = useUiStore()
const puestos = ['Mesero', 'Cocinero', 'Bartender', 'Cajero', 'Ayudante', 'Administrador']
const CAMPOS = ['nombre', 'apellido', 'foto', 'documento_identidad', 'fecha_nacimiento', 'telefono', 'email', 'direccion', 'emergencia_nombre', 'emergencia_parentesco',
  'emergencia_telefono', 'puesto', 'fecha_ingreso', 'tipo_contrato', 'tipo_pago', 'salario', 'tarifa_hora', 'banco', 'cuenta_bancaria', 'talla_camisa',
  'talla_pantalon', 'talla_calzado', 'notas_medicas', 'estado', 'dias_vacaciones']
const vacio = () => ({ id: null, nombre: '', apellido: '', foto: null, puesto: 'Mesero', tipo_contrato: 'Indefinido', tipo_pago: 'Mensual', salario: 0, tarifa_hora: 0, dias_vacaciones: 15, fecha_ingreso: isoDate(), estado: 'activo' })
const f = reactive(vacio())
const err = ref({})
const fileEl = ref(null)
const subiendo = ref(false)
const guardando = ref(false)

watch(() => props.empleado, (e) => {
  Object.keys(f).forEach(k => delete f[k])
  Object.assign(f, vacio(), e ? Object.fromEntries(['id', ...CAMPOS].map(k => [k, e[k] ?? null])) : {})
  err.value = {}
}, { immediate: true })

const subirFoto = async (e) => {
  const file = e.target.files[0]
  e.target.value = ''
  if (!file) return
  subiendo.value = true
  try { f.foto = (await catalogoService.subirImagen(await comprimirImagen(file, 600))).url } catch (er) { ui.error(er) }
  subiendo.value = false
}

const guardar = async () => {
  err.value = {}
  if (!f.nombre) { err.value.nombre = 'Escribe el nombre.'; return }
  guardando.value = true
  try {
    const data = Object.fromEntries(CAMPOS.map(k => [k, f[k] === '' ? null : f[k]]))
    const r = f.id ? await personalService.editar(f.id, data) : await personalService.crear(data)
    ui.toast(f.id ? 'Ficha actualizada' : 'Empleado registrado')
    emit('guardado', r)
  } catch (e) { err.value = e.errors; ui.error(e) }
  guardando.value = false
}
</script>
