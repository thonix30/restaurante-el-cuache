// Servicios por dominio (mismo patrón que exampleService.js del template).
import api, { get, post, put, del } from './api';

export const authService = {
    login: (usuario, password) => post('/auth/login', { usuario, password }, { skipAuth: true }),
    me: () => get('/auth/me'),
    logout: () => post('/auth/logout'),
    sync: () => get('/sync'),
};

export const publicService = {
    menu: () => get('/public/menu', null, { skipAuth: true }),
    estado: (token) => get(`/public/mesa/${token}/estado`, null, { skipAuth: true }),
    pedir: (token, items) => post(`/public/mesa/${token}/pedido`, { items }, { skipAuth: true }),
    llamar: (token, tipo, metodo_pago) => post(`/public/mesa/${token}/llamado`, { tipo, metodo_pago }, { skipAuth: true }),
    cancelarLlamado: (token, id) => del(`/public/mesa/${token}/llamado/${id}`, { skipAuth: true }),
    kioscoIdentificar: (pin) => post('/kiosco/identificar', { pin }, { skipAuth: true }),
    kioscoMarcar: (pin, tipo, foto) => post('/kiosco/marcar', { pin, tipo, foto }, { skipAuth: true }),
};

export const catalogoService = {
    productos: (params) => get('/productos', params),
    crearProducto: (d) => post('/productos', d),
    editarProducto: (id, d) => put(`/productos/${id}`, d),
    borrarProducto: (id) => del(`/productos/${id}`),
    disponibilidad: (id, disponible, stock) => put(`/productos/${id}/disponibilidad`, { disponible, stock }),
    categorias: () => get('/categorias'),
    guardarCategoria: (d) => d.id ? put(`/categorias/${d.id}`, d) : post('/categorias', d),
    borrarCategoria: (id) => del(`/categorias/${id}`),
    ordenCategorias: (ids) => put('/categorias/orden', { ids }),
    mesas: (todas = false) => get('/mesas', todas ? { todas: 1 } : null),
    guardarMesa: (d) => d.id ? put(`/mesas/${d.id}`, d) : post('/mesas', d),
    borrarMesa: (id) => del(`/mesas/${id}`),
    posiciones: (mesas) => put('/mesas/posiciones', { mesas }),
    regenerarQr: (id) => post(`/mesas/${id}/qr`),
    configuracion: () => get('/configuracion'),
    guardarConfiguracion: (d) => put('/configuracion', d),
    subirImagen: (file) => {
        const fd = new FormData();
        fd.append('imagen', file);
        return api.post('/uploads/imagen', fd, { headers: { 'Content-Type': 'multipart/form-data' } }).then(r => r.data.data);
    },
};

export const salonService = {
    salon: () => get('/salon'),
    abrirMesa: (id, personas) => post(`/mesas/${id}/abrir`, { personas }),
    cuentaMesa: (id) => get(`/mesas/${id}/cuenta`),
    pedir: (mesaId, items, personas) => post(`/mesas/${mesaId}/pedido`, { items, personas }),
    cuenta: (id) => get(`/cuentas/${id}`),
    cobrar: (id, d) => post(`/cuentas/${id}/pagos`, d),
    descuento: (id, d) => put(`/cuentas/${id}/descuento`, d),
    personas: (id, personas) => put(`/cuentas/${id}/personas`, { personas }),
    mover: (id, mesa_id) => put(`/cuentas/${id}/mesa`, { mesa_id }),
    cancelarCuenta: (id, motivo) => post(`/cuentas/${id}/cancelar`, { motivo }),
    cancelarItem: (id, cantidad, motivo) => post(`/items/${id}/cancelar`, { cantidad, motivo }),
    anularPago: (id, motivo) => post(`/pagos/${id}/anular`, { motivo }),
    atender: (id) => post(`/llamados/${id}/atender`),
    comandas: () => get('/cocina/comandas'),
    estadoComanda: (id, estado) => put(`/cocina/comandas/${id}/estado`, estado ? { estado } : {}),
};

