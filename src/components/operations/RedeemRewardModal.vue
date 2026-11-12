<template>
  <div
    v-if="modelValue && reward"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
    @click.self="close"
  >
    <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden my-6">
      <!-- Modal Header -->
      <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50">
        <div class="flex items-center gap-2.5">
          <Gift class="h-5 w-5 text-amber-600" />
          <div>
            <h3 class="text-base font-bold text-slate-900">Canjear Premio por Puntos</h3>
            <p class="text-xs text-slate-500">Programa de Fidelización de Clientes</p>
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

      <!-- Form Body -->
      <form @submit.prevent="handleSubmit" class="p-6 space-y-5">
        <!-- Selected Reward Card -->
        <div class="flex items-center gap-4 rounded-xl border border-amber-200 bg-amber-50/50 p-4">
          <div class="h-16 w-16 shrink-0 rounded-xl bg-white border border-amber-200 flex items-center justify-center overflow-hidden shadow-xs">
            <img
              v-if="reward.image_url"
              :src="reward.image_url"
              :alt="reward.name"
              class="h-full w-full object-cover"
            />
            <Package v-else class="h-8 w-8 text-amber-500" />
          </div>
          <div class="min-w-0 flex-1">
            <span class="inline-block rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 uppercase">
              {{ reward.category || 'Premio' }}
            </span>
            <h4 class="text-sm font-bold text-slate-900 truncate mt-0.5">{{ reward.name }}</h4>
            <p v-if="reward.description" class="text-xs text-slate-500 line-clamp-1 mt-0.5">
              {{ reward.description }}
            </p>
            <div class="flex items-center gap-3 mt-1.5 text-xs">
              <span class="font-bold text-amber-700 flex items-center gap-1">
                <Award class="h-3.5 w-3.5" />
                {{ reward.points_cost }} Puntos
              </span>
              <span class="text-slate-500">Valor: S/ {{ Number(reward.price_soles || 0).toFixed(2) }}</span>
              <span
                class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                :class="reward.stock > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
              >
                Stock: {{ reward.stock }}
              </span>
            </div>
          </div>
        </div>

        <!-- Client Selector -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">
            Seleccionar Cliente para el Canje *
          </label>
          <div class="relative">
            <input
              v-model="clientSearch"
              type="text"
              placeholder="Buscar cliente por DNI, RUC o nombre..."
              class="w-full rounded-xl border border-slate-300 pl-9 pr-8 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
              @focus="showClientDropdown = true"
            />
            <Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <button
              v-if="clientId"
              type="button"
              class="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600"
              @click="clearClient"
            >
              <X class="h-4 w-4" />
            </button>

            <!-- Dropdown List -->
            <div
              v-if="showClientDropdown && filteredClients.length > 0"
              class="absolute left-0 right-0 z-30 mt-1 max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
            >
              <button
                v-for="c in filteredClients"
                :key="c.client_id"
                type="button"
                class="w-full px-3.5 py-2 text-left text-xs hover:bg-slate-50 flex items-center justify-between transition"
                @click="selectClient(c)"
              >
                <div>
                  <p class="font-bold text-slate-900">{{ c.name }}</p>
                  <p class="text-slate-500">{{ c.document_type }}: {{ c.document_number }}</p>
                </div>
                <span
                  class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                  :class="c.points_balance >= reward.points_cost ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                >
                  {{ c.points_balance || 0 }} pts
                </span>
              </button>
            </div>
          </div>
        </div>

        <!-- Points Comparison Card -->
        <div v-if="selectedClient" class="rounded-xl border p-4 text-xs transition" :class="canAfford ? 'border-emerald-200 bg-emerald-50/50' : 'border-rose-200 bg-rose-50/50'">
          <div class="flex items-center justify-between mb-2">
            <span class="font-semibold text-slate-700">Saldo actual del cliente:</span>
            <span class="font-bold text-sm" :class="canAfford ? 'text-emerald-700' : 'text-rose-700'">
              {{ clientPoints }} puntos
            </span>
          </div>

          <div class="flex items-center justify-between mb-2">
            <span class="font-semibold text-slate-700">Costo del premio:</span>
            <span class="font-bold text-sm text-slate-900">{{ reward.points_cost }} puntos</span>
          </div>

          <!-- Progress bar -->
          <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden mt-1">
            <div
              class="h-full rounded-full transition-all duration-300"
              :class="canAfford ? 'bg-emerald-600' : 'bg-rose-500'"
              :style="{ width: `${Math.min(100, Math.round((clientPoints / reward.points_cost) * 100))}%` }"
            ></div>
          </div>

          <div class="mt-2.5 flex items-center justify-between text-[11px]">
            <span v-if="canAfford" class="text-emerald-700 font-bold flex items-center gap-1">
              <Check class="h-3.5 w-3.5" />
              ¡Puntos suficientes para realizar el canje!
            </span>
            <span v-else class="text-rose-700 font-bold flex items-center gap-1">
              <AlertCircle class="h-3.5 w-3.5" />
              Faltan {{ reward.points_cost - clientPoints }} puntos para este premio.
            </span>
            <span class="text-slate-500">
              Histórico: {{ selectedClient.lifetime_points || 0 }} pts
            </span>
          </div>
        </div>

        <!-- Reset Balance Policy Indicator -->
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 space-y-2 text-xs">
          <label class="flex items-start gap-2.5 cursor-pointer font-semibold text-slate-800">
            <input
              v-model="resetBalance"
              type="checkbox"
              class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
            />
            <span>Reiniciar saldo de puntos a 0 tras el canje (especificación del sistema)</span>
          </label>
          <p class="text-[11px] text-slate-500 pl-6.5 leading-relaxed">
            <span class="font-semibold text-indigo-700">Ranking protegido:</span>
            Los puntos acumulados históricos del cliente se conservan intactos en la tabla de historial para mantener su posición en el ranking de clientes más frecuentes.
          </p>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">
            Notas de entrega (Opcional)
          </label>
          <input
            v-model.trim="notes"
            type="text"
            maxlength="255"
            placeholder="Ej. Entregado en mostrador con bolsa de cortesía"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs focus:border-indigo-500 focus:outline-none"
          />
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
          <button
            type="button"
            class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
            @click="close"
          >
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="submitting || !canAfford || reward.stock <= 0"
            class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-amber-700 shadow-xs transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
            <Gift v-else class="h-4 w-4" />
            Confirmar Canje
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import {
  Gift,
  X,
  Package,
  Award,
  Search,
  Check,
  AlertCircle,
  Loader2,
} from '@lucide/vue'
import { useClientsStore } from '@/stores/clients'
import { useOperationsStore } from '@/stores/operations'
import { useUiStore } from '@/stores/ui'
import { findUnsafeField, sanitizeInput } from '@/utils/security'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  reward: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'redeemed'])

