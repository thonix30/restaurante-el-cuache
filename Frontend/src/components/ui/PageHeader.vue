<template>
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between mb-6" data-reveal>
    <div class="min-w-0">
      <p v-if="eyebrow" class="eyebrow mb-1.5">{{ eyebrow }}</p>
      <h1 class="font-serif text-3xl sm:text-4xl text-ink leading-[1.05]">{{ title }}</h1>
      <p v-if="subtitle" class="text-sm text-ink-3 mt-2 max-w-2xl">{{ subtitle }}</p>
    </div>
    <div v-if="$slots.default" class="flex flex-wrap items-center gap-2"><slot /></div>
  </div>
</template>

<script setup>
defineProps({ title: String, subtitle: String, eyebrow: String })
</script>
