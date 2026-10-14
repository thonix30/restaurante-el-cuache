<template>
  <div ref="root" class="min-h-[100dvh] bg-bg text-ink grain">
    <!-- ═══ Splash ═══ -->
    <Transition name="fade">
      <div v-if="splash" class="fixed inset-0 z-[150] bg-bg grid place-items-center">
        <div class="text-center">
          <BrandLogo :size="170" class="splash-logo mx-auto" />
          <div class="splash-line h-px w-0 mx-auto mt-6 bg-gold" />
          <p class="splash-text mt-5 font-serif text-2xl text-ink opacity-0">
            <template v-if="mesa">Bienvenidos a la <span class="gold-text">mesa {{ mesa.numero }}</span></template>
            <template v-else>Nuestra carta</template>
          </p>
        </div>
      </div>
    </Transition>

    <!-- ═══ Error de QR ═══ -->
    <div v-if="errorQr" class="min-h-[100dvh] grid place-items-center px-6">
      <EmptyState :icon="PhQrCode" title="Código QR no válido" :text="errorQr">
        <router-link to="/carta" class="btn btn-gold">Ver la carta sin pedir</router-link>
      </EmptyState>
    </div>

    <template v-else>
      <!-- ═══ Hero ═══ -->
      <section ref="hero" class="relative h-[44vh] min-h-[300px] max-h-[460px] overflow-hidden">
        <div ref="heroImg" class="absolute inset-0 will-change-transform">
          <img :src="'./img/desayuno.jpg'" alt="" class="w-full h-full object-cover scale-110" />
        </div>
        <div class="absolute inset-0 bg-gradient-to-b from-black/55 via-black/30 to-[rgb(var(--bg))]" />
        <div class="relative z-10 h-full max-w-6xl mx-auto px-4 sm:px-6 flex flex-col">
          <div class="flex items-center justify-between pt-4">
            <BrandLogo :size="64" tone="cream" class="drop-shadow-lg w-14 h-14 sm:w-16 sm:h-16" />
            <div class="flex items-center gap-2">
              <router-link v-if="!token && auth.isAuthenticated" :to="auth.home" class="btn btn-sm bg-black/30 text-[#F5ECDC] border border-white/15 backdrop-blur">
                <PhArrowLeft :size="16" /> Volver al sistema
              </router-link>
              <button class="btn btn-icon bg-black/30 text-[#F5ECDC] border border-white/15 backdrop-blur" :aria-label="ui.theme === 'dark' ? 'Modo claro' : 'Modo oscuro'" @click="ui.toggleTheme">
                <component :is="ui.theme === 'dark' ? PhSun : PhMoonStars" :size="18" />
              </button>
              <div v-if="mesa" class="flex items-center gap-2 rounded-full pl-3 pr-4 h-11 bg-black/35 border border-[#D6A75E]/50 backdrop-blur text-[#F5ECDC]">
                <PhMapPin :size="18" class="text-[#EECB8E]" weight="fill" />
                <span class="text-xs uppercase tracking-widest text-[#E6D9C3]/80">Mesa</span>
                <span class="num text-2xl font-bold text-[#EECB8E] leading-none">{{ mesa.numero }}</span>
              </div>
            </div>
          </div>
          <div class="mt-auto pb-8">
            <p class="eyebrow text-[#EECB8E]">{{ restaurante.lema || 'Café y Restaurante' }}</p>
            <h1 class="font-serif text-4xl min-[400px]:text-5xl sm:text-6xl text-[#F5ECDC] leading-none mt-1">{{ restaurante.nombre || 'El Cuache' }}</h1>
          </div>
        </div>
      </section>

      <!-- Aún no abrimos -->
      <div v-if="!abierto && !cargando" class="max-w-6xl mx-auto px-4 sm:px-6 -mt-3 relative z-10">
        <div class="card border-warn/40 p-4 flex items-center gap-4">
          <span class="grid place-items-center w-12 h-12 rounded-2xl bg-warn/15 text-warn shrink-0"><PhMoonStars :size="24" weight="duotone" /></span>
          <div>
            <p class="font-serif text-lg">Aún no abrimos</p>
            <p class="text-sm text-ink-3">Puedes ver la carta; podrás pedir en cuanto abramos la cocina.</p>
          </div>
        </div>
      </div>
      <div v-else-if="!token" class="max-w-6xl mx-auto px-4 sm:px-6 -mt-3 relative z-10">
        <div class="card p-4 flex items-center gap-4">
          <span class="grid place-items-center w-12 h-12 rounded-2xl bg-gold/15 text-gold shrink-0"><PhQrCode :size="24" weight="duotone" /></span>
          <p class="text-sm text-ink-2">Para pedir, escanea el código QR de tu mesa.</p>
        </div>
      </div>

      <!-- ═══ Categorías (sticky) ═══ -->
      <nav class="sticky top-0 z-30 glass border-b border-line/50 mt-4">
        <div ref="chipsEl" class="max-w-6xl mx-auto flex gap-2 overflow-x-auto scrollbar-none px-4 sm:px-6 py-3">
          <button v-for="c in categoriasConProductos" :key="c.id" :data-cat="c.id"
                  class="shrink-0 h-10 px-4 rounded-full text-sm font-bold border transition flex items-center gap-2"
                  :class="catActiva === c.id ? 'bg-gold text-on-gold border-gold shadow-glow' : 'bg-surface border-line text-ink-2 hover:text-ink'"
                  @click="irA(c.id)">
            <CatIcon :name="c.icono" :size="16" />{{ c.nombre }}
          </button>
        </div>
      </nav>

      <!-- ═══ Carta ═══ -->
      <main class="max-w-6xl mx-auto px-4 sm:px-6 pt-6" :class="token ? 'pb-48' : 'pb-16'">
        <div v-if="cargando" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="i in 4" :key="i" class="skeleton h-72" />
        </div>
        <section v-for="c in categoriasConProductos" :key="c.id" :id="'cat-' + c.id" :data-section="c.id" class="mb-10 scroll-mt-20">
          <div class="flex items-center gap-3 mb-4">
            <h2 class="font-serif text-2xl sm:text-3xl">{{ c.nombre }}</h2>
            <span class="flex-1 h-px bg-gradient-to-r from-line to-transparent" />
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <article v-for="p in porCategoria(c.id)" :key="p.id" data-card
                     class="card overflow-hidden group flex flex-col transition duration-500"
                     :class="agotado(p) ? 'opacity-90' : 'card-hover cursor-pointer'"
                     @click="!agotado(p) && abrirDetalle(p)">
              <div class="relative aspect-[16/10] overflow-hidden">
                <img :src="p.imagen || fallback(c)" :alt="p.nombre" loading="lazy" :data-img="p.id"
                     class="w-full h-full object-cover transition duration-700"
                     :class="agotado(p) ? 'grayscale-soft' : 'group-hover:scale-105'" :style="{ objectPosition: p.imagen_posicion }" />
                <span v-if="p.destacado && !agotado(p)" class="absolute top-3 left-3 chip chip-gold glass">
                  <PhStar :size="12" weight="fill" /> Recomendado
                </span>
                <!-- Cinta dorada "Agotado" -->
                <div v-if="agotado(p)" class="absolute inset-0 grid place-items-center bg-black/25">
                  <span class="ribbon">Agotado</span>
                </div>
                <span v-if="cart.qtyOf(p.id)" class="absolute top-3 right-3 num w-8 h-8 rounded-full bg-gold text-on-gold font-bold grid place-items-center shadow-glow">{{ cart.qtyOf(p.id) }}</span>
              </div>
              <div class="p-4 flex flex-col flex-1">
                <div class="flex items-start justify-between gap-3">
                  <h3 class="font-serif text-lg sm:text-xl leading-tight min-w-0 break-words" :class="agotado(p) ? 'text-ink-3' : ''">{{ p.nombre }}</h3>
                  <span class="num text-lg font-semibold shrink-0" :class="agotado(p) ? 'text-ink-3 line-through' : 'text-gold'">{{ money(p.precio) }}</span>
                </div>
                <p class="text-sm text-ink-3 mt-1.5 line-clamp-2 flex-1">{{ p.descripcion }}</p>
                <button v-if="token" class="btn mt-4 w-full" :class="agotado(p) ? 'btn-soft' : 'btn-gold'" :disabled="agotado(p) || !abierto"
                        @click.stop="agregarRapido(p, $event)">
                  <template v-if="agotado(p)"><PhProhibit :size="17" /> No disponible</template>
                  <template v-else><PhPlus :size="17" weight="bold" /> Agregar</template>
                </button>
              </div>
            </article>
          </div>
        </section>
      </main>

      <!-- ═══ Carrito flotante ═══ -->
      <Transition name="fade">
        <button v-if="token && (cart.count || cuenta)" ref="cartBtn" class="fixed z-40 right-3 sm:right-8 bottom-[92px] sm:bottom-[112px] h-12 sm:h-14 pl-4 pr-5 rounded-full btn-gold btn shadow-lift"
                @click="orden = true" aria-label="Ver tu orden">
          <PhShoppingBagOpen :size="22" weight="fill" />
          <span class="font-extrabold">{{ cart.count ? 'Tu orden' : 'Lo que pediste' }}</span>
          <span v-if="cart.count" class="num min-w-[26px] h-[26px] px-1 rounded-full bg-on-gold text-gold text-sm font-bold grid place-items-center">{{ cart.count }}</span>
        </button>
      </Transition>

      <!-- ═══ Barra fija: Llamar al mesero / Pedir la cuenta ═══ -->
      <div v-if="token" class="fixed bottom-0 inset-x-0 z-40 glass border-t border-line/60 safe-bottom">
        <div class="max-w-6xl mx-auto grid grid-cols-2 gap-2 sm:gap-3 p-2.5 sm:p-3">
          <button class="btn btn-lg h-14 sm:h-16 px-3 sm:px-5 rounded-2xl text-sm sm:text-[15px] leading-tight whitespace-normal text-left border"
                  :class="llamados.mesero ? 'bg-gold/15 border-gold text-gold pulse-gold' : 'btn-soft'"
                  :disabled="!abierto" @click="llamados.mesero ? hoja = 'mesero' : confirmarLlamado('mesero')">
            <PhBellRinging :size="24" :weight="llamados.mesero ? 'fill' : 'duotone'" />
            {{ llamados.mesero ? 'Mesero avisado' : 'Llamar al mesero' }}
          </button>
          <button class="btn btn-lg h-14 sm:h-16 px-3 sm:px-5 rounded-2xl text-sm sm:text-[15px] leading-tight whitespace-normal text-left border"
                  :class="llamados.cuenta ? 'bg-gold/15 border-gold text-gold pulse-gold' : 'btn-soft'"
                  :disabled="!abierto" @click="llamados.cuenta ? hoja = 'cuenta' : hoja = 'pagar'">
            <PhReceipt :size="24" :weight="llamados.cuenta ? 'fill' : 'duotone'" />
            {{ llamados.cuenta ? 'Cuenta pedida' : 'Pedir la cuenta' }}
          </button>
        </div>
      </div>
    </template>

    <!-- ═══ Detalle del platillo (notas + cantidad) ═══ -->
    <Modal v-model="detalleOpen" :title="detalle?.nombre" :eyebrow="detalle?.categoria" size="md">
      <template v-if="detalle">
        <div class="-mx-5 sm:-mx-6 -mt-1 mb-4 aspect-[16/9] overflow-hidden">
          <img :src="detalle.imagen || './img/almuerzo.jpg'" :alt="detalle.nombre" class="w-full h-full object-cover" :style="{ objectPosition: detalle.imagen_posicion }" />
        </div>
        <div class="flex items-baseline justify-between gap-3 mb-2">
          <span class="num text-2xl font-semibold text-gold">{{ money(detalle.precio) }}</span>
          <span v-if="detalle.destacado" class="chip chip-gold"><PhStar :size="12" weight="fill" /> Recomendado</span>
        </div>
        <p class="text-ink-2 leading-relaxed">{{ detalle.descripcion }}</p>
        <template v-if="token">
        <label class="label mt-5" for="notas">Notas para la cocina <span class="text-ink-3 font-normal">(opcional)</span></label>
        <textarea id="notas" v-model="detalleNotas" rows="2" maxlength="200" class="input" placeholder="Ej. sin cebolla, término medio, salsa aparte…" />
        <div class="flex flex-wrap gap-1.5 mt-2">
          <button v-for="s in sugerencias" :key="s" class="chip chip-neutral hover:border-gold/50" @click="detalleNotas = (detalleNotas ? detalleNotas + ', ' : '') + s">{{ s }}</button>
        </div>
        </template>
        <p v-else class="mt-5 rounded-2xl bg-gold/10 border border-gold/30 p-3.5 text-sm text-ink-2 flex gap-2.5">
          <PhQrCode :size="20" class="text-gold shrink-0" /> Para pedir este platillo, escanea el código QR de tu mesa.
        </p>
      </template>
      <template v-if="token" #footer>
        <QtyStepper big :value="detalleCant" @inc="detalleCant++" @dec="detalleCant = Math.max(1, detalleCant - 1)" />
        <button class="btn btn-gold btn-lg flex-1 min-w-0" :disabled="!abierto" @click="agregarDetalle">
          Agregar · <span class="num">{{ money((detalle?.precio || 0) * detalleCant) }}</span>
        </button>
      </template>
    </Modal>

    <!-- ═══ Tu Orden ═══ -->
    <Modal v-model="orden" title="Tu Orden" :eyebrow="mesa ? 'Mesa ' + mesa.numero : ''" size="lg">
      <!-- Aviso de agotado dentro del carrito -->
      <div v-if="cart.bloqueado" class="mb-4 rounded-2xl border border-danger/40 bg-danger/10 p-3.5 flex gap-3" role="alert">
        <PhWarningCircle :size="20" class="text-danger shrink-0 mt-0.5" />
        <p class="text-sm text-ink-2">Algunos platillos se agotaron mientras armabas tu orden. <b class="text-danger">Quítalos para poder enviar.</b></p>
      </div>

      <EmptyState v-if="!cart.items.length && !cuenta" :icon="PhShoppingBagOpen" title="Tu orden está vacía" text="Agrega platillos de la carta." />

      <TransitionGroup v-else name="list" tag="ul" class="space-y-3 relative">
        <li v-for="it in cart.items" :key="it.uid" class="rounded-2xl border p-3 transition"
            :class="it.no_disponible ? 'border-danger/50 bg-danger/[.07]' : 'border-line bg-surface-2/50'">
          <div class="flex gap-3">
            <img :src="it.imagen || './img/almuerzo.jpg'" alt="" class="w-16 h-16 rounded-xl object-cover shrink-0" :class="it.no_disponible ? 'grayscale-soft' : ''" :style="{ objectPosition: it.imagen_posicion }" />
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <p class="font-bold leading-tight" :class="it.no_disponible ? 'text-danger' : 'text-ink'">{{ it.nombre }}</p>
                <span class="num text-sm font-semibold shrink-0" :class="it.no_disponible ? 'text-ink-3 line-through' : 'text-ink'">{{ money(it.precio * it.cantidad) }}</span>
              </div>
              <p v-if="it.no_disponible" class="text-xs font-bold text-danger mt-0.5">Agotado — ya no está disponible</p>
              <div class="flex items-center justify-between gap-2 mt-2">
                <button v-if="it.no_disponible" class="btn btn-danger btn-sm" @click="cart.remove(it)"><PhTrash :size="15" /> Quitar</button>
                <QtyStepper v-else :value="it.cantidad" @inc="cart.inc(it)" @dec="cart.dec(it)" />
              </div>
            </div>
          </div>
          <input v-if="!it.no_disponible" v-model="it.notas" maxlength="200" class="input h-9 mt-2.5 text-xs" placeholder="Notas: sin cebolla, bien cocido…" />
        </li>
      </TransitionGroup>

      <!-- Lo que ya pidió la mesa -->
      <div v-if="cuenta?.items?.length" class="mt-6">
        <p class="eyebrow mb-2">Ya pedido en esta mesa</p>
        <ul class="divide-y divide-line/50 rounded-2xl border border-line/60 overflow-hidden">
          <li v-for="(i, k) in cuenta.items" :key="k" class="flex items-center gap-3 px-3.5 py-2.5 text-sm bg-surface-2/40">
            <span class="num text-ink-3 w-6">{{ i.cantidad }}×</span>
            <span class="flex-1 truncate">{{ i.nombre }}</span>
            <StatusChip :estado="i.estado" />
          </li>
        </ul>
        <p class="text-right text-sm text-ink-3 mt-2">Total de la mesa <b class="num text-ink">{{ money(cuenta.total) }}</b></p>
      </div>

      <template #footer>
        <div class="w-full flex items-center gap-3">
          <div class="flex-1">
            <p class="text-xs text-ink-3">Total de esta orden</p>
            <p class="num text-2xl font-semibold text-ink">{{ money(cart.total) }}</p>
          </div>
          <button class="btn btn-gold btn-lg" :disabled="!cart.items.length || cart.bloqueado || enviando || !abierto" @click="enviar">
            <span v-if="enviando" class="w-5 h-5 border-2 border-on-gold/40 border-t-on-gold rounded-full animate-spin" />
            <template v-else><PhCookingPot :size="20" weight="fill" /> Enviar a cocina</template>
          </button>
        </div>
      </template>
    </Modal>

    <!-- ═══ Hojas de llamado ═══ -->
    <Modal :model-value="!!hoja" @update:model-value="hoja = null" size="sm">
      <!-- Confirmación de aviso enviado -->
      <div v-if="hoja === 'mesero' || hoja === 'cuenta'" class="text-center pt-2">
        <div class="relative mx-auto w-24 h-24 mb-5">
          <span class="absolute inset-0 rounded-full bg-gold/20 pulse-gold" />
          <span class="relative grid place-items-center w-24 h-24 rounded-full bg-gold/15 text-gold border border-gold/40">
            <component :is="hoja === 'mesero' ? PhBellRinging : PhReceipt" :size="44" weight="fill" class="ring-icon" />
          </span>
        </div>
        <h3 class="font-serif text-2xl">{{ hoja === 'mesero' ? 'Tu mesero ya fue avisado' : 'En un momento te llevamos la cuenta' }}</h3>
        <p class="text-ink-3 mt-2 num">
          Mesa {{ mesa?.numero }} · {{ hace(llamados[hoja]?.creado_en, now) }}
          <template v-if="hoja === 'cuenta' && llamados.cuenta?.metodo_pago"> · <span class="capitalize">{{ llamados.cuenta.metodo_pago }}</span></template>
        </p>
        <div class="grid grid-cols-2 gap-2 mt-7">
          <button class="btn btn-soft" @click="cancelarLlamado(hoja)">Cancelar aviso</button>
          <button class="btn btn-gold" @click="hoja = null">Listo</button>
        </div>
      </div>

      <!-- ¿Cómo pagarás? -->
      <div v-else-if="hoja === 'pagar'" class="pt-2">
        <h3 class="font-serif text-2xl text-center">¿Cómo pagarás?</h3>
        <p class="text-sm text-ink-3 text-center mt-1 mb-5">Así tu mesero llega preparado.</p>
        <div class="grid gap-2.5">
          <button v-for="m in metodos" :key="m.id" class="card card-hover p-4 flex items-center gap-4 text-left" @click="llamar('cuenta', m.id)">
            <span class="grid place-items-center w-12 h-12 rounded-2xl bg-gold/15 text-gold"><component :is="m.icon" :size="24" weight="duotone" /></span>
            <span class="flex-1"><b class="block text-ink">{{ m.label }}</b><span class="text-xs text-ink-3">{{ m.desc }}</span></span>
            <PhCaretRight :size="18" class="text-ink-3" />
          </button>
        </div>
        <p v-if="cuenta" class="text-center text-sm text-ink-3 mt-5">Total de la mesa: <b class="num text-ink text-base">{{ money(cuenta.total) }}</b></p>
      </div>

      <!-- Confirmar llamado -->
      <div v-else-if="hoja === 'confirmar-mesero'" class="text-center pt-2">
        <span class="grid place-items-center w-20 h-20 mx-auto rounded-full bg-gold/15 text-gold mb-4"><PhBellRinging :size="38" weight="duotone" /></span>
        <h3 class="font-serif text-2xl">¿Llamamos a tu mesero?</h3>
        <p class="text-ink-3 mt-1">Le avisaremos que la mesa {{ mesa?.numero }} necesita atención.</p>
        <div class="grid grid-cols-2 gap-2 mt-6">
          <button class="btn btn-soft" @click="hoja = null">No, gracias</button>
          <button class="btn btn-gold" @click="llamar('mesero')">Sí, llamar</button>
        </div>
      </div>
    </Modal>

    <!-- Confirmación de envío -->
    <Transition name="fade">
      <div v-if="enviado" class="fixed inset-0 z-[160] bg-bg/95 backdrop-blur grid place-items-center text-center px-6" @click="enviado = false">
        <div>
          <svg viewBox="0 0 120 120" class="w-36 h-36 mx-auto">
            <circle class="ok-circle" cx="60" cy="60" r="52" fill="none" stroke="rgb(var(--gold))" stroke-width="4" stroke-linecap="round" />
            <path class="ok-check" d="M38 62 L54 77 L84 45" fill="none" stroke="rgb(var(--gold))" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <h2 class="font-serif text-3xl mt-6">¡Tu pedido va a la cocina!</h2>
          <p class="text-ink-3 mt-2">Te avisaremos con tu mesero cuando esté listo.</p>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import {
  PhQrCode, PhMapPin, PhStar, PhPlus, PhProhibit, PhShoppingBagOpen, PhBellRinging, PhReceipt, PhWarningCircle,
  PhTrash, PhCookingPot, PhCaretRight, PhMoney, PhCreditCard, PhUsersThree, PhSun, PhMoonStars, PhArrowLeft,
} from '@phosphor-icons/vue'
import BrandLogo from '@/components/ui/BrandLogo.vue'
import Modal from '@/components/ui/Modal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import QtyStepper from '@/components/ui/QtyStepper.vue'
import StatusChip from '@/components/ui/StatusChip.vue'
import CatIcon from '@/components/ui/CatIcon.vue'
import { publicService } from '@/services'
import { useCartStore } from '@/stores/cart'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { money, hace } from '@/utils/format'
import { gsap, reduced, flyTo } from '@/composables/useMotion'
import { setServerTime, useNow } from '@/composables/useNow'

