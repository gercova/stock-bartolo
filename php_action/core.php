<?php

require_once __DIR__ . '/http_error.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

set_exception_handler(function (Throwable $e): void {
    send_http_error(500, 'Error interno del servidor: ' . $e->getMessage());
});

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/ensure_schema.php';

if (!defined('SKIP_AUTH_CHECK')) {
    if (empty($_SESSION['userId'])) {
        send_http_error(401, 'Tu sesión ha expirado o no has iniciado sesión.');
    }
}

function get_request_data(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            send_http_error(400, 'El cuerpo JSON de la solicitud no es válido.');
        }
    }
    return [];
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
    $diskPath = dirname(__DIR__) . '/' . ltrim($clean, '/');
    if (!is_file($diskPath)) {
        return 'assests/images/photo_default.png';
    }
    return ltrim($clean, '/');
}
