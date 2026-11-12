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
require_once __DIR__ . '/security_roles.php';

ensure_app_schema($connect);

if (!defined('SKIP_AUTH_CHECK')) {
    if (empty($_SESSION['userId'])) {
        send_http_error(401, 'Tu sesión ha expirado o no has iniciado sesión.');
    }
    $authUid = (int)$_SESSION['userId'];

    // Validate session fingerprint (anti-hijacking)
    if (!validate_session_fingerprint($authUid)) {
        $_SESSION = [];
        session_regenerate_id(true);
        send_http_error(401, 'Tu sesión ha sido invalidada por seguridad debido a un cambio en el entorno de red o navegador.');
    }

    // Absolute session timeout check (12 hours maximum)
    if (!empty($_SESSION['session_start_time']) && (time() - (int)$_SESSION['session_start_time'] > 43200)) {
        $_SESSION = [];
        session_regenerate_id(true);
        send_http_error(401, 'Tu sesión ha alcanzado el límite máximo de duración (12 horas). Por favor, inicia sesión nuevamente.');
    }

    // Periodic session ID regeneration (every 30 minutes of active use)
    if (empty($_SESSION['session_created_at'])) {
        $_SESSION['session_created_at'] = time();
    } elseif (time() - (int)$_SESSION['session_created_at'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['session_created_at'] = time();
        issue_csrf_cookie(ensure_csrf_token());
    }

    $uCheck = $connect->prepare('SELECT active, role_id, role, branch_id FROM users WHERE user_id = ? LIMIT 1');
    if ($uCheck) {
        $uCheck->bind_param('i', $authUid);
        $uCheck->execute();
        $uRow = $uCheck->get_result()->fetch_assoc();
        $uCheck->close();
        if (!$uRow || (int)($uRow['active'] ?? 1) !== 1) {
            $_SESSION = [];
            send_http_error(401, 'Tu cuenta de usuario ha sido desactivada o no existe.');
        }
        if (!empty($uRow['role'])) {
            $_SESSION['user_role'] = (string)$uRow['role'];
        }
    }
}

$csrfToken      = ensure_csrf_token();
$requestMethod  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (in_array($requestMethod, ['GET', 'HEAD'], true)) {
    issue_csrf_cookie($csrfToken);
} elseif (!defined('SKIP_CSRF_CHECK')) {
    verify_csrf_token();
}

if (!empty($_GET)) {
    inspect_request_payload($_GET);
}

// Automatically inspect incoming request payload on mutating requests
if (in_array($requestMethod, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
    get_request_data();
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
        $clean  = substr($clean, 3);
    }
    if (str_starts_with($clean, './')) {
        $clean  = substr($clean, 2);
    }
    $clean      = ltrim($clean, '/');
    if (str_contains($clean, '..')) {
        return 'assests/images/photo_default.png';
    }

    $imagesRoot = realpath(dirname(__DIR__) . '/assests/images');
    $diskPath   = realpath(dirname(__DIR__) . '/' . $clean);
    if ($imagesRoot === false || $diskPath === false || !is_file($diskPath) || !str_starts_with($diskPath, $imagesRoot)) {
        return 'assests/images/photo_default.png';
    }

    return $clean;
}
