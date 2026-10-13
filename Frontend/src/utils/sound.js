/**
 * Sonidos de aviso con WebAudio (sin archivos). Singleton de módulo: la
 * preferencia sobrevive a la navegación y se guarda en localStorage.
 * Los navegadores exigen un gesto del usuario para arrancar el audio, así
 * que se "arma" con el primer toque en pantalla.
 */
import { ref } from 'vue'

const KEY = 'cuache_sonido'
let saved = '1'
try { saved = localStorage.getItem(KEY) ?? '1' } catch { /* noop */ }
export const soundEnabled = ref(saved === '1')
let ctx = null

function ensure() {
    if (!ctx) ctx = new (window.AudioContext || window.webkitAudioContext)()
    if (ctx.state === 'suspended') ctx.resume()
    return ctx
}

if (typeof window !== 'undefined') {
    const armar = () => { try { ensure() } catch { /* noop */ } }
    window.addEventListener('pointerdown', armar, { once: true })
    window.addEventListener('keydown', armar, { once: true })
}

export function toggleSound() {
    soundEnabled.value = !soundEnabled.value
    try { localStorage.setItem(KEY, soundEnabled.value ? '1' : '0') } catch { /* noop */ }
    if (soundEnabled.value) play('ready')
}

function tone(c, t, freq, dur, type = 'sine', vol = 0.35) {
    const o = c.createOscillator()
    const g = c.createGain()
    o.type = type
    o.frequency.setValueAtTime(freq, t)
    g.gain.setValueAtTime(0, t)
    g.gain.linearRampToValueAtTime(vol, t + 0.03)
    g.gain.exponentialRampToValueAtTime(0.001, t + dur)
    o.connect(g).connect(c.destination)
    o.start(t)
    o.stop(t + dur + 0.05)
}

/**
 * call  → campanita dorada (llamado de mesa)
 * bill  → dos notas graves (pidieron la cuenta)
 * order → timbre de comanda nueva en cocina (se repite)
 * ready → acorde corto: comanda lista para servir
 */
export function play(kind = 'call') {
    if (!soundEnabled.value) return
    let c
    try { c = ensure() } catch { return }
    const t = c.currentTime
    if (kind === 'call') { tone(c, t, 1318.5, .5, 'triangle'); tone(c, t + .16, 1760, .7, 'triangle') }
    if (kind === 'bill') { tone(c, t, 659.3, .35, 'sine', .4); tone(c, t + .22, 880, .5, 'sine', .4) }
    if (kind === 'order') for (let i = 0; i < 3; i++) { tone(c, t + i * .8, 880, .35); tone(c, t + i * .8 + .18, 1108.7, .45) }
    if (kind === 'ready') { tone(c, t, 784, .4, 'triangle'); tone(c, t + .12, 988, .4, 'triangle'); tone(c, t + .24, 1175, .6, 'triangle') }
}

export function vibrate(pattern = [120, 60, 120]) {
    try { navigator.vibrate?.(pattern) } catch { /* noop */ }
}
