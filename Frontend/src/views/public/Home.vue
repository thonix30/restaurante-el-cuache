<template>
  <div ref="root" class="bg-bg text-ink grain overflow-x-hidden">
    <!-- ═══ Barra superior ═══ -->
    <header class="fixed top-0 inset-x-0 z-40 transition-all duration-500" :class="scrolled ? 'glass border-b border-line/50' : ''">
      <div class="max-w-6xl mx-auto px-5 h-16 sm:h-20 flex items-center gap-3">
        <BrandLogo :size="scrolled ? 42 : 52" tone="cream" class="transition-all duration-500" />
        <span class="font-serif text-xl text-[#F5ECDC] hidden sm:inline" :class="scrolled ? 'text-ink' : ''">El Cuache</span>
        <div class="flex-1" />
        <router-link to="/carta" class="btn btn-ghost text-[#F5ECDC] hidden sm:inline-flex" :class="scrolled ? '!text-ink-2' : ''">La carta</router-link>
        <router-link to="/kiosco" class="btn btn-ghost text-[#F5ECDC] hidden sm:inline-flex" :class="scrolled ? '!text-ink-2' : ''">Asistencia</router-link>
        <router-link to="/login" class="btn btn-gold btn-sm sm:btn"><PhUserCircle :size="18" /> Equipo</router-link>
      </div>
    </header>

    <!-- ═══ Hero con parallax ═══ -->
    <section ref="hero" class="relative h-[100dvh] min-h-[560px] overflow-hidden">
      <div ref="heroImg" class="absolute inset-0 will-change-transform">
        <img :src="'./img/desayuno.jpg'" alt="" class="w-full h-full object-cover" fetchpriority="high" />
      </div>
      <div class="absolute inset-0 bg-gradient-to-b from-black/70 via-black/45 to-[rgb(var(--bg))]" />
      <div class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/30 to-transparent" />
      <Botanical class="hero-leaf absolute -left-8 bottom-24 w-56 sm:w-72 text-[#D6A75E]/40 -rotate-12" />
      <Botanical class="hero-leaf absolute -right-10 top-24 w-48 sm:w-64 text-[#D6A75E]/30 rotate-[150deg]" />

      <div ref="heroText" class="relative z-10 h-full max-w-6xl mx-auto px-5 flex flex-col justify-center">
        <p class="hero-eyebrow eyebrow text-[#EECB8E] mb-4">Café de altura · Cocina guatemalteca</p>
        <h1 class="font-serif text-[clamp(3.4rem,13vw,9rem)] leading-[.86] text-[#F5ECDC] tracking-tight" aria-label="El Cuache">
          <span v-for="(c, i) in 'El Cuache'" :key="i" class="hero-char inline-block" :class="c === ' ' ? 'w-[.25em]' : ''">{{ c }}</span>
        </h1>
        <p class="hero-sub mt-6 max-w-xl text-lg sm:text-xl text-[#E6D9C3]/90 font-light leading-relaxed">
          Desayunos chapines, café de olla y platos hechos con calma. Escanea el QR de tu mesa y pide sin esperar.
        </p>
        <div class="hero-cta mt-9 flex flex-wrap gap-3">
          <router-link ref="ctaEl" to="/carta" class="btn btn-gold btn-lg"><PhBookOpenText :size="20" /> Ver la carta</router-link>
          <a href="#experiencia" class="btn btn-lg bg-white/10 text-[#F5ECDC] border border-white/20 hover:bg-white/15 backdrop-blur">Conócenos</a>
        </div>
      </div>

      <a href="#experiencia" class="scroll-hint absolute bottom-8 left-1/2 -translate-x-1/2 z-10 flex flex-col items-center gap-2 text-[#E6D9C3]/70 text-xs" aria-label="Bajar">
        <span>Desliza</span>
        <span class="w-6 h-10 rounded-full border border-current grid justify-center pt-2"><span class="dot w-1 h-2 rounded-full bg-current" /></span>
      </a>
    </section>

    <!-- ═══ Experiencia ═══ -->
    <section id="experiencia" class="relative max-w-6xl mx-auto px-5 py-24 sm:py-32">
      <div class="grid grid-cols-1 md:grid-cols-[1.1fr_1fr] gap-12 items-center">
        <div data-scroll>
          <p class="eyebrow text-gold mb-3">La experiencia</p>
          <h2 class="font-serif text-4xl sm:text-5xl leading-[1.05]">Tu mesa, tu ritmo.<br /><em class="gold-text not-italic">Nosotros, atentos.</em></h2>
          <p class="text-ink-2 mt-6 leading-relaxed max-w-lg">
            Pide desde tu celular, llama al mesero con un toque y pide la cuenta cuando quieras.
            En cocina tu pedido aparece al instante, y si un platillo se agota lo verás en tiempo real.
          </p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div v-for="(f, i) in features" :key="f.t" data-scroll class="card p-5" :class="i % 2 ? 'translate-y-8' : ''">
            <span class="grid place-items-center w-11 h-11 rounded-xl bg-gold/15 text-gold mb-4"><component :is="f.i" :size="22" weight="duotone" /></span>
            <p class="font-bold text-ink">{{ f.t }}</p>
            <p class="text-sm text-ink-3 mt-1">{{ f.d }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══ Banda de café con parallax ═══ -->
    <section ref="band" class="relative h-[70vh] min-h-[420px] overflow-hidden">
      <div ref="bandImg" class="absolute -inset-y-[20%] inset-x-0 will-change-transform">
        <img :src="'./img/bebida.jpg'" alt="" class="w-full h-full object-cover" loading="lazy" />
      </div>
      <div class="absolute inset-0 bg-black/55" />
      <div class="relative z-10 h-full grid place-items-center text-center px-6">
        <div data-scroll>
          <p class="font-serif italic text-[clamp(1.8rem,5vw,3.6rem)] text-[#F5ECDC] leading-tight max-w-3xl">
            “Un buen café no se apura;<br />se comparte.”
          </p>
          <p class="eyebrow text-[#EECB8E] mt-5">Granos de Antigua Guatemala</p>
        </div>
      </div>
    </section>

    <!-- ═══ Destacados de la carta ═══ -->
    <section class="max-w-6xl mx-auto px-5 py-24 sm:py-28">
      <div class="flex items-end justify-between gap-4 mb-10" data-scroll>
        <div>
          <p class="eyebrow text-gold mb-2">Recomendados del chef</p>
          <h2 class="font-serif text-4xl sm:text-5xl">De nuestra cocina</h2>
        </div>
        <router-link to="/carta" class="btn btn-soft hidden sm:inline-flex">Carta completa <PhArrowRight :size="16" /></router-link>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <article v-for="p in destacados" :key="p.id" data-scroll class="card card-hover overflow-hidden group">
          <div class="aspect-[4/5] overflow-hidden">
            <img :src="p.imagen || './img/almuerzo.jpg'" :alt="p.nombre" :style="{ objectPosition: p.imagen_posicion }" loading="lazy"
                 class="w-full h-full object-cover transition duration-[1.2s] group-hover:scale-110" />
          </div>
          <div class="p-4">
            <p class="text-[11px] eyebrow">{{ p.categoria }}</p>
            <div class="flex items-start justify-between gap-2 mt-1">
              <h3 class="font-serif text-lg leading-tight">{{ p.nombre }}</h3>
              <span class="num text-gold font-semibold">{{ money(p.precio) }}</span>
            </div>
          </div>
        </article>
      </div>
    </section>

    <footer class="border-t border-line/60">
      <div class="max-w-6xl mx-auto px-5 py-12 flex flex-col sm:flex-row items-center gap-6 justify-between">
        <div class="flex items-center gap-4">
          <BrandLogo :size="64" />
          <div>
            <p class="font-serif text-xl">El Cuache</p>
            <p class="text-sm text-ink-3">Café y Restaurante</p>
          </div>
        </div>
        <div class="flex gap-2">
          <router-link to="/kiosco" class="btn btn-soft btn-sm"><PhFingerprint :size="16" /> Kiosco de asistencia</router-link>
          <router-link to="/login" class="btn btn-soft btn-sm"><PhLockKey :size="16" /> Acceso del equipo</router-link>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { PhUserCircle, PhBookOpenText, PhArrowRight, PhQrCode, PhBellRinging, PhReceipt, PhLightning, PhFingerprint, PhLockKey } from '@phosphor-icons/vue'
import BrandLogo from '@/components/ui/BrandLogo.vue'
import Botanical from '@/components/ui/Botanical.vue'
import { publicService } from '@/services'
import { money } from '@/utils/format'
import { gsap, ScrollTrigger, reduced, revealOnScroll, magnetic } from '@/composables/useMotion'

const root = ref(null), hero = ref(null), heroImg = ref(null), heroText = ref(null), band = ref(null), bandImg = ref(null), ctaEl = ref(null)
const scrolled = ref(false)
const destacados = ref([])
const features = [
  { t: 'Pide desde el QR', d: 'Tu mesa, tu celular, sin esperas.', i: PhQrCode },
  { t: 'Llama al mesero', d: 'Un toque y ya va en camino.', i: PhBellRinging },
  { t: 'Pide la cuenta', d: 'Efectivo, tarjeta o dividida.', i: PhReceipt },
  { t: 'En tiempo real', d: 'Cocina recibe tu pedido al instante.', i: PhLightning },
]
let ctx, unMag = () => {}
const onScroll = () => { scrolled.value = window.scrollY > 40 }

onMounted(async () => {
  window.addEventListener('scroll', onScroll, { passive: true })
  try {
    const m = await publicService.menu()
    const d = m.productos.filter(p => p.destacado)
    destacados.value = (d.length >= 4 ? d : [...d, ...m.productos.filter(p => !p.destacado)]).slice(0, 4)
  } catch { /* la portada funciona sin datos */ }

  unMag = magnetic(ctaEl.value?.$el, .2)
  if (reduced()) return
  ctx = gsap.context(() => {
    const tl = gsap.timeline({ defaults: { ease: 'power4.out' } })
    tl.from(heroImg.value, { scale: 1.3, duration: 2.2, ease: 'power2.out' })
      .from('.hero-eyebrow', { y: 20, opacity: 0, duration: .8 }, .3)
      .from('.hero-char', { yPercent: 110, opacity: 0, rotate: 8, duration: 1.1, stagger: .05 }, .4)
      .from('.hero-sub', { y: 24, opacity: 0, duration: .9 }, 1)
      .from('.hero-cta > *', { y: 20, opacity: 0, duration: .7, stagger: .1 }, 1.2)
      .from('.scroll-hint', { opacity: 0, duration: 1 }, 1.6)
    gsap.to('.scroll-hint .dot', { y: 10, opacity: 0, duration: 1.4, repeat: -1, ease: 'power1.in' })
    gsap.to('.hero-leaf', { y: '+=14', rotation: '+=3', duration: 5, yoyo: true, repeat: -1, ease: 'sine.inOut', stagger: 1.2 })

    // Parallax del hero: la foto baja más lento, el texto sube y se desvanece
    gsap.to(heroImg.value, { yPercent: 25, scale: 1.08, ease: 'none', scrollTrigger: { trigger: hero.value, start: 'top top', end: 'bottom top', scrub: true } })
    gsap.to(heroText.value, { yPercent: -30, opacity: 0, ease: 'none', scrollTrigger: { trigger: hero.value, start: 'top top', end: 'bottom 30%', scrub: true } })
    // Parallax de la banda de café
    gsap.fromTo(bandImg.value, { yPercent: -12 }, { yPercent: 12, ease: 'none', scrollTrigger: { trigger: band.value, start: 'top bottom', end: 'bottom top', scrub: true } })
  }, root.value)
  setTimeout(() => { revealOnScroll(root.value); ScrollTrigger.refresh() }, 50)
})
onUnmounted(() => { window.removeEventListener('scroll', onScroll); ctx?.revert(); ScrollTrigger.getAll().forEach(t => t.kill()); unMag() })
</script>
