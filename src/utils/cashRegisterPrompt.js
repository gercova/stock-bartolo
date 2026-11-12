import Swal from 'sweetalert2'
import apiClient from '@/api/client'

/**
 * Checks whether the active branch has an open cash register session.
 * @returns {Promise<{isOpen: boolean, branchName: string, session: object|null, openSessions: Array}>}
 */
export async function checkOpenCashSession() {
  try {
    const { data } = await apiClient.get('/fetchCashSessions.php')
    const openList = Array.isArray(data?.openSessions) ? data.openSessions : []
    const hasOpen = openList.length > 0 || !!data?.active_session
    const branchName = data?.activeBranch?.branch_name || 'esta sucursal'
    const session = openList.length > 0 ? openList[0] : (data?.active_session || null)

    return {
      isOpen: hasOpen,
      branchName,
      session,
      openSessions: openList,
    }
  } catch {
    return {
      isOpen: false,
      branchName: 'esta sucursal',
      session: null,
      openSessions: [],
    }
  }
}

/**
 * Displays a SweetAlert prompt urging the user to open the cash register for the given location.
 * @param {object} router - Vue Router instance
 * @param {string} branchName - Name of the location/branch
 * @param {string} operationDescription - Description of the attempted operation (e.g. 'ventas o emisión de comprobantes')
 * @returns {Promise<boolean>} True if user clicked confirm (and navigated), false if cancelled.
 */
export async function promptOpenCashRegister(router, branchName = 'esta sucursal', operationDescription = 'esta operación comercial') {
  const result = await Swal.fire({
    icon: 'warning',
    title: '¡Caja Registradora Cerrada!',
    html: `
      <div style="text-align: left; font-size: 13px; line-height: 1.6; color: #334155;">
        <p>
          Para realizar <strong>${operationDescription}</strong> en <strong>${branchName}</strong>, la caja registradora debe estar abierta.
        </p>
        <p style="margin-top: 8px; color: #64748b;">
          Por favor, abre un turno de caja para esta ubicación antes de continuar con la transacción.
        </p>
      </div>
    `,
    showCancelButton: true,
    confirmButtonColor: '#e11d48',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Abrir Turno de Caja',
    cancelButtonText: 'Cancelar',
    allowOutsideClick: false,
  })

  if (result.isConfirmed) {
    if (router) {
      router.push('/cash-register')
    }
    return true
  }
  return false
}

/**
 * Convenience helper: Verifies cash register status and displays prompt if closed.
 * @returns {Promise<boolean>} True if open, false if closed.
 */
export async function ensureCashRegisterOpen(router, branchName = '', operationDescription = 'esta operación') {
  const status = await checkOpenCashSession()
  const bName = branchName || status.branchName

  if (!status.isOpen) {
    await promptOpenCashRegister(router, bName, operationDescription)
    return false
  }
  return true
}

