<template>
  <div>
    <div class="flex items-end gap-1.5 h-40 relative" role="img" :aria-label="ariaLabel">
      <div v-for="(c, i) in cols" :key="c.label" class="flex-1 h-full flex flex-col justify-end items-stretch relative group cursor-default"
           @pointerenter="hover = i" @pointerleave="hover = null">
        <div class="flex flex-col-reverse gap-[2px] rounded-t-[4px] overflow-hidden transition-opacity" :class="hover != null && hover !== i ? 'opacity-50' : ''">
          <div v-for="s in series" :key="s.key" :style="{ height: h(c.values[s.key]) + 'px', background: `rgb(var(--${s.color}))` }" />
        </div>
        <div v-if="hover === i" class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 card glass px-3 py-2 text-xs z-10 whitespace-nowrap shadow-lift pointer-events-none">
          <p class="font-bold text-ink mb-1">{{ c.tooltip || c.label }}</p>
          <p v-for="s in series" :key="s.key" class="text-ink-2 flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-sm" :style="{ background: `rgb(var(--${s.color}))` }" />{{ s.label }}
            <b class="num text-ink ml-auto pl-3">{{ c.values[s.key] }}</b>
          </p>
          <p v-if="c.footer" class="text-ink-3 mt-1">{{ c.footer }}</p>
        </div>
      </div>
    </div>
    <div class="flex gap-1.5 mt-2">
      <span v-for="c in cols" :key="c.label" class="flex-1 text-center text-[10px] text-ink-3 truncate">{{ c.label }}</span>
    </div>
    <!-- Leyenda con etiquetas (estado nunca solo por color) -->
    <div class="flex flex-wrap gap-4 mt-3 text-xs text-ink-2">
      <span v-for="s in series" :key="s.key" class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm" :style="{ background: `rgb(var(--${s.color}))` }" />{{ s.label }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

// Columnas apiladas por día (puntualidad: a tiempo / tarde / falta)
const props = defineProps({ cols: { type: Array, default: () => [] }, series: { type: Array, default: () => [] }, ariaLabel: String })
const hover = ref(null)
const max = computed(() => Math.max(1, ...props.cols.map(c => props.series.reduce((s, x) => s + (c.values[x.key] || 0), 0))))
const h = (v) => (v || 0) / max.value * 150
</script>