const route = useRoute()
const ui = useUiStore()
const auth = useAuthStore()
const cart = useCartStore()
const { now } = useNow()

const token = route.params.token || null
const root = ref(null), hero = ref(null), heroImg = ref(null), chipsEl = ref(null), cartBtn = ref(null)
const splash = ref(true)
const cargando = ref(true)
const errorQr = ref('')
const restaurante = ref({})
const categorias = ref([])
const productos = ref([])
const agotadosSet = ref(new Set())
const abierto = ref(true)
const mesa = ref(null)
const cuenta = ref(null)
const llamados = ref({ mesero: null, cuenta: null })
const catActiva = ref(null)
const orden = ref(false)
const enviando = ref(false)
const enviado = ref(false)
const hoja = ref(null)
const detalle = ref(null)
const detalleOpen = ref(false)
const detalleNotas = ref('')
const detalleCant = ref(1)
const sugerencias = ['Sin cebolla', 'Sin chile', 'Poco hielo', 'Para llevar', 'Salsa aparte']
const metodos = [
  { id: 'efectivo', label: 'Efectivo', desc: 'Te llevamos el cambio', icon: PhMoney },
  { id: 'tarjeta', label: 'Tarjeta', desc: 'Llevamos la terminal a tu mesa', icon: PhCreditCard },
  { id: 'dividir', label: 'Dividir la cuenta', desc: 'Por platillo o por persona', icon: PhUsersThree },
]
let poll = null, ctx = null, observer = null

