<template>
  <div class="space-y-6">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
      <div>
        <h2 class="text-lg sm:text-xl font-bold text-slate-900">
          {{ isEdit ? `Editar Orden #${orderId}` : 'Agregar Nueva Orden' }}
        </h2>
        <p class="text-sm text-slate-500">
          Selecciona los productos, cantidades y detalles de pago del cliente.
        </p>
      </div>
      <RouterLink
        to="/orders"
        class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
      >
        <ArrowLeft class="h-4 w-4" />
        Volver al listado
      </RouterLink>
    </div>

    <div v-if="loadingInitial" class="rounded-2xl border border-slate-200 bg-white p-12 text-center text-sm text-slate-500">
      <Loader2 class="h-6 w-6 animate-spin mx-auto mb-2 text-indigo-600" />
      Cargando datos de la orden...
    </div>

    <form v-else class="space-y-6" @submit.prevent="submitOrder">
      <!-- Client & Date Card -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs space-y-4">
        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Datos del cliente</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Fecha de orden *</label>
            <input
              v-model="form.orderDate"
              type="date"
              required
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Nombre del cliente *</label>
            <input
              v-model.trim="form.clientName"
              type="text"
              required
              placeholder="Nombre completo"
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Teléfono del cliente *</label>
            <input
              v-model.trim="form.clientContact"
              type="text"
              required
              placeholder="Ej. +503 7000-0000"
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>
        </div>
      </div>

      <!-- Product Lines Card -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Productos de la orden</h3>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition"
            @click="addRow"
          >
            <Plus class="h-4 w-4" />
            Añadir fila
          </button>
        </div>

        <div class="space-y-3">
          <div
            v-for="(row, idx) in form.items"
            :key="row.uid"
            class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end rounded-xl border border-slate-200 bg-slate-50/50 p-3.5"
          >
            <div class="sm:col-span-5">
              <label class="block text-xs font-medium text-slate-600 mb-1">Producto *</label>
              <select
                v-model.number="row.productId"
                required
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                @change="onProductChange(row)"
              >
                <option :value="0" disabled>-- Selecciona producto --</option>
                <option
                  v-for="p in productOptionsForRow(row)"
                  :key="p.product_id"
                  :value="p.product_id"
                >
                  {{ p.product_name }} (Stock: {{ p.quantity }})
                </option>
              </select>
            </div>

            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Precio</label>
              <input
                type="text"
                disabled
                :value="formatCurrency(row.rate)"
                class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-700"
              />
            </div>

            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Cantidad *</label>
              <input
                v-model.number="row.quantity"
                type="number"
                min="1"
                required
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
              />
            </div>

            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Total</label>
              <input
                type="text"
                disabled
                :value="formatCurrency(row.rate * row.quantity)"
                class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-900"
              />
            </div>

            <div class="sm:col-span-1 flex justify-end">
              <button
                type="button"
                class="rounded-xl border border-rose-200 bg-white p-2 text-rose-600 hover:bg-rose-50 disabled:opacity-40"
                :disabled="form.items.length <= 1"
                title="Quitar fila"
                @click="removeRow(idx)"
              >
                <Trash2 class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Totals & Payment Card -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs space-y-3.5">
          <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Resumen de importes</h3>

          <div class="flex items-center justify-between text-sm">
            <span class="text-slate-600">Sub total</span>
            <span class="font-semibold text-slate-900">{{ formatCurrency(subTotal) }}</span>
          </div>
          <div class="flex items-center justify-between text-sm">
            <span class="text-slate-600">IVA ({{ taxRate }}%)</span>
            <span class="font-semibold text-slate-900">{{ formatCurrency(vat) }}</span>
          </div>
          <div class="flex items-center justify-between text-sm">
            <span class="text-slate-600">Total neto</span>
            <span class="font-semibold text-slate-900">{{ formatCurrency(totalAmount) }}</span>
          </div>
          <div class="flex items-center justify-between gap-4 text-sm pt-1">
            <label for="discount" class="text-slate-600">Descuento ({{ currencySymbol }})</label>
            <input
              id="discount"
              v-model.number="form.discount"
              type="number"
              step="0.01"
              min="0"
              class="w-36 rounded-xl border border-slate-300 px-3 py-1.5 text-right text-sm focus:border-indigo-500 focus:outline-none"
            />
          </div>
          <div class="border-t border-slate-200 pt-3 flex items-center justify-between">
            <span class="text-base font-bold text-slate-900">Total a pagar</span>
            <span class="text-xl font-extrabold text-indigo-700">{{ formatCurrency(grandTotal) }}</span>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs space-y-4">
          <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Detalles de pago</h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1.5">Monto pagado ({{ currencySymbol }}) *</label>
              <input
                v-model.number="form.paid"
                type="number"
                step="0.01"
                min="0"
                required
                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1.5">Saldo pendiente</label>
              <input
                type="text"
                disabled
                :value="formatCurrency(dueAmount)"
                class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3.5 py-2 text-sm font-bold"
                :class="dueAmount > 0 ? 'text-amber-700' : 'text-emerald-700'"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1.5">Método de pago *</label>
              <select
                v-model.number="form.paymentType"
                required
                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
              >
                <option :value="1">Cheque</option>
                <option :value="2">Efectivo</option>
                <option :value="3">Tarjeta de crédito</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1.5">Estado del pago *</label>
              <select
                v-model.number="form.paymentStatus"
                required
                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
              >
                <option :value="1">Pago completo</option>
                <option :value="2">Pago por adelantado</option>
                <option :value="3">No pagado</option>
              </select>
            </div>
          </div>

          <div class="pt-3 flex flex-wrap items-center justify-end gap-3 border-t border-slate-100">
            <button
              v-if="!isEdit"
              type="button"
              class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
              @click="resetForm"
            >
              Reiniciar
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60 shadow-sm transition"
            >
              <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
              <CheckCircle2 v-else class="h-4 w-4" />
              {{ isEdit ? 'Actualizar orden' : 'Guardar orden' }}
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Plus, Trash2, Loader2, CheckCircle2 } from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import { useOrdersStore } from '@/stores/orders'
import { useUiStore } from '@/stores/ui'
import { extractErrorMessage } from '@/api/client'
import { formatCurrency } from '@/utils/formatters'
import { findUnsafeField } from '@/utils/security'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const store = useOrdersStore()
const ui = useUiStore()

