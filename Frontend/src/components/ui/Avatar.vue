<template>
  <span class="relative inline-grid place-items-center shrink-0 rounded-full font-bold overflow-visible"
        :style="{ width: size + 'px', height: size + 'px', fontSize: size * 0.36 + 'px' }">
    <img v-if="src && !broken" :src="src" :alt="name" class="w-full h-full rounded-full object-cover ring-1 ring-line" @error="broken = true" />
    <span v-else class="w-full h-full rounded-full grid place-items-center ring-1"
          :style="{ background: `rgb(var(--${color}) / .18)`, color: `rgb(var(--${color}))`, '--tw-ring-color': `rgb(var(--${color}) / .35)` }">
      {{ initials(name) }}
    </span>
    <span v-if="dot" class="absolute bottom-0 right-0 rounded-full ring-2 ring-surface"
          :class="dot === 'ok' ? 'bg-ok' : dot === 'sage' ? 'bg-sage' : 'bg-muted'"
          :style="{ width: size * 0.28 + 'px', height: size * 0.28 + 'px' }" />
  </span>
</template>

<script setup>
import { computed, ref } from 'vue'
import { initials, PUESTO_COLOR } from '@/utils/format'

const props = defineProps({ src: String, name: { type: String, default: '' }, size: { type: Number, default: 40 }, puesto: String, dot: String })
const broken = ref(false)
const color = computed(() => {
  const c = PUESTO_COLOR[props.puesto]
  return c && c !== 'neutral' ? c : 'gold'
})
</script>
