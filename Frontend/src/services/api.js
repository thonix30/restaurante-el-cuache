import axios from 'axios';

// En producción la app vive en Frontend/dist y la API en Backend/api/v1:
// se calcula relativa a la página para funcionar en cualquier carpeta/dominio.
const baseURL = import.meta.env.VITE_API_URL
    || new URL('../../Backend/api/v1', window.location.href.split('#')[0]).href;

export const TOKEN_KEY = 'cuache_token';
export const USER_KEY = 'cuache_usuario';

const api = axios.create({
    baseURL,
    timeout: 20000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// Request interceptor for API calls
api.interceptors.request.use(
    config => {
        let token = null;
        try { token = localStorage.getItem(TOKEN_KEY); } catch { /* modo privado */ }
        if (token && !config.skipAuth) {
            config.headers['Authorization'] = `Bearer ${token}`;
        }
        return config;
    },
    error => {
        return Promise.reject(error);
    }
);

/**
 * Errores normalizados: siempre un Error con .message legible en español,
 * .status y .payload (lo que mandó el servidor, ej. retry_after, errors{}).
 */
api.interceptors.response.use(
    res => res,
    error => {
        const res = error.response;
        const payload = res?.data || {};
        let msg = payload.message;
        if (!res) msg = navigator.onLine ? 'No pudimos conectar con el servidor.' : 'Sin conexión a internet.';
        if (!msg) msg = 'Ocurrió un error inesperado.';
        const err = new Error(msg);
        err.status = res?.status || 0;
        err.payload = payload;
        err.errors = payload.errors || {};

        // Sesión vencida o revocada → al login (excepto en el propio login)
        if (err.status === 401 && !error.config?.skipAuth && !error.config?.url?.includes('/auth/login')) {
            try { localStorage.removeItem(TOKEN_KEY); localStorage.removeItem(USER_KEY); } catch { /* noop */ }
            if (!location.hash.startsWith('#/login')) {
                location.hash = '#/login?expirada=1&redirect=' + encodeURIComponent(location.hash.slice(1));
            }
        }
        return Promise.reject(err);
    }
);

/** Atajo: devuelve directamente `data` de la respuesta { status, message, data }. */
export const get = (url, params, config) => api.get(url, { params, ...config }).then(r => r.data.data);
export const post = (url, body, config) => api.post(url, body, config).then(r => r.data.data);
export const put = (url, body, config) => api.put(url, body, config).then(r => r.data.data);
export const del = (url, config) => api.delete(url, config).then(r => r.data.data);

/** Descarga un archivo privado con el token (nunca en la URL) y lo abre en otra pestaña. */
export const openFile = async (url) => {
    const res = await api.get(url, { responseType: 'blob' });
    const blobUrl = URL.createObjectURL(res.data);
    window.open(blobUrl, '_blank', 'noopener');
    setTimeout(() => URL.revokeObjectURL(blobUrl), 60000);
};

export const fileUrl = async (url) => {
    const res = await api.get(url, { responseType: 'blob' });
    return URL.createObjectURL(res.data);
};

export default api;
