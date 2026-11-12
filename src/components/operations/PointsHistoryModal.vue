<template>
  <div
    v-if="modelValue && client"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
    @click.self="close"
  >
    <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden my-6 flex flex-col max-h-[90vh]">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-700 font-bold">
            <Award class="h-5 w-5" />
          </div>
          <div>
            <h3 class="text-base font-bold text-slate-900">Historial de Puntos y Movimientos</h3>
            <p class="text-xs text-slate-500">
              Cliente: <span class="font-semibold text-slate-700">{{ client.name }}</span> ({{ client.document_type }} {{ client.document_number }})
            </p>
          </div>
        </div>
        <button
          type="button"
          class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition"
          @click="close"
        >
          <X class="h-5 w-5" />
        </button>
      </div>

      <!-- Client Metrics Strip -->
      <div class="grid grid-cols-3 gap-3 p-4 bg-amber-50/50 border-b border-amber-100 text-center">
        <div class="rounded-xl bg-white p-3 border border-amber-100">
          <p class="text-[11px] font-semibold text-slate-500">Saldo Actual Canjeable</p>
          <p class="text-lg font-extrabold text-amber-700 mt-0.5">
            {{ client.points_balance || 0 }} pts
          </p>
        </div>
        <div class="rounded-xl bg-white p-3 border border-amber-100">
          <p class="text-[11px] font-semibold text-slate-500">Histórico de por Vida (Ranking)</p>
          <p class="text-lg font-extrabold text-indigo-700 mt-0.5">
            {{ client.lifetime_points || 0 }} pts
          </p>
        </div>
        <div class="rounded-xl bg-white p-3 border border-amber-100">
          <p class="text-[11px] font-semibold text-slate-500">Total Operaciones</p>
          <p class="text-lg font-extrabold text-slate-800 mt-0.5">
            {{ client.total_ops || 0 }}
          </p>
        </div>
      </div>

      <!-- History List -->
      <div class="flex-1 overflow-y-auto p-5 space-y-3">
        <div v-if="loading" class="py-12 text-center">
          <Loader2 class="h-6 w-6 animate-spin text-indigo-600 mx-auto" />
          <p class="text-xs text-slate-500 mt-2">Cargando movimientos de puntos...</p>
        </div>

        <div v-else-if="history.length === 0" class="py-12 text-center text-slate-400 text-xs">
          No hay movimientos registrados de puntos para este cliente todavía.
        </div>

        <div
          v-else
          v-for="item in history"
          :key="item.history_id"
          class="flex items-start gap-3 rounded-xl border p-3.5 transition"
          :class="item.type === 'earned' ? 'border-emerald-200 bg-emerald-50/40' : 'border-amber-200 bg-amber-50/40'"
        >
          <div
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
            :class="item.type === 'earned' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
          >
            <ArrowUpRight v-if="item.type === 'earned'" class="h-4 w-4" />
            <Gift v-else class="h-4 w-4" />
          </div>

          <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-2">
              <p class="text-xs font-bold text-slate-900">
                {{ item.type === 'earned' ? 'Puntos Acumulados' : 'Canje de Premio' }}
              </p>
              <span
                class="font-mono text-xs font-extrabold"
                :class="item.points > 0 ? 'text-emerald-700' : 'text-amber-800'"
              >
                {{ item.points > 0 ? '+' : '' }}{{ item.points }} pts
              </span>
            </div>

            <p class="text-xs text-slate-600 mt-0.5">{{ item.description }}</p>

            <div class="flex flex-wrap items-center gap-3 mt-1.5 text-[11px] text-slate-500">
              <span>{{ item.created_at }}</span>
              <span>·</span>
              <span>Saldo canjeable: <strong>{{ item.balance_after }} pts</strong></span>
              <span>·</span>
              <span>Acumulado histórico: <strong>{{ item.lifetime_after }} pts</strong></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="border-t border-slate-100 px-6 py-3 bg-slate-50 text-right">
        <button
          type="button"
          class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
          @click="close"
        >
          Cerrar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Award, X, Loader2, ArrowUpRight, Gift } from '@lucide/vue'
import { useOperationsStore } from '@/stores/operations'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  client: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue'])

const operationsStore = useOperationsStore()
const loading = ref(false)
const history = ref([])

async function loadHistory() {
  if (!props.client?.client_id) return
  loading.value = true
  try {
    const res = await operationsStore.fetchLoyaltyRanking(props.client.client_id)
    history.value = res.client_history || []
  } catch (err) {
    history.value = []
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.modelValue, props.client],
  ([show, client]) => {
    if (show && client) {
      loadHistory()
    }
  }
)

function close() {
  emit('update:modelValue', false)
}
</script>

