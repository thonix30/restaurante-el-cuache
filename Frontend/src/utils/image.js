// Reduce una foto antes de subirla: la escala a un lado máximo y la re-codifica
// a WebP (o JPEG si el navegador no sabe codificar WebP, p. ej. Safari).
// Se hace en el navegador porque el servidor no siempre tiene GD instalado.
const LADO_MAX = 1400
const CALIDAD = 0.82

const cargar = async (file) => {
    if ('createImageBitmap' in window) {
        try { return await createImageBitmap(file, { imageOrientation: 'from-image' }) } catch { /* cae al <img> */ }
    }
    return new Promise((resolve, reject) => {
        const img = new Image()
        img.onload = () => { URL.revokeObjectURL(img.src); resolve(img) }
        img.onerror = reject
        img.src = URL.createObjectURL(file)
    })
}

const aBlob = (canvas, tipo) => new Promise(r => canvas.toBlob(r, tipo, CALIDAD))

export async function comprimirImagen(file, ladoMax = LADO_MAX) {
    let fuente
    try { fuente = await cargar(file) } catch { return file }

    const escala = Math.min(1, ladoMax / Math.max(fuente.width, fuente.height))
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(fuente.width * escala)
    canvas.height = Math.round(fuente.height * escala)
    const ctx = canvas.getContext('2d')
    ctx.imageSmoothingQuality = 'high'
    ctx.drawImage(fuente, 0, 0, canvas.width, canvas.height)
    fuente.close?.()

    let blob = await aBlob(canvas, 'image/webp')
    if (!blob || blob.type !== 'image/webp') {
        ctx.globalCompositeOperation = 'destination-over'
        ctx.fillStyle = '#fff'
        ctx.fillRect(0, 0, canvas.width, canvas.height)
        blob = await aBlob(canvas, 'image/jpeg')
    }
    if (!blob || blob.size >= file.size) return file
    const ext = blob.type === 'image/webp' ? 'webp' : 'jpg'
    return new File([blob], `foto.${ext}`, { type: blob.type })
}

/** Toma una foto de la cámara (video) y la devuelve como data URL JPEG liviana. */
export function capturarFrame(video, ancho = 480) {
    const escala = ancho / (video.videoWidth || ancho)
    const c = document.createElement('canvas')
    c.width = ancho
    c.height = Math.round((video.videoHeight || ancho) * escala)
    c.getContext('2d').drawImage(video, 0, 0, c.width, c.height)
    return c.toDataURL('image/jpeg', 0.7)
}
