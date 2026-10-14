/* ══════════════════════════════════════════════════════════════════════
   EL CUACHE · sitio público — animaciones (GSAP + ScrollTrigger +
   ScrollSmoother + SplitText). Sin dependencias del sistema administrador:
   solo lee la carta pública (opcional) para mostrar los recomendados.
   ══════════════════════════════════════════════════════════════════════ */
const CONFIG = {
    // Número de WhatsApp con código de país, sin espacios ni "+". Vacío = se oculta el botón.
    whatsapp: '',
    horario: 'Todos los días<br>7:00 a 23:00',
    // API pública del sistema (lectura de la carta). Relativa a esta carpeta.
    api: '../Backend/api/v1/public/menu',
    app: '../Frontend/dist/',
}

gsap.registerPlugin(ScrollTrigger, ScrollSmoother, SplitText)

const reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches
const punteroFino = window.matchMedia('(pointer: fine)').matches
const $ = (s, c = document) => c.querySelector(s)
const $$ = (s, c = document) => [...c.querySelectorAll(s)]

let smoother = null
if (!reducido && punteroFino) {
    smoother = ScrollSmoother.create({ wrapper: '#smooth-wrapper', content: '#smooth-content', smooth: 1.15, effects: true, smoothTouch: false })
}

/* ── Datos de configuración en la página ─────────────────────────────── */
$('#anio').textContent = new Date().getFullYear()
$('#horario').innerHTML = CONFIG.horario
if (CONFIG.whatsapp) {
    const w = $('#whatsapp')
    w.href = `https://wa.me/${CONFIG.whatsapp}?text=${encodeURIComponent('¡Hola, El Cuache! Quisiera información.')}`
    w.hidden = false
}

/* ── Navegación con anclas (funciona con y sin ScrollSmoother) ───────── */
const irA = (hash) => {
    const destino = $(hash)
    if (!destino) return
    if (smoother) smoother.scrollTo(destino, true, 'top top')
    else destino.scrollIntoView({ behavior: reducido ? 'auto' : 'smooth' })
}
$$('[data-ir]').forEach(a => a.addEventListener('click', (e) => {
    e.preventDefault()
    cerrarMenu()
    irA(a.getAttribute('href'))
}))

/* ── Menú móvil: se abre como un círculo de cochinilla ───────────────── */
const burger = $('.nav__burger')
const menu = $('.menu-movil')
let menuAbierto = false
const tlMenu = gsap.timeline({ paused: true })
    .set(menu, { visibility: 'visible' })
    .to(menu, { clipPath: 'circle(150% at calc(100% - 2.5rem) 2.5rem)', duration: .8, ease: 'power3.inOut' })
    .from('.menu-movil a', { yPercent: 120, opacity: 0, stagger: .06, duration: .6, ease: 'power3.out' }, '-=.35')
function cerrarMenu() {
    if (!menuAbierto) return
    menuAbierto = false
    document.body.classList.remove('menu-abierto')
    burger.setAttribute('aria-expanded', 'false')
    tlMenu.reverse()
}
burger.addEventListener('click', () => {
    menuAbierto = !menuAbierto
    document.body.classList.toggle('menu-abierto', menuAbierto)
    burger.setAttribute('aria-expanded', String(menuAbierto))
    menuAbierto ? tlMenu.play() : tlMenu.reverse()
})

/* ── Barra superior: sólida al bajar, se esconde al seguir bajando ──── */
const nav = $('.nav')
ScrollTrigger.create({
    start: 0, end: 'max',
    onUpdate: (self) => {
        nav.classList.toggle('solida', self.scroll() > 60)
        nav.classList.toggle('oculta', self.direction === 1 && self.scroll() > window.innerHeight * .9 && !menuAbierto)
    },
})
gsap.to('.progreso', { scaleX: 1, ease: 'none', scrollTrigger: { start: 0, end: 'max', scrub: .3 } })

/* ── Preloader: el hilo se teje mientras cargan fuentes y la foto ───── */
const esperarCarga = Promise.all([
    document.fonts.ready,
    new Promise(r => { const i = $('.hero__foto img'); i.complete ? r() : (i.onload = i.onerror = r) }),
    new Promise(r => setTimeout(r, reducido ? 0 : 900)),
])

