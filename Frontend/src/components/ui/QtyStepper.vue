<template>
  <div class="inline-flex items-center rounded-xl bg-surface-2 border border-line/80" :class="big ? 'h-12' : 'h-9'">
    <button type="button" class="grid place-items-center h-full rounded-l-xl text-ink-2 hover:text-ink active:scale-90 transition"
            :class="big ? 'w-12' : 'w-9'" :aria-label="'Quitar uno'" :disabled="disabled" @click="$emit('dec')">
      <PhMinus :size="big ? 20 : 15" weight="bold" />
    </button>
    <span class="num text-center font-bold text-ink" :class="big ? 'w-8 text-lg' : 'w-6 text-sm'">{{ value }}</span>
    <button type="button" class="grid place-items-center h-full rounded-r-xl text-gold hover:text-gold-2 active:scale-90 transition"
            :class="big ? 'w-12' : 'w-9'" :aria-label="'Agregar uno'" :disabled="disabled" @click="$emit('inc')">
      <PhPlus :size="big ? 20 : 15" weight="bold" />
    </button>
  </div>
</template>

<script setup>
import { PhMinus, PhPlus } from '@phosphor-icons/vue'

defineProps({ value: { type: Number, default: 0 }, big: Boolean, disabled: Boolean })
defineEmits(['inc', 'dec'])
</script>
