<template>
  <BaseModal
    :open="open"
    :title="session ? `Detalle de Turno ${session.session_code}` : 'Detalle de Turno de Caja'"
    :subtitle="session ? `${session.register_code} · ${session.register_name} — ${session.status === 'open' ? 'Turno Abierto' : 'Turno Cerrado'}` : ''"
    size="xl"
    @close="$emit('close')"
  >
    <div v-if="loading" class="py-12 text-center text-sm text-slate-500">
      <Loader2 class="h-6 w-6 animate-spin mx-auto mb-2 text-indigo-600" />
      Cargando detalle financiero de caja...
    </div>

    <div v-else-if="session" class="space-y-5">
      <!-- Top Metrics Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
          <p class="text-xs text-slate-500">Fondo Inicial</p>
          <p class="text-lg font-bold text-slate-900">{{ formatCurrency(session.opening_amount) }}</p>
        </div>
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3">
          <p class="text-xs text-emerald-700">Entradas Efectivo</p>
          <p class="text-lg font-bold text-emerald-900">+{{ formatCurrency(session.cash_in_total) }}</p>
        </div>
        <div class="rounded-xl bg-rose-50 border border-rose-200 p-3">
          <p class="text-xs text-rose-700">Salidas Efectivo</p>
          <p class="text-lg font-bold text-rose-900">-{{ formatCurrency(session.cash_out_total) }}</p>
        </div>
        <div class="rounded-xl bg-indigo-50 border border-indigo-200 p-3">
          <p class="text-xs text-indigo-700">Efectivo Esperado</p>
          <p class="text-lg font-extrabold text-indigo-900">{{ formatCurrency(session.expected_cash) }}</p>
        </div>
      </div>

      <!-- Metadata & Closing Result -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 text-xs">
        <div class="space-y-1">
          <p><span class="font-semibold text-slate-700">Apertura:</span> {{ formatDateTime(session.opened_at) }} ({{ session.opened_by_name }})</p>
          <p><span class="font-semibold text-slate-700">Fondeado de Caja General:</span> {{ session.funded_from_general_cash ? 'Sí' : 'No' }}</p>
          <p v-if="session.opening_notes"><span class="font-semibold text-slate-700">Notas apertura:</span> {{ session.opening_notes }}</p>
        </div>
        <div class="space-y-1">
          <p v-if="session.closed_at">
            <span class="font-semibold text-slate-700">Cierre:</span> {{ formatDateTime(session.closed_at) }} ({{ session.closed_by_name || 'Sistema' }})
          </p>
          <p v-if="session.counted_cash !== null">
            <span class="font-semibold text-slate-700">Último conteo:</span> {{ formatCurrency(session.counted_cash) }}
            <span class="ml-1 font-bold" :class="session.difference_amount < 0 ? 'text-rose-600' : session.difference_amount > 0 ? 'text-sky-700' : 'text-emerald-700'">
              (Dif: {{ session.difference_amount > 0 ? '+' : '' }}{{ formatCurrency(session.difference_amount) }})
            </span>
          </p>
          <p v-if="session.status === 'closed'">
            <span class="font-semibold text-slate-700">Devuelto a Caja General:</span> {{ formatCurrency(session.amount_returned_general) }} · Retenido: {{ formatCurrency(session.retained_float) }}
          </p>
        </div>
      </div>

      <!-- Reconciliations History -->
      <div v-if="reconciliations.length > 0" class="space-y-2">
        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Historial de arqueos y conciliaciones</h4>
        <div class="overflow-x-auto rounded-xl border border-slate-200">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 border-b border-slate-200 uppercase text-slate-500">
              <tr>
                <th class="px-3 py-2">Fecha</th>
                <th class="px-3 py-2">Tipo</th>
                <th class="px-3 py-2">Responsable</th>
                <th class="px-3 py-2 text-right">Esperado</th>
                <th class="px-3 py-2 text-right">Contado</th>
                <th class="px-3 py-2 text-right">Diferencia</th>
                <th class="px-3 py-2">Estado</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="rec in reconciliations" :key="rec.reconciliation_id">
                <td class="px-3 py-2 text-slate-600">{{ formatDateTime(rec.reconciled_at) }}</td>
                <td class="px-3 py-2 font-medium">{{ rec.reconciliation_type === 'closing' ? 'Cierre Z' : 'Arqueo parcial' }}</td>
                <td class="px-3 py-2 text-slate-700">{{ rec.username }}</td>
                <td class="px-3 py-2 text-right">{{ formatCurrency(rec.expected_cash) }}</td>
                <td class="px-3 py-2 text-right font-semibold">{{ formatCurrency(rec.counted_cash) }}</td>
                <td class="px-3 py-2 text-right font-bold" :class="rec.difference_amount < 0 ? 'text-rose-600' : rec.difference_amount > 0 ? 'text-sky-700' : 'text-emerald-700'">
                  {{ rec.difference_amount > 0 ? '+' : '' }}{{ formatCurrency(rec.difference_amount) }}
                </td>
                <td class="px-3 py-2">
                  <StatusBadge
                    :variant="reconciliationStatusMeta(rec.status_label).variant"
                    :label="reconciliationStatusMeta(rec.status_label).label"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Movements Ledger -->
      <div class="space-y-2">
        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Movimientos del turno ({{ movements.length }})</h4>
        <div v-if="movements.length === 0" class="rounded-xl border border-dashed border-slate-200 p-6 text-center text-xs text-slate-500">
          No se han registrado movimientos durante este turno.
        </div>
        <div v-else class="overflow-x-auto rounded-xl border border-slate-200">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 border-b border-slate-200 uppercase text-slate-500">
              <tr>
                <th class="px-3 py-2">Fecha</th>
                <th class="px-3 py-2">Tipo</th>
                <th class="px-3 py-2">Descripción</th>
                <th class="px-3 py-2">Usuario</th>
                <th class="px-3 py-2 text-right">Monto</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="m in movements" :key="m.movement_id">
                <td class="px-3 py-2 text-slate-500 whitespace-nowrap">{{ formatDateTime(m.created_at) }}</td>
                <td class="px-3 py-2">
                  <StatusBadge
                    :variant="cashMovementTypeMeta(m.movement_type).variant"
                    :label="cashMovementTypeMeta(m.movement_type).label"
                  />
                </td>
                <td class="px-3 py-2 text-slate-800">{{ m.description }}</td>
                <td class="px-3 py-2 text-slate-600">{{ m.username }}</td>
                <td
                  class="px-3 py-2 text-right font-bold whitespace-nowrap"
                  :class="m.direction === 'in' ? 'text-emerald-700' : 'text-rose-600'"
                >
                  {{ m.direction === 'in' ? '+' : '-' }}{{ formatCurrency(m.amount) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <template #footer>
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50"
        @click="$emit('close')"
      >
        Cerrar
      </button>
      <button
        v-if="session"
        type="button"
        class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 inline-flex items-center gap-2"
        @click="printReport"
      >
        <Printer class="h-4 w-4" />
        Imprimir Corte {{ session.status === 'closed' ? 'Z' : 'X' }}
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed } from 'vue'
import { Loader2, Printer } from '@lucide/vue'
import BaseModal from '@/components/common/BaseModal.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import {
  cashMovementTypeMeta,
  formatCurrency,
  formatDateTime,
  reconciliationStatusMeta,
} from '@/utils/formatters'
import { escapeHtml } from '@/utils/security'

