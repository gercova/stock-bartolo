<?php

function is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

function configure_secure_php_environment(): void
{
    @ini_set('display_errors', '0');
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
}

function send_security_headers(bool $isHtml = false): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('X-XSS-Protection: 0');

    if ($isHtml) {
        header("Content-Security-Policy: default-src 'self'; style-src 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; base-uri 'self'; frame-ancestors 'none'");
    } else {
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}

function enforce_session_timeout(int $maxIdleSeconds = 7200): void
{
    $now = time();
    if (!empty($_SESSION['userId']) && isset($_SESSION['last_activity'])) {
        if (($now - (int)$_SESSION['last_activity']) > $maxIdleSeconds) {
            unset($_SESSION['userId'], $_SESSION['username'], $_SESSION['csrf_token'], $_SESSION['last_activity']);
            session_regenerate_id(true);
            return;
        }
    }
    $_SESSION['last_activity'] = $now;
}

function ensure_csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) < 32) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function rotate_csrf_token(): string
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    issue_csrf_cookie($_SESSION['csrf_token']);
    return $_SESSION['csrf_token'];
}

function issue_csrf_cookie(?string $token = null): string
{
    $value = $token ?? ensure_csrf_token();
    if (!headers_sent()) {
        setcookie('XSRF-TOKEN', $value, [
            'expires' => 0,
            'path' => '/',
            'secure' => is_https_request(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
    return $value;
}

function extract_incoming_csrf_token(?array $bodyData = null): string
{
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_XSRF_TOKEN'] ?? '';
    if (is_string($headerToken) && $headerToken !== '') {
        return trim($headerToken);
    }
    if (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
        return trim($_POST['csrf_token']);
    }
    if (is_array($bodyData) && isset($bodyData['csrf_token']) && is_string($bodyData['csrf_token'])) {
        return trim($bodyData['csrf_token']);
    }
    return '';
}

function verify_csrf_token(?array $bodyData = null): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $incoming = extract_incoming_csrf_token($bodyData);

    if ($incoming === '' && empty($_POST) && $bodyData === null) {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $incoming = extract_incoming_csrf_token($decoded);
            }
        }
    }

    if (!is_string($sessionToken) || $sessionToken === '' || $incoming === '' || !hash_equals($sessionToken, $incoming)) {
        send_http_error(
            403,
            'Token de seguridad CSRF inválido o ausente. Recarga la aplicación e inténtalo nuevamente.',
            ['reason' => 'csrf_invalid']
        );
    }
}

function detect_malicious_payload(string $value): ?string
{
    if (str_contains($value, "\0")) {
        return 'null_byte';
    }

    $patterns = [
        'script_tag' => '/<\s*\/?\s*script\b/i',
        'php_tag' => '/<\?(?:php|=|\s)/i',
        'dangerous_tag' => '/<\s*(?:iframe|object|embed|applet|meta|base)\b/i',
        'svg_event' => '/<\s*svg\b[^>]*\bon[a-z]+\s*=/i',
        'script_uri' => '/(?:javascript|vbscript)\s*:/i',
        'data_html_uri' => '/data\s*:\s*text\/html/i',
        'inline_handler' => '/\bon(?:abort|blur|change|click|dblclick|error|focus|input|keydown|keypress|keyup|load|mousedown|mouseenter|mouseleave|mousemove|mouseout|mouseover|mouseup|reset|resize|select|submit|unload)\s*=/i',
        'sqli_union' => '/\bunion\s+(?:all\s+)?select\b/i',
        'sqli_outfile' => '/\binto\s+(?:outfile|dumpfile)\b/i',
        'sqli_timing' => '/\b(?:sleep|benchmark)\s*\(/i',
    ];

    foreach ($patterns as $name => $regex) {
        if (preg_match($regex, $value) === 1) {
            return $name;
        }
    }

    return null;
}

function inspect_request_payload(
    array $data,
    array $skipContentCheckKeys = ['password', 'npassword', 'cpassword', 'csrf_token'],
    int $depth = 0
): void {
    if ($depth > 6) {
        send_http_error(400, 'Estructura de solicitud demasiado profunda.');
    }

    foreach ($data as $key => $val) {
        $keyStr = is_string($key) ? $key : (string)$key;
        if (str_contains($keyStr, "\0")) {
            send_http_error(400, 'Parámetro de solicitud inválido.');
        }

        if (is_array($val)) {
            inspect_request_payload($val, $skipContentCheckKeys, $depth + 1);
            continue;
        }

        if (!is_scalar($val) && $val !== null) {
            send_http_error(400, "Tipo de dato no válido en el campo {$keyStr}.");
        }

        if ($val === null || is_bool($val) || is_int($val) || is_float($val)) {
            continue;
        }

        $str = (string)$val;
        if (str_contains($str, "\0")) {
            send_http_error(400, "Caracteres nulos no permitidos en {$keyStr}.");
        }

        if (in_array($keyStr, $skipContentCheckKeys, true)) {
            if (strlen($str) > 256) {
                send_http_error(400, "El campo {$keyStr} supera el tamaño máximo permitido.");
            }
            continue;
        }

        if (mb_strlen($str, 'UTF-8') > 4000) {
            send_http_error(400, "El campo {$keyStr} supera la longitud máxima permitida.");
        }

        $threat = detect_malicious_payload($str);
        if ($threat !== null) {
            send_http_error(
                400,
                "Se bloqueó código o contenido potencialmente malicioso en el campo \"{$keyStr}\".",
                ['field' => $keyStr, 'rule' => $threat]
            );
        }
    }
}

function sanitize_clean_text(mixed $value, string $fieldLabel, int $maxLength = 255, bool $required = false): string
{
    if ($value === null) {
        if ($required) {
            send_http_error(400, "El campo {$fieldLabel} es obligatorio.");
        }
        return '';
    }

    if (!is_string($value) && !is_numeric($value)) {
        send_http_error(400, "El formato del campo {$fieldLabel} no es válido.");
    }

    $raw = trim((string)$value);
    $threat = detect_malicious_payload($raw);
    if ($threat !== null) {
        send_http_error(
            400,
            "Se detectó contenido potencialmente malicioso en {$fieldLabel}.",
            ['field' => $fieldLabel, 'rule' => $threat]
        );
    }

    $clean = trim(strip_tags($raw));
    $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean) ?? $clean;

    if (mb_strlen($clean, 'UTF-8') > $maxLength) {
        send_http_error(400, "El campo {$fieldLabel} excede el máximo de {$maxLength} caracteres.");
    }

    if ($required && $clean === '') {
        send_http_error(400, "El campo {$fieldLabel} es obligatorio.");
    }

    return $clean;
}

