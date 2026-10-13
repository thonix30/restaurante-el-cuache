<template>
  <div class="max-w-2xl">
    <PageHeader eyebrow="En vivo" title="Avisos" subtitle="Mesas que llaman o piden la cuenta, ordenadas por tiempo de espera. Más de 3 minutos se resaltan en ámbar." />
    <AvisosList />
  </div>
</template>

<script setup>
import PageHeader from '@/components/ui/PageHeader.vue'
import AvisosList from '@/components/layout/AvisosList.vue'
</script>
