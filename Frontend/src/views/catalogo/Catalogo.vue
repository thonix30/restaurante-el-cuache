<template>
  <div>
    <PageHeader eyebrow="Gestión" title="Carta y mesas" subtitle="Platillos con foto, precio, costo y disponibilidad; categorías del menú y mesas del salón.">
      <button v-if="tab === 'productos'" class="btn btn-gold" @click="editar(null)"><PhPlus :size="18" weight="bold" /> Nuevo platillo</button>
      <button v-if="tab === 'categorias'" class="btn btn-gold" @click="editarCat({})"><PhPlus :size="18" weight="bold" /> Nueva categoría</button>
      <button v-if="tab === 'mesas'" class="btn btn-gold" @click="editarMesa({})"><PhPlus :size="18" weight="bold" /> Nueva mesa</button>
    </PageHeader>

    <div class="flex flex-wrap items-center gap-3 mb-5" data-reveal>
      <div class="seg">
        <button :class="{ on: tab === 'productos' }" @click="tab = 'productos'">Platillos <span class="num text-ink-3">{{ productos.length }}</span></button>
        <button :class="{ on: tab === 'categorias' }" @click="tab = 'categorias'">Categorías <span class="num text-ink-3">{{ categorias.length }}</span></button>
        <button :class="{ on: tab === 'mesas' }" @click="tab = 'mesas'">Mesas <span class="num text-ink-3">{{ mesas.length }}</span></button>
      </div>
      <template v-if="tab === 'productos'">
        <div class="relative flex-1 min-w-[200px]">
          <PhMagnifyingGlass :size="17" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
          <input v-model="q" type="search" class="input h-10 pl-9" placeholder="Buscar…" />
        </div>
        <select v-model="catFiltro" class="input h-10 w-auto"><option :value="null">Todas las categorías</option><option v-for="c in categorias" :key="c.id" :value="c.id">{{ c.nombre }}</option></select>
        <div class="seg">
          <button :class="{ on: vista === 'grilla' }" aria-label="Grilla" @click="vista = 'grilla'"><PhSquaresFour :size="16" /></button>
          <button :class="{ on: vista === 'tabla' }" aria-label="Tabla" @click="vista = 'tabla'"><PhRows :size="16" /></button>
        </div>
      </template>
    </div>

    <!-- ═══ Platillos ═══ -->
    <template v-if="tab === 'productos'">
      <div v-if="vista === 'grilla'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <article v-for="p in filtrados" :key="p.id" class="card card-hover overflow-hidden cursor-pointer group" @click="editar(p)">
          <div class="relative aspect-[16/10] overflow-hidden">
            <img :src="p.imagen || './img/almuerzo.jpg'" alt="" class="w-full h-full object-cover transition duration-700 group-hover:scale-105" :class="!p.disponible ? 'grayscale-soft' : ''" :style="{ objectPosition: p.imagen_posicion }" />
            <div class="absolute top-2 left-2 flex gap-1.5">
              <StatusChip v-if="!p.disponible" :estado="p.agotado_motivo === 'stock' ? 'sin_stock' : 'agotado'" class="glass" />
              <span v-if="p.destacado" class="chip chip-gold glass"><PhStar :size="11" weight="fill" /></span>
            </div>
          </div>
          <div class="p-4">
            <p class="text-[11px] eyebrow">{{ p.categoria || 'Sin categoría' }}</p>
            <p class="font-serif text-lg leading-tight mt-0.5 truncate">{{ p.nombre }}</p>
            <div class="flex items-center justify-between mt-3 text-sm">
              <span class="num text-gold font-semibold">{{ money(p.precio) }}</span>
              <span class="num text-xs text-ink-3">costo {{ money(p.costo) }} · <b :class="p.margen >= 55 ? 'text-ok' : 'text-warn'">{{ p.margen }}%</b></span>
            </div>
            <div class="flex items-center justify-between mt-3 pt-3 border-t border-line/50" @click.stop>
              <span class="text-xs" :class="p.controla_stock ? (p.stock_bajo ? 'text-warn font-bold' : 'text-ink-3') : 'text-ink-3'">
                {{ p.controla_stock ? `Stock ${p.stock}` : 'Sin control de stock' }}
              </span>
              <button type="button" class="switch" :class="{ on: p.disponible }" role="switch" :aria-checked="p.disponible" :aria-label="'Disponible: ' + p.nombre" @click="toggleDisp(p)" />
            </div>
          </div>
        </article>
      </div>
      <div v-else class="card overflow-x-auto">
        <table class="tbl">
          <thead><tr><th>Platillo</th><th>Categoría</th><th class="text-right">Precio</th><th class="text-right">Costo</th><th class="text-right">Margen</th><th class="text-right">Stock</th><th>Disponible</th></tr></thead>
          <tbody>
            <tr v-for="p in filtrados" :key="p.id" class="cursor-pointer" @click="editar(p)">
              <td><div class="flex items-center gap-3"><img :src="p.imagen || './img/almuerzo.jpg'" class="w-10 h-10 rounded-lg object-cover" :style="{ objectPosition: p.imagen_posicion }" alt="" /><span class="font-bold">{{ p.nombre }}</span></div></td>
              <td class="text-ink-2">{{ p.categoria || '—' }}</td>
              <td class="text-right num">{{ money(p.precio) }}</td>
              <td class="text-right num text-ink-3">{{ money(p.costo) }}</td>
              <td class="text-right num" :class="p.margen >= 55 ? 'text-ok' : 'text-warn'">{{ p.margen }}%</td>
              <td class="text-right num">{{ p.controla_stock ? p.stock : '—' }}</td>
              <td @click.stop><button type="button" class="switch" :class="{ on: p.disponible }" role="switch" :aria-checked="p.disponible" @click="toggleDisp(p)" /></td>
            </tr>
          </tbody>
        </table>
      </div>
      <EmptyState v-if="!filtrados.length" :icon="PhForkKnife" title="Sin platillos" text="Crea el primero con «Nuevo platillo»." />
    </template>

    <!-- ═══ Categorías ═══ -->
    <ul v-if="tab === 'categorias'" class="card divide-y divide-line/50 max-w-3xl">
      <li v-for="(c, i) in categorias" :key="c.id" class="flex items-center gap-3 px-4 py-3">
        <div class="flex flex-col">
          <button class="text-ink-3 hover:text-ink disabled:opacity-20" :disabled="i === 0" aria-label="Subir" @click="mover(i, -1)"><PhCaretUp :size="15" /></button>
          <button class="text-ink-3 hover:text-ink disabled:opacity-20" :disabled="i === categorias.length - 1" aria-label="Bajar" @click="mover(i, 1)"><PhCaretDown :size="15" /></button>
        </div>
        <span class="grid place-items-center w-11 h-11 rounded-xl bg-gold/15 text-gold"><CatIcon :name="c.icono" :size="22" /></span>
        <div class="flex-1">
          <p class="font-bold" :class="!c.activa ? 'text-ink-3' : ''">{{ c.nombre }}</p>
          <p class="text-xs text-ink-3">{{ c.productos }} platillos{{ !c.activa ? ' · oculta en el menú' : '' }}</p>
        </div>
        <button class="btn btn-ghost btn-sm" @click="editarCat(c)"><PhPencilSimple :size="16" /> Editar</button>
        <button class="btn btn-ghost btn-sm btn-icon text-ink-3 hover:text-danger" aria-label="Eliminar" @click="borrarCat(c)"><PhTrash :size="16" /></button>
      </li>
    </ul>

    <!-- ═══ Mesas ═══ -->
    <div v-if="tab === 'mesas'" class="card overflow-x-auto max-w-4xl">
      <table class="tbl">
        <thead><tr><th>Mesa</th><th>Zona</th><th>Capacidad</th><th>Forma</th><th>Estado</th><th /></tr></thead>
        <tbody>
          <tr v-for="m in mesas" :key="m.id">
            <td><span class="font-serif text-xl">{{ m.numero }}</span> <span v-if="m.nombre !== 'Mesa ' + m.numero" class="text-ink-3 text-sm">{{ m.nombre }}</span></td>
            <td>{{ m.zona }}</td>
            <td class="num">{{ m.capacidad }}</td>
            <td class="capitalize text-ink-2">{{ m.forma }}</td>
            <td><StatusChip :estado="m.estado" /></td>
            <td class="text-right whitespace-nowrap">
              <button class="btn btn-ghost btn-sm" @click="editarMesa(m)"><PhPencilSimple :size="16" /></button>
              <button class="btn btn-ghost btn-sm text-ink-3 hover:text-danger" @click="borrarMesa(m)"><PhTrash :size="16" /></button>
            </td>
          </tr>
        </tbody>
      </table>
      <p class="text-xs text-ink-3 p-4">La posición en el plano se ajusta en <router-link to="/salon" class="text-gold font-bold">Salón → Editar plano</router-link>; los QR, en <router-link to="/qr" class="text-gold font-bold">Códigos QR</router-link>.</p>
    </div>

    <!-- Formularios -->
    <SidePanel v-model="formOpen" :title="actual?.id ? 'Editar platillo' : 'Nuevo platillo'" eyebrow="Carta" size="lg">
      <ProductoForm :producto="actual" :categorias="categorias" @guardado="onGuardado" @cancelar="formOpen = false" @borrar="borrar" />
    </SidePanel>

    <Modal v-model="catOpen" :title="cat.id ? 'Editar categoría' : 'Nueva categoría'" size="md">
      <label class="label">Nombre</label>
      <input v-model.trim="cat.nombre" class="input mb-4" placeholder="Ej. Desayunos" />
      <p class="label">Ícono</p>
      <div class="grid grid-cols-6 sm:grid-cols-10 gap-2 mb-4">
        <button v-for="(ic, k) in ICONOS" :key="k" type="button" class="aspect-square rounded-xl border grid place-items-center transition"
                :class="cat.icono === k ? 'border-gold bg-gold/15 text-gold' : 'border-line text-ink-3 hover:text-ink'" :aria-label="k" @click="cat.icono = k">
          <component :is="ic" :size="20" />
        </button>
      </div>
      <label class="flex items-center gap-3 cursor-pointer">
        <button type="button" class="switch" :class="{ on: cat.activa !== false }" role="switch" @click="cat.activa = cat.activa === false" />
        <span class="text-sm font-bold text-ink-2">Visible en el menú</span>
      </label>
      <template #footer>
        <button class="btn btn-soft" @click="catOpen = false">Cancelar</button>
        <button class="btn btn-gold" :disabled="!cat.nombre" @click="guardarCat">Guardar</button>
      </template>
    </Modal>

    <Modal v-model="mesaOpen" :title="mesa.id ? `Editar mesa ${mesa.numero}` : 'Nueva mesa'" size="md">
      <div class="grid grid-cols-2 gap-3">
        <div><label class="label">Número</label><input v-model.number="mesa.numero" type="number" min="1" class="input num" /></div>
        <div><label class="label">Capacidad</label><input v-model.number="mesa.capacidad" type="number" min="1" max="30" class="input num" /></div>
        <div><label class="label">Zona</label><input v-model="mesa.zona" class="input" list="zonas" placeholder="Salón, Terraza…" />
          <datalist id="zonas"><option v-for="z in zonas" :key="z" :value="z" /></datalist></div>
        <div><label class="label">Nombre (opcional)</label><input v-model="mesa.nombre_custom" class="input" placeholder="Ej. Ventana" /></div>
      </div>
      <p class="label mt-4">Forma</p>
      <div class="grid grid-cols-3 gap-2">
        <button v-for="fm in ['redonda', 'cuadrada', 'larga']" :key="fm" type="button" class="h-20 rounded-2xl border grid place-items-center gap-1 transition"
                :class="mesa.forma === fm ? 'border-gold bg-gold/15 text-gold' : 'border-line text-ink-3'" @click="mesa.forma = fm">
          <span class="border-2 border-current" :class="{ redonda: 'w-8 h-8 rounded-full', cuadrada: 'w-8 h-8 rounded-lg', larga: 'w-14 h-7 rounded-lg' }[fm]" />
          <span class="text-xs font-bold capitalize">{{ fm }}</span>
        </button>
      </div>
      <template #footer>
        <button class="btn btn-soft" @click="mesaOpen = false">Cancelar</button>
        <button class="btn btn-gold" :disabled="!mesa.numero" @click="guardarMesa">Guardar</button>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { PhPlus, PhMagnifyingGlass, PhSquaresFour, PhRows, PhStar, PhForkKnife, PhCaretUp, PhCaretDown, PhPencilSimple, PhTrash } from '@phosphor-icons/vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SidePanel from '@/components/ui/SidePanel.vue'
