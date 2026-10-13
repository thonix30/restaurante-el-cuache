<template>
  <div class="max-w-4xl">
    <PageHeader eyebrow="Cocina" title="Disponibilidad" subtitle="Lo que marques como agotado desaparece al instante del menú del cliente y de la toma de pedidos.">
      <span class="chip chip-muted h-8 num">{{ totalAgotados }} agotados</span>
    </PageHeader>

    <div class="flex flex-col sm:flex-row gap-3 mb-6" data-reveal>
      <div class="relative flex-1">
        <PhMagnifyingGlass :size="18" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-3" />
        <input v-model="q" type="search" class="input pl-10 h-12" placeholder="Buscar platillo…" />
      </div>
      <label class="card h-12 px-4 flex items-center gap-3 cursor-pointer select-none">
        <button type="button" class="switch" :class="{ on: soloAgotados }" role="switch" :aria-checked="soloAgotados" @click="soloAgotados = !soloAgotados" />
        <span class="text-sm font-bold text-ink-2">Solo agotados</span>
      </label>
    </div>

    <div v-if="cargando" class="space-y-2"><div v-for="i in 6" :key="i" class="skeleton h-16" /></div>
    <section v-for="g in grupos" :key="g.nombre" class="mb-7" data-reveal>
      <div class="flex items-center gap-2 mb-2.5">
        <CatIcon :name="g.icono" :size="18" class="text-gold" />
        <h2 class="font-serif text-xl">{{ g.nombre }}</h2>
        <span class="text-xs text-ink-3 num">{{ g.items.filter(p => !p.disponible).length }}/{{ g.items.length }} agotados</span>
      </div>
      <ul class="card divide-y divide-line/50 overflow-hidden">
        <li v-for="p in g.items" :key="p.id" class="flex items-center gap-3 px-4 py-3 transition" :class="!p.disponible ? 'bg-surface-2/50' : ''">
          <img :src="p.imagen || './img/almuerzo.jpg'" alt="" class="w-11 h-11 rounded-xl object-cover" :class="!p.disponible ? 'grayscale-soft' : ''" :style="{ objectPosition: p.imagen_posicion }" />
          <div class="flex-1 min-w-0">
            <p class="font-bold truncate" :class="!p.disponible ? 'text-ink-3' : 'text-ink'">{{ p.nombre }}</p>
            <div class="flex items-center gap-2 mt-0.5">
              <span v-if="p.agotado_motivo === 'stock'" class="chip chip-muted">Sin stock</span>
              <span v-if="p.controla_stock" class="num text-[11px] px-2 py-0.5 rounded-md bg-surface-3 text-ink-2">stock {{ p.stock }}</span>
            </div>
          </div>
          <span class="text-xs font-bold w-20 text-right" :class="p.disponible ? 'text-ok' : 'text-ink-3'">{{ p.disponible ? 'Disponible' : 'Agotado' }}</span>
          <button type="button" class="switch switch-lg" :class="{ on: p.disponible }" role="switch" :aria-checked="p.disponible"
                  :aria-label="`${p.nombre}: ${p.disponible ? 'disponible' : 'agotado'}`" :disabled="ocupado.has(p.id)" @click="toggle(p)" />
        </li>
      </ul>
    </section>
    <EmptyState v-if="!cargando && !grupos.length" :icon="PhCheckCircle" :title="soloAgotados ? 'Nada agotado' : 'Sin resultados'" :text="soloAgotados ? 'Toda la carta está disponible.' : 'Prueba con otro nombre.'" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { PhMagnifyingGlass, PhCheckCircle } from '@phosphor-icons/vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import CatIcon from '@/components/ui/CatIcon.vue'
import { catalogoService } from '@/services'
import { useUiStore } from '@/stores/ui'
import { useRealtimeStore } from '@/stores/realtime'
import { pedirNumero } from '@/plugins/swal'

const ui = useUiStore()
const rt = useRealtimeStore()
const productos = ref([])
const categorias = ref([])
const q = ref('')
const soloAgotados = ref(false)
const cargando = ref(true)
const ocupado = ref(new Set())

const norm = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const totalAgotados = computed(() => productos.value.filter(p => !p.disponible).length)
const grupos = computed(() => {
  const lista = productos.value.filter(p => (!soloAgotados.value || !p.disponible) && (!q.value || norm(p.nombre).includes(norm(q.value))))
  const g = categorias.value.map(c => ({ nombre: c.nombre, icono: c.icono, items: lista.filter(p => p.categoria_id === c.id) }))
  const sin = lista.filter(p => !categorias.value.some(c => c.id === p.categoria_id))
  if (sin.length) g.push({ nombre: 'Sin categoría', icono: 'ForkKnife', items: sin })
  return g.filter(x => x.items.length)
})

const toggle = async (p, stock) => {
  const nuevo = !p.disponible
  ocupado.value = new Set([...ocupado.value, p.id])
  try {
    const r = await catalogoService.disponibilidad(p.id, nuevo, stock)
    Object.assign(p, r)
    rt.poll()
    ui.toast(nuevo ? `${p.nombre} vuelve a la carta` : `${p.nombre} marcado como agotado`, nuevo ? 'success' : 'info')
  } catch (e) {
    // Agotado por stock: para reactivarlo hay que indicar cuántas unidades hay
    if (e.payload?.requiere_stock) {
      const n = await pedirNumero(`¿Cuántas unidades de ${p.nombre} hay?`, 'Se agotó por stock. Indica el stock actual para reactivarlo.', { min: 1, valor: p.stock_minimo || 10 })
      ocupado.value.delete(p.id)
      if (n) return toggle(p, n)
    } else ui.error(e)
  }
  ocupado.value.delete(p.id)
  ocupado.value = new Set(ocupado.value)
}

onMounted(async () => {
  try {
    const [p, c] = await Promise.all([catalogoService.productos(), catalogoService.categorias()])
    productos.value = p
    categorias.value = c
  } catch (e) { ui.error(e) }
  cargando.value = false
})
</script>
