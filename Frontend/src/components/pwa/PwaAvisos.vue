<template>
  <!-- Versión nueva publicada: se actualiza cuando la persona quiera -->
  <Transition name="pwa">
    <div v-if="pwa.hayVersionNueva" class="fixed z-[210] left-3 right-3 sm:left-auto sm:right-4 sm:w-96 card glass shadow-lift p-3.5 flex items-center gap-3 no-print pwa-abajo"
         role="alert">
      <span class="grid place-items-center w-9 h-9 rounded-full bg-gold/20 shrink-0"><PhArrowsClockwise :size="18" weight="bold" class="text-gold" /></span>
      <div class="flex-1 min-w-0 leading-snug">
        <p class="text-sm font-extrabold text-ink">Hay una versión nueva</p>
        <p class="text-xs text-ink-3">Actualiza cuando termines lo que estás haciendo.</p>
      </div>
      <button class="btn btn-ghost btn-sm" @click="pwa.despues()">Luego</button>
      <button class="btn btn-gold btn-sm" @click="pwa.actualizar()">Actualizar</button>
    </div>
  </Transition>

  <!-- Sin internet -->
  <Transition name="pwa">
    <div v-if="!enLinea" class="fixed z-[205] top-0 inset-x-0 flex justify-center pointer-events-none no-print" role="status">
      <span class="mt-2 px-3.5 h-8 rounded-full bg-warn text-[#1b1208] text-xs font-bold flex items-center gap-1.5 shadow-lift">
        <PhWifiSlash :size="15" weight="bold" /> Sin conexión · los cambios no se guardarán hasta volver
      </span>
    </div>
  </Transition>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { PhArrowsClockwise, PhWifiSlash } from '@phosphor-icons/vue'
import { usePwaStore } from '@/stores/pwa'

const pwa = usePwaStore()
const enLinea = ref(navigator.onLine)
const cambio = () => { enLinea.value = navigator.onLine }
onMounted(() => { addEventListener('online', cambio); addEventListener('offline', cambio) })
onUnmounted(() => { removeEventListener('online', cambio); removeEventListener('offline', cambio) })
</script>

<style scoped>
/* Sobre la barra inferior del celular (y el área segura del iPhone) */
.pwa-abajo { bottom: calc(5.5rem + env(safe-area-inset-bottom)); }
@media (min-width: 1024px) { .pwa-abajo { bottom: 1.25rem; } }
.pwa-enter-active, .pwa-leave-active { transition: opacity .25s, transform .25s; }
.pwa-enter-from, .pwa-leave-to { opacity: 0; transform: translateY(10px); }
</style>