const intro = () => {
    document.body.classList.remove('cargando')
    if (reducido) { gsap.set(['.preloader', '.cortina'], { autoAlpha: 0 }); return }
    const tl = gsap.timeline()
    tl.to('.cortina span', { scaleY: 1, duration: .55, stagger: .07, ease: 'power3.inOut' })
        .set('.preloader', { autoAlpha: 0 })
        .to('.cortina span', { scaleY: 0, transformOrigin: 'bottom', duration: .6, stagger: .07, ease: 'power3.inOut' })
        .set('.cortina', { autoAlpha: 0 })

    // Título: letras que suben desde una máscara, línea por línea
    const split = SplitText.create('.hero__titulo .linea', { type: 'chars', mask: 'chars' })
    tl.from(split.chars, { yPercent: 115, rotate: 8, duration: 1, stagger: .028, ease: 'power4.out' }, '-=.55')
        .from('.hero__lugar', { x: -30, autoAlpha: 0, duration: .7 }, '-=.9')
        .from('.hero__bajada', { y: 24, autoAlpha: 0, duration: .8 }, '-=.6')
        .from('.hero__acciones > *', { y: 20, autoAlpha: 0, stagger: .1, duration: .6 }, '-=.5')
        .from('.sello', { scale: 0, rotate: -120, duration: 1.1, ease: 'back.out(1.6)' }, '-=.9')
        .from('.hero__scroll', { autoAlpha: 0, duration: .8 }, '-=.4')
}

gsap.to('.preloader__hilo', { width: '100%', duration: reducido ? 0 : 1.1, ease: 'power2.inOut' })
gsap.from('.preloader__nombre', { yPercent: 40, autoAlpha: 0, duration: .8, ease: 'power3.out' })
esperarCarga.then(() => setTimeout(intro, reducido ? 0 : 350))