const clientsStore = useClientsStore()
const operationsStore = useOperationsStore()
const ui = useUiStore()

const clientId = ref('')
const clientSearch = ref('')
const showClientDropdown = ref(false)
const resetBalance = ref(true)
const notes = ref('')
const submitting = ref(false)

const selectedClient = computed(() => {
  if (!clientId.value) return null
  return clientsStore.clients.find((c) => c.client_id === clientId.value) || null
})

const clientPoints = computed(() => Number(selectedClient.value?.points_balance || 0))

const canAfford = computed(() => {
  if (!selectedClient.value || !props.reward) return false
  return clientPoints.value >= Number(props.reward.points_cost || 0)
})

const filteredClients = computed(() => {
  const q = clientSearch.value.toLowerCase().trim()
  if (!q) return clientsStore.clients.slice(0, 10)
  return clientsStore.clients
    .filter(
      (c) =>
        c.name.toLowerCase().includes(q) ||
        (c.document_number && c.document_number.includes(q))
    )
    .slice(0, 10)
})

function selectClient(c) {
  clientId.value = c.client_id
  clientSearch.value = `${c.name} (${c.document_type} ${c.document_number})`
  showClientDropdown.value = false
}

function clearClient() {
  clientId.value = ''
  clientSearch.value = ''
}

function close() {
  emit('update:modelValue', false)
  clearClient()
  notes.value = ''
}

async function handleSubmit() {
  if (!canAfford.value || !props.reward || props.reward.stock <= 0) return
  if (notes.value) {
    const unsafe = findUnsafeField({ Notas: notes.value })
    if (unsafe) {
      ui.error(`El campo "${unsafe}" contiene caracteres o código no permitidos.`)
      return
    }
  }
  submitting.value = true
  try {
    const res = await operationsStore.redeemReward({
      client_id: clientId.value,
      reward_id: props.reward.reward_id,
      notes: sanitizeInput(notes.value),
      reset_balance: resetBalance.value,
    })
    emit('redeemed', res)
    close()
  } catch (err) {
    // handled by store
  } finally {
    submitting.value = false
  }
}

watch(
  () => props.modelValue,
  (val) => {
    if (val && clientsStore.clients.length === 0) {
      clientsStore.fetchClients()
    }
  }
)
</script>

