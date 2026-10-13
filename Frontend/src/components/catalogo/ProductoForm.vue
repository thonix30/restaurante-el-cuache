<template>
  <form class="space-y-5" @submit.prevent="guardar">
    <!-- Foto con enfoque -->
    <div>
      <p class="label">Foto del platillo</p>
      <div ref="area" class="relative aspect-[16/10] rounded-2xl overflow-hidden border border-line bg-surface-2 group"
           :class="f.imagen ? 'cursor-crosshair' : ''"
           @pointerdown="iniciarEnfoque" @dragover.prevent="dropping = true" @dragleave="dropping = false" @drop.prevent="soltarArchivo">
        <img v-if="f.imagen" :src="f.imagen" alt="" class="w-full h-full object-cover pointer-events-none" :style="{ objectPosition: f.imagen_posicion }" />
        <div v-else class="absolute inset-0 grid place-items-center text-center p-6 transition" :class="dropping ? 'bg-gold/10' : ''">
          <div>
            <PhImage :size="40" class="mx-auto text-ink-3" />
            <p class="text-sm text-ink-2 mt-2">Arrastra una foto aquí o</p>
            <button type="button" class="btn btn-soft btn-sm mt-2" @click.stop="fileEl.click()">Elegir archivo</button>
          </div>
        </div>
        <!-- mira del enfoque -->
        <div v-if="f.imagen" class="absolute w-10 h-10 -ml-5 -mt-5 rounded-full border-2 border-white shadow-[0_0_0_9999px_rgba(0,0,0,.18)] pointer-events-none grid place-items-center"
             :style="{ left: pos.x + '%', top: pos.y + '%' }"><span class="w-1.5 h-1.5 rounded-full bg-white" /></div>
        <div v-if="subiendo" class="absolute inset-0 bg-black/50 grid place-items-center"><span class="w-8 h-8 border-2 border-white/40 border-t-white rounded-full animate-spin" /></div>
        <div v-if="f.imagen" class="absolute bottom-2 right-2 flex gap-1.5 opacity-0 group-hover:opacity-100 transition">
          <button type="button" class="btn btn-sm bg-black/60 text-white border-0" @click.stop="fileEl.click()"><PhCamera :size="15" /> Cambiar</button>
          <button type="button" class="btn btn-sm bg-black/60 text-white border-0" @click.stop="f.imagen = null"><PhTrash :size="15" /></button>
        </div>
      </div>
      <input ref="fileEl" type="file" accept="image/*" class="hidden" @change="elegirArchivo" />
      <div v-if="f.imagen" class="flex items-center gap-3 mt-2.5">
        <p class="text-xs text-ink-3 flex-1 flex items-center gap-1.5"><PhCrosshair :size="14" /> Toca o arrastra sobre la foto para elegir el enfoque.</p>
        <!-- vistas previas: tarjeta del menú y miniatura cuadrada -->
        <div class="w-16 h-10 rounded-lg overflow-hidden border border-line"><img :src="f.imagen" class="w-full h-full object-cover" :style="{ objectPosition: f.imagen_posicion }" alt="" /></div>
        <div class="w-10 h-10 rounded-lg overflow-hidden border border-line"><img :src="f.imagen" class="w-full h-full object-cover" :style="{ objectPosition: f.imagen_posicion }" alt="" /></div>
      </div>
    </div>

    <div>
      <label class="label" for="pn">Nombre</label>
      <input id="pn" v-model.trim="f.nombre" class="input" :class="err.nombre ? 'input-error' : ''" placeholder="Pepián de pollo" />
      <p v-if="err.nombre" class="field-error">{{ err.nombre }}</p>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="label" for="pc">Categoría</label>
        <select id="pc" v-model="f.categoria_id" class="input">
          <option :value="null">Sin categoría</option>
          <option v-for="c in categorias" :key="c.id" :value="c.id">{{ c.nombre }}</option>
        </select>
      </div>
      <div class="flex items-end">
        <label class="flex items-center gap-3 h-11 cursor-pointer">
          <button type="button" class="switch" :class="{ on: f.destacado }" role="switch" :aria-checked="f.destacado" @click="f.destacado = !f.destacado" />
          <span class="text-sm font-bold text-ink-2">Recomendado</span>
        </label>
      </div>
    </div>

    <div>
      <label class="label" for="pd">Descripción</label>
      <textarea id="pd" v-model="f.descripcion" rows="3" maxlength="1000" class="input" placeholder="Ingredientes, acompañamientos…" />
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="label" for="pp">Precio de venta</label>
        <div class="relative"><span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-3 num">Q</span>
          <input id="pp" v-model.number="f.precio" type="number" step="0.5" min="0" class="input pl-8 num" :class="err.precio ? 'input-error' : ''" /></div>
        <p v-if="err.precio" class="field-error">{{ err.precio }}</p>
      </div>
      <div>
        <label class="label" for="pco">Costo</label>
        <div class="relative"><span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-3 num">Q</span>
          <input id="pco" v-model.number="f.costo" type="number" step="0.5" min="0" class="input pl-8 num" /></div>
      </div>
    </div>
    <div v-if="f.precio > 0" class="rounded-xl bg-surface-2/60 border border-line/60 px-4 py-3 flex items-center gap-3">
      <div class="flex-1 h-2 rounded-full bg-surface-3 overflow-hidden"><div class="h-full rounded-full transition-all" :class="margen >= 55 ? 'bg-ok' : margen >= 35 ? 'bg-warn' : 'bg-danger'" :style="{ width: Math.max(0, margen) + '%' }" /></div>
      <span class="text-sm">Margen <b class="num" :class="margen >= 55 ? 'text-ok' : margen >= 35 ? 'text-warn' : 'text-danger'">{{ margen }}%</b></span>
      <span class="text-xs text-ink-3 num">gana {{ money(f.precio - (f.costo || 0)) }}</span>
    </div>

    <div class="rounded-2xl border border-line/70 p-4 space-y-4">
      <label class="flex items-center justify-between gap-3 cursor-pointer">
        <span><b class="text-sm text-ink block">Disponible</b><span class="text-xs text-ink-3">Visible en el menú y la toma de pedidos</span></span>
        <button type="button" class="switch" :class="{ on: f.disponible }" role="switch" :aria-checked="f.disponible" @click="f.disponible = !f.disponible" />
      </label>
      <label class="flex items-center justify-between gap-3 cursor-pointer">
        <span><b class="text-sm text-ink block">Controlar stock</b><span class="text-xs text-ink-3">Se agota solo al llegar a 0</span></span>
        <button type="button" class="switch" :class="{ on: f.controla_stock }" role="switch" :aria-checked="f.controla_stock" @click="f.controla_stock = !f.controla_stock" />
      </label>
      <div v-if="f.controla_stock" class="grid grid-cols-2 gap-3">
        <div><label class="label">Stock actual</label><input v-model.number="f.stock" type="number" min="0" class="input num" /></div>
        <div><label class="label">Alerta de stock bajo</label><input v-model.number="f.stock_minimo" type="number" min="0" class="input num" /></div>
      </div>
    </div>

    <div class="flex gap-2 pt-2">
      <button v-if="f.id" type="button" class="btn btn-danger" @click="$emit('borrar', f)"><PhTrash :size="17" /></button>
      <button type="button" class="btn btn-soft flex-1" @click="$emit('cancelar')">Cancelar</button>
      <button type="submit" class="btn btn-gold flex-[2]" :disabled="guardando || subiendo">
        <span v-if="guardando" class="w-5 h-5 border-2 border-on-gold/40 border-t-on-gold rounded-full animate-spin" />
        <template v-else>{{ f.id ? 'Guardar cambios' : 'Crear platillo' }}</template>
      </button>
    </div>
  </form>
