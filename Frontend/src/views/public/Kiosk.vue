<template>
  <div ref="root" class="min-h-[100dvh] bg-bg text-ink grain relative overflow-hidden select-none">
    <div class="absolute inset-0 pointer-events-none">
      <div class="absolute -top-60 -left-40 w-[700px] h-[700px] rounded-full blur-3xl opacity-30" style="background: radial-gradient(closest-side, rgb(var(--gold) / .4), transparent)" />
      <Botanical class="absolute -right-16 -bottom-10 w-80 text-gold/15 rotate-12" />
    </div>

    <div class="relative z-10 min-h-[100dvh] grid grid-cols-1 lg:grid-cols-[1.05fr_1fr]">
      <!-- ═══ Reloj ═══ -->
      <section class="flex flex-col justify-between p-6 sm:p-10 lg:p-14 lg:border-r border-line/50">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <BrandLogo :size="60" />
            <div>
              <p class="font-serif text-xl">El Cuache</p>
              <p class="eyebrow text-[10px]">Kiosco de asistencia</p>
            </div>
          </div>
          <router-link to="/login" class="btn btn-ghost btn-sm text-ink-3"><PhSignIn :size="16" /> Sistema</router-link>
        </div>

        <div class="py-10 lg:py-0">
          <p class="clock num font-semibold leading-none gold-text text-[clamp(4.5rem,14vw,10rem)] tracking-tighter">
            {{ horaTxt }}<span class="text-[.4em] align-top ml-2 text-gold/70">{{ segTxt }}</span>
          </p>
          <p class="font-serif text-2xl sm:text-3xl text-ink-2 mt-4 first-letter:uppercase">{{ fechaTxt }}</p>
        </div>

        <p class="text-sm text-ink-3 hidden lg:flex items-center gap-2"><PhInfo :size="16" /> Escribe tu PIN de 4 dígitos para registrar tu entrada, descansos o salida.</p>
      </section>

      <!-- ═══ PIN / acciones ═══ -->
      <section class="flex items-center justify-center p-6 sm:p-10">
        <Transition name="fade" mode="out-in">
          <!-- Teclado -->
          <div v-if="!empleado" key="pin" class="w-full max-w-sm">
            <h1 class="font-serif text-3xl text-center">Marca tu asistencia</h1>
            <p class="text-ink-3 text-center mt-1">Ingresa tu PIN</p>
            <div ref="dotsEl" class="flex justify-center gap-4 my-8" aria-live="polite" :aria-label="`${pin.length} de 4 dígitos`">
              <span v-for="i in 4" :key="i" class="w-5 h-5 rounded-full border-2 transition-all duration-200"
                    :class="i <= pin.length ? 'bg-gold border-gold scale-110 shadow-glow' : 'border-line'" />
            </div>
            <p v-if="mensaje" class="text-center text-sm font-bold mb-4" :class="bloqueo ? 'text-warn' : 'text-danger'">{{ mensaje }}</p>
            <div class="grid grid-cols-3 gap-3">
              <button v-for="n in [1,2,3,4,5,6,7,8,9]" :key="n" class="key" :disabled="cargando || bloqueo > 0" @click="tecla(String(n))">{{ n }}</button>
              <button class="key text-base text-ink-3" :disabled="!pin" @click="pin = ''">Borrar</button>
              <button class="key" :disabled="cargando || bloqueo > 0" @click="tecla('0')">0</button>
              <button class="key text-ink-3" :disabled="!pin" aria-label="Borrar un dígito" @click="pin = pin.slice(0, -1)"><PhBackspace :size="28" class="mx-auto" /></button>
            </div>
          </div>

          <!-- Acciones del empleado -->
          <div v-else key="acciones" class="w-full max-w-md">
            <div class="flex items-center gap-4">
              <Avatar :src="empleado.empleado.foto" :name="empleado.empleado.nombre_completo" :puesto="empleado.empleado.puesto" :size="72" />
              <div>
                <h1 class="font-serif text-4xl leading-none">Hola, {{ empleado.empleado.nombre }}</h1>
                <p class="text-ink-3 mt-1.5">{{ empleado.empleado.puesto }}</p>
              </div>
            </div>
            <div class="card p-4 mt-6 flex items-center gap-3">
              <PhCalendarCheck :size="22" class="text-gold" />
              <p v-if="empleado.turno_hoy" class="text-sm">Tu turno de hoy: <b class="num text-ink">{{ empleado.turno_hoy.hora_inicio }} – {{ empleado.turno_hoy.hora_fin }}</b></p>
              <p v-else class="text-sm text-ink-3">Hoy no tienes turno programado.</p>
            </div>
            <p v-if="empleado.ultima" class="text-xs text-ink-3 mt-2 ml-1">Última marca: {{ etiquetas[empleado.ultima.tipo] }} a las <span class="num">{{ hora(empleado.ultima.marcado_en) }}</span></p>

            <div class="grid grid-cols-2 gap-3 mt-6">
              <button v-for="a in acciones" :key="a.tipo" class="accion" :class="permitida(a.tipo) ? a.cls : 'opacity-35'"
                      :disabled="!permitida(a.tipo) || cargando" @click="marcar(a.tipo)">
                <component :is="a.icon" :size="34" weight="duotone" />
                <span>{{ a.label }}</span>
              </button>
            </div>

            <div class="flex items-center justify-between mt-6">
              <label class="flex items-center gap-3 text-sm text-ink-2 cursor-pointer">
                <button type="button" class="switch" :class="{ on: conFoto }" role="switch" :aria-checked="conFoto" @click="toggleFoto" />
                Foto como comprobante
              </label>
              <button class="btn btn-ghost btn-sm" @click="reset">No soy yo</button>
            </div>
            <video v-show="conFoto && camOk" ref="video" autoplay playsinline muted class="mt-4 w-full aspect-video object-cover rounded-2xl border border-line -scale-x-100" />
            <p v-if="conFoto && !camOk" class="text-xs text-ink-3 mt-3">No pudimos acceder a la cámara; se marcará sin foto.</p>
          </div>
        </Transition>
      </section>
    </div>

    <!-- ═══ Confirmación a pantalla completa ═══ -->
    <Transition name="fade">
      <div v-if="resultado" class="fixed inset-0 z-50 bg-bg/97 backdrop-blur grid place-items-center text-center px-6" @click="reset">
        <div>
          <svg viewBox="0 0 120 120" class="w-40 h-40 mx-auto">
            <circle class="k-circle" cx="60" cy="60" r="52" fill="none" stroke="rgb(var(--ok))" stroke-width="4" stroke-linecap="round" />
            <path class="k-check" d="M38 62 L54 77 L84 45" fill="none" stroke="rgb(var(--ok))" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <p class="eyebrow mt-6">{{ resultado.accion }} registrada</p>
          <p class="num text-7xl font-semibold gold-text mt-2">{{ hora(resultado.marcado_en) }}</p>
          <p class="font-serif text-3xl mt-4">{{ despedida }}, {{ resultado.empleado.nombre }}</p>
          <p v-if="resultado.detalle" class="mt-2 text-ink-2">{{ resultado.detalle }}</p>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { PhSignIn, PhInfo, PhBackspace, PhCalendarCheck, PhSignOut, PhCoffee, PhArrowCounterClockwise, PhDoorOpen } from '@phosphor-icons/vue'
