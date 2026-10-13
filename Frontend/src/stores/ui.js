import { defineStore } from 'pinia'
import { ref } from 'vue'

let nextId = 1

export const useUiStore = defineStore('ui', () => {
    let saved = null
    try { saved = localStorage.getItem('cuache_tema') } catch { /* noop */ }
    const theme = ref(saved === 'light' ? 'light' : 'dark')
    const toasts = ref([])

    const applyTheme = () => {
        document.documentElement.setAttribute('data-theme', theme.value)
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme.value === 'dark' ? '#14110F' : '#F8F2E5')
    }
    const toggleTheme = () => {
        theme.value = theme.value === 'dark' ? 'light' : 'dark'
        try { localStorage.setItem('cuache_tema', theme.value) } catch { /* noop */ }
        applyTheme()
    }

    /** tipo: success | error | info | warn | call */
    const toast = (mensaje, tipo = 'success', opts = {}) => {
        const id = nextId++
        toasts.value.push({ id, mensaje, tipo, titulo: opts.titulo, accion: opts.accion })
        setTimeout(() => dismiss(id), opts.duracion ?? (tipo === 'error' ? 6000 : 3800))
        return id
    }
    const dismiss = (id) => { toasts.value = toasts.value.filter(t => t.id !== id) }
    const error = (e) => toast(e?.message || String(e), 'error')

    applyTheme()
    return { theme, toggleTheme, toasts, toast, dismiss, error }
})
