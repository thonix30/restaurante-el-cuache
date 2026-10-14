import {
    PhChartLineUp, PhSquaresFour, PhNotePencil, PhBellRinging, PhCookingPot, PhToggleRight, PhForkKnife,
    PhPackage, PhCashRegister, PhClockCounterClockwise, PhShieldCheck, PhQrCode, PhUsersThree, PhCalendarDots,
    PhFingerprint, PhTray, PhGear, PhUserCircle, PhBookOpenText,
} from '@phosphor-icons/vue'

/**
 * Menú de navegación por rol. `mobile` = aparece en la barra inferior del
 * celular (máx. 4 por rol; el resto va en "Más").
 */
export const NAV = [
    { section: 'Operación', items: [
        { to: '/panel', label: 'Panel', icon: PhChartLineUp, roles: ['Admin'], mobile: 1 },
        { to: '/salon', label: 'Salón', icon: PhSquaresFour, roles: ['Admin', 'Mesero'], mobile: 2 },
        { to: '/pedido', label: 'Tomar pedido', short: 'Pedido', icon: PhNotePencil, roles: ['Admin', 'Mesero'], mobile: 3 },
        { to: '/avisos', label: 'Avisos', icon: PhBellRinging, roles: ['Mesero'], mobile: 4, badge: 'avisos' },
        { to: '/carta', label: 'Carta para clientes', short: 'Carta', icon: PhBookOpenText, roles: ['Admin', 'Mesero'] },
        { to: '/cocina', label: 'Cocina', icon: PhCookingPot, roles: ['Admin', 'Cocinero'], mobile: 1, badge: 'cocina' },
        { to: '/disponibilidad', label: 'Disponibilidad', short: 'Agotados', icon: PhToggleRight, roles: ['Admin', 'Cocinero'], mobile: 2 },
        { to: '/caja', label: 'Caja', icon: PhCashRegister, roles: ['Admin'], mobile: 4 },
    ] },
    { section: 'Gestión', items: [
        { to: '/catalogo', label: 'Carta y mesas', icon: PhForkKnife, roles: ['Admin'] },
        { to: '/inventario', label: 'Inventario', icon: PhPackage, roles: ['Admin'], badge: 'stock' },
        { to: '/historial', label: 'Historial', icon: PhClockCounterClockwise, roles: ['Admin'] },
        { to: '/auditoria', label: 'Auditoría', icon: PhShieldCheck, roles: ['Admin'] },
        { to: '/qr', label: 'Códigos QR', icon: PhQrCode, roles: ['Admin'] },
    ] },
    { section: 'Equipo', items: [
        { to: '/personal', label: 'Personal', icon: PhUsersThree, roles: ['Admin'] },
        { to: '/turnos', label: 'Turnos', icon: PhCalendarDots, roles: ['Admin'] },
        { to: '/asistencia', label: 'Asistencia', icon: PhFingerprint, roles: ['Admin'] },
        { to: '/solicitudes', label: 'Solicitudes', icon: PhTray, roles: ['Admin'], badge: 'solicitudes' },
        { to: '/ajustes', label: 'Ajustes', icon: PhGear, roles: ['Admin'] },
        { to: '/perfil', label: 'Mi perfil', short: 'Perfil', icon: PhUserCircle, roles: ['Admin', 'Mesero', 'Cocinero'], mobile: 5 },
    ] },
]

export const navFor = (rol) => NAV
    .map(s => ({ ...s, items: s.items.filter(i => i.roles.includes(rol)) }))
    .filter(s => s.items.length)

export const mobileNavFor = (rol) => NAV.flatMap(s => s.items)
    .filter(i => i.roles.includes(rol) && i.mobile)
    .sort((a, b) => a.mobile - b.mobile)
    .slice(0, 4)