function validate_iso_date(?string $rawDate, string $fieldLabel, bool $required = true): ?string
{
    $clean = trim((string)$rawDate);
    if ($clean === '') {
        if ($required) {
            send_http_error(400, "La {$fieldLabel} es obligatoria.");
        }
        return null;
    }

    foreach (['Y-m-d', 'm/d/Y', 'd/m/Y'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $clean);
        if ($dt instanceof DateTime && $dt->format($fmt) === $clean) {
            return $dt->format('Y-m-d');
        }
    }

    send_http_error(400, "El formato de {$fieldLabel} no es válido (usa YYYY-MM-DD).");
}

function get_client_ip(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (is_string($remote) && filter_var($remote, FILTER_VALIDATE_IP) !== false) {
        return $remote;
    }
    return 'unknown';
}

function check_login_rate_limit(mysqli $connect, string $ip, string $username): void
{
    ensure_app_schema($connect);

    $maxIpAttempts = 15;
    $windowSeconds = 900;
    $normalizedUser = mb_strtolower($username, 'UTF-8');

    $stmt = $connect->prepare('SELECT attempts, UNIX_TIMESTAMP(first_attempt_at) AS first_ts, UNIX_TIMESTAMP(locked_until) AS locked_ts FROM login_attempts WHERE ip_address = ? AND username = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('ss', $ip, $normalizedUser);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && !empty($row['locked_ts']) && (int)$row['locked_ts'] > time()) {
            $retryAfter = max(1, (int)$row['locked_ts'] - time());
            if (!headers_sent()) {
                header('Retry-After: ' . $retryAfter);
            }
            $minutes = (int)ceil($retryAfter / 60);
            send_http_error(
                429,
                "Demasiados intentos fallidos de inicio de sesión. Espera {$minutes} minuto(s) antes de volver a intentarlo.",
                ['retryAfter' => $retryAfter, 'scope' => 'account']
            );
        }
    }

    $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);
    $ipStmt = $connect->prepare('SELECT COALESCE(SUM(attempts), 0) AS total_attempts FROM login_attempts WHERE ip_address = ? AND updated_at >= ?');
    if ($ipStmt) {
        $ipStmt->bind_param('ss', $ip, $cutoff);
        $ipStmt->execute();
        $ipRow = $ipStmt->get_result()->fetch_assoc();
        $ipStmt->close();

        if ($ipRow && (int)$ipRow['total_attempts'] >= $maxIpAttempts) {
            if (!headers_sent()) {
                header('Retry-After: ' . $windowSeconds);
            }
            send_http_error(
                429,
                'Se superó el límite de intentos de acceso desde esta dirección IP. Espera unos minutos antes de reintentar.',
                ['retryAfter' => $windowSeconds, 'scope' => 'ip']
            );
        }
    }
}

