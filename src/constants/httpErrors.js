export const HTTP_ERROR_CATALOG = {
  400: {
    code: 400,
    short: 'Bad Request',
    category: 'client',
    title: 'Solicitud incorrecta',
    description:
      'El servidor no pudo entender o procesar la solicitud debido a una sintaxis inválida o parámetros faltantes.',
    hint: 'Verifica los datos enviados en el formulario o los parámetros de la URL.',
    tone: 'amber',
  },
  401: {
    code: 401,
    short: 'Unauthorized',
    category: 'client',
    title: 'No autorizado',
    description:
      'Tu sesión ha expirado o necesitas autenticarte para acceder a esta sección del sistema.',
    hint: 'Inicia sesión nuevamente con tu usuario y contraseña.',
    tone: 'amber',
  },
  403: {
    code: 403,
    short: 'Forbidden',
    category: 'client',
    title: 'Acceso prohibido',
    description:
      'El servidor comprendió tu solicitud, pero no tienes los permisos necesarios para ejecutar esta acción o ver este recurso.',
    hint: 'Solicita autorización al administrador o regresa al panel principal.',
    tone: 'rose',
  },
  404: {
    code: 404,
    short: 'Not Found',
    category: 'client',
    title: 'Página o recurso no encontrado',
    description:
      'La ruta o el registro solicitado no existe, fue eliminado o cambió de ubicación.',
    hint: 'Comprueba la dirección URL o navega desde el menú lateral.',
    tone: 'indigo',
  },
  405: {
    code: 405,
    short: 'Method Not Allowed',
    category: 'client',
    title: 'Método no permitido',
    description:
      'El método HTTP utilizado para comunicarse con el servidor no está permitido para este recurso.',
    hint: 'Realiza la operación utilizando los controles de la aplicación.',
    tone: 'amber',
  },
  408: {
    code: 408,
    short: 'Request Timeout',
    category: 'client',
    title: 'Tiempo de espera agotado',
    description:
      'El servidor cerró la conexión porque la solicitud tardó demasiado tiempo en completarse.',
    hint: 'Verifica tu conexión a internet e intenta recargar la operación.',
    tone: 'amber',
  },
  422: {
    code: 422,
    short: 'Unprocessable Entity',
    category: 'client',
    title: 'Datos no procesables',
    description:
      'La solicitud tiene un formato válido, pero contiene errores de validación o reglas de negocio.',
    hint: 'Revisa los valores ingresados y corrige los campos requeridos.',
    tone: 'amber',
  },
  429: {
    code: 429,
    short: 'Too Many Requests',
    category: 'client',
    title: 'Demasiadas solicitudes',
    description:
      'Se han enviado demasiadas peticiones en un intervalo corto de tiempo.',
    hint: 'Espera unos momentos antes de volver a intentarlo.',
    tone: 'amber',
  },
  500: {
    code: 500,
    short: 'Internal Server Error',
    category: 'server',
    title: 'Error interno del servidor',
    description:
      'El servidor encontró una condición inesperada que le impidió completar la solicitud.',
    hint: 'Inténtalo de nuevo en unos momentos. Si el problema persiste, revisa la configuración del servidor PHP.',
    tone: 'red',
  },
  501: {
    code: 501,
    short: 'Not Implemented',
    category: 'server',
    title: 'Funcionalidad no implementada',
    description:
      'El servidor no admite o aún no implementa el método o capacidad requerida para esta solicitud.',
    hint: 'Esta operación no se encuentra habilitada en el servidor actual.',
    tone: 'purple',
  },
  502: {
    code: 502,
    short: 'Bad Gateway',
    category: 'server',
    title: 'Puerta de enlace incorrecta',
    description:
      'El servidor proxy o puerta de enlace recibió una respuesta inválida del servidor de aplicaciones.',
    hint: 'Asegúrate de que el servicio backend (PHP/Apache) esté en ejecución.',
    tone: 'red',
  },
  503: {
    code: 503,
    short: 'Service Unavailable',
    category: 'server',
    title: 'Servicio no disponible',
    description:
      'El servidor o la base de datos no están disponibles temporalmente por mantenimiento, sobrecarga o desconexión.',
    hint: 'Verifica que MySQL/MariaDB y el servidor backend estén activos e inténtalo nuevamente.',
    tone: 'red',
  },
  504: {
    code: 504,
    short: 'Gateway Timeout',
    category: 'server',
    title: 'Tiempo de espera de puerta de enlace agotado',
    description:
      'El servidor intermediario no recibió una respuesta a tiempo del servicio ascendente.',
    hint: 'Espera unos segundos y reintenta la operación.',
    tone: 'red',
  },
}

export const RELEVANT_ERROR_CODES = [400, 401, 403, 404, 405, 408, 422, 429, 500, 501, 502, 503, 504]

export function getHttpErrorInfo(code) {
  const numeric = Number(code)
  if (HTTP_ERROR_CATALOG[numeric]) {
    return HTTP_ERROR_CATALOG[numeric]
  }
  return {
    code: numeric || 500,
    short: 'HTTP Error',
    category: numeric >= 500 ? 'server' : 'client',
    title: `Error HTTP ${numeric || 500}`,
    description: 'Se produjo un error inesperado al comunicarse con el servidor.',
    hint: 'Vuelve al inicio o recarga la página.',
    tone: 'red',
  }
}