/* ══════════════════ Animaciones por scroll ══════════════════ */
if (!reducido) {
    // Hero: la foto baja más lento (parallax), el texto se va hacia arriba
    gsap.to('.hero__foto', { yPercent: 22, scale: 1.12, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } })
    gsap.to('.hero__contenido', { yPercent: -18, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } })
    // Sello: gira solo y acelera con el scroll
    // Sello: giro lento propio (una vuelta cada 45 s). El scroll le da un impulso
    // suave y limitado (máx. ~2.2×), hacia adelante al bajar y hacia atrás al subir;
    // al dejar de mover, vuelve con calma a su ritmo.
    const giro = gsap.to('.sello svg', { rotate: 360, duration: 45, repeat: -1, ease: 'none' })
    const reposo = gsap.delayedCall(0.25, () => gsap.to(giro, { timeScale: 1, duration: 2.4, ease: 'sine.out', overwrite: true })).pause()
    ScrollTrigger.create({
        trigger: '.hero', start: 'top top', end: 'bottom top',
        onUpdate: (s) => {
            const impulso = 1 + Math.min(Math.abs(s.getVelocity()) / 2500, 1.2)
            gsap.to(giro, { timeScale: impulso * s.direction, duration: 1.2, ease: 'power2.out', overwrite: true })
            reposo.restart(true)
        },
    })
    gsap.to('.sello__centro', { scale: 1.06, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } })

    // La casa: el logo grande entra girando y el anillo tejido rota con el scroll
    gsap.fromTo('.casa__logo', { scale: .72, rotate: -14, autoAlpha: 0 }, { scale: 1, rotate: 0, autoAlpha: 1, duration: 1.3, ease: 'back.out(1.5)', scrollTrigger: { trigger: '.casa', start: 'top 75%' } })
    gsap.to('.casa__anillo', { rotate: 180, ease: 'none', scrollTrigger: { trigger: '.casa', start: 'top bottom', end: 'bottom top', scrub: true } })
    gsap.from('.casa__texto > *', { y: 40, autoAlpha: 0, stagger: .1, duration: .9, ease: 'power3.out', scrollTrigger: { trigger: '.casa', start: 'top 65%' } })

    // Marquesinas infinitas que se inclinan y aceleran con la velocidad del scroll
    const cintas = $$('.cinta').map(c => {
        const dir = Number(c.dataset.dir)
        const pistas = $$('.cinta__pista', c)
        return gsap.fromTo(pistas, { xPercent: dir < 0 ? 0 : -100 }, { xPercent: dir < 0 ? -100 : 0, duration: 26, ease: 'none', repeat: -1 })
    })
    const sesgo = gsap.quickTo('.cinta__pista', 'skewX', { duration: .5, ease: 'power3' })
    ScrollTrigger.create({
        trigger: '.marquesinas', start: 'top bottom', end: 'bottom top',
        onUpdate: (s) => {
            const v = s.getVelocity()
            sesgo(gsap.utils.clamp(-12, 12, v / -220))
            cintas.forEach(t => gsap.to(t, { timeScale: (1 + Math.abs(v) / 250) * (s.direction === 1 ? 1 : -1), duration: .3, overwrite: true }))
        },
    })

    // Manifiesto: las palabras se "tiñen" de carbón al bajar
    const tinta = SplitText.create('[data-tinta]', { type: 'words', wordsClass: 'word' })
    gsap.fromTo(tinta.words, { color: 'rgba(23,17,13,.32)' }, { color: '#17110D', stagger: .1, ease: 'none', scrollTrigger: { trigger: '[data-tinta]', start: 'top 90%', end: 'top 35%', scrub: true } })

    // Collage: en celular (sin ScrollSmoother) se hace el parallax a mano
    if (!smoother) {
        $$('.collage__foto').forEach((f, i) => gsap.to(f, { y: (i - 1) * -40, ease: 'none', scrollTrigger: { trigger: '.manifiesto__collage', scrub: true } }))
    }
    gsap.from('.collage__foto', { y: 80, autoAlpha: 0, stagger: .15, duration: 1, ease: 'power3.out', scrollTrigger: { trigger: '.manifiesto__collage', start: 'top 80%' } })

    // Títulos de sección: líneas que suben desde una máscara
    $$('[data-revelar]').forEach(el => {
        SplitText.create(el, {
            type: 'lines', mask: 'lines', autoSplit: true,
            onSplit: (s) => gsap.from(s.lines, { yPercent: 105, duration: 1, stagger: .12, ease: 'power4.out', scrollTrigger: { trigger: el, start: 'top 85%' } }),
        })
    })

    // Platos apilados: cada tarjeta se queda fija y la siguiente la cubre
    const platos = $$('.plato')
    platos.forEach((p, i) => {
        gsap.fromTo($('.plato__foto img', p), { yPercent: -8 }, { yPercent: 8, ease: 'none', scrollTrigger: { trigger: p, start: 'top bottom', end: 'bottom top', scrub: true } })
        gsap.from([$('.plato__nombre', p), $('.plato__desc', p), $('.plato__nota', p)], { y: 50, autoAlpha: 0, stagger: .1, duration: .9, ease: 'power3.out', scrollTrigger: { trigger: p, start: 'top 60%' } })
        gsap.from($('.plato__num', p), { xPercent: 40, autoAlpha: 0, duration: 1.2, ease: 'power3.out', scrollTrigger: { trigger: p, start: 'top 60%' } })
        if (i === platos.length - 1) return
        ScrollTrigger.create({ trigger: p, start: 'top top', end: () => '+=' + p.offsetHeight * (platos.length - 1 - i), pin: true, pinSpacing: false })
        gsap.fromTo(p, { scale: 1, borderRadius: 0, filter: 'brightness(1)' }, { scale: .95, borderRadius: 24, filter: 'brightness(.8)', ease: 'none', scrollTrigger: { trigger: platos[i + 1], start: 'top 85%', end: 'top top', scrub: true } })
    })

    // Del comal: la foto se aleja, el texto sube y saltan chispas
    gsap.fromTo('.comal__foto', { scale: 1.45 }, { scale: 1, ease: 'none', scrollTrigger: { trigger: '.comal', start: 'top bottom', end: 'bottom top', scrub: true } })
    gsap.from('.linea-comal', { yPercent: 60, autoAlpha: 0, stagger: .2, duration: 1, ease: 'power3.out', scrollTrigger: { trigger: '.comal', start: 'top 55%' } })
    gsap.from('.comal__bajada', { y: 30, autoAlpha: 0, duration: .9, delay: .3, ease: 'power3.out', scrollTrigger: { trigger: '.comal', start: 'top 55%' } })
    const comal = $('.comal')
    for (let i = 0; i < 22; i++) {
        const c = document.createElement('i')
        c.className = 'chispa'
        comal.appendChild(c)
        const chispa = () => gsap.fromTo(c,
            { left: gsap.utils.random(30, 70) + '%', top: gsap.utils.random(70, 95) + '%', opacity: 0, scale: gsap.utils.random(.4, 1.2) },
            { top: '-=' + gsap.utils.random(20, 45) + '%', x: gsap.utils.random(-80, 80), opacity: 1, duration: gsap.utils.random(2, 4.5), ease: 'power1.out', delay: gsap.utils.random(0, 3),
              onUpdate() { if (this.progress() > .7) c.style.opacity = String((1 - this.progress()) * 3) }, onComplete: chispa })
        chispa()
    }

    // Fiestas: recorrido horizontal anclado
    const pista = $('.fiestas__pista')
    const recorrido = () => Math.max(0, pista.scrollWidth - window.innerWidth)
    const horizontal = gsap.to(pista, {
        x: () => -recorrido(), ease: 'none',
        scrollTrigger: { trigger: '.fiestas', start: 'top top', end: () => '+=' + recorrido(), pin: true, scrub: 1, invalidateOnRefresh: true, anticipatePin: 1 },
    })
    $$('.fiesta__foto img').forEach(img => gsap.fromTo(img, { xPercent: -12 }, { xPercent: 0, ease: 'none', scrollTrigger: { trigger: img.parentElement, containerAnimation: horizontal, start: 'left right', end: 'right left', scrub: true } }))
    // Entrada de la pista completa al llegar a la sección (las tarjetas nunca quedan ocultas)
    gsap.from('.fiesta', { y: 70, autoAlpha: 0, stagger: .08, duration: .9, ease: 'power3.out', scrollTrigger: { trigger: '.fiestas__pista', start: 'top 85%' } })

    // Café de altura
    gsap.fromTo('.altura__foto img', { yPercent: -10 }, { yPercent: 10, ease: 'none', scrollTrigger: { trigger: '.altura', start: 'top bottom', end: 'bottom top', scrub: true } })
    gsap.from('.granos span', { y: -120, rotate: -200, autoAlpha: 0, stagger: .12, duration: 1, ease: 'bounce.out', scrollTrigger: { trigger: '.granos', start: 'top 90%' } })

    // Visítanos y pie
    gsap.from('.visita__bloque', { y: 40, autoAlpha: 0, stagger: .12, duration: .8, ease: 'power3.out', scrollTrigger: { trigger: '.visita__info', start: 'top 85%' } })
    gsap.from('.pie__grande span', { yPercent: 100, duration: 1.2, stagger: .12, ease: 'power4.out', scrollTrigger: { trigger: '.pie__grande', start: 'top 95%' } })
    $$('main .faja').forEach(f => gsap.from(f, { scaleX: 0, transformOrigin: 'left', duration: 1.2, ease: 'power3.inOut', scrollTrigger: { trigger: f, start: 'top 95%' } }))
}

