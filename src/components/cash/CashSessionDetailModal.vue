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

      <!-- Reporte Consolidado de Operaciones y Desglose por Entidad Bancaria -->
      <div v-if="operationsSummary && operationsSummary.total_count > 0" class="space-y-4">
        <!-- Resumen Consolidado -->
        <div class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-4 space-y-3">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-950 flex items-center gap-1.5">
              <Landmark class="h-4 w-4 text-indigo-700" />
              Reporte General Consolidado de Operaciones (Agente Multibanco)
            </h4>
            <span class="rounded-full bg-indigo-200/80 px-2.5 py-0.5 text-[11px] font-bold text-indigo-900">
              {{ operationsSummary.total_count }} transacciones realizadas
            </span>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
            <div class="rounded-lg bg-white p-3 border border-indigo-100 shadow-xs">
              <p class="text-slate-500 text-[11px]">Depósitos ({{ operationsSummary.deposit_count }})</p>
              <p class="text-sm font-bold text-emerald-700 mt-0.5">+{{ formatCurrency(operationsSummary.deposit_amount) }}</p>
            </div>
            <div class="rounded-lg bg-white p-3 border border-indigo-100 shadow-xs">
              <p class="text-slate-500 text-[11px]">Retiros ({{ operationsSummary.withdrawal_count }})</p>
              <p class="text-sm font-bold text-rose-700 mt-0.5">-{{ formatCurrency(operationsSummary.withdrawal_amount) }}</p>
            </div>
            <div class="rounded-lg bg-white p-3 border border-indigo-100 shadow-xs">
              <p class="text-slate-500 text-[11px]">Comisiones Ganadas</p>
              <p class="text-sm font-bold text-amber-700 mt-0.5">+{{ formatCurrency(operationsSummary.commission_total) }}</p>
            </div>
            <div class="rounded-lg bg-white p-3 border border-indigo-100 shadow-xs">
              <p class="text-slate-500 text-[11px]">Flujo Neto Operaciones</p>
              <p class="text-sm font-extrabold text-indigo-950 mt-0.5">
                {{ operationsSummary.net_customer_flow >= 0 ? '+' : '' }}{{ formatCurrency(operationsSummary.net_customer_flow) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Desglose por Entidad Bancaria -->
        <div v-if="bankBreakdown && bankBreakdown.length > 0" class="space-y-2">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600">
              Desglose por Entidad Bancaria (Fondos Operados)
            </h4>
            <span class="text-[11px] text-slate-500">{{ bankBreakdown.length }} entidades</span>
          </div>
          <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-50 border-b border-slate-200 uppercase text-slate-500">
                <tr>
                  <th class="px-3 py-2">Entidad Bancaria</th>
                  <th class="px-3 py-2 text-right">Depósitos (Cant / Monto)</th>
                  <th class="px-3 py-2 text-right">Retiros (Cant / Monto)</th>
                  <th class="px-3 py-2 text-right">Comisiones Ganadas</th>
                  <th class="px-3 py-2 text-right">Flujo Neto</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="b in bankBreakdown" :key="b.bank_entity">
                  <td class="px-3 py-2 font-bold">
                    <span
                      class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-bold border"
                      :class="getBankBadgeClass(b.bank_entity)"
                    >
                      {{ b.bank_entity }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-right text-emerald-700 font-semibold">
                    {{ b.deposit_count }} ops · +{{ formatCurrency(b.deposit_amount) }}
                  </td>
                  <td class="px-3 py-2 text-right text-rose-700 font-semibold">
                    {{ b.withdrawal_count }} ops · -{{ formatCurrency(b.withdrawal_amount) }}
                  </td>
                  <td class="px-3 py-2 text-right font-bold text-amber-700">
                    +{{ formatCurrency(b.commission_total) }}
                  </td>
                  <td
                    class="px-3 py-2 text-right font-extrabold"
                    :class="b.net_flow >= 0 ? 'text-indigo-950' : 'text-rose-700'"
                  >
                    {{ b.net_flow >= 0 ? '+' : '' }}{{ formatCurrency(b.net_flow) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
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
      <div v-if="session" class="flex items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-indigo-700 bg-white border border-indigo-200 rounded-lg hover:bg-indigo-50 shadow-xs transition"
          @click="downloadPdf"
        >
          <Download class="h-4 w-4" />
          Descargar / Guardar PDF
        </button>
        <button
          type="button"
          class="px-4 py-2 text-xs sm:text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 inline-flex items-center gap-2 shadow-xs transition"
          @click="printReport"
        >
          <Printer class="h-4 w-4" />
          Imprimir Corte {{ session.status === 'closed' ? 'Z' : 'X' }}
        </button>
      </div>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed } from 'vue'
import { Loader2, Printer, Download, Landmark } from '@lucide/vue'
import BaseModal from '@/components/common/BaseModal.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import {
  cashMovementTypeMeta,
  formatCurrency,
  formatDateTime,
  reconciliationStatusMeta,
} from '@/utils/formatters'
import { escapeHtml } from '@/utils/security'
import { getBankBadgeClass } from '@/utils/bankEntities'
import { printHtmlDocument } from '@/utils/printHelper'

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
const operationsSummary = computed(() => props.detail?.operations_summary || null)
const bankBreakdown = computed(() => props.detail?.bank_breakdown || [])

function buildReportHtml() {
  if (!session.value) return ''
  const s = session.value
  const b = branch.value
  const opsSum = operationsSummary.value
  const banks = bankBreakdown.value

  const movRows = movements.value
    .map(
      (m) =>
        `<tr>
          <td>${escapeHtml(m.created_at)}</td>
          <td><span style="font-weight:600">${escapeHtml(cashMovementTypeMeta(m.movement_type).label)}</span></td>
          <td>${escapeHtml(m.description)}</td>
          <td style="text-align:right; font-weight: bold; color:${m.direction === 'in' ? '#047857' : '#b91c1c'}">
            ${m.direction === 'in' ? '+' : '-'}S/ ${Number(m.amount).toFixed(2)}
          </td>
        </tr>`
    )
    .join('')

  let consolidatedHtml = ''
  if (opsSum && opsSum.total_count > 0) {
    consolidatedHtml = `
      <div style="margin: 16px 0; padding: 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px;">
        <h3 style="margin: 0 0 8px; font-size: 14px; text-transform: uppercase; color: #1e1b4b;">
          ★ Reporte General Consolidado de Operaciones (Agente Multibanco)
        </h3>
        <p style="margin: 0 0 10px; font-size: 12px; color: #475569;">
          Total Transacciones: <strong>${opsSum.total_count}</strong> |
          Comisiones Ganadas: <strong style="color: #b45309;">+S/ ${Number(opsSum.commission_total).toFixed(2)}</strong> |
          Flujo Neto: <strong>S/ ${Number(opsSum.net_customer_flow).toFixed(2)}</strong>
        </p>
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
          <tr style="background: #e2e8f0;">
            <th style="padding: 6px; border: 1px solid #cbd5e1;">Concepto</th>
            <th style="padding: 6px; border: 1px solid #cbd5e1; text-align: center;">Operaciones</th>
            <th style="padding: 6px; border: 1px solid #cbd5e1; text-align: right;">Monto Total</th>
          </tr>
          <tr>
            <td style="padding: 6px; border: 1px solid #cbd5e1; font-weight: 600; color: #047857;">Depósitos (Ingreso)</td>
            <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: center;">${opsSum.deposit_count}</td>
            <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: right; font-weight: bold; color: #047857;">+S/ ${Number(opsSum.deposit_amount).toFixed(2)}</td>
          </tr>
          <tr>
            <td style="padding: 6px; border: 1px solid #cbd5e1; font-weight: 600; color: #b91c1c;">Retiros (Egreso)</td>
            <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: center;">${opsSum.withdrawal_count}</td>
            <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: right; font-weight: bold; color: #b91c1c;">-S/ ${Number(opsSum.withdrawal_amount).toFixed(2)}</td>
          </tr>
          <tr style="background: #f1f5f9; font-weight: bold;">
            <td style="padding: 6px; border: 1px solid #cbd5e1;">Comisiones del Agente</td>
            <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: center;">${opsSum.total_count}</td>
            <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: right; color: #b45309;">+S/ ${Number(opsSum.commission_total).toFixed(2)}</td>
          </tr>
        </table>
      </div>
    `
  }

  let bankBreakdownHtml = ''
  if (banks && banks.length > 0) {
    const bankRows = banks
      .map(
        (b) => `<tr>
          <td style="padding: 6px; border: 1px solid #cbd5e1; font-weight: bold;">${escapeHtml(b.bank_entity)}</td>
          <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: right; color: #047857;">${b.deposit_count} ops · +S/ ${Number(b.deposit_amount).toFixed(2)}</td>
          <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: right; color: #b91c1c;">${b.withdrawal_count} ops · -S/ ${Number(b.withdrawal_amount).toFixed(2)}</td>
          <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: right; font-weight: bold; color: #b45309;">+S/ ${Number(b.commission_total).toFixed(2)}</td>
          <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: right; font-weight: bold;">S/ ${Number(b.net_flow).toFixed(2)}</td>
        </tr>`
      )
      .join('')

    bankBreakdownHtml = `
      <div style="margin: 16px 0;">
        <h4 style="margin: 0 0 6px; font-size: 13px; text-transform: uppercase; color: #334155;">
          Desglose por Entidad Bancaria
        </h4>
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
          <thead style="background: #e2e8f0;">
            <tr>
              <th style="padding: 6px; border: 1px solid #cbd5e1; text-align: left;">Entidad Bancaria</th>
              <th style="padding: 6px; border: 1px solid #cbd5e1; text-align: right;">Depósitos</th>
              <th style="padding: 6px; border: 1px solid #cbd5e1; text-align: right;">Retiros</th>
              <th style="padding: 6px; border: 1px solid #cbd5e1; text-align: right;">Comisiones</th>
              <th style="padding: 6px; border: 1px solid #cbd5e1; text-align: right;">Flujo Neto</th>
            </tr>
          </thead>
          <tbody>${bankRows}</tbody>
        </table>
      </div>
    `
  }

  return `
    <div style="padding: 16px; font-family: ui-sans-serif, system-ui, sans-serif; color: #0f172a;">
      <div style="border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
          <h2 style="margin: 0; font-size: 18px; font-weight: 800; text-transform: uppercase;">
            Comprobante de Corte ${s.status === 'closed' ? 'Z (Cierre)' : 'X (Parcial)'}
          </h2>
          <p style="margin: 2px 0 0; font-size: 13px; font-weight: bold; color: #4338ca;">Turno: ${escapeHtml(s.session_code)}</p>
        </div>
        <div style="text-align: right; font-size: 12px;">
          <p style="margin: 0; font-weight: bold;">${escapeHtml(b?.branch_name || 'Sucursal')}</p>
          <p style="margin: 0; color: #64748b;">Caja: ${escapeHtml(s.register_name)} [${escapeHtml(s.bank_entity || 'BCP')}]</p>
        </div>
      </div>

      <div style="font-size: 12px; line-height: 1.6; margin-bottom: 12px;">
        <p style="margin: 0;"><strong>Apertura:</strong> ${escapeHtml(s.opened_at)} (${escapeHtml(s.opened_by_name)})</p>
        ${s.closed_at ? `<p style="margin: 0;"><strong>Cierre:</strong> ${escapeHtml(s.closed_at)} (${escapeHtml(s.closed_by_name || 'Sistema')})</p>` : ''}
      </div>

      <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 16px;">
        <tr>
          <th style="padding: 6px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left;">Fondo Inicial</th>
          <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: right;">S/ ${Number(s.opening_amount).toFixed(2)}</td>
          <th style="padding: 6px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left;">Entradas Efectivo</th>
          <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: right; color: #047857; font-weight: bold;">+S/ ${Number(s.cash_in_total).toFixed(2)}</td>
        </tr>
        <tr>
          <th style="padding: 6px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left;">Salidas Efectivo</th>
          <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: right; color: #b91c1c; font-weight: bold;">-S/ ${Number(s.cash_out_total).toFixed(2)}</td>
          <th style="padding: 6px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left;">Efectivo Esperado</th>
          <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: right; font-weight: 800; color: #1e1b4b;">S/ ${Number(s.expected_cash).toFixed(2)}</td>
        </tr>
        ${s.counted_cash !== null ? `
        <tr>
          <th style="padding: 6px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left;">Efectivo Contado</th>
          <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: right; font-weight: bold;">S/ ${Number(s.counted_cash).toFixed(2)}</td>
          <th style="padding: 6px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left;">Diferencia</th>
          <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: right; font-weight: bold; color: ${s.difference_amount < 0 ? '#b91c1c' : '#047857'}">
            S/ ${Number(s.difference_amount || 0).toFixed(2)}
          </td>
        </tr>` : ''}
        ${s.status === 'closed' ? `
        <tr>
          <th style="padding: 6px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left;">Entregado a Caja General</th>
          <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: right;">S/ ${Number(s.amount_returned_general).toFixed(2)}</td>
          <th style="padding: 6px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left;">Retenido Gaveta</th>
          <td style="padding: 6px; border: 1px solid #cbd5e1; text-align: right;">S/ ${Number(s.retained_float).toFixed(2)}</td>
        </tr>` : ''}
      </table>

      ${consolidatedHtml}
      ${bankBreakdownHtml}

      <div style="margin-top: 16px;">
        <h4 style="margin: 0 0 6px; font-size: 13px; text-transform: uppercase; color: #334155;">
          Movimientos Registrados (${movements.value.length})
        </h4>
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
          <thead style="background: #e2e8f0;">
            <tr>
              <th style="padding: 5px; border: 1px solid #cbd5e1; text-align: left;">Fecha</th>
              <th style="padding: 5px; border: 1px solid #cbd5e1; text-align: left;">Tipo</th>
              <th style="padding: 5px; border: 1px solid #cbd5e1; text-align: left;">Descripción</th>
              <th style="padding: 5px; border: 1px solid #cbd5e1; text-align: right;">Monto</th>
            </tr>
          </thead>
          <tbody>${movRows || '<tr><td colspan="4" style="text-align:center; padding: 10px;">Sin movimientos</td></tr>'}</tbody>
        </table>
      </div>
    </div>
  `
}

function printReport() {
  const content = buildReportHtml()
  if (!content) return
  printHtmlDocument({
    title: `Corte_${session.value.status === 'closed' ? 'Z' : 'X'}_${session.value.session_code}`,
    htmlContent: content,
    format: 'a4',
  })
}

function downloadPdf() {
  const content = buildReportHtml()
  if (!content) return
  printHtmlDocument({
    title: `Reporte_Corte_${session.value.status === 'closed' ? 'Z' : 'X'}_${session.value.session_code}.pdf`,
    htmlContent: content,
    format: 'a4',
  })
}
</script>