const agotado = (p) => !p.disponible || agotadosSet.value.has(p.id)
const porCategoria = (id) => productos.value.filter(p => p.categoria_id === id)
const categoriasConProductos = computed(() => categorias.value.filter(c => porCategoria(c.id).length))
const fallback = (c) => './img/' + ({ Desayunos: 'desayuno', Cafetería: 'bebida', Bebidas: 'bebida', Postres: 'postre' }[c.nombre] || 'almuerzo') + '.jpg'

const irA = (id) => {
  const el = document.getElementById('cat-' + id)
  if (el) window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - 70, behavior: reduced() ? 'auto' : 'smooth' })
}

// ── Detalle / agregar ──
const abrirDetalle = (p) => { detalle.value = p; detalleNotas.value = ''; detalleCant.value = 1; detalleOpen.value = true }
const agregarDetalle = () => {
  cart.add(detalle.value, detalleCant.value, detalleNotas.value.trim())
  detalleOpen.value = false
  ui.toast(`${detalle.value.nombre} agregado`, 'success', { duracion: 1800 })
  nextTick(() => cartBtn.value && gsap.fromTo(cartBtn.value, { scale: 1 }, { scale: 1.12, yoyo: true, repeat: 1, duration: .15 }))
}
const agregarRapido = async (p, ev) => {
  cart.add(p)
  await nextTick()
  const img = ev.currentTarget.closest('[data-card]')?.querySelector('img')
  flyTo(img, cartBtn.value)
}

