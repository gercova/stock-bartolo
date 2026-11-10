<?php

require_once __DIR__ . '/http_error.php';

$rawCode = $_GET['code'] ?? $_SERVER['REDIRECT_STATUS'] ?? 404;
$code = (int)$rawCode;
$catalog = get_http_error_catalog();

if (!isset($catalog[$code])) {
    $code = 404;
}

$customMessage = isset($_GET['message']) ? trim((string)$_GET['message']) : '';
send_http_error($code, $customMessage);
