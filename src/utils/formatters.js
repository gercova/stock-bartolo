export function formatCurrency(amount) {
  const num = Number(amount) || 0
  return new Intl.NumberFormat('es-SV', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num)
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
