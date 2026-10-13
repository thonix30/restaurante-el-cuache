<template>
  <div class="min-h-[100dvh] flex bg-bg grain" :style="{ '--sb': collapsed ? '84px' : '16rem' }">
    <!-- ═══ Barra lateral (escritorio) ═══ -->
    <aside class="hidden lg:flex flex-col sticky top-0 h-[100dvh] shrink-0 border-r border-line/60 bg-surface/60 transition-[width] duration-300 z-30"
           :class="collapsed ? 'w-[84px]' : 'w-64'">
      <div class="flex items-center gap-3 px-4 h-20 shrink-0">
        <router-link :to="auth.home" class="flex items-center gap-3 min-w-0" aria-label="Inicio">
          <BrandLogo :size="48" class="shrink-0 drop-shadow" />
          <div v-if="!collapsed" class="min-w-0 leading-tight">
            <p class="font-serif text-xl text-ink">El Cuache</p>
            <p class="text-[10px] uppercase tracking-[.2em] text-gold">Café · Restaurante</p>
          </div>
        </router-link>
      </div>

      <nav class="flex-1 overflow-y-auto px-3 pb-4 scrollbar-none" aria-label="Principal">
        <div v-for="s in nav" :key="s.section" class="mt-4 first:mt-1">
          <p v-if="!collapsed" class="eyebrow px-3 mb-1.5 text-[10px]">{{ s.section }}</p>
          <div v-else class="mx-auto my-2 w-6 h-px bg-line" />
          <router-link v-for="i in s.items" :key="i.to" :to="i.to" v-slot="{ isActive }" custom>
            <a :href="'#' + i.to" @click.prevent="router.push(i.to)" :title="collapsed ? i.label : null"
               class="relative flex items-center gap-3 h-11 rounded-xl px-3 text-sm font-semibold transition group"
               :class="isActive || route.path.startsWith(i.to + '/') ? 'bg-gold/15 text-ink' : 'text-ink-3 hover:text-ink hover:bg-surface-2'">
              <span v-if="isActive || route.path.startsWith(i.to + '/')" class="absolute left-0 top-2.5 bottom-2.5 w-[3px] rounded-r bg-gold" />
              <component :is="i.icon" :size="21" :weight="isActive ? 'fill' : 'regular'" :class="isActive ? 'text-gold' : ''" class="shrink-0" />
              <span v-if="!collapsed" class="truncate flex-1">{{ i.label }}</span>
              <span v-if="badge(i.badge)" class="num text-[10px] font-bold rounded-full min-w-[20px] h-5 px-1.5 grid place-items-center"
                    :class="[collapsed ? 'absolute top-1 right-1' : '', i.badge === 'avisos' ? 'bg-gold text-on-gold' : 'bg-surface-3 text-ink-2']">{{ badge(i.badge) }}</span>
            </a>
          </router-link>
        </div>
      </nav>

      <div class="p-3 border-t border-line/60">
        <div class="flex items-center gap-3 p-2 rounded-xl" :class="collapsed ? 'justify-center' : ''">
          <Avatar :src="auth.usuario?.foto" :name="auth.usuario?.nombre" :puesto="auth.usuario?.puesto" :size="38" dot="ok" />
          <div v-if="!collapsed" class="min-w-0 flex-1 leading-tight">
            <p class="text-sm font-bold text-ink truncate">{{ auth.usuario?.nombre }}</p>
            <p class="text-[11px] text-ink-3">{{ rolLabel }}</p>
          </div>
          <button v-if="!collapsed" class="btn btn-ghost btn-sm btn-icon" title="Cerrar sesión" @click="salir"><PhSignOut :size="18" /></button>
        </div>
        <button class="btn btn-ghost btn-sm w-full mt-1 text-ink-3" @click="collapsed = !collapsed" :aria-label="collapsed ? 'Expandir menú' : 'Contraer menú'">
          <component :is="collapsed ? PhCaretDoubleRight : PhCaretDoubleLeft" :size="16" />
          <span v-if="!collapsed">Contraer</span>
        </button>
      </div>
    </aside>

    <!-- ═══ Contenido ═══ -->
    <div class="flex-1 min-w-0 flex flex-col relative z-10">
      <header class="sticky top-0 z-20 glass border-b border-line/50">
        <div class="flex items-center gap-2 sm:gap-3 h-16 px-4 sm:px-6">
          <router-link :to="auth.home" class="lg:hidden shrink-0" aria-label="Inicio"><BrandLogo :size="40" /></router-link>
          <div class="min-w-0 flex-1">
            <p class="font-serif text-lg sm:text-xl text-ink truncate leading-tight">{{ route.meta.title }}</p>
            <p class="text-[11px] text-ink-3 hidden sm:block first-letter:uppercase">{{ hoy }}</p>
          </div>

          <span v-if="!rt.online" class="chip chip-danger hidden sm:inline-flex"><PhWifiSlash :size="13" /> Sin conexión</span>
          <router-link v-else-if="!rt.cajaAbierta && auth.can('Admin', 'Mesero')" :to="auth.isAdmin ? '/caja' : route.path"
                       class="chip chip-warn hidden sm:inline-flex"><PhLockKey :size="13" /> Caja cerrada</router-link>

          <button class="btn btn-ghost btn-icon hidden sm:inline-flex" :title="soundEnabled ? 'Silenciar avisos' : 'Activar sonido de avisos'" @click="toggleSound">
            <component :is="soundEnabled ? PhSpeakerHigh : PhSpeakerSlash" :size="20" />
          </button>
          <button class="btn btn-ghost btn-icon hidden sm:inline-flex" :title="ui.theme === 'dark' ? 'Modo claro' : 'Modo oscuro'" @click="ui.toggleTheme">
            <component :is="ui.theme === 'dark' ? PhSun : PhMoonStars" :size="20" />
          </button>
          <button v-if="auth.can('Admin', 'Mesero')" ref="bellEl" class="btn btn-soft btn-icon relative" aria-label="Avisos" @click="trayOpen = true">
            <PhBell :size="20" :weight="rt.avisosCount ? 'fill' : 'regular'" :class="rt.avisosCount ? 'text-gold' : ''" />
            <span v-if="rt.avisosCount" class="absolute -top-1.5 -right-1.5 num min-w-[20px] h-5 px-1 rounded-full bg-gold text-on-gold text-[11px] font-bold grid place-items-center ring-2 ring-bg pulse-gold">{{ rt.avisosCount }}</span>
          </button>
          <button class="lg:hidden" @click="router.push('/perfil')" aria-label="Mi perfil">
            <Avatar :src="auth.usuario?.foto" :name="auth.usuario?.nombre" :puesto="auth.usuario?.puesto" :size="36" />
          </button>
        </div>
      </header>

      <main ref="mainEl" class="flex-1 pb-28 lg:pb-10" :class="route.meta.full ? 'px-3 sm:px-5 pt-4' : 'px-4 sm:px-6 lg:px-10 pt-6 lg:pt-8'">
        <router-view v-slot="{ Component }">
          <component :is="Component" :key="route.path" />
        </router-view>
      </main>
    </div>

    <!-- ═══ Navegación inferior (celular) ═══ -->
    <nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 glass border-t border-line/60 safe-bottom" aria-label="Navegación">
      <div class="grid h-[68px]" :style="{ gridTemplateColumns: `repeat(${mobileNav.length + 1}, minmax(0,1fr))` }">
        <router-link v-for="i in mobileNav" :key="i.to" :to="i.to" v-slot="{ isActive }" custom>
          <a :href="'#' + i.to" @click.prevent="router.push(i.to)" class="relative flex flex-col items-center justify-center gap-1 text-[11px] font-bold transition"
             :class="isActive ? 'text-gold' : 'text-ink-3'">
            <span class="relative grid place-items-center w-12 h-8 rounded-full transition" :class="isActive ? 'bg-gold/15' : ''">
              <component :is="i.icon" :size="22" :weight="isActive ? 'fill' : 'regular'" />
              <span v-if="badge(i.badge)" class="absolute -top-1 right-0.5 num min-w-[18px] h-[18px] px-1 rounded-full bg-gold text-on-gold text-[10px] grid place-items-center">{{ badge(i.badge) }}</span>
            </span>
            {{ i.short || i.label }}
          </a>
        </router-link>
        <button class="flex flex-col items-center justify-center gap-1 text-[11px] font-bold text-ink-3" @click="moreOpen = true">
          <span class="grid place-items-center w-12 h-8"><PhDotsNine :size="22" /></span> Más
        </button>
      </div>
    </nav>

    <!-- Menú completo (celular) -->
    <Modal v-model="moreOpen" title="Menú" :subtitle="auth.usuario?.nombre + ' · ' + rolLabel">
      <div v-for="s in nav" :key="s.section" class="mb-5">
        <p class="eyebrow mb-2">{{ s.section }}</p>
        <div class="grid grid-cols-3 gap-2">
          <button v-for="i in s.items" :key="i.to" class="card p-3 flex flex-col items-center gap-2 text-center active:scale-95 transition"
                  :class="route.path === i.to ? 'border-gold/60' : ''" @click="go(i.to)">
            <component :is="i.icon" :size="24" class="text-gold" />
            <span class="text-xs font-bold text-ink leading-tight">{{ i.label }}</span>
          </button>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-2 mb-3">
        <button class="btn btn-soft" @click="toggleSound"><component :is="soundEnabled ? PhSpeakerHigh : PhSpeakerSlash" :size="18" /> {{ soundEnabled ? 'Sonido activo' : 'Sin sonido' }}</button>
        <button class="btn btn-soft" @click="ui.toggleTheme"><component :is="ui.theme === 'dark' ? PhSun : PhMoonStars" :size="18" /> {{ ui.theme === 'dark' ? 'Modo claro' : 'Modo oscuro' }}</button>
      </div>
      <button class="btn btn-danger w-full" @click="salir"><PhSignOut :size="18" /> Cerrar sesión</button>
    </Modal>

    <!-- Bandeja de avisos -->
    <SidePanel v-model="trayOpen" title="Avisos" eyebrow="En vivo" subtitle="Llamados de mesas y platillos listos">
      <AvisosList />
    </SidePanel>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  PhSignOut, PhCaretDoubleLeft, PhCaretDoubleRight, PhBell, PhSun, PhMoonStars, PhSpeakerHigh, PhSpeakerSlash,
  PhDotsNine, PhWifiSlash, PhLockKey,
} from '@phosphor-icons/vue'
import BrandLogo from '@/components/ui/BrandLogo.vue'
import Avatar from '@/components/ui/Avatar.vue'
import Modal from '@/components/ui/Modal.vue'
import SidePanel from '@/components/ui/SidePanel.vue'
import AvisosList from './AvisosList.vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useRealtimeStore } from '@/stores/realtime'
import { navFor, mobileNavFor } from '@/router/nav'
import { soundEnabled, toggleSound } from '@/utils/sound'
import { revealIn } from '@/composables/useMotion'
import { fechaLarga } from '@/utils/format'