/* ── Contadores ──────────────────────────────────────────────────────── */
$$('[data-contar]').forEach(el => {
    const fin = Number(el.dataset.contar)
    const fmt = (v) => el.dataset.miles !== undefined ? Math.round(v).toLocaleString('es-GT') : Math.round(v)
    if (reducido) { el.textContent = fmt(fin); return }
    const o = { v: 0 }
    gsap.to(o, { v: fin, duration: 2, ease: 'power2.out', onUpdate: () => (el.textContent = fmt(o.v)), scrollTrigger: { trigger: el, start: 'top 88%' } })
})

/* ── Carta: pizarra de comedor con la carta en vivo del sistema ─────── */
const RESPALDO = [
    { nombre: 'Pepián de pollo', categoria: 'De la olla', descripcion: 'Recado de semillas tostadas, güisquil, papa y arroz.', destacado: true },
    { nombre: 'Tamal colorado', categoria: 'De la olla', descripcion: 'Masa, recado rojo y hoja; los sábados.' },
    { nombre: 'Chuchitos', categoria: 'Refacción', descripcion: 'En tusa, con salsa de tomate y queso seco.' },
    { nombre: 'Frijol negro con tortillas', categoria: 'Refacción', descripcion: 'Volteado o parado, con queso fresco.' },
    { nombre: 'Atol de elote', categoria: 'Para tomar', descripcion: 'Maíz tierno con canela, caliente y espeso.', destacado: true },
    { nombre: 'Café de olla', categoria: 'Para tomar', descripcion: 'Con canela y panela.' },
]
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))

