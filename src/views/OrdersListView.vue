<template>
  <div class="space-y-5">
    <!-- Toolbar -->
    <div class="no-print flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
      <div class="flex flex-col sm:flex-row gap-3 flex-1">
        <div class="relative flex-1 max-w-sm">
          <Search class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input
            v-model.trim="search"
            type="text"
            placeholder="Buscar cliente, teléfono o # orden..."
            class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
        <select
          v-model="statusFilter"
          class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
        >
          <option value="all">Todos los estados</option>
          <option value="1">Pago completo</option>
          <option value="2">Pago parcial</option>
          <option value="3">No pagado</option>
        </select>
      </div>

      <RouterLink
        to="/orders/create"
        class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 shadow-xs transition shrink-0"
      >
        <Plus class="h-4 w-4" />
        Agregar orden
      </RouterLink>
    </div>

    <!-- Orders Content -->
    <div class="no-print rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
      <div v-if="loading && items.length === 0" class="p-10 text-center text-sm text-slate-500">
        <Loader2 class="h-6 w-6 animate-spin mx-auto mb-2 text-indigo-600" />
        Cargando órdenes...
      </div>

      <div v-else-if="filtered.length === 0" class="p-10 text-center text-sm text-slate-500">
        No se encontraron órdenes.
      </div>

      <template v-else>
        <!-- Desktop Table -->
        <div class="hidden lg:block overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase text-slate-500">
              <tr>
                <th class="px-5 py-3.5">#</th>
                <th class="px-5 py-3.5">Fecha</th>
                <th class="px-5 py-3.5">Cliente</th>
                <th class="px-5 py-3.5">Teléfono</th>
                <th class="px-5 py-3.5">Productos</th>
                <th class="px-5 py-3.5">Total</th>
                <th class="px-5 py-3.5">Saldo</th>
                <th class="px-5 py-3.5">Estado pago</th>
                <th class="px-5 py-3.5 text-right">Opciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="order in filtered" :key="order.order_id" class="hover:bg-slate-50/70 transition">
                <td class="px-5 py-3.5 font-mono text-xs text-slate-500">#{{ order.order_id }}</td>
                <td class="px-5 py-3.5 text-slate-600">{{ formatDate(order.order_date) }}</td>
                <td class="px-5 py-3.5 font-semibold text-slate-900">{{ order.client_name }}</td>
                <td class="px-5 py-3.5 text-slate-600">{{ order.client_contact }}</td>
                <td class="px-5 py-3.5 text-slate-600">{{ order.item_count }}</td>
                <td class="px-5 py-3.5 font-semibold text-slate-900">{{ formatCurrency(order.grand_total) }}</td>
                <td class="px-5 py-3.5 font-medium" :class="order.due > 0 ? 'text-amber-700' : 'text-emerald-700'">
                  {{ formatCurrency(order.due) }}
                </td>
                <td class="px-5 py-3.5">
                  <StatusBadge
                    :variant="orderPaymentStatusMeta(order.payment_status).variant"
                    :label="orderPaymentStatusMeta(order.payment_status).label"
                  />
                </td>
                <td class="px-5 py-3.5 text-right">
                  <div class="inline-flex items-center gap-1">
                    <RouterLink
                      :to="`/orders/${order.order_id}/edit`"
                      class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 inline-flex items-center gap-1"
                    >
                      <Pencil class="h-3.5 w-3.5" />
                      Editar
                    </RouterLink>
                    <button
                      type="button"
                      class="rounded-lg border border-emerald-200 px-2.5 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50 inline-flex items-center gap-1"
                      @click="openPaymentModal(order)"
                    >
                      <CreditCard class="h-3.5 w-3.5" />
                      Pagar
                    </button>
                    <button
                      type="button"
                      class="rounded-lg border border-slate-200 p-1.5 text-slate-600 hover:bg-slate-100"
                      title="Imprimir factura"
                      @click="openPrintModal(order.order_id)"
                    >
                      <Printer class="h-3.5 w-3.5" />
                    </button>
                    <button
                      type="button"
                      class="rounded-lg border border-rose-200 p-1.5 text-rose-600 hover:bg-rose-50"
                      title="Eliminar"
                      @click="deletingOrder = order"
                    >
                      <Trash2 class="h-3.5 w-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile & Tablet Cards -->
        <div class="lg:hidden divide-y divide-slate-100">
          <div v-for="order in filtered" :key="order.order_id" class="p-4 space-y-3">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-semibold text-slate-900">{{ order.client_name }}</p>
                <p class="text-xs text-slate-500">
                  Orden #{{ order.order_id }} · {{ formatDate(order.order_date) }} · {{ order.client_contact }}
                </p>
              </div>
              <StatusBadge
                :variant="orderPaymentStatusMeta(order.payment_status).variant"
                :label="orderPaymentStatusMeta(order.payment_status).label"
              />
            </div>

            <div class="grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-2.5 text-xs">
              <div>
                <p class="text-slate-500">Productos</p>
                <p class="font-semibold text-slate-900">{{ order.item_count }}</p>
              </div>
              <div>
                <p class="text-slate-500">Total</p>
                <p class="font-semibold text-slate-900">{{ formatCurrency(order.grand_total) }}</p>
              </div>
              <div>
                <p class="text-slate-500">Saldo</p>
                <p class="font-semibold" :class="order.due > 0 ? 'text-amber-700' : 'text-emerald-700'">
                  {{ formatCurrency(order.due) }}
                </p>
              </div>
            </div>

            <div class="flex flex-wrap gap-2">
              <RouterLink
                :to="`/orders/${order.order_id}/edit`"
                class="flex-1 rounded-lg border border-slate-200 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 inline-flex items-center justify-center gap-1"
              >
                <Pencil class="h-3.5 w-3.5" /> Editar
              </RouterLink>
              <button
                type="button"
                class="flex-1 rounded-lg border border-emerald-200 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50 inline-flex items-center justify-center gap-1"
                @click="openPaymentModal(order)"
              >
                <CreditCard class="h-3.5 w-3.5" /> Pagar
              </button>
              <button
                type="button"
                class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                @click="openPrintModal(order.order_id)"
              >
                <Printer class="h-3.5 w-3.5" />
              </button>
              <button
                type="button"
                class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50"
                @click="deletingOrder = order"
              >
                <Trash2 class="h-3.5 w-3.5" />
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Payment Modal -->
    <BaseModal
      :open="Boolean(paymentTarget)"
      title="Editar pago de orden"
      :subtitle="paymentTarget ? `Orden #${paymentTarget.order_id} · ${paymentTarget.client_name}` : ''"
      @close="paymentTarget = null"
    >
      <form id="orderPaymentForm" class="space-y-4" @submit.prevent="submitPayment">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Saldo pendiente</label>
          <input
            type="text"
            disabled
            :value="formatCurrency(paymentTarget?.due || 0)"
            class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3.5 py-2 text-sm font-semibold text-slate-700"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Monto a pagar ($) *</label>
          <input
            v-model.number="paymentForm.payAmount"
            type="number"
            step="0.01"
            min="0"
            required
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Método de pago *</label>
          <select
            v-model.number="paymentForm.paymentType"
            required
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          >
            <option :value="1">Cheque</option>
            <option :value="2">Efectivo</option>
            <option :value="3">Tarjeta de crédito</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Estado del pago *</label>
          <select
            v-model.number="paymentForm.paymentStatus"
            required
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          >
            <option :value="1">Pago completo</option>
            <option :value="2">Pago por adelantado</option>
            <option :value="3">No pagado</option>
          </select>
        </div>
      </form>
      <template #footer>
        <button
          type="button"
          class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50"
          @click="paymentTarget = null"
        >
          Cerrar
        </button>
        <button
          type="submit"
          form="orderPaymentForm"
          :disabled="savingPayment"
          class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 inline-flex items-center gap-2"
        >
          <Loader2 v-if="savingPayment" class="h-4 w-4 animate-spin" />
          Guardar cambios
        </button>
      </template>
    </BaseModal>

    <!-- Print Invoice Modal -->
    <BaseModal
      :open="Boolean(printData)"
      title="Comprobante de Orden"
      size="lg"
      @close="printData = null"
    >
      <div v-if="printData" class="space-y-5 text-sm">
        <div class="flex flex-col sm:flex-row justify-between gap-3 border-b border-slate-200 pb-4">
          <div>
            <p class="text-xs uppercase font-semibold text-slate-400">Stock Bartolo</p>
            <h4 class="text-lg font-bold text-slate-900">Orden #{{ printData.order.order_id }}</h4>
            <p class="text-slate-600">Fecha: {{ formatDate(printData.order.order_date) }}</p>
          </div>
          <div class="sm:text-right">
            <p class="font-semibold text-slate-900">{{ printData.order.client_name }}</p>
            <p class="text-slate-600">Tel: {{ printData.order.client_contact }}</p>
            <p class="text-xs text-slate-500">{{ paymentMethodLabel(printData.order.payment_type) }}</p>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm border border-slate-200 rounded-xl">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500">
              <tr>
                <th class="px-3 py-2">#</th>
                <th class="px-3 py-2">Producto</th>
                <th class="px-3 py-2 text-right">Precio</th>
                <th class="px-3 py-2 text-right">Cantidad</th>
                <th class="px-3 py-2 text-right">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="(item, idx) in printData.items" :key="idx">
                <td class="px-3 py-2">{{ idx + 1 }}</td>
                <td class="px-3 py-2 font-medium">{{ item.product_name }}</td>
                <td class="px-3 py-2 text-right">{{ formatCurrency(item.rate) }}</td>
                <td class="px-3 py-2 text-right">{{ item.quantity }}</td>
                <td class="px-3 py-2 text-right font-semibold">{{ formatCurrency(item.total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 rounded-xl bg-slate-50 p-4 text-xs">
          <div>
            <p class="text-slate-500">Sub total</p>
            <p class="font-bold text-slate-900 text-sm">{{ formatCurrency(printData.order.sub_total) }}</p>
          </div>
          <div>
            <p class="text-slate-500">IVA (13%)</p>
            <p class="font-bold text-slate-900 text-sm">{{ formatCurrency(printData.order.vat) }}</p>
          </div>
          <div>
            <p class="text-slate-500">Descuento</p>
            <p class="font-bold text-slate-900 text-sm">{{ formatCurrency(printData.order.discount) }}</p>
          </div>
          <div>
            <p class="text-slate-500">Total</p>
            <p class="font-bold text-indigo-700 text-sm">{{ formatCurrency(printData.order.grand_total) }}</p>
          </div>
          <div>
            <p class="text-slate-500">Pagado</p>
            <p class="font-bold text-emerald-700 text-sm">{{ formatCurrency(printData.order.paid) }}</p>
          </div>
          <div>
            <p class="text-slate-500">Pendiente</p>
            <p class="font-bold text-amber-700 text-sm">{{ formatCurrency(printData.order.due) }}</p>
          </div>
        </div>
      </div>

      <template #footer>
        <button
          type="button"
          class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50"
          @click="printData = null"
        >
          Cerrar
        </button>
        <button
          type="button"
          class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 inline-flex items-center gap-2"
          @click="printInvoice"
        >
          <Printer class="h-4 w-4" />
          Imprimir
        </button>
      </template>
    </BaseModal>

    <ConfirmModal
      :open="Boolean(deletingOrder)"
      title="Eliminar orden"
      :message="`¿Realmente deseas eliminar la orden #${deletingOrder?.order_id || ''} de ${deletingOrder?.client_name || ''}?`"
      :loading="deleting"
      @close="deletingOrder = null"
      @confirm="confirmDelete"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { storeToRefs } from 'pinia'
import { Search, Plus, Pencil, CreditCard, Printer, Trash2, Loader2 } from '@lucide/vue'
import { useOrdersStore } from '@/stores/orders'
import { useUiStore } from '@/stores/ui'
import { extractErrorMessage } from '@/api/client'
import {
  formatCurrency,
  formatDate,
  orderPaymentStatusMeta,
  paymentMethodLabel,
} from '@/utils/formatters'
import { escapeHtml } from '@/utils/security'
import StatusBadge from '@/components/common/StatusBadge.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import ConfirmModal from '@/components/common/ConfirmModal.vue'

const store = useOrdersStore()
const ui = useUiStore()
const { items, loading } = storeToRefs(store)

const search = ref('')
const statusFilter = ref('all')
const paymentTarget = ref(null)
const savingPayment = ref(false)
const printData = ref(null)
const deletingOrder = ref(null)
const deleting = ref(false)

const paymentForm = reactive({
  payAmount: 0,
  paymentType: 2,
  paymentStatus: 1,
})

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  return items.value.filter((o) => {
    const matchesSearch =
      !q ||
      o.client_name.toLowerCase().includes(q) ||
      o.client_contact.toLowerCase().includes(q) ||
      String(o.order_id).includes(q)
    if (!matchesSearch) return false
    if (statusFilter.value !== 'all') {
      return String(o.payment_status) === statusFilter.value
    }
    return true
  })
})

function openPaymentModal(order) {
  paymentTarget.value = order
  paymentForm.payAmount = order.due
  paymentForm.paymentType = order.payment_type || 2
  paymentForm.paymentStatus = order.payment_status || 1
}

async function submitPayment() {
  if (!paymentTarget.value) return
  savingPayment.value = true
  try {
    await store.addPayment({
      orderId: paymentTarget.value.order_id,
      payAmount: paymentForm.payAmount,
      paymentType: paymentForm.paymentType,
      paymentStatus: paymentForm.paymentStatus,
    })
    ui.success('Pago actualizado exitosamente.')
    paymentTarget.value = null
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    savingPayment.value = false
  }
}

async function openPrintModal(orderId) {
  try {
    printData.value = await store.fetchPrintOrder(orderId)
  } catch (err) {
    ui.error(extractErrorMessage(err))
  }
}

function printInvoice() {
  if (!printData.value) return
  const o = printData.value.order
  const rows = printData.value.items
    .map(
      (i, idx) =>
        `<tr><td>${idx + 1}</td><td>${escapeHtml(i.product_name)}</td><td style="text-align:right">$${Number(i.rate).toFixed(2)}</td><td style="text-align:right">${Number(i.quantity)}</td><td style="text-align:right">$${Number(i.total).toFixed(2)}</td></tr>`
    )
    .join('')
  const win = window.open('', '_blank', 'height=650,width=820')
  if (!win) {
    window.print()
    return
  }
  win.document.write(`
    <!DOCTYPE html>
    <html lang="es"><head><meta charset="utf-8"><title>Orden #${escapeHtml(o.order_id)}</title>
    <style>
      body{font-family:system-ui,sans-serif;padding:24px;color:#0f172a}
      table{width:100%;border-collapse:collapse;margin:16px 0}
      th,td{border:1px solid #cbd5e1;padding:8px;font-size:13px}
      th{background:#f8fafc;text-align:left}
      .totals{max-width:300px;margin-left:auto}
    </style></head><body>
    <h2>Comprobante de Orden #${escapeHtml(o.order_id)}</h2>
    <p><strong>Fecha:</strong> ${escapeHtml(o.order_date)} &nbsp;|&nbsp; <strong>Cliente:</strong> ${escapeHtml(o.client_name)} &nbsp;|&nbsp; <strong>Teléfono:</strong> ${escapeHtml(o.client_contact)}</p>
    <table><thead><tr><th>#</th><th>Producto</th><th>Precio</th><th>Cantidad</th><th>Total</th></tr></thead><tbody>${rows}</tbody></table>
    <table class="totals">
      <tr><th>Sub total</th><td style="text-align:right">$${Number(o.sub_total).toFixed(2)}</td></tr>
      <tr><th>IVA (13%)</th><td style="text-align:right">$${Number(o.vat).toFixed(2)}</td></tr>
      <tr><th>Descuento</th><td style="text-align:right">$${Number(o.discount).toFixed(2)}</td></tr>
      <tr><th>Total</th><td style="text-align:right"><strong>$${Number(o.grand_total).toFixed(2)}</strong></td></tr>
      <tr><th>Pagado</th><td style="text-align:right">$${Number(o.paid).toFixed(2)}</td></tr>
      <tr><th>Pendiente</th><td style="text-align:right">$${Number(o.due).toFixed(2)}</td></tr>
    </table></body></html>
  `)
  win.document.close()
  win.focus()
  win.print()
  win.close()
}

async function confirmDelete() {
  if (!deletingOrder.value) return
  deleting.value = true
  try {
    await store.deleteOrder(deletingOrder.value.order_id)
    ui.success('Orden eliminada exitosamente.')
    deletingOrder.value = null
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    deleting.value = false
  }
}

onMounted(() => store.fetchOrders())
</script>