import BrandLogo from '@/components/ui/BrandLogo.vue'
import Botanical from '@/components/ui/Botanical.vue'
import Avatar from '@/components/ui/Avatar.vue'
import { publicService } from '@/services'
import { hora } from '@/utils/format'
import { capturarFrame } from '@/utils/image'
import { gsap, reduced } from '@/composables/useMotion'

const root = ref(null), dotsEl = ref(null), video = ref(null)
const ahora = ref(new Date())
const pin = ref('')
const empleado = ref(null)
const cargando = ref(false)
const mensaje = ref('')
const bloqueo = ref(0)
const resultado = ref(null)
const conFoto = ref(false)
const camOk = ref(false)
let stream = null, tick = null, idle = null, bloqTimer = null

const etiquetas = { entrada: 'Entrada', inicio_descanso: 'Inicio de descanso', fin_descanso: 'Fin de descanso', salida: 'Salida' }
const acciones = [
  { tipo: 'entrada', label: 'Entrada', icon: PhDoorOpen, cls: 'accion-ok' },
  { tipo: 'inicio_descanso', label: 'Iniciar descanso', icon: PhCoffee, cls: 'accion-gold' },
  { tipo: 'fin_descanso', label: 'Terminar descanso', icon: PhArrowCounterClockwise, cls: 'accion-gold' },
  { tipo: 'salida', label: 'Salida', icon: PhSignOut, cls: 'accion-danger' },
]
const permitida = (t) => empleado.value?.permitidas.includes(t)
const horaTxt = computed(() => ahora.value.toLocaleTimeString('es-GT', { hour: '2-digit', minute: '2-digit', hour12: false }))
const segTxt = computed(() => String(ahora.value.getSeconds()).padStart(2, '0'))
const fechaTxt = computed(() => ahora.value.toLocaleDateString('es-GT', { weekday: 'long', day: 'numeric', month: 'long' }))
const despedida = computed(() => ({ entrada: '¡Buen turno', inicio_descanso: 'Buen provecho', fin_descanso: '¡De vuelta', salida: 'Hasta pronto' }[resultado.value?.tipo] || 'Listo'))

