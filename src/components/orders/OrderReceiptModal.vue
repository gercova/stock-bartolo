<template>
  <BaseModal
    :open="open"
    title="Comprobante de Venta"
    size="4xl"
    @close="handleClose"
  >
    <div v-if="order" class="space-y-4">
      <!-- Format Switcher Bar -->
      <div class="no-print flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
        <div class="flex items-center gap-2">
          <span class="text-xs font-bold text-slate-700">Formato de Impresión:</span>
          <div class="inline-flex rounded-xl bg-white p-1 border border-slate-200 shadow-xs">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
              :class="selectedFormat === 'ticket' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
              @click="selectedFormat = 'ticket'"
            >
              <Receipt class="h-3.5 w-3.5" />
              Ticket (80mm)
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
              :class="selectedFormat === 'a5' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
              @click="selectedFormat = 'a5'"
            >
              <FileText class="h-3.5 w-3.5" />
              A5 (Media Página)
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
              :class="selectedFormat === 'a4' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
              @click="selectedFormat = 'a4'"
            >
              <FileSpreadsheet class="h-3.5 w-3.5" />
              A4 (Hoja Completa)
            </button>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <!-- Double ticket copy option -->
          <label v-if="selectedFormat === 'ticket'" class="flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer">
            <input
              v-model="doubleTicketCopy"
              type="checkbox"
              class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
            />
            <span>Doble copia (Cliente + Control)</span>
          </label>

          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs sm:text-sm font-semibold text-white hover:bg-indigo-700 shadow-sm transition"
            @click="triggerPrint"
          >
            <Printer class="h-4 w-4" />
            Imprimir {{ formatLabel }}
          </button>
        </div>
      </div>

      <!-- Preview Container -->
      <div class="overflow-y-auto max-h-[65vh] bg-slate-100 rounded-2xl p-4 sm:p-6 flex justify-center border border-slate-200">
        <!-- ================= FORMAT 1: TICKET 80mm ================= -->
        <div
          v-if="selectedFormat === 'ticket'"
          id="print-area-ticket"
          class="w-[300px] bg-white p-5 text-slate-900 font-mono text-xs shadow-md rounded-lg space-y-3.5 leading-tight"
        >
          <!-- Branch Header -->
          <div class="text-center space-y-1">
            <h3 class="font-extrabold text-sm uppercase tracking-wider text-slate-900">
              {{ branchInfo.branch_name || 'STOCK BARTOLO' }}
            </h3>
            <p v-if="branchInfo.branch_code" class="text-[11px] text-slate-600 font-bold">
              SUCURSAL: {{ branchInfo.branch_code }}
            </p>
            <p v-if="branchInfo.address" class="text-[10px] text-slate-600">
              {{ branchInfo.address }}
            </p>
            <p v-if="branchInfo.phone" class="text-[10px] text-slate-600">
              Tel: {{ branchInfo.phone }}
            </p>
            <div class="border-b border-dashed border-slate-400 my-2"></div>
            <p class="font-bold text-xs uppercase">
              TICKET DE VENTA #{{ order.order_id }}
            </p>
            <p class="text-[10px] text-slate-500">
              Fecha: {{ formatDate(order.order_date) }}
            </p>
          </div>

          <!-- Customer Data -->
          <div class="border-y border-dashed border-slate-300 py-1.5 text-[11px] space-y-0.5">
            <p><span class="font-bold">Cliente:</span> {{ order.client_name }}</p>
            <p v-if="order.client_contact"><span class="font-bold">Contacto:</span> {{ order.client_contact }}</p>
            <p><span class="font-bold">Pago:</span> {{ paymentMethodLabel(order.payment_type) }}</p>
          </div>

          <!-- Items Table -->
          <div class="space-y-1 text-[11px]">
            <div class="flex justify-between font-bold border-b border-slate-300 pb-1">
              <span>Cant / Descripción</span>
              <span>Total</span>
            </div>
            <div
              v-for="(item, idx) in items"
              :key="idx"
              class="flex justify-between items-start gap-2 py-0.5"
            >
              <div class="min-w-0 flex-1">
                <p class="font-semibold truncate">{{ item.product_name || item.item_name }}</p>
                <p class="text-[10px] text-slate-500">
                  {{ item.quantity }} x {{ formatCurrency(item.rate) }}
                  <span v-if="item.is_custom" class="text-[9px] italic text-indigo-600">(Servicio)</span>
                </p>
              </div>
              <span class="font-bold shrink-0">{{ formatCurrency(item.total) }}</span>
            </div>
          </div>

          <!-- Totals -->
          <div class="border-t border-dashed border-slate-400 pt-2 text-[11px] space-y-1">
            <div class="flex justify-between">
              <span>Subtotal:</span>
              <span>{{ formatCurrency(order.sub_total) }}</span>
            </div>
            <div class="flex justify-between">
              <span>IVA/IGV ({{ branchTaxRate }}%):</span>
              <span>{{ formatCurrency(order.vat) }}</span>
            </div>
            <div v-if="order.discount > 0" class="flex justify-between text-rose-600">
              <span>Descuento:</span>
              <span>-{{ formatCurrency(order.discount) }}</span>
            </div>
            <div class="flex justify-between font-extrabold text-sm border-t border-slate-300 pt-1">
              <span>TOTAL:</span>
              <span>{{ formatCurrency(order.grand_total) }}</span>
            </div>
            <div class="flex justify-between text-[11px] pt-1">
              <span>Pagado:</span>
              <span class="font-semibold">{{ formatCurrency(order.paid) }}</span>
            </div>
            <div v-if="order.due > 0" class="flex justify-between text-[11px] text-amber-700 font-bold">
              <span>Pendiente:</span>
              <span>{{ formatCurrency(order.due) }}</span>
            </div>
          </div>

          <!-- Footer -->
          <div class="text-center pt-2 border-t border-dashed border-slate-300 text-[10px] text-slate-500 space-y-1">
            <p class="font-bold">¡GRACIAS POR SU COMPRA!</p>
            <p>Comprobante simplificado para fines de control interno.</p>
            <p class="text-[9px] text-slate-400">Stock Bartolo POS</p>
          </div>

          <!-- Optional Second Copy for Company/Cashier -->
          <div v-if="doubleTicketCopy" class="pt-4 border-t-2 border-dashed border-slate-500 text-center space-y-3">
            <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">
              --- ✂ COPIA DE CONTROL (EMPRESA / CAJA) ✂ ---
            </p>
            <div class="flex justify-between text-[10px]">
              <span>TICKET #{{ order.order_id }}</span>
              <span>TOTAL: {{ formatCurrency(order.grand_total) }}</span>
            </div>
            <div class="pt-6 border-b border-slate-400 w-3/4 mx-auto"></div>
            <p class="text-[9px] text-slate-500">Firma del Cliente / Receptor</p>
          </div>
        </div>

        <!-- ================= FORMAT 2: A5 (MEDIA PÁGINA) ================= -->
        <div
          v-else-if="selectedFormat === 'a5'"
          id="print-area-a5"
          class="w-[560px] bg-white p-6 text-slate-800 shadow-md rounded-lg space-y-4 text-xs leading-normal"
        >
          <!-- Top Header Box -->
          <div class="flex justify-between items-start border-b-2 border-indigo-600 pb-3">
            <div class="space-y-1">
              <h3 class="font-black text-lg text-slate-900 tracking-tight">
                {{ branchInfo.branch_name || 'STOCK BARTOLO' }}
              </h3>
              <p class="text-xs text-slate-600">{{ branchInfo.address || 'Casa Matriz' }}</p>
              <p class="text-[11px] text-slate-500">
                Teléfono: {{ branchInfo.phone || 'S/N' }} · Sucursal: {{ branchInfo.branch_code || 'SUC-001' }}
              </p>
            </div>
            <div class="text-right rounded-xl border border-indigo-200 bg-indigo-50/70 p-3 min-w-[170px]">
              <p class="text-[11px] font-bold text-indigo-700 uppercase">NOTA DE VENTA / PEDIDO</p>
              <p class="text-base font-black text-indigo-950">Nº {{ String(order.order_id).padStart(6, '0') }}</p>
              <p class="text-[10px] text-slate-500 mt-1">Fecha: {{ formatDate(order.order_date) }}</p>
            </div>
          </div>

          <!-- Customer Info Strip -->
          <div class="grid grid-cols-2 gap-3 rounded-xl bg-slate-50 border border-slate-200 p-3 text-xs">
            <div>
              <p class="text-[10px] font-bold uppercase text-slate-400">Cliente</p>
              <p class="font-bold text-slate-900 text-sm truncate">{{ order.client_name }}</p>
              <p class="text-slate-600 mt-0.5">Contacto / Tel: {{ order.client_contact || '-' }}</p>
            </div>
            <div>
              <p class="text-[10px] font-bold uppercase text-slate-400">Condición de Pago</p>
              <p class="font-semibold text-slate-800">{{ paymentMethodLabel(order.payment_type) }}</p>
              <p class="text-[11px]" :class="order.payment_status === 1 ? 'text-emerald-700 font-bold' : 'text-amber-700'">
                Estado: {{ order.payment_status === 1 ? 'Cancelado / Pagado' : order.payment_status === 2 ? 'Adelanto parcial' : 'Por cobrar' }}
              </p>
            </div>
          </div>

          <!-- Items Table -->
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-slate-300 bg-slate-100 text-[11px] font-bold text-slate-700">
                <th class="py-2 px-2.5">Ítem</th>
                <th class="py-2 px-2.5">Descripción</th>
                <th class="py-2 px-2 text-center">Cant.</th>
                <th class="py-2 px-2.5 text-right">Precio Unit.</th>
                <th class="py-2 px-2.5 text-right">Importe</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="(item, idx) in items" :key="idx" class="hover:bg-slate-50/50">
                <td class="py-2 px-2.5 text-slate-400">{{ idx + 1 }}</td>
                <td class="py-2 px-2.5 font-medium text-slate-900">
                  {{ item.product_name || item.item_name }}
                  <span v-if="item.is_custom" class="text-[10px] text-indigo-600 font-normal"> (Servicio/Personalizado)</span>
                </td>
                <td class="py-2 px-2 text-center font-semibold">{{ item.quantity }}</td>
                <td class="py-2 px-2.5 text-right text-slate-600">{{ formatCurrency(item.rate) }}</td>
                <td class="py-2 px-2.5 text-right font-bold text-slate-900">{{ formatCurrency(item.total) }}</td>
              </tr>
            </tbody>
          </table>

          <!-- Summary & Signatures Row -->
          <div class="grid grid-cols-2 gap-4 items-end pt-2 border-t border-slate-200">
            <div class="space-y-4">
              <p class="text-[10px] text-slate-400 leading-tight">
                * Este documento representa un comprobante de venta emitido bajo el sistema de inventario y facturación Stock Bartolo.
              </p>
              <div class="flex gap-4 pt-4">
                <div class="flex-1 text-center border-t border-slate-300 pt-1">
                  <p class="text-[10px] text-slate-500 font-medium">Entregado Conforme</p>
                </div>
                <div class="flex-1 text-center border-t border-slate-300 pt-1">
                  <p class="text-[10px] text-slate-500 font-medium">Recibido Conforme</p>
                </div>
              </div>
            </div>

            <!-- Financials -->
            <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 space-y-1.5 text-xs">
              <div class="flex justify-between text-slate-600">
                <span>Subtotal:</span>
                <span class="font-medium text-slate-900">{{ formatCurrency(order.sub_total) }}</span>
              </div>
              <div class="flex justify-between text-slate-600">
                <span>IVA/IGV ({{ branchTaxRate }}%):</span>
                <span class="font-medium text-slate-900">{{ formatCurrency(order.vat) }}</span>
              </div>
              <div v-if="order.discount > 0" class="flex justify-between text-rose-600">
                <span>Descuento aplicado:</span>
                <span class="font-semibold">-{{ formatCurrency(order.discount) }}</span>
              </div>
              <div class="flex justify-between font-black text-sm border-t border-slate-300 pt-1.5 text-indigo-900">
                <span>TOTAL A PAGAR:</span>
                <span>{{ formatCurrency(order.grand_total) }}</span>
              </div>
              <div class="flex justify-between text-[11px] pt-0.5 text-slate-500">
                <span>Monto abonado:</span>
                <span class="font-semibold text-emerald-700">{{ formatCurrency(order.paid) }}</span>
              </div>
              <div v-if="order.due > 0" class="flex justify-between text-[11px] text-amber-700 font-bold">
                <span>Saldo por cobrar:</span>
                <span>{{ formatCurrency(order.due) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ================= FORMAT 3: A4 (HOJA COMPLETA) ================= -->
        <div
          v-else-if="selectedFormat === 'a4'"
          id="print-area-a4"
          class="w-[720px] bg-white p-8 text-slate-800 shadow-md rounded-lg space-y-6 text-sm leading-normal"
        >
          <!-- Corporate Banner -->
          <div class="flex justify-between items-start border-b-2 border-slate-900 pb-4">
            <div class="space-y-1.5">
              <div class="flex items-center gap-2">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600 text-white font-extrabold text-lg">
                  SB
                </div>
                <h2 class="font-black text-xl text-slate-900 tracking-tight">
                  {{ branchInfo.branch_name || 'STOCK BARTOLO' }}
                </h2>
              </div>
              <p class="text-xs text-slate-600">
                Dirección: {{ branchInfo.address || 'Casa Matriz Central' }}
              </p>
              <p class="text-xs text-slate-600">
                Teléfono: {{ branchInfo.phone || 'S/N' }} · Email: {{ branchInfo.email || 'contacto@stockbartolo.com' }}
              </p>
              <p class="text-xs font-semibold text-indigo-700">
                Sucursal Activa: {{ branchInfo.branch_code || 'SUC-001' }}
              </p>
            </div>

            <div class="text-center rounded-2xl border-2 border-slate-900 p-4 min-w-[220px] space-y-1">
              <p class="text-xs font-black tracking-widest text-slate-500 uppercase">COMPROBANTE COMERCIAL</p>
              <h3 class="text-xl font-black text-slate-950">ORDEN Nº {{ String(order.order_id).padStart(6, '0') }}</h3>
              <p class="text-xs text-slate-600 pt-1 border-t border-slate-200">
                Fecha Emisión: <span class="font-bold text-slate-900">{{ formatDate(order.order_date) }}</span>
              </p>
            </div>
          </div>

          <!-- Customer & Sale Meta Box -->
          <div class="grid grid-cols-2 gap-4 rounded-2xl bg-slate-50 border border-slate-200 p-4 text-xs">
            <div class="space-y-1">
              <p class="font-bold text-slate-400 uppercase text-[10px]">Información del Cliente</p>
              <p class="font-bold text-slate-900 text-sm">{{ order.client_name }}</p>
              <p class="text-slate-600">Teléfono / Contacto: {{ order.client_contact || 'Sin registro' }}</p>
              <p class="text-slate-500 text-[11px]">Tipo de Venta: Mostrador / Punto de Venta Directo</p>
            </div>
            <div class="space-y-1">
              <p class="font-bold text-slate-400 uppercase text-[10px]">Detalles Financieros</p>
              <p class="text-slate-700"><span class="font-semibold">Método de Pago:</span> {{ paymentMethodLabel(order.payment_type) }}</p>
              <p class="text-slate-700">
                <span class="font-semibold">Estado de Pago:</span>
                <span
                  class="ml-1 inline-block rounded-md px-2 py-0.5 text-[10px] font-bold"
                  :class="order.payment_status === 1 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                >
                  {{ order.payment_status === 1 ? 'CANCELADO (100%)' : order.payment_status === 2 ? 'PAGO ADELANTADO' : 'PENDIENTE' }}
                </span>
              </p>
              <p class="text-slate-500 text-[11px]">Moneda: {{ branchInfo.currency_symbol || '$' }} {{ branchInfo.currency_code || 'USD' }}</p>
            </div>
          </div>

          <!-- Itemized Table -->
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b-2 border-slate-300 bg-slate-100 text-slate-700 font-bold">
                <th class="py-2.5 px-3">#</th>
                <th class="py-2.5 px-3">Descripción del Producto o Concepto</th>
                <th class="py-2.5 px-3 text-center">Cantidad</th>
                <th class="py-2.5 px-3 text-right">Precio Unitario</th>
                <th class="py-2.5 px-3 text-right">Subtotal</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="(item, idx) in items" :key="idx" class="hover:bg-slate-50">
                <td class="py-2.5 px-3 text-slate-400">{{ idx + 1 }}</td>
                <td class="py-2.5 px-3 font-semibold text-slate-900">
                  {{ item.product_name || item.item_name }}
                  <span v-if="item.is_custom" class="text-[11px] text-indigo-600 font-normal"> (Concepto / Servicio Especial)</span>
                </td>
                <td class="py-2.5 px-3 text-center font-bold text-slate-800">{{ item.quantity }}</td>
                <td class="py-2.5 px-3 text-right text-slate-600">{{ formatCurrency(item.rate) }}</td>
                <td class="py-2.5 px-3 text-right font-bold text-slate-900">{{ formatCurrency(item.total) }}</td>
              </tr>
            </tbody>
          </table>

          <!-- Totals Strip -->
          <div class="grid grid-cols-2 gap-6 items-start pt-3 border-t-2 border-slate-200">
            <!-- Notes and Terms -->
            <div class="space-y-3 text-xs text-slate-500">
              <div class="rounded-xl bg-slate-50 p-3 border border-slate-200 space-y-1">
                <p class="font-bold text-slate-700 text-xs">Términos y Condiciones:</p>
                <p class="text-[11px] leading-relaxed">
                  Conserve este comprobante para cualquier cambio o reclamo dentro de las 48 horas posteriores a la compra. Los cambios requieren producto sin uso y en empaque original.
                </p>
              </div>

              <!-- Signature boxes -->
              <div class="grid grid-cols-2 gap-4 pt-6">
                <div class="border-t border-slate-400 pt-1 text-center">
                  <p class="text-[11px] font-semibold text-slate-700">Firma Autorizada</p>
                  <p class="text-[9px] text-slate-400">Stock Bartolo</p>
                </div>
                <div class="border-t border-slate-400 pt-1 text-center">
                  <p class="text-[11px] font-semibold text-slate-700">Firma del Cliente</p>
                  <p class="text-[9px] text-slate-400">DNI / RUC Conforme</p>
                </div>
              </div>
            </div>

            <!-- Financial Figures -->
            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4 space-y-2 text-xs">
              <div class="flex justify-between text-slate-600">
                <span>Subtotal Gravado:</span>
                <span class="font-semibold text-slate-900">{{ formatCurrency(order.sub_total) }}</span>
              </div>
              <div class="flex justify-between text-slate-600">
                <span>IVA / IGV ({{ branchTaxRate }}%):</span>
                <span class="font-semibold text-slate-900">{{ formatCurrency(order.vat) }}</span>
              </div>
              <div v-if="order.discount > 0" class="flex justify-between text-rose-600">
                <span>Descuento aplicado:</span>
                <span class="font-bold">-{{ formatCurrency(order.discount) }}</span>
              </div>
              <div class="flex justify-between font-black text-base border-t border-slate-300 pt-2 text-indigo-900">
                <span>TOTAL A PAGAR:</span>
                <span>{{ formatCurrency(order.grand_total) }}</span>
              </div>
              <div class="flex justify-between text-xs pt-1 border-t border-slate-200 text-slate-600">
                <span>Monto Pagado:</span>
                <span class="font-bold text-emerald-700">{{ formatCurrency(order.paid) }}</span>
              </div>
              <div v-if="order.due > 0" class="flex justify-between text-xs text-amber-700 font-bold">
                <span>Saldo Pendiente:</span>
                <span>{{ formatCurrency(order.due) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-between w-full">
        <span class="text-xs text-slate-400">
          Formato actual: <strong class="text-slate-700">{{ formatLabel }}</strong>
        </span>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
            @click="handleClose"
          >
            Cerrar
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs sm:text-sm font-semibold text-white hover:bg-indigo-700 shadow-sm transition"
            @click="triggerPrint"
          >
            <Printer class="h-4 w-4" />
            Imprimir
          </button>
        </div>
      </div>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Receipt, FileText, FileSpreadsheet, Printer } from '@lucide/vue'
import BaseModal from '@/components/common/BaseModal.vue'
import { formatCurrency, formatDate, paymentMethodLabel } from '@/utils/formatters'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  orderData: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['close'])

const auth = useAuthStore()
const selectedFormat = ref('ticket')
const doubleTicketCopy = ref(false)

const order = computed(() => props.orderData?.order || null)
const items = computed(() => props.orderData?.items || [])
const branchInfo = computed(() => props.orderData?.branch || auth.activeBranch || {})
const branchTaxRate = computed(() => Number(branchInfo.value.tax_rate ?? 13))

const formatLabel = computed(() => {
  if (selectedFormat.value === 'ticket') return 'Ticket Térmico (80mm)'
  if (selectedFormat.value === 'a5') return 'Formato A5 (Media Página)'
  return 'Formato A4 (Hoja Completa)'
})

function handleClose() {
  emit('close')
}

function triggerPrint() {
  if (!order.value) return

  let printElementId = 'print-area-ticket'
  let pageSizeRule = '@page { size: 80mm auto; margin: 3mm; }'

  if (selectedFormat.value === 'a5') {
    printElementId = 'print-area-a5'
    pageSizeRule = '@page { size: A5 landscape; margin: 8mm; }'
  } else if (selectedFormat.value === 'a4') {
    printElementId = 'print-area-a4'
    pageSizeRule = '@page { size: A4 portrait; margin: 10mm; }'
  }

  const printNode = document.getElementById(printElementId)
  if (!printNode) return

  const printWindow = window.open('', '_blank', 'width=850,height=900')
  if (!printWindow) {
    window.print()
    return
  }

  const htmlContent = `
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <title>Comprobante de Venta #${order.value.order_id} - ${formatLabel.value}</title>
      <script src="https://cdn.tailwindcss.com"><\/script>
      <style>
        ${pageSizeRule}
        body {
          margin: 0;
          padding: 8px;
          background: #ffffff;
          font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
          color: #0f172a;
          -webkit-print-color-adjust: exact;
          print-color-adjust: exact;
        }
        @media print {
          body {
            padding: 0;
          }
        }
      </style>
    </head>
    <body>
      <div class="flex justify-center">
        ${printNode.outerHTML}
      </div>
      <script>
        window.onload = function() {
          window.focus();
          window.print();
          setTimeout(function() { window.close(); }, 500);
        };
      <\/script>
    </body>
    </html>
  `

  printWindow.document.open()
  printWindow.document.write(htmlContent)
  printWindow.document.close()
}
</script>