import Modal from '@/components/ui/Modal.vue'
import CatIcon, { ICONOS } from '@/components/ui/CatIcon.vue'
import ProductoForm from '@/components/catalogo/ProductoForm.vue'
import { catalogoService } from '@/services'
import { useUiStore } from '@/stores/ui'
import { money } from '@/utils/format'
import { confirmar, pedirNumero } from '@/plugins/swal'

const ui = useUiStore()
const tab = ref('productos')
const vista = ref('grilla')
const productos = ref([])
const categorias = ref([])
const mesas = ref([])
const q = ref('')
const catFiltro = ref(null)
const formOpen = ref(false)
const actual = ref(null)
const catOpen = ref(false)
const cat = reactive({})
const mesaOpen = ref(false)
const mesa = reactive({})

const norm = (s) => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const filtrados = computed(() => productos.value.filter(p => (!catFiltro.value || p.categoria_id === catFiltro.value) && (!q.value || norm(p.nombre).includes(norm(q.value)))))
const zonas = computed(() => [...new Set(mesas.value.map(m => m.zona))])

const cargar = async () => {
  try {
    const [p, c, m] = await Promise.all([catalogoService.productos(), catalogoService.categorias(), catalogoService.mesas()])
    productos.value = p; categorias.value = c; mesas.value = m
  } catch (e) { ui.error(e) }
}