const shake = () => !reduced() && gsap.fromTo(dotsEl.value, { x: -14 }, { x: 0, duration: .6, ease: 'elastic.out(1,.25)' })

const tecla = async (d) => {
  if (pin.value.length >= 4) return
  mensaje.value = ''
  pin.value += d
  if (pin.value.length === 4) await identificar()
}

const identificar = async () => {
  cargando.value = true
  try {
    empleado.value = await publicService.kioscoIdentificar(pin.value)
    armarIdle()
  } catch (e) {
    mensaje.value = e.message
    if (e.status === 429) iniciarBloqueo(e.payload.retry_after)
    pin.value = ''
    shake()
  } finally { cargando.value = false }
}

const iniciarBloqueo = (s) => {
  bloqueo.value = s
  clearInterval(bloqTimer)
  bloqTimer = setInterval(() => {
    bloqueo.value--
    mensaje.value = bloqueo.value > 0 ? `Demasiados intentos. Espera ${bloqueo.value} segundos.` : ''
    if (bloqueo.value <= 0) clearInterval(bloqTimer)
  }, 1000)
}

const toggleFoto = async () => {
  conFoto.value = !conFoto.value
  if (conFoto.value) {
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 640 } })
      camOk.value = true
      await nextTick()
      video.value.srcObject = stream
    } catch { camOk.value = false }
  } else apagarCam()
}
const apagarCam = () => { stream?.getTracks().forEach(t => t.stop()); stream = null; camOk.value = false }

const marcar = async (tipo) => {
  cargando.value = true
  try {
    const foto = conFoto.value && camOk.value && video.value?.videoWidth ? capturarFrame(video.value) : null
    resultado.value = await publicService.kioscoMarcar(pin.value, tipo, foto)
    await nextTick()
    if (!reduced()) {
      gsap.fromTo('.k-circle', { strokeDasharray: 330, strokeDashoffset: 330 }, { strokeDashoffset: 0, duration: .8, ease: 'power2.out' })
      gsap.fromTo('.k-check', { strokeDasharray: 80, strokeDashoffset: 80 }, { strokeDashoffset: 0, duration: .5, delay: .55, ease: 'back.out(2)' })
    }
    clearTimeout(idle)
    idle = setTimeout(reset, 4500)
  } catch (e) {
    mensaje.value = e.message
    if (e.status === 409) empleado.value = await publicService.kioscoIdentificar(pin.value).catch(() => empleado.value)
  } finally { cargando.value = false }
}

// Por privacidad, la pantalla vuelve al teclado si nadie la toca
const armarIdle = () => { clearTimeout(idle); idle = setTimeout(reset, 25000) }
const reset = () => {
  clearTimeout(idle)
  resultado.value = null
  empleado.value = null
  pin.value = ''
  mensaje.value = ''
  if (conFoto.value) { conFoto.value = false; apagarCam() }
}

const onKey = (e) => {
  if (empleado.value || resultado.value) return
  if (/^\d$/.test(e.key)) tecla(e.key)
  if (e.key === 'Backspace') pin.value = pin.value.slice(0, -1)
}

onMounted(() => {
  tick = setInterval(() => { ahora.value = new Date() }, 1000)
  window.addEventListener('keydown', onKey)
  if (!reduced()) {
    gsap.from('.clock', { y: 30, opacity: 0, duration: 1, ease: 'power3.out' })
    gsap.from('.key', { scale: .8, opacity: 0, duration: .5, stagger: .03, ease: 'back.out(2)', delay: .2, clearProps: 'transform,opacity' })
  }
})
onUnmounted(() => { clearInterval(tick); clearTimeout(idle); clearInterval(bloqTimer); apagarCam(); window.removeEventListener('keydown', onKey) })
</script>

<style scoped>
.key {
  @apply h-20 rounded-2xl bg-surface border border-line text-3xl font-semibold text-ink transition-colors active:bg-surface-3 disabled:opacity-40;
  font-family: "JetBrains Mono", monospace;
  box-shadow: 0 1px 0 rgb(255 255 255 / .04) inset, 0 8px 20px -14px rgb(0 0 0 / .6);
}
.key:hover:not(:disabled) { @apply border-gold/50; }
.accion { @apply h-32 rounded-3xl border flex flex-col items-center justify-center gap-3 font-extrabold text-lg transition active:scale-95; }
.accion-ok { @apply bg-ok/15 border-ok/40 text-ok hover:bg-ok/25; }
.accion-gold { @apply bg-gold/15 border-gold/40 text-gold hover:bg-gold/25; }
.accion-danger { @apply bg-danger/15 border-danger/40 text-danger hover:bg-danger/25; }
.accion:disabled { @apply bg-surface border-line text-ink-3; }
</style>
