<template>
  <div class="flex flex-col sm:flex-row items-center gap-6">
    <div class="relative w-44 h-44 shrink-0">
      <svg viewBox="0 0 120 120" class="w-full h-full -rotate-90" role="img" :aria-label="ariaLabel">
        <circle cx="60" cy="60" r="46" fill="none" stroke="rgb(var(--surface-2))" stroke-width="16" />
        <circle v-for="(s, i) in segs" :key="s.label" ref="arcs" cx="60" cy="60" r="46" fill="none"
                :stroke="s.color" :stroke-width="hover === i ? 19 : 16"
                :stroke-dasharray="`${s.len} ${C - s.len}`" :stroke-dashoffset="-s.off"
                class="transition-[stroke-width] duration-200 cursor-pointer"
                @pointerenter="hover = i" @pointerleave="hover = null" />
      </svg>
      <div class="absolute inset-0 grid place-items-center text-center pointer-events-none">
        <div>
          <p class="num text-base font-semibold text-ink">{{ hover != null ? pct(segs[hover].value) : (fmtCentro || fmt)(total) }}</p>
          <p class="text-[11px] text-ink-3 max-w-[90px] truncate">{{ hover != null ? segs[hover].label : 'Total' }}</p>
        </div>
      </div>
    </div>
    <!-- Leyenda: la identidad nunca depende solo del color -->
    <ul class="flex-1 w-full space-y-2">
      <li v-for="(s, i) in segs" :key="s.label" class="flex items-center gap-2.5 text-sm rounded-lg px-2 py-1 -mx-2 transition"
          :class="hover === i ? 'bg-surface-2' : ''" @pointerenter="hover = i" @pointerleave="hover = null">
        <span class="w-2.5 h-2.5 rounded-sm shrink-0" :style="{ background: s.color }" />
        <span class="flex-1 truncate text-ink">{{ s.label }}</span>
        <span class="num text-ink-3 text-xs">{{ pct(s.value) }}</span>
        <span class="num text-ink-2 w-24 text-right">{{ fmt(s.value) }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, watch, nextTick } from 'vue'
import { gsap, reduced } from '@/composables/useMotion'

// Colores categóricos en orden fijo; más de 5 categorías se agrupan en "Otras"
const props = defineProps({ rows: { type: Array, default: () => [] }, fmt: { type: Function, default: (v) => v }, fmtCentro: Function, ariaLabel: String })
const C = 2 * Math.PI * 46
const GAP = 1.6
const hover = ref(null)
const arcs = ref([])
const total = computed(() => props.rows.reduce((s, r) => s + r.value, 0))
const grouped = computed(() => {
  const sorted = [...props.rows].sort((a, b) => b.value - a.value)
  if (sorted.length <= 5) return sorted
  const rest = sorted.slice(4).reduce((s, r) => s + r.value, 0)
  return [...sorted.slice(0, 4), { label: 'Otras', value: rest, other: true }]
})
const segs = computed(() => {
  let off = 0
  return grouped.value.map((r, i) => {
    const full = total.value ? r.value / total.value * C : 0
    const s = { ...r, color: r.other ? 'var(--cat-other)' : `var(--cat-${i + 1})`, len: Math.max(0, full - GAP), off }
    off += full
    return s
  })
})
const pct = (v) => total.value ? Math.round(v / total.value * 100) + '%' : '0%'
const anim = async () => {
  await nextTick()
  if (reduced() || !arcs.value.length) return
  gsap.from(arcs.value, { strokeDasharray: `0 ${C}`, duration: 1.1, ease: 'power3.out', stagger: .08 })
}
onMounted(anim)
watch(() => props.rows, anim)
</script>
