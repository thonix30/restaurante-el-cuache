<template>
  <router-view />
  <ToastHost />
  <PwaAvisos />
</template>

<script setup>
// Root Component
import ToastHost from './components/ui/ToastHost.vue'
import PwaAvisos from './components/pwa/PwaAvisos.vue'
</script>