const editar = (p) => { actual.value = p; formOpen.value = true }
const onGuardado = () => { formOpen.value = false; cargar() }
const borrar = async (p) => {
  if (!await confirmar(`¿Eliminar «${p.nombre}»?`, 'Sale de la carta. El historial de ventas conserva su nombre y costo.', { confirmar: 'Eliminar', peligro: true })) return
  try { await catalogoService.borrarProducto(p.id); formOpen.value = false; ui.toast('Platillo eliminado', 'info'); cargar() } catch (e) { ui.error(e) }
}
const toggleDisp = async (p, stock) => {
  try { Object.assign(p, await catalogoService.disponibilidad(p.id, !p.disponible, stock)) } catch (e) {
    if (e.payload?.requiere_stock) {
      const n = await pedirNumero(`Stock de ${p.nombre}`, 'Se agotó por stock. ¿Cuántas unidades hay ahora?', { min: 1, valor: 10 })
      if (n) toggleDisp(p, n)
    } else ui.error(e)
  }
}

const editarCat = (c) => { Object.keys(cat).forEach(k => delete cat[k]); Object.assign(cat, { icono: 'ForkKnife', activa: true }, c); catOpen.value = true }
const guardarCat = async () => {
  try { await catalogoService.guardarCategoria({ ...cat }); catOpen.value = false; ui.toast('Categoría guardada'); cargar() } catch (e) { ui.error(e) }
}
const borrarCat = async (c) => {
  if (!await confirmar(`¿Eliminar la categoría «${c.nombre}»?`, '', { confirmar: 'Eliminar', peligro: true })) return
  try { await catalogoService.borrarCategoria(c.id); cargar() } catch (e) { ui.error(e) }
}
const mover = async (i, dir) => {
  const arr = [...categorias.value]
  ;[arr[i], arr[i + dir]] = [arr[i + dir], arr[i]]
  categorias.value = arr
  try { await catalogoService.ordenCategorias(arr.map(c => c.id)) } catch (e) { ui.error(e) }
}

