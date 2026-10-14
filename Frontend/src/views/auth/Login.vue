<template>
  <div ref="root" class="min-h-[100dvh] relative overflow-hidden grid place-items-center px-4 py-10 bg-bg grain">
    <!-- Fondo: resplandor cálido + ramas botánicas que se dibujan -->
    <div class="absolute inset-0 pointer-events-none">
      <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[900px] h-[600px] rounded-full blur-3xl opacity-40"
           style="background: radial-gradient(closest-side, rgb(var(--gold) / .35), transparent)" />
      <Botanical class="branch absolute -left-10 bottom-0 w-64 sm:w-80 text-gold/25 -rotate-12" />
      <Botanical class="branch absolute -right-12 top-0 w-56 sm:w-72 text-sage/25 rotate-[160deg]" />
      <Botanical class="branch absolute right-[12%] bottom-[-60px] w-40 text-gold/15 rotate-[24deg] hidden md:block" />
    </div>

    <div class="relative w-full max-w-[420px]">
      <div class="text-center mb-7">
        <BrandLogo :size="132" class="logo mx-auto drop-shadow-[0_10px_30px_rgba(0,0,0,.5)]" />
        <h1 class="title font-serif text-[2.6rem] leading-none mt-3 gold-text">El Cuache</h1>
        <p class="subtitle eyebrow mt-2 text-gold/80">Café · Restaurante · Sistema de gestión</p>
      </div>

      <form class="card-in card glass p-6 sm:p-8 shadow-lift" @submit.prevent="enviar" novalidate>
        <h2 class="font-serif text-2xl text-ink">Bienvenido de vuelta</h2>
        <p class="text-sm text-ink-3 mt-1 mb-6">Ingresa con tu usuario del equipo.</p>

        <div v-if="expirada && !error" class="mb-4 chip chip-info h-auto py-2 px-3 rounded-xl w-full whitespace-normal">
          <PhClock :size="15" /> Tu sesión expiró. Vuelve a ingresar.
        </div>

        <!-- Bloqueo temporal -->
        <div v-if="bloqueo > 0" class="mb-5 rounded-2xl border border-warn/40 bg-warn/10 p-4 flex items-center gap-4" role="alert">
          <svg viewBox="0 0 36 36" class="w-12 h-12 -rotate-90 shrink-0">
            <circle cx="18" cy="18" r="15" fill="none" stroke="rgb(var(--warn) / .2)" stroke-width="3" />
            <circle cx="18" cy="18" r="15" fill="none" stroke="rgb(var(--warn))" stroke-width="3" stroke-linecap="round"
                    :stroke-dasharray="`${(bloqueo / bloqueoTotal) * 94.2} 94.2`" class="transition-all duration-1000 ease-linear" />
          </svg>
          <div>
            <p class="font-bold text-warn">Demasiados intentos</p>
            <p class="text-sm text-ink-2">Espera <b class="num text-ink">{{ bloqueo }}</b> segundos para volver a intentar.</p>
          </div>
        </div>
        <div v-else-if="error" ref="errEl" class="mb-5 rounded-2xl border border-danger/40 bg-danger/10 p-3.5 flex items-start gap-3" role="alert">
          <PhWarningCircle :size="20" class="text-danger shrink-0 mt-0.5" />
          <div class="text-sm">
            <p class="font-bold text-danger">{{ error }}</p>
            <p v-if="restantes != null && restantes <= 3" class="text-ink-3 mt-0.5">Te quedan {{ restantes }} intento{{ restantes === 1 ? '' : 's' }} antes del bloqueo temporal.</p>
          </div>
        </div>

        <label class="label" for="u">Usuario</label>
        <div class="relative mb-4">
          <PhUser :size="18" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-3" />
          <input id="u" v-model.trim="usuario" class="input pl-10 h-12" :class="error && !bloqueo ? 'input-error' : ''"
                 autocomplete="username" autocapitalize="none" placeholder="tu.usuario" :disabled="bloqueo > 0" />
        </div>

        <label class="label" for="p">Contraseña</label>
        <div class="relative mb-6">
          <PhLockKey :size="18" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-3" />
          <input id="p" v-model="password" :type="ver ? 'text' : 'password'" class="input pl-10 pr-11 h-12"
                 :class="error && !bloqueo ? 'input-error' : ''" autocomplete="current-password" placeholder="••••••••" :disabled="bloqueo > 0" />
          <button type="button" class="absolute right-1.5 top-1/2 -translate-y-1/2 btn btn-ghost btn-sm btn-icon" :aria-label="ver ? 'Ocultar contraseña' : 'Mostrar contraseña'" @click="ver = !ver">
            <component :is="ver ? PhEyeSlash : PhEye" :size="18" />
          </button>
        </div>

        <button ref="btnEl" type="submit" class="btn btn-gold btn-lg w-full" :disabled="cargando || bloqueo > 0 || !usuario || !password">
          <span v-if="cargando" class="w-5 h-5 border-2 border-on-gold/40 border-t-on-gold rounded-full animate-spin" />
          <template v-else>Ingresar <PhArrowRight :size="18" weight="bold" /></template>
        </button>

        <div class="mt-6 pt-5 border-t border-line/60 grid grid-cols-2 gap-2">
          <router-link to="/kiosco" class="btn btn-soft btn-sm"><PhFingerprint :size="16" /> Marcar asistencia</router-link>
          <router-link to="/carta" class="btn btn-soft btn-sm"><PhBookOpenText :size="16" /> Ver la carta</router-link>
        </div>
        <InstalarApp :clase="'btn btn-ghost btn-sm w-full mt-2 text-gold'" />
      </form>

      <p class="text-center text-[11px] text-ink-3 mt-6">© {{ new Date().getFullYear() }} El Cuache · Café y Restaurante</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { PhUser, PhLockKey, PhEye, PhEyeSlash, PhArrowRight, PhWarningCircle, PhClock, PhFingerprint, PhBookOpenText } from '@phosphor-icons/vue'