</template>

<script setup>
import { reactive, ref, computed, watch } from 'vue'
import { PhImage, PhCamera, PhTrash, PhCrosshair } from '@phosphor-icons/vue'
import { catalogoService } from '@/services'
import { useUiStore } from '@/stores/ui'
import { comprimirImagen } from '@/utils/image'
import { money } from '@/utils/format'

const props = defineProps({ producto: Object, categorias: Array })
const emit = defineEmits(['guardado', 'cancelar', 'borrar'])
const ui = useUiStore()
const vacio = () => ({ id: null, nombre: '', categoria_id: props.categorias?.[0]?.id ?? null, descripcion: '', precio: null, costo: null, imagen: null, imagen_posicion: '50% 50%', disponible: true, controla_stock: false, stock: 0, stock_minimo: 5, destacado: false })
const f = reactive(vacio())
const err = ref({})
const area = ref(null), fileEl = ref(null)
const subiendo = ref(false), guardando = ref(false), dropping = ref(false)

watch(() => props.producto, (p) => { Object.assign(f, vacio(), p ? { ...p } : {}); err.value = {} }, { immediate: true })

const pos = computed(() => { const [x, y] = f.imagen_posicion.split(' ').map(parseFloat); return { x: x || 50, y: y || 50 } })
const margen = computed(() => f.precio > 0 ? Math.round((f.precio - (f.costo || 0)) / f.precio * 100) : 0)

// Enfoque: el punto que se toca/arrastra se vuelve el object-position de la foto
const setEnfoque = (e) => {
  const r = area.value.getBoundingClientRect()
  const x = Math.round(Math.max(0, Math.min(100, (e.clientX - r.left) / r.width * 100)))
  const y = Math.round(Math.max(0, Math.min(100, (e.clientY - r.top) / r.height * 100)))
  f.imagen_posicion = `${x}% ${y}%`
}
const iniciarEnfoque = (e) => {
  if (!f.imagen || e.target.closest('button')) return
  setEnfoque(e)
  const mover = (ev) => setEnfoque(ev)
  window.addEventListener('pointermove', mover)
  window.addEventListener('pointerup', () => window.removeEventListener('pointermove', mover), { once: true })
}

const subir = async (file) => {
  if (!file?.type.startsWith('image/')) return ui.toast('El archivo debe ser una imagen', 'error')
  subiendo.value = true
  try {
    const r = await catalogoService.subirImagen(await comprimirImagen(file))
    f.imagen = r.url
    f.imagen_posicion = '50% 50%'
  } catch (e) { ui.error(e) }
  subiendo.value = false
}
const elegirArchivo = (e) => { subir(e.target.files[0]); e.target.value = '' }
const soltarArchivo = (e) => { dropping.value = false; subir(e.dataTransfer.files[0]) }

const guardar = async () => {
  err.value = {}
  if (!f.nombre) err.value.nombre = 'El platillo necesita un nombre.'
  if (!(f.precio > 0)) err.value.precio = 'El precio debe ser mayor a cero.'
  if (Object.keys(err.value).length) return
  guardando.value = true
  try {
    const data = { ...f, costo: f.costo || 0 }
    const r = f.id ? await catalogoService.editarProducto(f.id, data) : await catalogoService.crearProducto(data)
    ui.toast(f.id ? 'Platillo actualizado' : 'Platillo creado')
    emit('guardado', r)
  } catch (e) { err.value = e.errors; ui.error(e) }
  guardando.value = false
}
</script>
