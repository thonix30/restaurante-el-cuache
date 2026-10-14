<template>
  <template v-if="pwa.puedeInstalar || pwa.instruccionesIOS">
    <button :class="clase" @click="pulsar">
      <PhDownloadSimple :size="18" /> <span v-if="!soloIcono">Instalar app</span>
    </button>

    <!-- iPhone/iPad: Safari no muestra ventana de instalación -->
    <Modal v-model="verIOS" title="Instalar en iPhone" subtitle="Queda en tu pantalla de inicio, como una app">
      <ol class="space-y-3 text-sm text-ink-2">
        <li class="flex gap-3 items-start"><span class="paso">1</span><span>Abre esta página en <b class="text-ink">Safari</b>.</span></li>
        <li class="flex gap-3 items-start"><span class="paso">2</span><span>Toca <b class="text-ink">Compartir</b> <PhExport :size="16" class="inline -mt-1 text-gold" /> en la barra de abajo.</span></li>
        <li class="flex gap-3 items-start"><span class="paso">3</span><span>Elige <b class="text-ink">Agregar a inicio</b> <PhPlusSquare :size="16" class="inline -mt-1 text-gold" /> y confirma con <b class="text-ink">Agregar</b>.</span></li>
      </ol>
      <button class="btn btn-gold w-full mt-5" @click="verIOS = false">Entendido</button>
    </Modal>
  </template>
</template>

<script setup>
import { ref } from 'vue'
import { PhDownloadSimple, PhExport, PhPlusSquare } from '@phosphor-icons/vue'
import Modal from '@/components/ui/Modal.vue'
import { usePwaStore } from '@/stores/pwa'

defineProps({ clase: { type: String, default: 'btn btn-soft' }, soloIcono: Boolean })
const pwa = usePwaStore()
const verIOS = ref(false)
const pulsar = () => { if (pwa.puedeInstalar) pwa.instalar(); else verIOS.value = true }
</script>

<style scoped>
.paso { flex-shrink: 0; display: grid; place-items: center; width: 1.6rem; height: 1.6rem; border-radius: 999px;
        background: rgb(var(--gold) / .18); color: rgb(var(--gold)); font-weight: 800; font-size: .8rem; }
</style>
