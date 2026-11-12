<template>
  <BaseModal
    :open="open"
    title="Detalle de Evento de Auditoría"
    :subtitle="log ? `Registro #${log.log_id} — ${formatDateTime(log.created_at)}` : ''"
    size="lg"
    @close="$emit('close')"
  >
    <div v-if="log" class="space-y-4">
      <!-- Top banner with Action badge & summary -->
      <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 p-4 border border-slate-200">
        <div>
          <span
            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider"
            :class="getActionBadgeClass(log.action)"
          >
            <component :is="getActionIcon(log.action)" class="h-3.5 w-3.5" />
            {{ log.action }}
          </span>
          <p class="mt-2 text-sm font-semibold text-slate-900">{{ log.description }}</p>
        </div>
        <div class="text-right">
          <span class="text-xs text-slate-500">Módulo</span>
          <p class="text-sm font-bold text-slate-800 capitalize">{{ log.module }}</p>
        </div>
      </div>

      <!-- Metadata Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <!-- User -->
        <div class="rounded-xl border border-slate-200 p-3 bg-white">
          <p class="text-xs text-slate-500 font-medium">Usuario Responsable</p>
          <p class="text-sm font-bold text-slate-900 mt-0.5">
            {{ log.full_name || log.username }}
          </p>
          <p class="text-xs text-slate-500 font-mono">@{{ log.username }} (ID: {{ log.user_id || 'N/A' }})</p>
        </div>

        <!-- Branch -->
        <div class="rounded-xl border border-slate-200 p-3 bg-white">
          <p class="text-xs text-slate-500 font-medium">Sucursal</p>
          <p class="text-sm font-bold text-slate-900 mt-0.5">
            {{ log.branch_name }}
          </p>
          <p class="text-xs text-slate-500 font-mono">Código: {{ log.branch_code }}</p>
        </div>

        <!-- IP Address -->
        <div class="rounded-xl border border-slate-200 p-3 bg-white">
          <p class="text-xs text-slate-500 font-medium">Dirección IP</p>
          <p class="text-sm font-bold font-mono text-slate-900 mt-0.5">{{ log.ip_address }}</p>
        </div>

        <!-- Timestamp -->
        <div class="rounded-xl border border-slate-200 p-3 bg-white">
          <p class="text-xs text-slate-500 font-medium">Fecha y Hora</p>
          <p class="text-sm font-bold text-slate-900 mt-0.5">{{ formatDateTime(log.created_at) }}</p>
        </div>
      </div>

      <!-- User Agent -->
      <div class="rounded-xl border border-slate-200 p-3 bg-white">
        <p class="text-xs text-slate-500 font-medium mb-1">Navegador / Dispositivo (User-Agent)</p>
        <p class="text-xs font-mono text-slate-700 break-all bg-slate-50 p-2 rounded-lg border border-slate-100">
          {{ log.user_agent || 'No registrado' }}
        </p>
      </div>

      <!-- Details JSON (if present) -->
      <div v-if="log.details" class="space-y-2">
        <div class="flex items-center justify-between">
          <p class="text-xs font-bold uppercase tracking-wider text-slate-700">
            Parámetros y Datos de la Operación (Payload JSON)
          </p>
          <button
            type="button"
            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1"
            @click="copyJson"
          >
            <Copy class="h-3.5 w-3.5" />
            <span>{{ copied ? '¡Copiado!' : 'Copiar' }}</span>
          </button>
        </div>
        <pre class="max-h-60 overflow-y-auto rounded-xl bg-slate-900 p-3 text-xs font-mono text-emerald-400 border border-slate-800">{{ formattedJson }}</pre>
      </div>
    </div>

    <template #footer>
      <button
        type="button"
        class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
        @click="$emit('close')"
      >
        Cerrar
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, ref } from 'vue'
import BaseModal from '@/components/common/BaseModal.vue'
import {
  AlertTriangle,
  CheckCircle2,
  Copy,
  Info,
  KeyRound,
  LogIn,
  LogOut,
  ShieldAlert,
  Trash2,
} from '@lucide/vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  log: { type: Object, default: null },
})

defineEmits(['close'])

const copied = ref(false)

const formattedJson = computed(() => {
  if (!props.log?.details) return ''
  try {
    return JSON.stringify(props.log.details, null, 2)
  } catch {
    return String(props.log.details)
  }
})

function formatDateTime(str) {
  if (!str) return '—'
  const d = new Date(str.replace(' ', 'T'))
  if (isNaN(d.getTime())) return str
  return d.toLocaleString('es-SV', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
}

function getActionBadgeClass(action) {
  switch (action) {
    case 'CREATE':
      return 'bg-emerald-100 text-emerald-800 border border-emerald-200'
    case 'UPDATE':
      return 'bg-amber-100 text-amber-800 border border-amber-200'
    case 'DELETE':
    case 'DEACTIVATE':
      return 'bg-rose-100 text-rose-800 border border-rose-200'
    case 'LOGIN':
      return 'bg-blue-100 text-blue-800 border border-blue-200'
    case 'LOGOUT':
      return 'bg-slate-100 text-slate-800 border border-slate-200'
    case 'ACCESS_DENIED':
    case 'LOGIN_FAILED':
      return 'bg-red-100 text-red-800 border border-red-300'
    case 'PASSWORD_CHANGE':
      return 'bg-purple-100 text-purple-800 border border-purple-200'
    default:
      return 'bg-slate-100 text-slate-800 border border-slate-200'
  }
}

function getActionIcon(action) {
  switch (action) {
    case 'CREATE':
      return CheckCircle2
    case 'UPDATE':
      return Info
    case 'DELETE':
    case 'DEACTIVATE':
      return Trash2
    case 'LOGIN':
      return LogIn
    case 'LOGOUT':
      return LogOut
    case 'ACCESS_DENIED':
    case 'LOGIN_FAILED':
      return ShieldAlert
    case 'PASSWORD_CHANGE':
      return KeyRound
    default:
      return AlertTriangle
  }
}

async function copyJson() {
  if (!formattedJson.value) return
  try {
    await navigator.clipboard.writeText(formattedJson.value)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch {
    // ignore
  }
}
</script>