// ── Estado en vivo (agotados, llamados, cuenta) ──
const aplicarAgotados = (ids) => {
  const antes = agotadosSet.value
  const nuevos = ids.filter(id => !antes.has(id))
  agotadosSet.value = new Set(ids)
  if (antes.size || nuevos.length) {
    nuevos.forEach(id => {
      const p = productos.value.find(x => x.id === id)
      if (p && !p._avisado) { p._avisado = true; ui.toast(`Lo sentimos, ${p.nombre} se acaba de agotar`, 'warn', { duracion: 5000 }) }
    })
  }
  productos.value.forEach(p => { if (!agotadosSet.value.has(p.id)) p._avisado = false })
  cart.marcarAgotados(ids)
}

const refrescar = async () => {
  if (!token) return
  try {
    const e = await publicService.estado(token)
    setServerTime(e.server_time)
    mesa.value = e.mesa
    abierto.value = e.abierto
    llamados.value = e.llamados
    cuenta.value = e.cuenta
    aplicarAgotados(e.agotados)
  } catch (e) {
    if (e.status === 404) errorQr.value = e.message
  }
}

// ── Llamados ──
const confirmarLlamado = (tipo) => { hoja.value = 'confirmar-' + tipo }
const llamar = async (tipo, metodo = null) => {
  try {
    const l = await publicService.llamar(token, tipo, metodo)
    llamados.value = { ...llamados.value, [tipo]: { id: l.id, creado_en: l.creado_en, metodo_pago: l.metodo_pago } }
    hoja.value = tipo
  } catch (e) { ui.error(e); hoja.value = null }
}
const cancelarLlamado = async (tipo) => {
  const l = llamados.value[tipo]
  hoja.value = null
  if (!l) return
  try { await publicService.cancelarLlamado(token, l.id); llamados.value = { ...llamados.value, [tipo]: null }; ui.toast('Aviso cancelado', 'info') } catch (e) { ui.error(e) }
}