const props = defineProps({
  open: { type: Boolean, default: false },
  detail: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

defineEmits(['close'])

const session = computed(() => props.detail?.session || null)
const branch = computed(() => props.detail?.branch || null)
const movements = computed(() => props.detail?.movements || [])
const reconciliations = computed(() => props.detail?.reconciliations || [])

function printReport() {
  if (!session.value) return
  const s = session.value
  const b = branch.value
  const movRows = movements.value
    .map(
      (m) =>
        `<tr><td>${escapeHtml(m.created_at)}</td><td>${escapeHtml(cashMovementTypeMeta(m.movement_type).label)}</td><td>${escapeHtml(m.description)}</td><td style="text-align:right">${m.direction === 'in' ? '+' : '-'}$${Number(m.amount).toFixed(2)}</td></tr>`
    )
    .join('')

  const win = window.open('', '_blank', 'height=700,width=840')
  if (!win) {
    window.print()
    return
  }

  win.document.write(`
    <!DOCTYPE html>
    <html lang="es"><head><meta charset="utf-8"><title>Corte ${s.status === 'closed' ? 'Z' : 'X'} — ${escapeHtml(s.session_code)}</title>
    <style>
      body{font-family:system-ui,sans-serif;padding:24px;color:#0f172a;font-size:13px}
      h2{margin:0 0 4px}
      table{width:100%;border-collapse:collapse;margin:14px 0}
      th,td{border:1px solid #cbd5e1;padding:7px;font-size:12px}
      th{background:#f8fafc;text-align:left}
    </style></head><body>
    <h2>Comprobante de Corte ${s.status === 'closed' ? 'Z (Cierre)' : 'X (Parcial)'} — ${escapeHtml(s.session_code)}</h2>
    <p><strong>Sucursal:</strong> ${escapeHtml(b?.branch_name || '')} (${escapeHtml(b?.branch_code || '')}) &nbsp;|&nbsp; <strong>Caja:</strong> ${escapeHtml(s.register_name)}</p>
    <p><strong>Apertura:</strong> ${escapeHtml(s.opened_at)} (${escapeHtml(s.opened_by_name)}) ${s.closed_at ? `&nbsp;|&nbsp; <strong>Cierre:</strong> ${escapeHtml(s.closed_at)} (${escapeHtml(s.closed_by_name || '')})` : ''}</p>
    <table>
      <tr><th>Fondo Inicial</th><td style="text-align:right">$${Number(s.opening_amount).toFixed(2)}</td><th>Entradas Efectivo</th><td style="text-align:right">$${Number(s.cash_in_total).toFixed(2)}</td></tr>
      <tr><th>Salidas Efectivo</th><td style="text-align:right">$${Number(s.cash_out_total).toFixed(2)}</td><th>Efectivo Esperado</th><td style="text-align:right"><strong>$${Number(s.expected_cash).toFixed(2)}</strong></td></tr>
      ${s.counted_cash !== null ? `<tr><th>Efectivo Contado</th><td style="text-align:right">$${Number(s.counted_cash).toFixed(2)}</td><th>Diferencia</th><td style="text-align:right"><strong>$${Number(s.difference_amount || 0).toFixed(2)}</strong></td></tr>` : ''}
      ${s.status === 'closed' ? `<tr><th>Entregado Caja General</th><td style="text-align:right">$${Number(s.amount_returned_general).toFixed(2)}</td><th>Retenido Gaveta</th><td style="text-align:right">$${Number(s.retained_float).toFixed(2)}</td></tr>` : ''}
    </table>
    <h3>Movimientos (${movements.value.length})</h3>
    <table><thead><tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Monto</th></tr></thead><tbody>${movRows || '<tr><td colspan="4" style="text-align:center">Sin movimientos</td></tr>'}</tbody></table>
    </body></html>
  `)
  win.document.close()
  win.focus()
  win.print()
  win.close()
}
</script>

