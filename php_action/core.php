<?php

require_once __DIR__ . '/http_error.php';
require_once __DIR__ . '/security.php';

configure_secure_php_environment();
send_security_headers(false);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

set_exception_handler(function (Throwable $e): void {
    error_log('[stock-bartolo] Uncaught exception: ' . $e->getMessage());
    send_http_error(500, 'Error interno del servidor al procesar la solicitud.');
});

enforce_session_timeout(7200);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/ensure_schema.php';
require_once __DIR__ . '/tenant.php';

ensure_app_schema($connect);

if (!defined('SKIP_AUTH_CHECK')) {
    if (empty($_SESSION['userId'])) {
        send_http_error(401, 'Tu sesión ha expirado o no has iniciado sesión.');
    }
}

$csrfToken = ensure_csrf_token();
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (in_array($requestMethod, ['GET', 'HEAD'], true)) {
    issue_csrf_cookie($csrfToken);
} elseif (!defined('SKIP_CSRF_CHECK')) {
    verify_csrf_token();
}

if (!empty($_GET)) {
    inspect_request_payload($_GET);
}

function get_request_data(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (!empty($_POST)) {
        inspect_request_payload($_POST);
        $cached = $_POST;
        return $cached;
    }

    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        if (strlen($raw) > 512 * 1024) {
            send_http_error(400, 'El tamaño de la solicitud supera el límite permitido.');
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            inspect_request_payload($decoded);
            $cached = $decoded;
            return $cached;
        }
        send_http_error(400, 'El cuerpo JSON de la solicitud no es válido.');
    }

    $cached = [];
    return $cached;
}

function require_method(array $allowed): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, $allowed, true)) {
        send_http_error(405, 'Método HTTP ' . $method . ' no permitido para este endpoint.');
    }
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    send_security_headers(false);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalize_image_url(?string $path): string
{
    $clean = trim((string)$path);
    if ($clean === '') {
        return 'assests/images/photo_default.png';
    }
    if (str_starts_with($clean, '../')) {
        $clean = substr($clean, 3);
    }
    if (str_starts_with($clean, './')) {
        $clean = substr($clean, 2);
    }
    $clean = ltrim($clean, '/');
    if (str_contains($clean, '..')) {
        return 'assests/images/photo_default.png';
    }

    $imagesRoot = realpath(dirname(__DIR__) . '/assests/images');
    $diskPath = realpath(dirname(__DIR__) . '/' . $clean);
    if ($imagesRoot === false || $diskPath === false || !is_file($diskPath) || !str_starts_with($diskPath, $imagesRoot)) {
        return 'assests/images/photo_default.png';
    }

    return $clean;
}