const auth = useAuthStore()
const ui = useUiStore()
const rt = useRealtimeStore()
const route = useRoute()
const router = useRouter()

let savedCollapsed = false
try { savedCollapsed = localStorage.getItem('cuache_menu') === '1' } catch { /* noop */ }
const collapsed = ref(savedCollapsed)
watch(collapsed, (v) => { try { localStorage.setItem('cuache_menu', v ? '1' : '0') } catch { /* noop */ } })

const trayOpen = ref(false)
const moreOpen = ref(false)
const mainEl = ref(null)
const nav = computed(() => navFor(auth.rol))
const mobileNav = computed(() => mobileNavFor(auth.rol))
const rolLabel = computed(() => ({ Admin: 'Administrador', Mesero: 'Mesero', Cocinero: 'Cocina' }[auth.rol] || ''))
const hoy = fechaLarga(new Date())

const badge = (k) => ({
  avisos: rt.avisosCount,
  cocina: rt.comandasPendientes,
  stock: rt.stockBajo,
  solicitudes: rt.solicitudesPendientes,
}[k] || 0)

const go = (to) => { moreOpen.value = false; router.push(to) }
const salir = async () => {
  moreOpen.value = false
  rt.stop()
  await auth.logout()
  router.replace('/login')
}

// Entrada animada de cada pantalla (elementos con data-reveal)
watch(() => route.path, async () => { await nextTick(); setTimeout(() => revealIn(mainEl.value), 30) })
onMounted(() => { rt.start(); setTimeout(() => revealIn(mainEl.value), 60) })
onUnmounted(() => rt.stop())
</script>
