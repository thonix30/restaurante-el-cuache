<template>
  <div class="space-y-6">
    <section>
      <div class="flex items-center justify-between mb-3">
        <p class="eyebrow">Mesas que esperan · {{ ordenados.length }}</p>
        <span class="text-[11px] text-ink-3">Ordenado por tiempo de espera</span>
      </div>
      <EmptyState v-if="!ordenados.length" :icon="PhBellSimpleSlash" title="Todo tranquilo" text="Cuando una mesa llame al mesero o pida la cuenta aparecerá aquí." />
      <TransitionGroup name="list" tag="ul" class="space-y-2.5 relative">
        <li v-for="l in ordenados" :key="l.id"
            class="card p-3.5 flex items-center gap-3 transition-colors"
            :class="esperaDe(l) > 180 ? 'border-warn/60 bg-warn/[.06]' : 'border-gold/40'">
          <span class="grid place-items-center w-12 h-12 rounded-2xl shrink-0 pulse-gold"
                :class="l.tipo === 'mesero' ? 'bg-gold/20 text-gold' : 'bg-sage/20 text-sage'">
            <component :is="l.tipo === 'mesero' ? PhBellRinging : PhReceipt" :size="24" weight="duotone" />
          </span>
          <div class="flex-1 min-w-0">
            <p class="font-extrabold text-ink">
              Mesa {{ l.mesa_numero }} <span class="text-ink-3 font-semibold">·</span>
              <span class="font-semibold">{{ l.tipo === 'mesero' ? 'Llamando' : 'Pide la cuenta' }}</span>
            </p>
            <p class="text-xs text-ink-3 flex items-center gap-1.5 mt-0.5">
              <PhTimer :size="13" />
              <span class="num" :class="esperaDe(l) > 180 ? 'text-warn font-bold' : ''">{{ espera(esperaDe(l)) }}</span>
              <template v-if="l.metodo_pago"> · <span class="capitalize">{{ l.metodo_pago }}</span></template>
            </p>
          </div>
          <button class="btn btn-gold btn-sm" @click="atender(l)"><PhCheck :size="16" weight="bold" /> Atender</button>
        </li>
      </TransitionGroup>
    </section>

    <section v-if="rt.listas.length">
      <p class="eyebrow mb-3">Listo para servir · {{ rt.listas.length }}</p>
      <ul class="space-y-2.5">
        <li v-for="c in rt.listas" :key="c.id" class="card p-3.5 flex items-center gap-3 border-ok/40">
          <span class="grid place-items-center w-12 h-12 rounded-2xl bg-ok/15 text-ok shrink-0"><PhCookingPot :size="24" weight="duotone" /></span>
          <div class="flex-1 min-w-0">
            <p class="font-extrabold text-ink">Mesa {{ c.mesa_numero ?? '—' }} · Comanda #{{ c.id }}</p>
            <p class="text-xs text-ink-3 num">Lista hace {{ espera(since(c.listo_en)) }}</p>
          </div>
          <button class="btn btn-ok btn-sm" @click="entregar(c)"><PhHandPalm :size="16" /> Entregado</button>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { PhBellRinging, PhReceipt, PhCheck, PhTimer, PhCookingPot, PhHandPalm, PhBellSimpleSlash } from '@phosphor-icons/vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { useRealtimeStore } from '@/stores/realtime'
import { useUiStore } from '@/stores/ui'
import { useNow } from '@/composables/useNow'
import { espera } from '@/utils/format'
import { salonService } from '@/services'

// Bandeja de avisos: llamados de mesa (más antiguos primero, ámbar a los 3 min) y comandas listas
const rt = useRealtimeStore()
const ui = useUiStore()
const { since } = useNow()
const esperaDe = (l) => since(l.creado_en)
const ordenados = computed(() => [...rt.llamados].sort((a, b) => a.creado_en.localeCompare(b.creado_en)))

const atender = async (l) => {
  try { await rt.atender(l.id); ui.toast(`Mesa ${l.mesa_numero} atendida`) } catch (e) { ui.error(e) }
}
const entregar = async (c) => {
  try { await salonService.estadoComanda(c.id, 'entregado'); rt.poll() } catch (e) { ui.error(e) }
}
</script>
