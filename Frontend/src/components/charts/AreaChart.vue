<template>
  <div class="relative select-none" ref="wrap" @pointermove="onMove" @pointerleave="hover = null">
    <svg :viewBox="`0 0 ${W} ${H}`" class="w-full h-auto block" role="img" :aria-label="ariaLabel">
      <defs>
        <linearGradient :id="gid" x1="0" x2="0" y1="0" y2="1">
          <stop offset="0%" stop-color="rgb(var(--gold))" stop-opacity=".32" />
          <stop offset="100%" stop-color="rgb(var(--gold))" stop-opacity="0" />
        </linearGradient>
      </defs>
      <!-- cuadrícula recesiva -->
      <g>
        <line v-for="t in ticks" :key="t" :x1="P.l" :x2="W - P.r" :y1="y(t)" :y2="y(t)" stroke="rgb(var(--line))" stroke-opacity=".5" stroke-dasharray="2 4" />
        <text v-for="t in ticks" :key="'l' + t" :x="P.l - 8" :y="y(t) + 4" text-anchor="end" class="fill-ink-3" style="font: 500 10px 'JetBrains Mono'">{{ fmtAxis(t) }}</text>
      </g>
      <!-- periodo anterior (punteado) -->
      <path v-if="prev.length" :d="line(prev)" fill="none" stroke="rgb(var(--ink-3))" stroke-width="1.5" stroke-dasharray="4 4" stroke-opacity=".7" />
      <!-- serie actual -->
      <path ref="areaEl" :d="area(values)" :fill="`url(#${gid})`" />
      <path ref="lineEl" :d="line(values)" fill="none" stroke="rgb(var(--gold))" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
      <!-- etiquetas X -->
      <text v-for="(l, i) in labels" v-show="showLabel(i)" :key="'x' + i" :x="x(i)" :y="H - 6" text-anchor="middle" class="fill-ink-3" style="font: 500 10px 'Familjen Grotesk'">{{ l }}</text>
      <!-- crosshair -->
      <g v-if="hover != null">
        <line :x1="x(hover)" :x2="x(hover)" :y1="P.t" :y2="H - P.b" stroke="rgb(var(--ink-2))" stroke-opacity=".35" />
        <circle :cx="x(hover)" :cy="y(values[hover])" r="5" fill="rgb(var(--gold))" stroke="rgb(var(--surface))" stroke-width="2" />
      </g>
    </svg>
    <div v-if="hover != null" class="absolute pointer-events-none card glass px-3 py-2 text-xs shadow-lift -translate-x-1/2 -translate-y-full whitespace-nowrap"
         :style="{ left: (x(hover) / W * 100) + '%', top: (y(values[hover]) / H * 100) + '%', marginTop: '-12px' }">
      <p class="font-bold text-ink">{{ tooltipLabels[hover] }}</p>
      <p class="text-ink-2"><span class="inline-block w-2 h-2 rounded-full bg-gold mr-1.5" />Actual <b class="num text-ink ml-1">{{ fmt(values[hover]) }}</b></p>
      <p v-if="prev.length" class="text-ink-3"><span class="inline-block w-2 h-0.5 bg-ink-3 mr-1.5 align-middle" />Anterior <b class="num ml-1">{{ fmt(prev[hover] ?? 0) }}</b></p>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { gsap, reduced } from '@/composables/useMotion'

// Área de una serie (ingresos) + periodo anterior punteado. Un solo eje Y.
const props = defineProps({
  values: { type: Array, default: () => [] },
  prev: { type: Array, default: () => [] },
  labels: { type: Array, default: () => [] },
  tooltipLabels: { type: Array, default: () => [] },
  fmt: { type: Function, default: (v) => v },
  fmtAxis: { type: Function, default: (v) => v },
  ariaLabel: { type: String, default: 'Gráfica de ingresos' },
})
const W = 640, H = 240
const P = { l: 52, r: 12, t: 16, b: 28 }
const gid = 'ag' + Math.random().toString(36).slice(2, 8)
const wrap = ref(null), lineEl = ref(null), areaEl = ref(null), hover = ref(null)

const max = computed(() => {
  const m = Math.max(1, ...props.values, ...props.prev)
  const pow = Math.pow(10, Math.floor(Math.log10(m)))
  return Math.ceil(m / pow) * pow
})
const ticks = computed(() => [0, .25, .5, .75, 1].map(f => Math.round(max.value * f)))
const x = (i) => P.l + (props.values.length <= 1 ? 0 : i * (W - P.l - P.r) / (props.values.length - 1))
const y = (v) => H - P.b - (v / max.value) * (H - P.t - P.b)
const line = (arr) => arr.map((v, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(v).toFixed(1)}`).join('')
const area = (arr) => arr.length ? line(arr) + `L${x(arr.length - 1)},${H - P.b}L${x(0)},${H - P.b}Z` : ''
const showLabel = (i) => {
  const n = props.labels.length
  const step = Math.ceil(n / 8)
  return i % step === 0 || i === n - 1
}
const onMove = (e) => {
  if (!props.values.length) return
  const r = wrap.value.getBoundingClientRect()
  const px = (e.clientX - r.left) / r.width * W
  const i = Math.round((px - P.l) / ((W - P.l - P.r) / Math.max(1, props.values.length - 1)))
  hover.value = Math.max(0, Math.min(props.values.length - 1, i))
}
const draw = () => {
  if (reduced() || !lineEl.value) return
  const len = lineEl.value.getTotalLength?.() || 1000
  gsap.fromTo(lineEl.value, { strokeDasharray: len, strokeDashoffset: len }, { strokeDashoffset: 0, duration: 1.2, ease: 'power2.inOut', clearProps: 'strokeDasharray,strokeDashoffset' })
  gsap.fromTo(areaEl.value, { opacity: 0 }, { opacity: 1, duration: 1, delay: .4 })
}
onMounted(draw)
watch(() => props.values, draw)
</script>
