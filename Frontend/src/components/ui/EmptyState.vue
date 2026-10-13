<template>
  <div class="flex flex-col items-center justify-center text-center py-14 px-6">
    <div class="relative mb-5">
      <Botanical class="absolute -inset-10 w-36 text-gold/15 -rotate-12" />
      <span class="relative grid place-items-center w-16 h-16 rounded-2xl bg-surface-2 border border-line">
        <component :is="icon" :size="30" class="text-gold" />
      </span>
    </div>
    <p class="font-serif text-xl text-ink">{{ title }}</p>
    <p v-if="text" class="text-sm text-ink-3 mt-1.5 max-w-sm text-balance">{{ text }}</p>
    <div v-if="$slots.default" class="mt-5"><slot /></div>
  </div>
</template>

<script setup>
import { PhCoffee } from '@phosphor-icons/vue'
import Botanical from './Botanical.vue'

defineProps({ title: String, text: String, icon: { type: [Object, Function], default: () => PhCoffee } })
</script>
