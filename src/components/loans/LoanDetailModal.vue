<template>
  <BaseModal
    :open="open"
    :title="loan ? `Detalle de Préstamo ${loan.loan_code}` : 'Detalle de Préstamo'"
    size="lg"
    @close="$emit('close')"
  >
    <div v-if="loan" class="space-y-5 text-sm">
      <!-- Header Info -->
      <div class="flex flex-col sm:flex-row justify-between gap-3 border-b border-slate-200 pb-4">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Stock Bartolo · Préstamos</p>
          <h4 class="text-lg font-bold text-slate-900">{{ loan.loan_code }}</h4>
          <p class="text-xs text-slate-500">Emitido por: <span class="font-semibold text-slate-700">{{ loan.issued_by }}</span></p>
        </div>
        <div class="sm:text-right space-y-1">
          <StatusBadge
            :variant="loanStatusMeta(loan.loan_status).variant"
            :label="loanStatusMeta(loan.loan_status).label"
          />
          <p class="text-xs text-slate-500">Emisión: {{ formatDate(loan.loan_date) }}</p>
          <p v-if="loan.due_date" class="text-xs text-slate-500">Vencimiento: {{ formatDate(loan.due_date) }}</p>
        </div>
      </div>

      <!-- Client Card -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 rounded-xl bg-slate-50 p-3.5 border border-slate-200">
        <div>
          <p class="text-xs text-slate-500">Cliente</p>
          <p class="font-semibold text-slate-900">{{ loan.client_name }}</p>
        </div>
        <div>
          <p class="text-xs text-slate-500">Teléfono</p>
          <p class="font-medium text-slate-800">{{ loan.client_contact }}</p>
        </div>
        <div>
          <p class="text-xs text-slate-500">Documento</p>
          <p class="font-medium text-slate-800">{{ loan.client_document || 'No especificado' }}</p>
        </div>
      </div>

      <!-- Financial Breakdown -->
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 rounded-xl border border-indigo-200 bg-indigo-50/50 p-3.5">
        <div>
          <p class="text-xs text-slate-500">Capital prestado</p>
          <p class="font-bold text-slate-900">{{ formatCurrency(loan.principal_amount) }}</p>
        </div>
        <div>
          <p class="text-xs text-slate-500">Interés</p>
          <p class="font-bold text-indigo-700">
            {{ loan.has_interest ? `${formatCurrency(loan.interest_amount)} (${loan.interest_rate}%)` : 'Sin interés' }}
          </p>
        </div>
        <div>
          <p class="text-xs text-slate-500">Total préstamo</p>
          <p class="font-bold text-slate-900">{{ formatCurrency(loan.total_amount) }}</p>
        </div>
        <div>
          <p class="text-xs text-slate-500">Abonado</p>
          <p class="font-bold text-emerald-700">{{ formatCurrency(loan.paid_amount) }}</p>
        </div>
        <div>
          <p class="text-xs text-slate-500">Saldo pendiente</p>
          <p class="font-extrabold text-amber-700">{{ formatCurrency(loan.balance_due) }}</p>
        </div>
      </div>

      <p v-if="loan.notes" class="text-xs text-slate-600 bg-slate-50 rounded-xl p-3 border border-slate-200">
        <span class="font-semibold text-slate-800">Notas:</span> {{ loan.notes }}
      </p>

      <!-- Payment History -->
      <div>
        <h5 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Historial de pagos</h5>
        <div v-if="payments.length === 0" class="text-xs text-slate-500 italic">Sin abonos registrados.</div>
        <div v-else class="overflow-x-auto border border-slate-200 rounded-xl">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase">
              <tr>
                <th class="px-3 py-2">Fecha</th>
                <th class="px-3 py-2">Método</th>
                <th class="px-3 py-2">Notas</th>
                <th class="px-3 py-2 text-right">Monto</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="p in payments" :key="p.payment_id">
                <td class="px-3 py-2">{{ formatDate(p.payment_date) }}</td>
                <td class="px-3 py-2">{{ paymentMethodLabel(p.payment_method) }}</td>
                <td class="px-3 py-2 text-slate-500">{{ p.notes || '—' }}</td>
                <td class="px-3 py-2 text-right font-semibold text-emerald-700">{{ formatCurrency(p.amount) }}</td>
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
        v-if="loan"
        type="button"
        class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 inline-flex items-center gap-2"
        @click="printLoan"
      >
        <Printer class="h-4 w-4" />
        Imprimir comprobante
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { Printer } from '@lucide/vue'
import { formatCurrency, formatDate, loanStatusMeta, paymentMethodLabel } from '@/utils/formatters'
import { escapeHtml } from '@/utils/security'
import BaseModal from '@/components/common/BaseModal.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  loan: { type: Object, default: null },
  payments: { type: Array, default: () => [] },
})