// ── Enviar a cocina ──
const enviar = async () => {
  if (cart.bloqueado) return
  enviando.value = true
  try {
    await publicService.pedir(token, cart.payload)
    cart.clear()
    orden.value = false
    enviado.value = true
    await nextTick()
    if (!reduced()) {
      gsap.fromTo('.ok-circle', { strokeDasharray: 330, strokeDashoffset: 330 }, { strokeDashoffset: 0, duration: .8, ease: 'power2.out' })
      gsap.fromTo('.ok-check', { strokeDasharray: 80, strokeDashoffset: 80 }, { strokeDashoffset: 0, duration: .5, delay: .6, ease: 'power2.out' })
    }
    setTimeout(() => { enviado.value = false }, 2600)
    refrescar()
  } catch (e) {
    if (e.payload?.code === 'NO_DISPONIBLE') {
      const problemas = e.payload.no_disponibles
      // Agotados de verdad (o sin ninguna unidad): se marcan y bloquean el envío
      const agotadosIds = problemas.filter(x => x.motivo === 'agotado' || x.disponibles <= 0).map(x => x.producto_id)
      if (agotadosIds.length) aplicarAgotados([...new Set([...agotadosSet.value, ...agotadosIds])])
      // Quedan menos de las pedidas: se ajusta la cantidad a lo disponible
      problemas.filter(x => x.motivo === 'stock' && x.disponibles > 0).forEach(x => {
        let quedan = x.disponibles
        cart.items.filter(i => i.producto_id === x.producto_id).forEach(i => {
          const toma = Math.min(i.cantidad, quedan)
          quedan -= toma
          if (toma === 0) cart.remove(i); else i.cantidad = toma
        })
        ui.toast(`Solo quedan ${x.disponibles} de ${x.nombre}. Ajustamos tu orden; revísala y vuelve a enviar.`, 'warn', { duracion: 6000 })
      })
      if (agotadosIds.length) ui.error(e)
    } else {
      ui.error(e)
    }
  } finally {
    enviando.value = false
  }
}