async function cargarCarta() {
    let platillos = RESPALDO
    let ordenCategorias = []
    let enVivo = false
    try {
        const r = await fetch(CONFIG.api, { headers: { Accept: 'application/json' } })
        const j = await r.json()
        const disponibles = (j.data?.productos || []).filter(p => p.disponible)
        if (disponibles.length) {
            platillos = disponibles
            ordenCategorias = (j.data?.categorias || []).map(c => c.nombre)
            enVivo = true
        }
    } catch { /* sin API: se usa la lista de respaldo */ }

    // Todas las categorías y todos los platillos disponibles, en el orden
    // configurado en el sistema; dentro de cada categoría, primero los recomendados.
    const grupos = new Map(ordenCategorias.map(n => [n, []]))
    for (const p of [...platillos].sort((a, b) => (b.destacado ? 1 : 0) - (a.destacado ? 1 : 0))) {
        const c = p.categoria || 'De la casa'
        if (!grupos.has(c)) grupos.set(c, [])
        grupos.get(c).push(p)
    }
    const html = [...grupos.entries()].filter(([, items]) => items.length).map(([cat, items]) => `
        <div class="pizarra__grupo">
            <h3 class="pizarra__cat">${esc(cat)}</h3>
            ${items.map(p => `
            <div class="pizarra__item">
                <h3>${esc(p.nombre)}${p.destacado ? '<span class="estrella" title="Recomendado">✦</span>' : ''}</h3>
                <span class="pizarra__puntos"></span>
                <span class="pizarra__precio">${enVivo ? 'Q' + Number(p.precio).toFixed(0) : ''}</span>
                ${p.descripcion ? `<p class="pizarra__desc">${esc(p.descripcion)}</p>` : ''}
            </div>`).join('')}
        </div>`).join('')
    $('#carta-grid').innerHTML = html

    if (!reducido) {
        gsap.from('.pizarra', { rotate: -3, y: 60, autoAlpha: 0, duration: 1, ease: 'power3.out', scrollTrigger: { trigger: '.pizarra', start: 'top 85%' } })
        gsap.from('.pizarra__item', { x: 30, autoAlpha: 0, stagger: .05, duration: .6, ease: 'power3.out', scrollTrigger: { trigger: '.pizarra', start: 'top 70%' } })
        gsap.from('.carta__foto', { scale: .6, autoAlpha: 0, stagger: .15, duration: 1, ease: 'back.out(1.6)', scrollTrigger: { trigger: '.carta__fotos', start: 'top 85%' } })
        if (window.innerWidth > 960) {
            // Escritorio: el collage acompaña a la pizarra (anclado) y, mientras se
            // lee, las fotos se mueven, giran y cambian de profundidad con el scroll.
            const fotos = $('.carta__fotos'), pizarra = $('.pizarra')
            const recorrido = () => Math.max(400, pizarra.offsetHeight - fotos.offsetHeight)
            ScrollTrigger.create({ trigger: '.carta__cuerpo', start: 'top 110px', end: () => '+=' + recorrido(), pin: fotos, pinSpacing: false, invalidateOnRefresh: true })
            gsap.timeline({ scrollTrigger: { trigger: '.carta__cuerpo', start: 'top 80%', end: () => '+=' + (recorrido() + window.innerHeight * .3), scrub: 1, invalidateOnRefresh: true } })
                .fromTo('.carta__foto--1', { yPercent: 6, rotation: -6 }, { yPercent: -10, rotation: 3, scale: .94, ease: 'none' }, 0)
                .fromTo('.carta__foto--2', { yPercent: 30, rotation: 10 }, { yPercent: -45, rotation: -4, scale: 1.08, ease: 'none' }, 0)
                .fromTo('.carta__foto--3', { yPercent: 55, rotation: -10 }, { yPercent: -70, rotation: 5, scale: 1.12, ease: 'none' }, 0)
        } else {
            // Celular: parallax simple entre las fotos
            gsap.to('.carta__foto--2', { yPercent: -18, ease: 'none', scrollTrigger: { trigger: '.carta__fotos', scrub: true } })
            gsap.to('.carta__foto--3', { yPercent: -32, ease: 'none', scrollTrigger: { trigger: '.carta__fotos', scrub: true } })
        }
    }
    activarCursor()
    ScrollTrigger.refresh()
}
cargarCarta()