export const adminService = {
    caja: () => get('/caja'),
    abrirCaja: (monto_inicial, notas) => post('/caja/abrir', { monto_inicial, notas }),
    cerrarCaja: (efectivo_contado, notas) => post('/caja/cerrar', { efectivo_contado, notas }),
    historialCaja: () => get('/caja/historial'),
    inventario: () => get('/inventario'),
    ajustar: (id, stock, motivo) => post(`/inventario/${id}/ajuste`, { stock, motivo }),
    compras: (params) => get('/compras', params),
    registrarCompra: (d) => post('/compras', d),
    historial: (params) => get('/historial', params),
    auditoria: (params) => get('/auditoria', params),
    estadisticas: (params) => get('/estadisticas', params),
};

export const personalService = {
    empleados: (params) => get('/empleados', params),
    empleado: (id) => get(`/empleados/${id}`),
    crear: (d) => post('/empleados', d),
    editar: (id, d) => put(`/empleados/${id}`, d),
    baja: (id, motivo, fecha) => post(`/empleados/${id}/baja`, { motivo, fecha }),
    acceso: (id, d) => put(`/empleados/${id}/acceso`, d),
    pin: (id, pin) => put(`/empleados/${id}/pin`, { pin }),
    asistenciaMes: (id, mes) => get(`/empleados/${id}/asistencia`, { mes }),
    subirDocumento: (id, file, meta) => {
        const fd = new FormData();
        fd.append('archivo', file);
        Object.entries(meta).forEach(([k, v]) => v != null && fd.append(k, v));
        return api.post(`/empleados/${id}/documentos`, fd, { headers: { 'Content-Type': 'multipart/form-data' } }).then(r => r.data.data);
    },
    borrarDocumento: (id, doc) => del(`/empleados/${id}/documentos/${doc}`),
    alertas: () => get('/alertas'),
    semana: (semana) => get('/turnos', { semana }),
    crearTurno: (d) => post('/turnos', d),
    editarTurno: (id, d) => put(`/turnos/${id}`, d),
    borrarTurno: (id) => del(`/turnos/${id}`),
    copiarSemana: (semana) => post('/turnos/copiar', { semana, reemplazar: true }),
    publicar: (semana) => post('/turnos/publicar', { semana }),
    limpiar: (semana) => post('/turnos/limpiar', { semana }),
    plantillas: () => get('/turnos/plantillas'),
    guardarPlantilla: (nombre, semana, rotativa) => post('/turnos/plantillas', { nombre, semana, rotativa }),
    aplicarPlantilla: (id, semana) => post(`/turnos/plantillas/${id}/aplicar`, { semana, reemplazar: true }),
    borrarPlantilla: (id) => del(`/turnos/plantillas/${id}`),
    asistencia: (fecha) => get('/asistencia', { fecha }),
    corregir: (d) => post('/asistencia/corregir', d),
    justificar: (d, file) => {
        const fd = new FormData();
        Object.entries(d).forEach(([k, v]) => fd.append(k, v ?? ''));
        if (file) fd.append('archivo', file);
        return api.post('/asistencia/justificar', fd, { headers: { 'Content-Type': 'multipart/form-data' } }).then(r => r.data.data);
    },
    reporteHoras: (desde, hasta) => get('/reportes/horas', { desde, hasta }),
    solicitudes: (estado) => get('/solicitudes', { estado }),
    resolver: (id, aprobar, respuesta) => put(`/solicitudes/${id}/resolver`, { aprobar, respuesta }),
    perfil: () => get('/perfil'),
    marcar: (tipo, foto) => post('/perfil/marcar', { tipo, foto }),
    solicitar: (d) => post('/perfil/solicitudes', d),
    cancelarSolicitud: (id) => put(`/perfil/solicitudes/${id}/cancelar`),
    cambiarPassword: (actual, nueva) => put('/perfil/password', { actual, nueva }),
};
