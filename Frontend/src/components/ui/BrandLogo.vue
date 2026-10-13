<template>
  <img :src="src" alt="El Cuache · Café y Restaurante" :width="size" :height="size"
       class="select-none object-contain" draggable="false" />
</template>

<script setup>
import { computed } from 'vue'
import { useUiStore } from '@/stores/ui'

// Logo en tinta crema sobre fondos oscuros y tinta carbón sobre fondos claros
const props = defineProps({ size: { type: Number, default: 48 }, tone: { type: String, default: 'auto' } })
const ui = useUiStore()
const src = computed(() => {
  const t = props.tone === 'auto' ? (ui.theme === 'dark' ? 'cream' : 'ink') : props.tone
  return `./img/logo-${t}.png`
})
</script>
