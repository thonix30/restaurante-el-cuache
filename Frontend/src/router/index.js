import { createRouter, createWebHashHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

// Layouts
const MainLayout = () => import('../components/layout/MainLayout.vue');

const ALL = ['Admin', 'Mesero', 'Cocinero'];

const routes = [
    // ── Públicas ─────────────────────────────────────────────────────────
    { path: '/', name: 'Home', component: () => import('../views/public/Home.vue'), meta: { requiresAuth: false } },
    { path: '/m/:token', name: 'MenuMesa', component: () => import('../views/public/CustomerMenu.vue'), meta: { requiresAuth: false } },
    { path: '/carta', name: 'Carta', component: () => import('../views/public/CustomerMenu.vue'), meta: { requiresAuth: false } },
    { path: '/kiosco', name: 'Kiosco', component: () => import('../views/public/Kiosk.vue'), meta: { requiresAuth: false } },
    { path: '/login', name: 'Login', component: () => import('../views/auth/Login.vue'), meta: { requiresAuth: false, guest: true } },

    // ── Sistema (con sesión) ─────────────────────────────────────────────
    {
        path: '/',
        component: MainLayout,
        meta: { requiresAuth: true },
        children: [
            { path: 'panel', name: 'Panel', component: () => import('../views/admin/Dashboard.vue'), meta: { roles: ['Admin'], title: 'Panel' } },
            { path: 'salon', name: 'Salon', component: () => import('../views/salon/Salon.vue'), meta: { roles: ['Admin', 'Mesero'], title: 'Salón' } },
            { path: 'pedido', name: 'Pedido', component: () => import('../views/pedidos/TomaPedido.vue'), meta: { roles: ['Admin', 'Mesero'], title: 'Tomar pedido' } },
            { path: 'avisos', name: 'Avisos', component: () => import('../views/salon/Avisos.vue'), meta: { roles: ['Admin', 'Mesero'], title: 'Avisos' } },
            { path: 'cocina', name: 'Cocina', component: () => import('../views/cocina/Kds.vue'), meta: { roles: ['Admin', 'Cocinero'], title: 'Cocina', full: true } },
            { path: 'disponibilidad', name: 'Disponibilidad', component: () => import('../views/cocina/Disponibilidad.vue'), meta: { roles: ['Admin', 'Cocinero'], title: 'Disponibilidad' } },
            { path: 'catalogo', name: 'Catalogo', component: () => import('../views/catalogo/Catalogo.vue'), meta: { roles: ['Admin'], title: 'Carta y mesas' } },
            { path: 'inventario', name: 'Inventario', component: () => import('../views/inventario/Inventario.vue'), meta: { roles: ['Admin'], title: 'Inventario y compras' } },
            { path: 'caja', name: 'Caja', component: () => import('../views/caja/Caja.vue'), meta: { roles: ['Admin'], title: 'Caja' } },
            { path: 'historial', name: 'Historial', component: () => import('../views/historial/Historial.vue'), meta: { roles: ['Admin'], title: 'Historial de pedidos' } },
            { path: 'auditoria', name: 'Auditoria', component: () => import('../views/historial/Auditoria.vue'), meta: { roles: ['Admin'], title: 'Auditoría' } },
            { path: 'qr', name: 'QR', component: () => import('../views/qr/Codigos.vue'), meta: { roles: ['Admin'], title: 'Códigos QR' } },
            { path: 'personal', name: 'Personal', component: () => import('../views/personal/Personal.vue'), meta: { roles: ['Admin'], title: 'Personal' } },
            { path: 'personal/:id', name: 'Empleado', component: () => import('../views/personal/Empleado.vue'), meta: { roles: ['Admin'], title: 'Ficha del empleado' } },
            { path: 'turnos', name: 'Turnos', component: () => import('../views/personal/Turnos.vue'), meta: { roles: ['Admin'], title: 'Planificador de turnos' } },
            { path: 'asistencia', name: 'Asistencia', component: () => import('../views/personal/Asistencia.vue'), meta: { roles: ['Admin'], title: 'Asistencia' } },
            { path: 'solicitudes', name: 'Solicitudes', component: () => import('../views/personal/Solicitudes.vue'), meta: { roles: ['Admin'], title: 'Solicitudes y horas' } },
            { path: 'ajustes', name: 'Ajustes', component: () => import('../views/admin/Ajustes.vue'), meta: { roles: ['Admin'], title: 'Ajustes' } },
            { path: 'perfil', name: 'Perfil', component: () => import('../views/perfil/MiPerfil.vue'), meta: { roles: ALL, title: 'Mi perfil' } },
        ]
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
    history: createWebHashHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

// Guard con control de acceso por rol
router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (to.meta.requiresAuth) {
        if (!auth.token) return { name: 'Login', query: { redirect: to.fullPath } };
        const ok = await auth.verify();
        if (!ok) return { name: 'Login', query: { redirect: to.fullPath } };
        const roles = to.meta.roles;
        if (roles && !roles.includes(auth.rol)) return auth.home;
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return auth.home;
    }
});

router.afterEach((to) => {
    document.title = to.meta.title ? `${to.meta.title} · El Cuache` : 'El Cuache · Café y Restaurante';
});

export default router;