function record_failed_login(mysqli $connect, string $ip, string $username): array
{
    ensure_app_schema($connect);

    $maxAttempts = 5;
    $windowSeconds = 900;
    $lockSeconds = 900;
    $normalizedUser = mb_strtolower($username, 'UTF-8');
    $now = time();
    $nowStr = date('Y-m-d H:i:s', $now);

    $connect->query("DELETE FROM login_attempts WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");

    $stmt = $connect->prepare('SELECT attempt_id, attempts, UNIX_TIMESTAMP(first_attempt_at) AS first_ts FROM login_attempts WHERE ip_address = ? AND username = ? LIMIT 1');
    $row = null;
    if ($stmt) {
        $stmt->bind_param('ss', $ip, $normalizedUser);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$row) {
        $attempts = 1;
        $lockedUntil = null;
        $ins = $connect->prepare('INSERT INTO login_attempts (ip_address, username, attempts, first_attempt_at, locked_until) VALUES (?, ?, ?, ?, ?)');
        if ($ins) {
            $ins->bind_param('ssiss', $ip, $normalizedUser, $attempts, $nowStr, $lockedUntil);
            $ins->execute();
            $ins->close();
        }
        return ['attempts' => 1, 'remaining' => $maxAttempts - 1, 'locked' => false, 'retryAfter' => 0];
    }

    $withinWindow = ($now - (int)$row['first_ts']) <= $windowSeconds;
    $attempts = $withinWindow ? ((int)$row['attempts'] + 1) : 1;
    $firstAt = $withinWindow ? date('Y-m-d H:i:s', (int)$row['first_ts']) : $nowStr;
    $locked = $attempts >= $maxAttempts;
    $lockedUntil = $locked ? date('Y-m-d H:i:s', $now + $lockSeconds) : null;

    $upd = $connect->prepare('UPDATE login_attempts SET attempts = ?, first_attempt_at = ?, locked_until = ? WHERE attempt_id = ?');
    if ($upd) {
        $attemptId = (int)$row['attempt_id'];
        $upd->bind_param('issi', $attempts, $firstAt, $lockedUntil, $attemptId);
        $upd->execute();
        $upd->close();
    }

    return [
        'attempts' => $attempts,
        'remaining' => max(0, $maxAttempts - $attempts),
        'locked' => $locked,
        'retryAfter' => $locked ? $lockSeconds : 0,
    ];
}

function clear_login_attempts(mysqli $connect, string $ip, string $username): void
{
    $normalizedUser = mb_strtolower($username, 'UTF-8');
    $stmt = $connect->prepare('DELETE FROM login_attempts WHERE ip_address = ? AND username = ?');
    if ($stmt) {
        $stmt->bind_param('ss', $ip, $normalizedUser);
        $stmt->execute();
        $stmt->close();
    }
}

function process_uploaded_product_image(array $file): string
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        send_http_error(400, 'Error al subir la imagen del producto.');
    }

    $tmpName = $file['tmp_name'] ?? '';
    if (!is_string($tmpName) || $tmpName === '' || !is_uploaded_file($tmpName)) {
        send_http_error(400, 'Archivo de imagen inválido.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        send_http_error(400, 'La imagen debe pesar entre 1 byte y 5 MB.');
    }

    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowedExts = ['gif', 'jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExts, true)) {
        send_http_error(400, 'Formato de imagen no permitido. Usa JPG, PNG, GIF o WEBP.');
    }

    $mimeMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($tmpName);
    if (!is_string($detectedMime) || !isset($mimeMap[$detectedMime])) {
        send_http_error(400, 'El archivo subido no corresponde a una imagen válida.');
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
        send_http_error(400, 'La imagen está corrupta o no contiene dimensiones válidas.');
    }

    $canonicalExt = $mimeMap[$detectedMime];
    $uploadDir = dirname(__DIR__) . '/assests/images/stock';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $canonicalExt;
    $destDisk = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpName, $destDisk)) {
        send_http_error(500, 'No se pudo almacenar la imagen subida en el servidor.');
    }

    @chmod($destDisk, 0644);
    return '../assests/images/stock/' . $filename;
}

