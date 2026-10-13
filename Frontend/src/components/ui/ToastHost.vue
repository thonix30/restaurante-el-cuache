<template>
  <div class="fixed z-[200] top-3 right-3 left-3 sm:left-auto sm:w-96 flex flex-col gap-2 pointer-events-none no-print"
       aria-live="polite">
    <TransitionGroup name="list">
      <div v-for="t in ui.toasts" :key="t.id"
           class="pointer-events-auto card glass flex items-start gap-3 p-3.5 pr-2 shadow-lift"
           :class="t.tipo === 'call' ? 'border-gold/60' : ''" role="status">
        <span class="mt-0.5 grid place-items-center w-8 h-8 rounded-full shrink-0" :class="tone(t.tipo).bg">
          <component :is="tone(t.tipo).icon" :size="18" weight="bold" :class="tone(t.tipo).text" />
        </span>
        <div class="flex-1 min-w-0">
          <p v-if="t.titulo" class="text-sm font-extrabold text-ink">{{ t.titulo }}</p>
          <p class="text-sm text-ink-2 leading-snug">{{ t.mensaje }}</p>
        </div>
        <button class="btn btn-ghost btn-sm btn-icon" aria-label="Cerrar" @click="ui.dismiss(t.id)">
          <PhX :size="16" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { PhX, PhCheckCircle, PhWarningCircle, PhInfo, PhBellRinging, PhWarning } from '@phosphor-icons/vue'
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()
const tone = (t) => ({
  success: { icon: PhCheckCircle, bg: 'bg-ok/15', text: 'text-ok' },
  error: { icon: PhWarningCircle, bg: 'bg-danger/15', text: 'text-danger' },
  warn: { icon: PhWarning, bg: 'bg-warn/15', text: 'text-warn' },
  info: { icon: PhInfo, bg: 'bg-info/15', text: 'text-info' },
  call: { icon: PhBellRinging, bg: 'bg-gold/20 pulse-gold', text: 'text-gold' },
}[t] || { icon: PhInfo, bg: 'bg-surface-2', text: 'text-ink-2' })
</script>
