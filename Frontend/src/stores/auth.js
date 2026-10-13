import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authService } from '@/services'
import { TOKEN_KEY, USER_KEY } from '@/services/api'

const read = (k) => { try { return localStorage.getItem(k) } catch { return null } }
const write = (k, v) => { try { v == null ? localStorage.removeItem(k) : localStorage.setItem(k, v) } catch { /* noop */ } }

/** Pantalla de inicio según el rol. */
export const HOME_BY_ROLE = { Admin: '/panel', Mesero: '/salon', Cocinero: '/cocina' }

export const useAuthStore = defineStore('auth', () => {
    const token = ref(read(TOKEN_KEY) || '')
    let u = null
    try { u = JSON.parse(read(USER_KEY) || 'null') } catch { /* noop */ }
    const usuario = ref(u)
    const verificado = ref(false)

    const isAuthenticated = computed(() => !!token.value && !!usuario.value)
    const rol = computed(() => usuario.value?.rol || '')
    const isAdmin = computed(() => rol.value === 'Admin')
    const home = computed(() => HOME_BY_ROLE[rol.value] || '/perfil')

    const login = async (user, password) => {
        const data = await authService.login(user, password)
        token.value = data.token
        usuario.value = data.usuario
        write(TOKEN_KEY, data.token)
        write(USER_KEY, JSON.stringify(data.usuario))
        verificado.value = true
        return data.usuario
    }

    const clear = () => {
        token.value = ''
        usuario.value = null
        verificado.value = false
        write(TOKEN_KEY, null)
        write(USER_KEY, null)
    }

    const logout = async () => {
        try { await authService.logout() } catch { /* noop */ }
        clear()
    }

    /** Valida el token contra el servidor (una vez por carga de la app). */
    const verify = async () => {
        if (!token.value) return false
        if (verificado.value) return true
        try {
            usuario.value = await authService.me()
            write(USER_KEY, JSON.stringify(usuario.value))
            verificado.value = true
            return true
        } catch (e) {
            if (e.status === 401 || e.status === 403) { clear(); return false }
            return !!usuario.value // sin conexión: se confía en la sesión guardada
        }
    }

    const can = (...roles) => roles.includes(rol.value)

    return { token, usuario, isAuthenticated, rol, isAdmin, home, login, logout, verify, clear, can }
})