defineEmits(['close'])

function printLoan() {
  if (!props.loan) return
  const l = props.loan
  const payRows = props.payments
    .map(
      (p) =>
        `<tr><td>${escapeHtml(p.payment_date)}</td><td>${escapeHtml(paymentMethodLabel(p.payment_method))}</td><td>${escapeHtml(p.notes || '—')}</td><td style="text-align:right">$${Number(p.amount).toFixed(2)}</td></tr>`
    )
    .join('')

  const interestSummary = l.has_interest
    ? `$${Number(l.interest_amount).toFixed(2)} (${escapeHtml(l.interest_rate)}%)`
    : 'Sin interés (0%)'

  const win = window.open('', '_blank', 'height=650,width=820')
  if (!win) {
    window.print()
    return
  }
  win.document.write(`
    <!DOCTYPE html>
    <html lang="es"><head><meta charset="utf-8"><title>Comprobante ${escapeHtml(l.loan_code)}</title>
    <style>
      body{font-family:system-ui,sans-serif;padding:24px;color:#0f172a}
      table{width:100%;border-collapse:collapse;margin:14px 0}
      th,td{border:1px solid #cbd5e1;padding:8px;font-size:13px}
      th{background:#f8fafc;text-align:left}
    </style></head><body>
    <h2>Comprobante de Préstamo — ${escapeHtml(l.loan_code)}</h2>
    <p><strong>Cliente:</strong> ${escapeHtml(l.client_name)} &nbsp;|&nbsp; <strong>Teléfono:</strong> ${escapeHtml(l.client_contact)} &nbsp;|&nbsp; <strong>Documento:</strong> ${escapeHtml(l.client_document || 'N/A')}</p>
    <p><strong>Emitido por:</strong> ${escapeHtml(l.issued_by)} &nbsp;|&nbsp; <strong>Fecha emisión:</strong> ${escapeHtml(l.loan_date)} &nbsp;|&nbsp; <strong>Vencimiento:</strong> ${escapeHtml(l.due_date || 'No definido')}</p>
    <table>
      <tr><th>Capital prestado</th><td>$${Number(l.principal_amount).toFixed(2)}</td><th>Interés</th><td>${interestSummary}</td></tr>
      <tr><th>Total a pagar</th><td><strong>$${Number(l.total_amount).toFixed(2)}</strong></td><th>Saldo pendiente</th><td><strong>$${Number(l.balance_due).toFixed(2)}</strong></td></tr>
    </table>
    ${props.payments.length > 0 ? `<h3>Historial de abonos</h3><table><thead><tr><th>Fecha</th><th>Método</th><th>Notas</th><th style="text-align:right">Monto</th></tr></thead><tbody>${payRows}</tbody></table>` : ''}
    ${l.notes ? `<p><strong>Notas:</strong> ${escapeHtml(l.notes)}</p>` : ''}
    </body></html>
  `)
  win.document.close()
  win.focus()
  win.print()
  win.close()
}
</script>