/* ── Créditos de fotos (licencias de Wikimedia Commons) ──────────────── */
fetch('img/creditos.json').then(r => r.json()).then(c => {
    const nombres = { pueblo: 'San Antonio Huista', escudo: 'Escudo de San Antonio Huista', jacaltecas: 'Mujeres jacaltecas', chirimia: 'Chirimía en Jacaltenango',
        cuchumatanes: 'Sierra de los Cuchumatanes', mercado: 'Mercado de Colotenango', pepian: 'Pepián', tamal: 'Tamal colorado', chuchito: 'Chuchito', atol: 'Atol de elote',
        frijoles: 'Frijoles negros', tortillera: 'Tortillas en comal de leña', comal: 'Tortillas en comal', conquista: 'Baile de la Conquista', marimba: 'Marimba', maiz: 'Derivados del maíz tierno' }
    $('#creditos').innerHTML = Object.entries(c).filter(([k]) => nombres[k]).map(([k, v]) =>
        `<li>${esc(nombres[k])} — ${esc(v.autor)} · <a href="${esc(v.pagina)}" target="_blank" rel="noopener">${esc(v.licencia)}</a></li>`).join('')
}).catch(() => {})

/* ── Cursor y botones magnéticos (solo con mouse) ────────────────────── */
let cursorListo = false
function activarCursor() {
    if (!punteroFino || reducido) return
    const cur = $('.cursor'), txt = $('.cursor__texto')
    if (!cursorListo) {
        cursorListo = true
        const x = gsap.quickTo(cur, 'x', { duration: .25, ease: 'power3' }), y = gsap.quickTo(cur, 'y', { duration: .25, ease: 'power3' })
        const tx = gsap.quickTo(txt, 'x', { duration: .25, ease: 'power3' }), ty = gsap.quickTo(txt, 'y', { duration: .25, ease: 'power3' })
        window.addEventListener('pointermove', (e) => { x(e.clientX); y(e.clientY); tx(e.clientX); ty(e.clientY) })
        $$('a, button').forEach(el => {
            el.addEventListener('pointerenter', () => gsap.to(cur, { scale: 3, duration: .3 }))
            el.addEventListener('pointerleave', () => gsap.to(cur, { scale: 1, duration: .3 }))
        })
        $$('.magnetico').forEach(b => {
            const mx = gsap.quickTo(b, 'x', { duration: .5, ease: 'power3' }), my = gsap.quickTo(b, 'y', { duration: .5, ease: 'power3' })
            b.addEventListener('pointermove', (e) => { const r = b.getBoundingClientRect(); mx((e.clientX - r.left - r.width / 2) * .35); my((e.clientY - r.top - r.height / 2) * .35) })
            b.addEventListener('pointerleave', () => { mx(0); my(0) })
        })
    }
    // Sobre fotos: el cursor crece y dice "Ver"
    $$('.plato__foto, .fiesta__foto, .carta__fotos, .collage__foto, .casa__logo').forEach(el => {
        if (el.dataset.cursor) return
        el.dataset.cursor = '1'
        el.addEventListener('pointerenter', () => { gsap.to(cur, { scale: 5.5, background: '#EFB52E', mixBlendMode: 'normal', duration: .35 }); gsap.to(txt, { autoAlpha: 1, duration: .2 }) })
        el.addEventListener('pointerleave', () => { gsap.to(cur, { scale: 1, clearProps: 'background,mixBlendMode', duration: .35 }); gsap.to(txt, { autoAlpha: 0, duration: .2 }) })
    })
}

window.addEventListener('load', () => ScrollTrigger.refresh())