// ── Scroll-spy de categorías ──
const iniciarSpy = () => {
  observer = new IntersectionObserver((entries) => {
    entries.forEach(en => {
      if (en.isIntersecting) {
        catActiva.value = Number(en.target.dataset.section)
        chipsEl.value?.querySelector(`[data-cat="${catActiva.value}"]`)?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' })
      }
    })
  }, { rootMargin: '-30% 0px -60% 0px' })
  root.value.querySelectorAll('[data-section]').forEach(s => observer.observe(s))
}

onMounted(async () => {
  cart.load(token || 'carta')
  const splashAnim = reduced() ? Promise.resolve() : gsap.timeline()
    .from('.splash-logo', { scale: .5, opacity: 0, rotate: -25, duration: 1, ease: 'back.out(1.7)' })
    .to('.splash-line', { width: 120, duration: .6, ease: 'power2.out' }, '-=.3')
    .to('.splash-text', { opacity: 1, y: -6, duration: .6 }, '-=.2')
  try {
    const m = await publicService.menu()
    restaurante.value = m.restaurante
    categorias.value = m.categorias
    productos.value = m.productos
    abierto.value = m.abierto
    agotadosSet.value = new Set(m.productos.filter(p => !p.disponible).map(p => p.id))
    catActiva.value = categoriasConProductos.value[0]?.id
    await refrescar()
  } catch (e) { ui.error(e) }
  cargando.value = false
  await splashAnim
  setTimeout(async () => {
    splash.value = false
    await nextTick()
    iniciarSpy()
    if (reduced()) return
    ctx = gsap.context(() => {
      gsap.to(heroImg.value, { yPercent: 22, ease: 'none', scrollTrigger: { trigger: hero.value, start: 'top top', end: 'bottom top', scrub: true } })
      gsap.from('[data-card]', { y: 30, opacity: 0, duration: .7, ease: 'power3.out', stagger: .05, delay: .1 })
    }, root.value)
  }, reduced() ? 0 : 500)
  if (token) poll = setInterval(() => { if (!document.hidden) refrescar() }, 5000)
  else poll = setInterval(async () => {
    if (document.hidden) return
    try {
      const m = await publicService.menu()
      abierto.value = m.abierto
      agotadosSet.value = new Set(m.productos.filter(p => !p.disponible).map(p => p.id))
    } catch { /* se reintenta en el siguiente ciclo */ }
  }, 20000)
})
onUnmounted(() => { clearInterval(poll); ctx?.revert(); observer?.disconnect() })
</script>

<style scoped>
.ribbon {
  position: relative;
  padding: .45rem 2.6rem;
  font: 800 .78rem/1 "Familjen Grotesk", sans-serif;
  letter-spacing: .28em;
  text-transform: uppercase;
  color: rgb(var(--on-gold));
  background: linear-gradient(135deg, rgb(var(--gold-2)), rgb(var(--gold)));
  transform: rotate(-8deg);
  box-shadow: 0 10px 30px -8px rgb(0 0 0 / .6);
}
.ribbon::before, .ribbon::after {
  content: ""; position: absolute; top: 0; bottom: 0; width: 14px; background: inherit;
}
.ribbon::before { left: -10px; clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%, 60% 50%); }
.ribbon::after { right: -10px; clip-path: polygon(0 0, 100% 0, 40% 50%, 100% 100%, 0 100%); }
.ring-icon { animation: ring 1.8s ease-in-out infinite; transform-origin: 50% 10%; }
@keyframes ring { 0%, 60%, 100% { transform: rotate(0); } 10%, 30% { transform: rotate(14deg); } 20%, 40% { transform: rotate(-14deg); } }
</style>
