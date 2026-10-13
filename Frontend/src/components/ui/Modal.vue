<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="modelValue" class="fixed inset-0 z-[120] bg-black/60 backdrop-blur-sm" @click="close" />
    </Transition>
    <Transition :name="isMobile ? 'sheet' : 'pop'">
      <div v-if="modelValue" class="fixed z-[121] inset-x-0 bottom-0 sm:inset-0 sm:grid sm:place-items-center sm:p-6 pointer-events-none"
           role="dialog" aria-modal="true" :aria-label="title">
        <div class="pointer-events-auto bg-surface border border-line/80 shadow-lift flex flex-col max-h-[92dvh]
                    rounded-t-3xl sm:rounded-3xl w-full" :class="widthClass">
          <div class="sm:hidden mx-auto mt-2.5 h-1.5 w-12 rounded-full bg-line" />
          <header v-if="title || $slots.header" class="flex items-start gap-3 px-5 sm:px-6 pt-4 sm:pt-6 pb-3">
            <div class="flex-1 min-w-0">
              <slot name="header">
                <p v-if="eyebrow" class="eyebrow mb-1">{{ eyebrow }}</p>
                <h2 class="font-serif text-xl sm:text-2xl text-ink leading-tight">{{ title }}</h2>
                <p v-if="subtitle" class="text-sm text-ink-3 mt-1">{{ subtitle }}</p>
              </slot>
            </div>
            <button class="btn btn-ghost btn-icon -mr-2 -mt-1" aria-label="Cerrar" @click="close"><PhX :size="20" /></button>
          </header>
          <div class="px-5 sm:px-6 pb-5 overflow-y-auto flex-1"><slot /></div>
          <footer v-if="$slots.footer" class="px-5 sm:px-6 py-4 border-t border-line/60 flex gap-2 justify-end safe-bottom">
            <slot name="footer" />
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { PhX } from '@phosphor-icons/vue'

// Centrado en escritorio, hoja inferior en celular
const props = defineProps({
  modelValue: Boolean,
  title: String,
  subtitle: String,
  eyebrow: String,
  size: { type: String, default: 'md' },
  persistent: Boolean,
})
const emit = defineEmits(['update:modelValue', 'close'])
const isMobile = ref(window.innerWidth < 640)
const widthClass = computed(() => ({ sm: 'sm:max-w-md', md: 'sm:max-w-lg', lg: 'sm:max-w-2xl', xl: 'sm:max-w-4xl' }[props.size]))
const close = () => { if (props.persistent) return; emit('update:modelValue', false); emit('close') }
const onKey = (e) => { if (e.key === 'Escape' && props.modelValue) close() }
const onResize = () => { isMobile.value = window.innerWidth < 640 }
watch(() => props.modelValue, (v) => { document.documentElement.style.overflow = v ? 'hidden' : '' })
onMounted(() => { window.addEventListener('keydown', onKey); window.addEventListener('resize', onResize) })
onUnmounted(() => { window.removeEventListener('keydown', onKey); window.removeEventListener('resize', onResize); document.documentElement.style.overflow = '' })
</script>

<style scoped>
.pop-enter-active, .pop-leave-active { transition: transform .35s cubic-bezier(.2,.9,.25,1.15), opacity .25s; }
.pop-enter-from, .pop-leave-to { transform: translateY(14px) scale(.97); opacity: 0; }
</style>
