<template>
  <div
    v-if="modelValue && receiptData"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto no-print"
    @click.self="close"
  >
    <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden my-6 flex flex-col max-h-[90vh]">
      <!-- Modal Header -->
      <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5 bg-slate-50">
        <div class="flex items-center gap-2">
          <Printer class="h-4 w-4 text-indigo-600" />
          <h3 class="text-sm font-bold text-slate-900">Comprobante de Operación</h3>
        </div>
        <button
          type="button"
          class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition"
          @click="close"
        >
          <X class="h-4 w-4" />
        </button>
      </div>

      <!-- Controls Toolbar -->
      <div class="px-5 py-2.5 bg-indigo-50/50 border-b border-indigo-100/70 flex items-center justify-between gap-3 text-xs">
        <label class="flex items-center gap-2 cursor-pointer font-semibold text-slate-700">
          <input
            v-model="doublePrint"
            type="checkbox"
            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
          />
          <span>Doble impresión (Cliente + Empresa)</span>
        </label>
        <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-700">
          Formato Ticket 80mm
        </span>
      </div>

      <!-- Scrollable Ticket Body Preview -->
      <div class="flex-1 overflow-y-auto p-5 bg-slate-100/70 flex justify-center">
        <!-- Printable Ticket Element -->
        <div
          id="operation-thermal-ticket"
          class="printable-ticket bg-white text-slate-900 p-6 rounded-lg shadow-sm border border-slate-200 font-mono text-[12px] leading-relaxed w-full max-w-[340px]"
        >
          <!-- TICKET 1: COPIA CLIENTE -->
          <div class="ticket-copy space-y-3">
            <div class="text-center space-y-0.5">
              <img
                v-if="receiptData.company?.logo"
                :src="resolveLogoUrl(receiptData.company.logo)"
                alt="Logo"
                class="max-h-12 max-w-[130px] mx-auto object-contain mb-1"
              />
              <h1 v-if="receiptData.company?.legal_name" class="text-xs font-black uppercase text-slate-900 leading-tight">
                {{ receiptData.company.legal_name }}
              </h1>
              <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-900">
                {{ receiptData.company?.name || 'STOCK BARTOLO' }}
              </h2>
              <p v-if="receiptData.company?.tax_id" class="text-[11px] font-bold text-slate-800">
                RUC: {{ receiptData.company.tax_id }}
              </p>
              <p class="text-[11px] font-bold text-slate-700">
                {{ receiptData.branch?.branch_name || 'Sucursal Principal' }}
              </p>
              <p v-if="receiptData.branch?.address" class="text-[10px] text-slate-500">
                {{ receiptData.branch.address }}
              </p>
              <p v-if="receiptData.branch?.phone" class="text-[10px] text-slate-500">
                Telf: {{ receiptData.branch.phone }}
              </p>
            </div>

            <div class="border-t border-b border-dashed border-slate-400 py-1.5 text-center">
              <p class="font-extrabold text-sm uppercase">
                *** {{ receiptData.operation_type_label || receiptData.operation_type }} ***
              </p>
              <p class="font-bold text-[11px] mt-0.5">{{ receiptData.operation_code }}</p>
              <p class="text-[10px] text-slate-500">{{ receiptData.created_at }}</p>
            </div>

            <div class="space-y-1 text-[11px]">
              <div class="flex justify-between">
                <span class="text-slate-600">Cajero:</span>
                <span class="font-bold">{{ receiptData.cashier || 'Cajero' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-600">Cliente:</span>
                <span class="font-bold text-right max-w-[190px] truncate">{{ receiptData.client?.name }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-600">Doc:</span>
                <span>{{ receiptData.client?.document_type }} {{ receiptData.client?.document_number }}</span>
              </div>
            </div>

            <div class="border-t border-b border-dashed border-slate-400 py-2 space-y-1 text-[11px]">
              <div class="flex justify-between">
                <span>Monto Base:</span>
                <span class="font-bold">S/ {{ Number(receiptData.amount || 0).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Comisión (1/100):</span>
                <span>S/ {{ Number(receiptData.commission_amount || 0).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between text-[10px] text-slate-500">
                <span>Comisión incluida:</span>
                <span>{{ receiptData.commission_included ? 'SÍ' : 'NO' }}</span>
              </div>
              <div class="flex justify-between text-[10px] text-slate-500">
                <span>Monto Neto:</span>
                <span>S/ {{ Number(receiptData.net_amount || 0).toFixed(2) }}</span>
              </div>
              <div class="pt-1.5 border-t border-dotted border-slate-300 flex justify-between text-xs font-extrabold">
                <span>TOTAL:</span>
                <span>S/ {{ Number(receiptData.total_customer_amount || 0).toFixed(2) }}</span>
              </div>
            </div>

            <!-- Points Section -->
            <div class="rounded bg-slate-50 border border-slate-200 p-2 text-[10px] space-y-0.5">
              <div class="flex justify-between">
                <span class="font-semibold text-amber-800">Puntos ganados:</span>
                <span class="font-bold text-amber-800">+{{ receiptData.points_earned || 0 }} pts</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-600">Saldo actual puntos:</span>
                <span class="font-bold">{{ receiptData.client_new_points || 0 }} pts</span>
              </div>
              <div class="flex justify-between text-[9px] text-slate-500">
                <span>Histórico acumulado:</span>
                <span>{{ receiptData.client_lifetime_points || 0 }} pts</span>
              </div>
            </div>

            <div class="text-center pt-1 space-y-1">
              <p class="text-[10px] text-slate-500 italic">
                {{ receiptData.company?.receipt_footer || 'Gracias por su preferencia.' }}
              </p>
              <p class="text-[10px] font-bold tracking-widest text-slate-700">
                *** COPIA CLIENTE ***
              </p>
            </div>
          </div>

          <!-- TEAR LINE & TICKET 2: COPIA EMPRESA -->
          <div v-if="doublePrint" class="mt-6 pt-6 border-t-2 border-dashed border-slate-500 space-y-3">
            <div class="text-center text-[10px] font-bold text-slate-400 py-1">
              - - - - - ✂ CORTAR AQUÍ ✂ - - - - -
            </div>

            <div class="text-center space-y-0.5">
              <img
                v-if="receiptData.company?.logo"
                :src="resolveLogoUrl(receiptData.company.logo)"
                alt="Logo"
                class="max-h-12 max-w-[130px] mx-auto object-contain mb-1"
              />
              <h1 v-if="receiptData.company?.legal_name" class="text-xs font-black uppercase text-slate-900 leading-tight">
                {{ receiptData.company.legal_name }}
              </h1>
              <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-900">
                {{ receiptData.company?.name || 'STOCK BARTOLO' }}
              </h2>
              <p v-if="receiptData.company?.tax_id" class="text-[11px] font-bold text-slate-800">
                RUC: {{ receiptData.company.tax_id }}
              </p>
              <p class="text-[11px] font-bold text-slate-700">
                {{ receiptData.branch?.branch_name || 'Sucursal Principal' }}
              </p>
            </div>

            <div class="border-t border-b border-dashed border-slate-400 py-1.5 text-center">
              <p class="font-extrabold text-sm uppercase">
                *** {{ receiptData.operation_type_label || receiptData.operation_type }} ***
              </p>
              <p class="font-bold text-[11px] mt-0.5">{{ receiptData.operation_code }}</p>
              <p class="text-[10px] text-slate-500">{{ receiptData.created_at }}</p>
            </div>

            <div class="space-y-1 text-[11px]">
              <div class="flex justify-between">
                <span class="text-slate-600">Cajero:</span>
                <span class="font-bold">{{ receiptData.cashier || 'Cajero' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-600">Cliente:</span>
                <span class="font-bold text-right max-w-[190px] truncate">{{ receiptData.client?.name }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-600">Doc:</span>
                <span>{{ receiptData.client?.document_type }} {{ receiptData.client?.document_number }}</span>
              </div>
            </div>

            <div class="border-t border-b border-dashed border-slate-400 py-2 space-y-1 text-[11px]">
              <div class="flex justify-between">
                <span>Monto Base:</span>
                <span class="font-bold">S/ {{ Number(receiptData.amount || 0).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Comisión (1/100):</span>
                <span>S/ {{ Number(receiptData.commission_amount || 0).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between text-[10px] text-slate-500">
                <span>Comisión incluida:</span>
                <span>{{ receiptData.commission_included ? 'SÍ' : 'NO' }}</span>
              </div>
              <div class="pt-1.5 border-t border-dotted border-slate-300 flex justify-between text-xs font-extrabold">
                <span>TOTAL:</span>
                <span>S/ {{ Number(receiptData.total_customer_amount || 0).toFixed(2) }}</span>
              </div>
            </div>

            <div class="pt-4 text-center space-y-3">
              <div class="pt-8 border-b border-slate-400 w-3/4 mx-auto"></div>
              <p class="text-[10px] text-slate-600">Firma del Cliente</p>
              <p class="text-[10px] font-bold tracking-widest text-slate-700">
                *** COPIA EMPRESA / CAJA ***
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-5 py-3.5 bg-slate-50">
        <button
          type="button"
          class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
          @click="close"
        >
          Cerrar
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-xs font-bold text-white hover:bg-indigo-700 shadow-xs transition"
          @click="printTicket"
        >
          <Printer class="h-4 w-4" />
          Imprimir Ticket
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Printer, X } from '@lucide/vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  receiptData: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue'])

const doublePrint = ref(true)

watch(
  () => props.receiptData,
  (val) => {
    if (val && typeof val.print_double_ticket !== 'undefined') {
      doublePrint.value = Boolean(val.print_double_ticket)
    }
  },
  { immediate: true }
)

function close() {
  emit('update:modelValue', false)
}

function resolveLogoUrl(path) {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) return path
  return path.startsWith('/') ? path : `/${path}`
}

function printTicket() {
  window.print()
}
</script>

<style>
@media print {
  body * {
    visibility: hidden;
  }
  .no-print {
    display: none !important;
  }
  #operation-thermal-ticket,
  #operation-thermal-ticket * {
    visibility: visible;
  }
  #operation-thermal-ticket {
    position: absolute;
    left: 0;
    top: 0;
    width: 80mm !important;
    max-width: 80mm !important;
    margin: 0;
    padding: 10px;
    box-shadow: none !important;
    border: none !important;
    background: #fff !important;
    color: #000 !important;
  }
}
</style>

