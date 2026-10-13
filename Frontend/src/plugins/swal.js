import Swal from 'sweetalert2'

/**
 * Diálogos de confirmación con la identidad de El Cuache (sweetalert2 del template,
 * estilizado con las variables del tema en style.css → .cuache-swal).
 */
const base = Swal.mixin({
    customClass: {
        popup: 'cuache-swal',
        confirmButton: 'btn btn-gold',
        cancelButton: 'btn btn-soft',
        denyButton: 'btn btn-danger',
        input: 'input',
        validationMessage: 'cuache-swal-error',
    },
    buttonsStyling: false,
    reverseButtons: true,
    showClass: { popup: 'cuache-swal-in' },
    hideClass: { popup: 'cuache-swal-out' },
})

/** ¿Seguro? → true/false */
export const confirmar = async (titulo, texto = '', { confirmar = 'Sí, continuar', peligro = false } = {}) => {
    const r = await base.fire({
        title: titulo, text: texto, showCancelButton: true, confirmButtonText: confirmar, cancelButtonText: 'Cancelar',
        customClass: { popup: 'cuache-swal', confirmButton: peligro ? 'btn btn-danger' : 'btn btn-gold', cancelButton: 'btn btn-soft' },
    })
    return r.isConfirmed
}

/** Pide un motivo obligatorio (cancelaciones, correcciones…). Devuelve el texto o null. */
export const pedirMotivo = async (titulo, texto = '', { placeholder = 'Escribe el motivo…', confirmar = 'Confirmar', peligro = true, opciones = null } = {}) => {
    const r = await base.fire({
        title: titulo, text: texto,
        input: opciones ? 'select' : 'textarea',
        inputOptions: opciones || undefined,
        inputPlaceholder: placeholder,
        showCancelButton: true, confirmButtonText: confirmar, cancelButtonText: 'Volver',
        customClass: { popup: 'cuache-swal', confirmButton: peligro ? 'btn btn-danger' : 'btn btn-gold', cancelButton: 'btn btn-soft', input: 'input' },
        inputValidator: (v) => (!v || !String(v).trim() ? 'El motivo es obligatorio.' : undefined),
    })
    return r.isConfirmed ? String(r.value).trim() : null
}

export const pedirNumero = async (titulo, texto = '', { valor = '', min = 0 } = {}) => {
    const r = await base.fire({
        title: titulo, text: texto, input: 'number', inputValue: valor, showCancelButton: true,
        confirmButtonText: 'Guardar', cancelButtonText: 'Cancelar', inputAttributes: { min, step: 1, inputmode: 'numeric' },
        inputValidator: (v) => (v === '' || Number(v) < min ? `Debe ser un número mayor o igual a ${min}.` : undefined),
    })
    return r.isConfirmed ? Number(r.value) : null
}

export default base