import BrandLogo from '@/components/ui/BrandLogo.vue'
import Botanical from '@/components/ui/Botanical.vue'
import InstalarApp from '@/components/pwa/InstalarApp.vue'
import { useAuthStore } from '@/stores/auth'
import { gsap, reduced, magnetic } from '@/composables/useMotion'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const root = ref(null), errEl = ref(null), btnEl = ref(null)

const usuario = ref('')
const password = ref('')
const ver = ref(false)
const cargando = ref(false)
const error = ref('')
const restantes = ref(null)
const bloqueo = ref(0)
const bloqueoTotal = ref(60)
const expirada = route.query.expirada === '1'
let timer = null
let unMag = () => {}

const iniciarBloqueo = (seg) => {
  bloqueo.value = seg
  bloqueoTotal.value = seg
  clearInterval(timer)
  timer = setInterval(() => {
    bloqueo.value = Math.max(0, bloqueo.value - 1)
    if (!bloqueo.value) { clearInterval(timer); error.value = '' }
  }, 1000)
}

const enviar = async () => {
  if (cargando.value || bloqueo.value) return
  cargando.value = true
  error.value = ''
  try {
    await auth.login(usuario.value, password.value)
    const destino = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : auth.home
    if (!reduced()) await gsap.to('.card-in', { y: -14, opacity: 0, duration: .35, ease: 'power2.in' })
    router.replace(destino)
  } catch (e) {
    if (e.status === 429) iniciarBloqueo(e.payload.retry_after || 60)
    error.value = e.message
    restantes.value = e.payload?.intentos_restantes ?? null
    password.value = ''
    if (!reduced()) gsap.fromTo('.card-in', { x: -10 }, { x: 0, duration: .5, ease: 'elastic.out(1,.3)' })
  } finally {
    cargando.value = false
  }
}

onMounted(() => {
  unMag = magnetic(btnEl.value, .12)
  if (reduced()) return
  // Las ramas se "dibujan" trazo a trazo
  root.value.querySelectorAll('.branch path, .branch ellipse').forEach((p) => {
    const len = p.getTotalLength?.() || 300
    gsap.fromTo(p, { strokeDasharray: len, strokeDashoffset: len }, { strokeDashoffset: 0, duration: 2.4, ease: 'power2.inOut', delay: Math.random() * .6 })
  })
  gsap.to('.branch', { y: '+=10', rotation: '+=2', duration: 6, ease: 'sine.inOut', yoyo: true, repeat: -1, stagger: 1.5 })
  const tl = gsap.timeline({ defaults: { ease: 'power3.out' } })
  tl.from('.logo', { scale: .6, opacity: 0, rotate: -20, duration: 1.1, ease: 'back.out(1.6)' })
    .from('.title', { y: 20, opacity: 0, duration: .7 }, '-=.6')
    .from('.subtitle', { opacity: 0, letterSpacing: '.5em', duration: .8 }, '-=.5')
    .from('.card-in', { y: 40, opacity: 0, duration: .8 }, '-=.5')
})
onUnmounted(() => { clearInterval(timer); unMag() })
</script>