const editarMesa = (m) => {
  Object.keys(mesa).forEach(k => delete mesa[k])
  const sig = Math.max(0, ...mesas.value.map(x => x.numero)) + 1
  Object.assign(mesa, { numero: sig, capacidad: 4, zona: 'Salón', forma: 'cuadrada' }, m, { nombre_custom: m.nombre && m.nombre !== 'Mesa ' + m.numero ? m.nombre : '' })
  mesaOpen.value = true
}
const guardarMesa = async () => {
  try {
    await catalogoService.guardarMesa({ id: mesa.id, numero: mesa.numero, capacidad: mesa.capacidad, zona: mesa.zona, forma: mesa.forma, nombre: mesa.nombre_custom || null, ...(mesa.id ? {} : { pos_x: 40, pos_y: 40 }) })
    mesaOpen.value = false; ui.toast('Mesa guardada'); cargar()
  } catch (e) { ui.error(e) }
}
const borrarMesa = async (m) => {
  if (!await confirmar(`¿Eliminar la mesa ${m.numero}?`, 'Su código QR dejará de funcionar.', { confirmar: 'Eliminar', peligro: true })) return
  try { await catalogoService.borrarMesa(m.id); cargar() } catch (e) { ui.error(e) }
}

onMounted(cargar)
</script>
