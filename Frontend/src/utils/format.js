// Formatos en español (Guatemala) usados en toda la app.
const nf = new Intl.NumberFormat('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

export const money = (n) => 'Q' + nf.format(Number(n) || 0)
export const moneyShort = (n) => {
    const v = Number(n) || 0
    if (Math.abs(v) >= 10000) return 'Q' + (v / 1000).toFixed(1).replace('.0', '') + 'k'
    return 'Q' + Math.round(v).toLocaleString('es-GT')
}

// Fechas del backend: "YYYY-MM-DD HH:MM:SS" en hora local de Guatemala
export const toDate = (s) => (s instanceof Date ? s : s ? new Date(String(s).replace(' ', 'T')) : null)
export const hora = (s) => { const d = toDate(s); return d ? d.toLocaleTimeString('es-GT', { hour: '2-digit', minute: '2-digit', hour12: false }) : '—' }
export const fecha = (s, opts = { day: 'numeric', month: 'short' }) => { const d = toDate(s); return d ? d.toLocaleDateString('es-GT', opts) : '—' }
export const fechaLarga = (s) => fecha(s, { weekday: 'long', day: 'numeric', month: 'long' })
export const fechaHora = (s) => (s ? `${fecha(s)} · ${hora(s)}` : '—')
export const isoDate = (d = new Date()) => {
    const x = toDate(d)
    return `${x.getFullYear()}-${String(x.getMonth() + 1).padStart(2, '0')}-${String(x.getDate()).padStart(2, '0')}`
}
export const addDays = (iso, n) => { const d = toDate(iso + ' 12:00:00'); d.setDate(d.getDate() + n); return isoDate(d) }
export const weekStart = (iso) => { const d = toDate(iso + ' 12:00:00'); const dow = (d.getDay() + 6) % 7; d.setDate(d.getDate() - dow); return isoDate(d) }
export const diaCorto = (iso) => fecha(iso + ' 12:00:00', { weekday: 'short' }).replace('.', '')

/** "2 min", "1 h 05", "30 s" */
export const espera = (seg) => {
    seg = Math.max(0, Math.floor(seg))
    if (seg < 60) return `${seg} s`
    const m = Math.floor(seg / 60)
    if (m < 60) return `${m} min`
    return `${Math.floor(m / 60)} h ${String(m % 60).padStart(2, '0')}`
}
/** Cronómetro mm:ss o h:mm:ss */
export const crono = (seg) => {
    seg = Math.max(0, Math.floor(seg))
    const h = Math.floor(seg / 3600), m = Math.floor((seg % 3600) / 60), s = seg % 60
    return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0')
}
export const horasMin = (min) => {
    min = Math.round(min || 0)
    const h = Math.floor(min / 60), m = min % 60
    return h ? `${h} h${m ? ' ' + String(m).padStart(2, '0') : ''}` : `${m} min`
}
export const hace = (s, now = Date.now()) => {
    const d = toDate(s)
    if (!d) return ''
    const seg = (now - d.getTime()) / 1000
    if (seg < 45) return 'hace un momento'
    return 'hace ' + espera(seg)
}

export const initials = (name = '') => name.split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase()

// Color de chip por puesto (planificador, lista de personal)
export const PUESTO_COLOR = {
    Administrador: 'gold', Mesero: 'info', Cocinero: 'warn', Bartender: 'sage', Cajero: 'ok', Ayudante: 'neutral',
}
export const TURNO_TIPO = {
    manana: { label: 'Mañana', color: 'var(--turno-manana)' },
    tarde: { label: 'Tarde', color: 'var(--turno-tarde)' },
    noche: { label: 'Noche', color: 'var(--turno-noche)' },
    personalizado: { label: 'Personalizado', color: 'var(--turno-custom)' },
}

export const pct = (v) => (v == null ? '—' : `${v > 0 ? '+' : ''}${v}%`)

/** Exporta filas a CSV compatible con Excel (BOM UTF-8). */
export const downloadCsv = (filename, headers, rows) => {
    const esc = (v) => {
        const s = v == null ? '' : String(v)
        return /[",\n;]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s
    }
    const csv = '﻿' + [headers, ...rows].map(r => r.map(esc).join(',')).join('\r\n')
    const a = document.createElement('a')
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
    a.download = filename
    a.click()
    setTimeout(() => URL.revokeObjectURL(a.href), 2000)
}
