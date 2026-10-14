<template>
  <div>
    <PageHeader eyebrow="Mesas" title="Códigos QR" subtitle="Cada QR abre el menú digital ya asignado a su mesa. Regenera uno si sospechas que se copió: el anterior deja de funcionar.">
      <button class="btn btn-gold" :disabled="!mesas.length" @click="imprimir()"><PhPrinter :size="18" /> Imprimir todos</button>
    </PageHeader>

    <div class="card p-4 mb-6 flex flex-col md:flex-row md:items-end gap-3 no-print" data-reveal>
      <div class="flex-1">
        <label class="label">URL base del menú</label>
        <input v-model="base" class="input num text-xs" :placeholder="baseAuto" />
        <p class="text-[11px] text-ink-3 mt-1">Vacío = se usa la dirección actual del sistema. Útil si la app pública vive en otro dominio.</p>
      </div>
      <button class="btn btn-soft" @click="guardarBase"><PhFloppyDisk :size="17" /> Guardar</button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <div v-for="m in mesas" :key="m.id" class="card p-5 text-center" data-reveal>
        <p class="eyebrow">{{ m.zona }}</p>
        <p class="font-serif text-3xl mt-1">Mesa {{ m.numero }}</p>
        <div class="mx-auto my-4 w-44 h-44 rounded-2xl bg-white p-2.5 shadow-soft">
          <img v-if="qrs[m.id]" :src="qrs[m.id]" :alt="'QR mesa ' + m.numero" class="w-full h-full" />
        </div>
        <p class="text-[10px] text-ink-3 num truncate" :title="urlDe(m)">{{ urlDe(m) }}</p>
        <div class="grid grid-cols-3 gap-1.5 mt-4">
          <button class="btn btn-soft btn-sm" title="Vista previa" @click="preview = m"><PhEye :size="16" /></button>
          <a class="btn btn-soft btn-sm" :href="urlDe(m)" target="_blank" rel="noopener" title="Abrir menú"><PhArrowSquareOut :size="16" /></a>
          <button class="btn btn-soft btn-sm" title="Regenerar QR" @click="regenerar(m)"><PhArrowsClockwise :size="16" /></button>
        </div>
      </div>
    </div>

    <!-- Vista previa de la tarjeta imprimible -->
    <Modal :model-value="!!preview" @update:model-value="preview = null" title="Vista previa" size="sm">
      <div v-if="preview" class="mx-auto" v-html="tarjetaHtml(preview)" />
      <template #footer>
        <button class="btn btn-soft" @click="preview = null">Cerrar</button>
        <button class="btn btn-gold" @click="imprimir([preview])"><PhPrinter :size="17" /> Imprimir esta</button>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import QRCode from 'qrcode'
import { PhPrinter, PhFloppyDisk, PhEye, PhArrowSquareOut, PhArrowsClockwise } from '@phosphor-icons/vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Modal from '@/components/ui/Modal.vue'
import { catalogoService } from '@/services'
import { useUiStore } from '@/stores/ui'
import { confirmar } from '@/plugins/swal'

const ui = useUiStore()
const mesas = ref([])
const qrs = ref({})
const base = ref('')
const preview = ref(null)
const baseAuto = window.location.href.split('#')[0]
const logoUrl = new URL('./img/logo-ink.png', baseAuto).href

const urlDe = (m) => `${(base.value || baseAuto).replace(/#.*$/, '')}#/m/${m.qr_token}`

const generar = async () => {
  const out = {}
  for (const m of mesas.value) {
    out[m.id] = await QRCode.toDataURL(urlDe(m), { width: 520, margin: 1, errorCorrectionLevel: 'M', color: { dark: '#231F1C', light: '#FFFFFF' } })
  }
  qrs.value = out
}
watch(base, () => generar())

// Tarjeta imprimible (estilos en línea: se usa también en la ventana de impresión)
const tarjetaHtml = (m) => `
  <div style="width:260px;margin:0 auto;padding:22px 20px 18px;border-radius:22px;background:#FBF5E6;color:#231F1C;text-align:center;font-family:Manrope,Arial,sans-serif;border:1.5px solid #231F1C;box-sizing:border-box">
    <img src="${logoUrl}" style="width:96px;height:96px;object-fit:contain;display:block;margin:0 auto" alt="El Cuache"/>
    <div style="font:700 11px Manrope,Arial;letter-spacing:.25em;text-transform:uppercase;margin-top:6px;color:#8a6a2c">Escanea y pide</div>
    <div style="font:500 34px Fraunces,Georgia,serif;line-height:1;margin:6px 0 12px">Mesa ${m.numero}</div>
    <img src="${qrs.value[m.id]}" style="width:190px;height:190px;display:block;margin:0 auto;border-radius:10px;background:#fff;padding:6px" alt="QR"/>
    <div style="font-size:11px;margin-top:12px;line-height:1.4;color:#5a5047">Menú · Llamar al mesero · Pedir la cuenta</div>
  </div>`

const imprimir = (lista = mesas.value) => {
  const w = window.open('', '_blank')
  if (!w) return ui.toast('Permite las ventanas emergentes para imprimir', 'warn')
  w.document.write(`<!doctype html><html lang="es"><head><meta charset="utf-8"><title>QR El Cuache</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500&family=Manrope:wght@500;700&display=swap" rel="stylesheet">
    <style>@page{margin:12mm}body{margin:0;background:#fff}.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14mm 8mm;justify-items:center}
    .grid>div{break-inside:avoid}</style></head><body><div class="grid">${lista.map(m => `<div>${tarjetaHtml(m)}</div>`).join('')}</div>
    <script>window.onload=()=>setTimeout(()=>{window.print()},400)<\/script></body></html>`)
  w.document.close()
}

const guardarBase = async () => {
  try { await catalogoService.guardarConfiguracion({ url_base_menu: base.value.trim() }); ui.toast('URL guardada') } catch (e) { ui.error(e) }
}
const regenerar = async (m) => {
  if (!await confirmar(`¿Nuevo QR para la mesa ${m.numero}?`, 'El código impreso actual dejará de funcionar; tendrás que imprimir el nuevo.', { confirmar: 'Regenerar', peligro: true })) return
  try {
    const r = await catalogoService.regenerarQr(m.id)
    m.qr_token = r.qr_token
    await generar()
    ui.toast('QR regenerado')
  } catch (e) { ui.error(e) }
}

onMounted(async () => {
  try {
    const [m, cfg] = await Promise.all([catalogoService.mesas(), catalogoService.configuracion()])
    mesas.value = m
    base.value = cfg.url_base_menu || ''
    await generar()
  } catch (e) { ui.error(e) }
})
</script>
