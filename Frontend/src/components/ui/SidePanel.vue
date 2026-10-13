<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="modelValue" class="fixed inset-0 z-[110] bg-black/50 backdrop-blur-[2px]" @click="close" />
    </Transition>
    <Transition name="side">
      <aside v-if="modelValue" class="fixed z-[111] top-0 right-0 h-[100dvh] w-full bg-surface border-l border-line/80 shadow-lift flex flex-col"
             :class="widthClass" role="dialog" aria-modal="true" :aria-label="title">
        <header class="flex items-start gap-3 px-5 sm:px-6 pt-5 pb-4 border-b border-line/60">
          <div class="flex-1 min-w-0">
            <slot name="header">
              <p v-if="eyebrow" class="eyebrow mb-1">{{ eyebrow }}</p>
              <h2 class="font-serif text-2xl text-ink leading-tight">{{ title }}</h2>
              <p v-if="subtitle" class="text-sm text-ink-3 mt-1">{{ subtitle }}</p>
            </slot>
          </div>
          <button class="btn btn-ghost btn-icon -mr-2" aria-label="Cerrar" @click="close"><PhX :size="20" /></button>
        </header>
        <div class="flex-1 overflow-y-auto px-5 sm:px-6 py-5"><slot /></div>
        <footer v-if="$slots.footer" class="px-5 sm:px-6 py-4 border-t border-line/60 safe-bottom"><slot name="footer" /></footer>
      </aside>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue'
import { PhX } from '@phosphor-icons/vue'

// Panel lateral (formularios de edición, detalle de mesa)
const props = defineProps({ modelValue: Boolean, title: String, subtitle: String, eyebrow: String, size: { type: String, default: 'md' } })
const emit = defineEmits(['update:modelValue', 'close'])
const widthClass = computed(() => ({ sm: 'sm:max-w-sm', md: 'sm:max-w-md', lg: 'sm:max-w-xl', xl: 'sm:max-w-2xl' }[props.size]))
const close = () => { emit('update:modelValue', false); emit('close') }
const onKey = (e) => { if (e.key === 'Escape' && props.modelValue) close() }
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>
