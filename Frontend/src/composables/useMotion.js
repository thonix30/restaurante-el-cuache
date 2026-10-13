import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'
import { onMounted, onBeforeUnmount, nextTick } from 'vue'

gsap.registerPlugin(ScrollTrigger)

export const reduced = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

/** Entrada escalonada de los elementos [data-reveal] dentro de root. */
export function revealIn(root, opts = {}) {
    if (!root || reduced()) return
    const els = root.querySelectorAll(opts.selector || '[data-reveal]')
    if (!els.length) return
    gsap.fromTo(els,
        { y: opts.y ?? 18, opacity: 0, filter: 'blur(6px)' },
        { y: 0, opacity: 1, filter: 'blur(0px)', duration: opts.duration ?? .7, ease: 'power3.out', stagger: opts.stagger ?? .06, delay: opts.delay ?? 0, clearProps: 'filter,transform' })
}

/** Igual que revealIn pero cada elemento aparece al entrar en pantalla (scroll). */
export function revealOnScroll(root, selector = '[data-scroll]') {
    if (!root || reduced()) return []
    return [...root.querySelectorAll(selector)].map(el =>
        gsap.fromTo(el, { y: 40, opacity: 0 }, {
            y: 0, opacity: 1, duration: .9, ease: 'power3.out',
            scrollTrigger: { trigger: el, start: 'top 88%', once: true },
        }))
}

/** Parallax: el elemento se desplaza más lento que el scroll. */
export function parallax(el, { y = 120, scale = 1.12, trigger, scroller } = {}) {
    if (!el || reduced()) return null
    return gsap.fromTo(el, { yPercent: 0, scale }, {
        yPercent: y / 10, ease: 'none',
        scrollTrigger: { trigger: trigger || el, start: 'top top', end: 'bottom top', scrub: true, scroller },
    })
}

/** Cuenta animada de un número dentro de un elemento (KPIs). */
export function countTo(el, value, format = (v) => Math.round(v), duration = 1.1) {
    if (!el) return
    if (reduced()) { el.textContent = format(value); return }
    const obj = { v: Number(el.dataset.v || 0) }
    gsap.to(obj, {
        v: value, duration, ease: 'power2.out',
        onUpdate: () => { el.textContent = format(obj.v) },
        onComplete: () => { el.dataset.v = value },
    })
}

/** Efecto "imán" sutil para botones destacados (solo con mouse). */
export function magnetic(el, strength = 0.25) {
    if (!el || reduced() || !window.matchMedia('(pointer: fine)').matches) return () => {}
    const move = (e) => {
        const r = el.getBoundingClientRect()
        gsap.to(el, { x: (e.clientX - r.left - r.width / 2) * strength, y: (e.clientY - r.top - r.height / 2) * strength, duration: .4, ease: 'power3.out' })
    }
    const leave = () => gsap.to(el, { x: 0, y: 0, duration: .6, ease: 'elastic.out(1,.4)' })
    el.addEventListener('pointermove', move)
    el.addEventListener('pointerleave', leave)
    return () => { el.removeEventListener('pointermove', move); el.removeEventListener('pointerleave', leave) }
}

/** Animación de "pop" (agregar al carrito, confirmar). */
export function pop(el) {
    if (!el || reduced()) return
    gsap.fromTo(el, { scale: 1 }, { scale: 1.18, duration: .14, yoyo: true, repeat: 1, ease: 'power2.out' })
}

/** Vuela una imagen desde un punto hasta el botón del carrito. */
export function flyTo(fromEl, toEl) {
    if (!fromEl || !toEl || reduced()) return
    const a = fromEl.getBoundingClientRect(), b = toEl.getBoundingClientRect()
    const ghost = fromEl.cloneNode(true)
    Object.assign(ghost.style, { position: 'fixed', left: a.left + 'px', top: a.top + 'px', width: a.width + 'px', height: a.height + 'px', zIndex: 9999, pointerEvents: 'none', borderRadius: '16px', objectFit: 'cover' })
    document.body.appendChild(ghost)
    gsap.to(ghost, {
        left: b.left + b.width / 2 - 14, top: b.top + b.height / 2 - 14, width: 28, height: 28, opacity: .4, borderRadius: '50%',
        duration: .7, ease: 'power3.in', onComplete: () => { ghost.remove(); pop(toEl) },
    })
}

/** Hook: ejecuta una animación al montar y limpia los ScrollTriggers creados. */
export function useMotion(fn) {
    let ctx
    onMounted(async () => {
        await nextTick()
        ctx = gsap.context(() => fn())
    })
    onBeforeUnmount(() => ctx?.revert())
}

export { gsap, ScrollTrigger }