const taxRate = computed(() => Number(auth.activeBranch?.tax_rate ?? 13))
const currencySymbol = computed(() => auth.activeBranch?.currency_symbol || '$')

const orderId = computed(() => (route.params.id ? Number(route.params.id) : null))
const isEdit = computed(() => Boolean(orderId.value))

const loadingInitial = ref(false)
const saving = ref(false)
let uidCounter = 1

const form = reactive({
  orderDate: new Date().toISOString().slice(0, 10),
  clientName: '',
  clientContact: '',
  discount: 0,
  paid: 0,
  paymentType: 2,
  paymentStatus: 1,
  items: [{ uid: uidCounter++, productId: 0, rate: 0, quantity: 1 }],
})

const subTotal = computed(() =>
  Math.round(
    form.items.reduce((sum, item) => sum + (Number(item.rate) || 0) * (Number(item.quantity) || 0), 0) *
      100
  ) / 100
)

const vat = computed(() => Math.round(subTotal.value * (taxRate.value / 100) * 100) / 100)
const totalAmount = computed(() => Math.round((subTotal.value + vat.value) * 100) / 100)
const grandTotal = computed(() =>
  Math.round(Math.max(0, totalAmount.value - (Number(form.discount) || 0)) * 100) / 100
)
const dueAmount = computed(() =>
  Math.round(Math.max(0, grandTotal.value - (Number(form.paid) || 0)) * 100) / 100
)

function productOptionsForRow(row) {
  const list = [...store.availableProducts]
  if (row.productId && !list.some((p) => p.product_id === row.productId)) {
    list.unshift({
      product_id: row.productId,
      product_name: row.productNameFallback || `Producto #${row.productId}`,
      quantity: row.quantity,
      rate: row.rate,
    })
  }
  return list
}

function addRow() {
  form.items.push({ uid: uidCounter++, productId: 0, rate: 0, quantity: 1 })
}

function removeRow(index) {
  if (form.items.length > 1) {
    form.items.splice(index, 1)
  }
}

function onProductChange(row) {
  const prod = store.availableProducts.find((p) => p.product_id === row.productId)
  if (prod) {
    row.rate = prod.rate
    if (!row.quantity || row.quantity < 1) {
      row.quantity = 1
    }
  } else {
    row.rate = 0
  }
}

function resetForm() {
  form.orderDate = new Date().toISOString().slice(0, 10)
  form.clientName = ''
  form.clientContact = ''
  form.discount = 0
  form.paid = 0
  form.paymentType = 2
  form.paymentStatus = 1
  form.items = [{ uid: uidCounter++, productId: 0, rate: 0, quantity: 1 }]
}

async function submitOrder() {
  const unsafe = findUnsafeField({
    'Nombre del cliente': form.clientName,
    'Teléfono del cliente': form.clientContact,
  })
  if (unsafe) {
    ui.error(`El campo "${unsafe}" contiene código o caracteres no permitidos.`)
    return
  }
  const validItems = form.items.filter((i) => i.productId > 0 && i.quantity > 0)
  if (validItems.length === 0) {
    ui.warning('Debes seleccionar al menos un producto con cantidad válida.')
    return
  }

  saving.value = true
  try {
    const payload = {
      orderDate: form.orderDate,
      clientName: form.clientName,
      clientContact: form.clientContact,
      discount: Number(form.discount) || 0,
      paid: Number(form.paid) || 0,
      paymentType: form.paymentType,
      paymentStatus: form.paymentStatus,
      items: validItems.map((i) => ({ productId: i.productId, quantity: i.quantity })),
    }

    if (isEdit.value) {
      await store.updateOrder({ orderId: orderId.value, ...payload })
      ui.success('Orden actualizada exitosamente.')
    } else {
      await store.createOrder(payload)
      ui.success('Orden creada exitosamente.')
    }
    router.push('/orders')
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  loadingInitial.value = true
  try {
    await store.fetchAvailableProducts(isEdit.value)
    if (isEdit.value) {
      const data = await store.fetchOrderById(orderId.value)
      if (data?.order) {
        form.orderDate = data.order.order_date
        form.clientName = data.order.client_name
        form.clientContact = data.order.client_contact
        form.discount = data.order.discount
        form.paid = data.order.paid
        form.paymentType = data.order.payment_type
        form.paymentStatus = data.order.payment_status
        form.items = (data.items || []).map((i) => ({
          uid: uidCounter++,
          productId: i.product_id,
          productNameFallback: i.product_name,
          rate: i.rate,
          quantity: i.quantity,
        }))
        if (form.items.length === 0) {
          addRow()
        }
      }
    }
  } catch (err) {
    if (err?.response?.status === 404) {
      router.replace({ name: 'error-page', params: { code: '404' } })
      return
    }
    ui.error(extractErrorMessage(err))
  } finally {
    loadingInitial.value = false
  }
})
</script>
