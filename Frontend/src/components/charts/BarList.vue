<template>
  <ul class="space-y-3" role="list">
    <li v-for="(r, i) in rows" :key="r.label" class="group" :title="`${r.label}: ${fmt(r.value)}${r.extra ? ' · ' + r.extra : ''}`">
      <div class="flex items-baseline justify-between gap-3 text-sm mb-1.5">
        <span class="flex items-center gap-2 min-w-0">
          <span class="num text-[11px] text-ink-3 w-4">{{ i + 1 }}</span>
          <span class="truncate text-ink font-semibold">{{ r.label }}</span>
        </span>
        <span class="num text-ink-2 shrink-0">{{ fmt(r.value) }}<span v-if="r.extra" class="text-ink-3 ml-1.5 text-xs">{{ r.extra }}</span></span>
      </div>
      <div class="h-2 rounded-full bg-surface-2 overflow-hidden">
        <div ref="bars" class="h-full rounded-full transition-[filter] group-hover:brightness-125"
             :style="{ width: (r.value / max * 100) + '%', background: 'rgb(var(--gold))', opacity: 1 - i * 0.06 }" />
      </div>
    </li>
  </ul>
</template>

<script setup>
import { computed, ref, onMounted, watch, nextTick } from 'vue'
import { gsap, reduced } from '@/composables/useMotion'

// Ranking horizontal de un solo tono (magnitud), etiquetas en texto, no en color
const props = defineProps({ rows: { type: Array, default: () => [] }, fmt: { type: Function, default: (v) => v } })
const bars = ref([])
const max = computed(() => Math.max(1, ...props.rows.map(r => r.value)))
const grow = async () => {
  await nextTick()
  if (reduced() || !bars.value.length) return
  gsap.from(bars.value, { width: 0, duration: 1, ease: 'power3.out', stagger: .06 })
}
onMounted(grow)
watch(() => props.rows, grow)
</script>
