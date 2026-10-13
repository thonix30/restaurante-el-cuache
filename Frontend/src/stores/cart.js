import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'

/**
 * Carrito "Tu Orden" del cliente (y del mesero en Toma de pedidos).
 * Se guarda por mesa en localStorage: si el cliente recarga, no pierde su orden.
 */
export const useCartStore = defineStore('cart', () => {
    const key = ref('cuache_carrito')
    const items = ref([])

    const load = (scope) => {
        key.value = `cuache_carrito_${scope}`
        try { items.value = JSON.parse(localStorage.getItem(key.value) || '[]') } catch { items.value = [] }
    }
    watch(items, (v) => { try { localStorage.setItem(key.value, JSON.stringify(v)) } catch { /* noop */ } }, { deep: true })

    const uid = () => Math.random().toString(36).slice(2, 9)

    const add = (p, cantidad = 1, notas = '') => {
        const ex = items.value.find(i => i.producto_id === p.id && (i.notas || '') === (notas || ''))
        if (ex) ex.cantidad += cantidad
        else items.value.push({ uid: uid(), producto_id: p.id, nombre: p.nombre, precio: p.precio, imagen: p.imagen, imagen_posicion: p.imagen_posicion, cantidad, notas, no_disponible: false })
    }
    const inc = (it) => { it.cantidad++ }
    const dec = (it) => { if (it.cantidad > 1) it.cantidad--; else remove(it) }
    const remove = (it) => { items.value = items.value.filter(i => i.uid !== it.uid) }
    const clear = () => { items.value = [] }
    const qtyOf = (productoId) => items.value.filter(i => i.producto_id === productoId).reduce((s, i) => s + i.cantidad, 0)

    /** Marca en el carrito los platillos que se agotaron mientras se armaba la orden. */
    const marcarAgotados = (ids) => {
        const set = new Set(ids)
        const recien = []
        for (const it of items.value) {
            const agotado = set.has(it.producto_id)
            if (agotado && !it.no_disponible) recien.push(it)
            it.no_disponible = agotado
        }
        return recien
    }

    const count = computed(() => items.value.reduce((s, i) => s + i.cantidad, 0))
    const total = computed(() => items.value.reduce((s, i) => s + i.precio * i.cantidad, 0))
    const bloqueado = computed(() => items.value.some(i => i.no_disponible))
    const payload = computed(() => items.value.map(i => ({ producto_id: i.producto_id, cantidad: i.cantidad, notas: i.notas || null })))

    return { items, load, add, inc, dec, remove, clear, qtyOf, marcarAgotados, count, total, bloqueado, payload }
})
