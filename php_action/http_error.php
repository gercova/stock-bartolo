<?php

function get_http_error_catalog(): array
{
    return [
        400 => [
            'title' => 'Solicitud incorrecta',
            'short' => 'Bad Request',
            'description' => 'El servidor no pudo procesar la solicitud debido a datos inválidos o parámetros faltantes.',
            'hint' => 'Verifica los datos ingresados e inténtalo nuevamente.',
        ],
        401 => [
            'title' => 'No autorizado',
            'short' => 'Unauthorized',
            'description' => 'Tu sesión ha expirado o necesitas iniciar sesión para acceder a este recurso.',
            'hint' => 'Inicia sesión con tus credenciales para continuar.',
        ],
        403 => [
            'title' => 'Acceso prohibido',
            'short' => 'Forbidden',
            'description' => 'No tienes los permisos necesarios para acceder o realizar esta acción.',
            'hint' => 'Si crees que es un error, contacta al administrador del sistema.',
        ],
        404 => [
            'title' => 'Página o recurso no encontrado',
            'short' => 'Not Found',
            'description' => 'El recurso, página o registro que estás buscando no existe o fue movido.',
            'hint' => 'Revisa la dirección URL o regresa al panel principal.',
        ],
        405 => [
            'title' => 'Método no permitido',
            'short' => 'Method Not Allowed',
            'description' => 'El método HTTP utilizado no está permitido para este endpoint o recurso.',
            'hint' => 'Usa la acción correspondiente desde la interfaz de la aplicación.',
        ],
        408 => [
            'title' => 'Tiempo de espera agotado',
            'short' => 'Request Timeout',
            'description' => 'La solicitud tardó demasiado tiempo en completarse y el servidor cerró la conexión.',
            'hint' => 'Comprueba tu conexión a internet e inténtalo de nuevo.',
        ],
        422 => [
            'title' => 'Datos no procesables',
            'short' => 'Unprocessable Entity',
            'description' => 'La solicitud tiene el formato correcto, pero contiene errores semánticos o de validación.',
            'hint' => 'Corrige los campos marcados en el formulario.',
        ],
        429 => [
            'title' => 'Demasiadas solicitudes',
            'short' => 'Too Many Requests',
            'description' => 'Has realizado demasiadas solicitudes en un corto período de tiempo.',
            'hint' => 'Espera unos segundos antes de volver a intentarlo.',
        ],
        500 => [
            'title' => 'Error interno del servidor',
            'short' => 'Internal Server Error',
            'description' => 'Ocurrió un error inesperado en el servidor al procesar tu solicitud.',
            'hint' => 'Inténtalo nuevamente en unos instantes o revisa los registros del servidor.',
        ],
        501 => [
            'title' => 'No implementado',
            'short' => 'Not Implemented',
            'description' => 'El servidor no admite la funcionalidad requerida para completar esta solicitud.',
            'hint' => 'Esta operación aún no está disponible en esta versión del sistema.',
        ],
        502 => [
            'title' => 'Puerta de enlace incorrecta',
            'short' => 'Bad Gateway',
            'description' => 'El servidor recibió una respuesta inválida de un servicio intermedio.',
            'hint' => 'Verifica el estado del servidor backend o inténtalo más tarde.',
        ],
        503 => [
            'title' => 'Servicio no disponible',
            'short' => 'Service Unavailable',
            'description' => 'El servidor o la base de datos no están disponibles temporalmente por mantenimiento o sobrecarga.',
            'hint' => 'Verifica que el servicio de base de datos esté activo e inténtalo nuevamente.',
        ],
        504 => [
            'title' => 'Tiempo de espera de puerta de enlace agotado',
            'short' => 'Gateway Timeout',
            'description' => 'El servidor no recibió una respuesta oportuna de un servicio ascendente.',
            'hint' => 'Espera un momento y vuelve a cargar la página.',
        ],
    ];
}

function prefers_html_response(): bool
{
    $format = $_GET['format'] ?? '';
    if ($format === 'json') {
        return false;
    }
    if ($format === 'html') {
        return true;
    }

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if (strtolower($xhr) === 'xmlhttprequest' || str_contains($accept, 'application/json')) {
        return false;
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (str_ends_with($script, '/error.php') && str_contains($accept, 'text/html')) {
        return true;
    }

    return false;
}

function send_http_error(int $code, string $message = '', array $details = [], ?bool $forceHtml = null): void
{
    $catalog = get_http_error_catalog();
    $entry = $catalog[$code] ?? [
        'title' => 'Error HTTP ' . $code,
        'short' => 'HTTP Error',
        'description' => 'Ha ocurrido un error al procesar la solicitud.',
        'hint' => 'Regresa al inicio e inténtalo nuevamente.',
    ];

    $finalMessage = $message !== '' ? $message : $entry['description'];
    http_response_code($code);

    $renderHtml = $forceHtml ?? prefers_html_response();

    if ($renderHtml) {
        header('Content-Type: text/html; charset=utf-8');
        $safeTitle = htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8');
        $safeShort = htmlspecialchars($entry['short'], ENT_QUOTES, 'UTF-8');
        $safeMsg = htmlspecialchars($finalMessage, ENT_QUOTES, 'UTF-8');
        $safeHint = htmlspecialchars($entry['hint'], ENT_QUOTES, 'UTF-8');

        echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{$code} — {$safeTitle} | Stock Bartolo</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
      color: #0f172a;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 1.25rem;
      max-width: 34rem;
      width: 100%;
      padding: 2.25rem;
      box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.08);
      text-align: center;
    }
    .badge {
      display: inline-block;
      padding: 0.35rem 0.9rem;
      border-radius: 9999px;
      background: #fee2e2;
      color: #b91c1c;
      font-weight: 700;
      font-size: 0.85rem;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      margin-bottom: 1rem;
    }
    .code {
      font-size: clamp(3.5rem, 10vw, 5rem);
      font-weight: 800;
      line-height: 1;
      color: #1e293b;
      margin-bottom: 0.5rem;
    }
    h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; color: #0f172a; }
    p { color: #475569; line-height: 1.6; margin-bottom: 1rem; }
    .hint {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 0.75rem;
      padding: 0.85rem 1rem;
      font-size: 0.9rem;
      color: #334155;
      margin-bottom: 1.5rem;
    }
    .actions { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.65rem 1.25rem;
      border-radius: 0.65rem;
      font-weight: 600;
      font-size: 0.925rem;
      text-decoration: none;
      cursor: pointer;
      border: 1px solid transparent;
    }
    .btn-primary { background: #2563eb; color: #ffffff; }
    .btn-primary:hover { background: #1d4ed8; }
    .btn-secondary { background: #ffffff; color: #334155; border-color: #cbd5e1; }
    .btn-secondary:hover { background: #f8fafc; }
  </style>
</head>
<body>
  <main class="card">
    <span class="badge">HTTP {$code} · {$safeShort}</span>
    <div class="code">{$code}</div>
    <h1>{$safeTitle}</h1>
    <p>{$safeMsg}</p>
    <div class="hint">{$safeHint}</div>
    <div class="actions">
      <a href="/" class="btn btn-primary">Ir al inicio</a>
      <button type="button" class="btn btn-secondary" onclick="history.back()">Volver atrás</button>
    </div>
  </main>
</body>
</html>
HTML;
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'status' => $code,
        'code' => $code,
        'error' => $entry['short'],
        'title' => $entry['title'],
        'messages' => $finalMessage,
        'message' => $finalMessage,
        'hint' => $entry['hint'],
        'details' => $details,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
