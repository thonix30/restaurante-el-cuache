<template>
  <div class="card p-4 sm:p-5 relative overflow-hidden group" data-reveal>
    <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full blur-2xl opacity-40 transition group-hover:opacity-70"
         :style="{ background: `rgb(var(--${tone}) / .35)` }" />
    <div class="flex items-center justify-between gap-2 relative">
      <p class="eyebrow">{{ label }}</p>
      <span class="grid place-items-center w-9 h-9 rounded-xl border border-line/60" :style="{ background: `rgb(var(--${tone}) / .12)`, color: `rgb(var(--${tone}))` }">
        <component :is="icon" :size="18" weight="duotone" />
      </span>
    </div>
    <p ref="valEl" class="num text-2xl sm:text-[28px] font-semibold text-ink mt-3 relative">{{ display }}</p>
    <div class="flex items-center gap-2 mt-1 relative min-h-[20px]">
      <span v-if="delta != null" class="text-xs font-bold num" :class="delta >= 0 ? 'text-ok' : 'text-danger'">
        {{ delta >= 0 ? '▲' : '▼' }} {{ Math.abs(delta) }}%
      </span>
      <span v-if="hint" class="text-xs text-ink-3 truncate">{{ hint }}</span>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import { countTo } from '@/composables/useMotion'
import { money } from '@/utils/format'

// Tarjeta de indicador con cuenta animada (GSAP) y variación contra el periodo anterior
const props = defineProps({
  label: String, value: { type: Number, default: 0 }, icon: [Object, Function],
  format: { type: String, default: 'int' }, delta: { type: Number, default: null }, hint: String, tone: { type: String, default: 'gold' },
})
const valEl = ref(null)
const fmt = (v) => props.format === 'money' ? money(v) : props.format === 'pct' ? Math.round(v) + '%' : Math.round(v).toLocaleString('es-GT')
const display = computed(() => fmt(0))
const run = () => countTo(valEl.value, props.value, fmt)
onMounted(run)
watch(() => props.value, run)
</script>
