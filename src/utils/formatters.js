let activeCurrencyCode = 'USD'
let activeCurrencySymbol = '$'

export function setActiveCurrency(code = 'USD', symbol = '$') {
  activeCurrencyCode = (code || 'USD').toUpperCase()
  activeCurrencySymbol = symbol || '$'
}

export function formatCurrency(amount, currencyOverride = null) {
  const num = Number(amount) || 0
  const code = (currencyOverride || activeCurrencyCode || 'USD').toUpperCase()
  try {
    return new Intl.NumberFormat('es-SV', {
      style: 'currency',
      currency: code,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(num)
  } catch {
    return `${activeCurrencySymbol}${num.toFixed(2)}`
  }
}

export function formatDate(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr + (dateStr.length === 10 ? 'T00:00:00' : ''))
  if (Number.isNaN(d.getTime())) return dateStr
  return d.toLocaleDateString('es-ES', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
  })
}

export function formatDateTime(dateTimeStr) {
  if (!dateTimeStr) return '—'
  const normalized = String(dateTimeStr).includes('T')
    ? dateTimeStr
    : String(dateTimeStr).replace(' ', 'T')
  const d = new Date(normalized)
  if (Number.isNaN(d.getTime())) return dateTimeStr
  return d.toLocaleString('es-ES', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function paymentMethodLabel(method) {
  switch (Number(method)) {
    case 1:
      return 'Cheque'
    case 2:
      return 'Efectivo'
    case 3:
      return 'Tarjeta / Transferencia'
    default:
      return 'Otro'
  }
}

export function orderPaymentStatusMeta(status) {
  switch (Number(status)) {
    case 1:
      return { label: 'Pago completo', variant: 'success' }
    case 2:
      return { label: 'Pago parcial', variant: 'info' }
    default:
      return { label: 'No pagado', variant: 'warning' }
  }
}

export function loanStatusMeta(status) {
  switch (Number(status)) {
    case 1:
      return { label: 'Activo', variant: 'info' }
    case 2:
      return { label: 'En abonos', variant: 'warning' }
    case 3:
      return { label: 'Pagado', variant: 'success' }
    case 4:
      return { label: 'Vencido', variant: 'danger' }
    default:
      return { label: 'Desconocido', variant: 'neutral' }
  }
}

export function generalCashMovementMeta(type) {
  switch (type) {
    case 'initial_capital':
      return { label: 'Capital inicial', variant: 'indigo' }
    case 'deposit':
      return { label: 'Ingreso / Aporte', variant: 'success' }
    case 'withdrawal':
      return { label: 'Retiro', variant: 'danger' }
    case 'till_open_allocation':
      return { label: 'Fondeo a Caja', variant: 'warning' }
    case 'till_close_return':
      return { label: 'Retorno de Caja', variant: 'success' }
    case 'adjustment_in':
      return { label: 'Ajuste (+)', variant: 'info' }
    case 'adjustment_out':
      return { label: 'Ajuste (-)', variant: 'warning' }
    default:
      return { label: type || 'Movimiento', variant: 'neutral' }
  }
}

export function cashMovementTypeMeta(type) {
  switch (type) {
    case 'sale_cash':
      return { label: 'Venta en efectivo', variant: 'success' }
    case 'order_payment_cash':
      return { label: 'Abono a orden', variant: 'success' }
    case 'loan_payment_cash':
      return { label: 'Cobro préstamo', variant: 'info' }
    case 'loan_disbursement':
      return { label: 'Desembolso préstamo', variant: 'warning' }
    case 'manual_in':
      return { label: 'Ingreso manual', variant: 'indigo' }
    case 'manual_out':
      return { label: 'Retiro manual', variant: 'danger' }
    case 'expense':
      return { label: 'Gasto operativo', variant: 'danger' }
    case 'sweep_to_general':
      return { label: 'Envío a Caja General', variant: 'warning' }
    default:
      return { label: type || 'Movimiento', variant: 'neutral' }
  }
}

export function reconciliationStatusMeta(statusLabel) {
  switch (statusLabel) {
    case 'exact':
      return { label: 'Cuadrado', variant: 'success' }
    case 'surplus':
      return { label: 'Sobrante', variant: 'info' }
    case 'shortage':
      return { label: 'Faltante', variant: 'danger' }
    default:
      return { label: 'Pendiente', variant: 'neutral' }
  }
}
