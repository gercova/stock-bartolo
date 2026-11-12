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
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
        return true;
    }
    if (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower((string)$_SERVER['HTTP_FRONT_END_HTTPS']) === 'on') {
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
    @ini_set('session.cookie_samesite', 'Lax');
    @ini_set('session.use_trans_sid', '0');
    @ini_set('session.sid_length', '48');
    @ini_set('session.sid_bits_per_character', '6');
    @ini_set('session.gc_maxlifetime', '7200');
}

function send_security_headers(bool $isHtml = false): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    header('X-XSS-Protection: 0');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');

    if (is_https_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }

    if ($isHtml) {
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    } else {
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

function create_session_fingerprint(int $userId): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown_agent';
    $ip = get_client_ip();
    $ipPrefix = substr($ip, 0, 7); // First octets / subnet prefix
    return hash('sha256', $userId . '|' . $ua . '|' . $ipPrefix);
}

function validate_session_fingerprint(int $userId): bool
{
    if (empty($_SESSION['auth_fingerprint'])) {
        return true; // Graceful migration
    }
    $expected = create_session_fingerprint($userId);
    return hash_equals($_SESSION['auth_fingerprint'], $expected);
}

function enforce_session_timeout(int $maxIdleSeconds = 7200): void
{
    $now = time();
    if (!empty($_SESSION['userId']) && isset($_SESSION['last_activity'])) {
        if (($now - (int)$_SESSION['last_activity']) > $maxIdleSeconds) {
            unset(
                $_SESSION['userId'],
                $_SESSION['username'],
                $_SESSION['user_role'],
                $_SESSION['assigned_branch_id'],
                $_SESSION['active_branch_id'],
                $_SESSION['csrf_token'],
                $_SESSION['last_activity']
            );
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
            'expires'   => 0,
            'path'      => '/',
            'secure'    => is_https_request(),
            'httponly'  => false,
            'samesite'  => 'Lax',
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

    $sessionToken   = $_SESSION['csrf_token'] ?? '';
    $incoming       = extract_incoming_csrf_token($bodyData);

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
        'script_tag'         => '/<\s*\/?\s*script\b/i',
        'php_tag'            => '/<\?(?:php|=|\s)/i',
        'dangerous_tag'      => '/<\s*(?:iframe|object|embed|applet|meta|base)\b/i',
        'svg_event'          => '/<\s*svg\b[^>]*\bon[a-z]+\s*=/i',
        'script_uri'         => '/(?:javascript|vbscript)\s*:/i',
        'data_html_uri'      => '/data\s*:\s*text\/html/i',
        'inline_handler'     => '/\bon(?:abort|blur|change|click|dblclick|error|focus|input|keydown|keypress|keyup|load|mousedown|mouseenter|mouseleave|mousemove|mouseout|mouseover|mouseup|reset|resize|select|submit|unload)\s*=/i',
        'sqli_union'         => '/\bunion\s+(?:all\s+)?select\b/i',
        'sqli_outfile'       => '/\binto\s+(?:outfile|dumpfile)\b/i',
        'sqli_timing'        => '/\b(?:sleep|benchmark)\s*\(/i',
        'path_traversal'     => '/(?:\.\.\/|\.\.\\)/',
        'command_injection'  => '/(?:;|&&|\|\|)\s*(?:cat|ls|rm|chmod|chown|wget|curl|bash|sh|nc|python|perl|eval|exec|system|passthru)\b/i',
        'system_probe'       => '/(?:\/etc\/(?:passwd|shadow|hosts)|\/proc\/self)/i',
        'dangerous_protocol' => '/\b(?:gopher|dict|phar|ldap|expect):\/\//i',
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

function sanitize_integer(mixed $val, string $fieldLabel, ?int $min = null, ?int $max = null, int $default = 0, bool $required = false): int
{
    if ($val === null || (is_string($val) && trim($val) === '')) {
        if ($required) {
            send_http_error(400, "El campo numérico {$fieldLabel} es obligatorio.");
        }
        return $default;
    }
    if (!is_numeric($val)) {
        send_http_error(400, "El valor ingresado en {$fieldLabel} debe ser un número entero.");
    }
    $intVal = (int)$val;
    if ($min !== null && $intVal < $min) {
        send_http_error(400, "El valor de {$fieldLabel} no puede ser menor a {$min}.");
    }
    if ($max !== null && $intVal > $max) {
        send_http_error(400, "El valor de {$fieldLabel} no puede ser mayor a {$max}.");
    }
    return $intVal;
}

function sanitize_float(mixed $val, string $fieldLabel, ?float $min = null, ?float $max = null, float $default = 0.0, bool $required = false): float
{
    if ($val === null || (is_string($val) && trim($val) === '')) {
        if ($required) {
            send_http_error(400, "El importe o valor {$fieldLabel} es obligatorio.");
        }
        return $default;
    }
    if (!is_numeric($val)) {
        send_http_error(400, "El valor ingresado en {$fieldLabel} debe ser un importe numérico válido.");
    }
    $floatVal = round((float)$val, 4);
    if ($min !== null && $floatVal < $min) {
        send_http_error(400, "El importe de {$fieldLabel} no puede ser inferior a {$min}.");
    }
    if ($max !== null && $floatVal > $max) {
        send_http_error(400, "El importe de {$fieldLabel} no puede exceder {$max}.");
    }
    return $floatVal;
}

function sanitize_email(mixed $val, string $fieldLabel = 'correo electrónico', bool $required = false): string
{
    $clean = strtolower(trim((string)($val ?? '')));
    if ($clean === '') {
        if ($required) {
            send_http_error(400, "El {$fieldLabel} es obligatorio.");
        }
        return '';
    }
    if (!filter_var($clean, FILTER_VALIDATE_EMAIL)) {
        send_http_error(400, "El formato del {$fieldLabel} no es válido.");
    }
    if (strlen($clean) > 191) {
        send_http_error(400, "El {$fieldLabel} no puede exceder los 191 caracteres.");
    }
    return $clean;
}

function sanitize_phone(mixed $val, string $fieldLabel = 'teléfono', bool $required = false): string
{
    $clean = trim((string)($val ?? ''));
    if ($clean === '') {
        if ($required) {
            send_http_error(400, "El {$fieldLabel} es obligatorio.");
        }
        return '';
    }
    if (!preg_match('/^[+()0-9\s-]{6,30}$/', $clean)) {
        send_http_error(400, "El formato del {$fieldLabel} no es válido.");
    }
    return $clean;
}

function sanitize_document(mixed $val, string $docType, string $fieldLabel = 'documento', bool $required = true): string
{
    $clean = strtoupper(trim((string)($val ?? '')));
    if ($clean === '') {
        if ($required) {
            send_http_error(400, "El número de {$fieldLabel} es obligatorio.");
        }
        return '';
    }
    switch (strtoupper($docType)) {
        case 'DNI':
            if (!preg_match('/^\d{8}$/', $clean)) {
                send_http_error(400, "El DNI debe contener exactamente 8 dígitos numéricos.");
            }
            break;
        case 'RUC':
            if (!preg_match('/^\d{11}$/', $clean)) {
                send_http_error(400, "El RUC debe contener exactamente 11 dígitos numéricos.");
            }
            break;
        case 'CE':
            if (!preg_match('/^[A-Z0-9]{8,15}$/', $clean)) {
                send_http_error(400, "El Carnet de Extranjería debe tener entre 8 y 15 caracteres alfanuméricos.");
            }
            break;
        default:
            if (strlen($clean) < 3 || strlen($clean) > 30) {
                send_http_error(400, "El {$fieldLabel} debe tener entre 3 y 30 caracteres.");
            }
            break;
    }
    return $clean;
}

function sanitize_boolean(mixed $val): bool
{
    if (is_bool($val)) return $val;
    if (is_numeric($val)) return (int)$val === 1;
    if (is_string($val)) {
        $clean = strtolower(trim($val));
        return in_array($clean, ['1', 'true', 'yes', 'on', 'si'], true);
    }
    return false;
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

    $maxIpAttempts  = 15;
    $windowSeconds  = 900;
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

    $maxAttempts    = 5;
    $windowSeconds  = 900;
    $lockSeconds    = 900;
    $normalizedUser = mb_strtolower($username, 'UTF-8');
    $now            = time();
    $nowStr         = date('Y-m-d H:i:s', $now);

    $connect->query("DELETE FROM login_attempts WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");

    $stmt   = $connect->prepare('SELECT attempt_id, attempts, UNIX_TIMESTAMP(first_attempt_at) AS first_ts FROM login_attempts WHERE ip_address = ? AND username = ? LIMIT 1');
    $row    = null;
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

    $withinWindow   = ($now - (int)$row['first_ts']) <= $windowSeconds;
    $attempts       = $withinWindow ? ((int)$row['attempts'] + 1) : 1;
    $firstAt        = $withinWindow ? date('Y-m-d H:i:s', (int)$row['first_ts']) : $nowStr;
    $locked         = $attempts >= $maxAttempts;
    $lockedUntil    = $locked ? date('Y-m-d H:i:s', $now + $lockSeconds) : null;

    $upd = $connect->prepare('UPDATE login_attempts SET attempts = ?, first_attempt_at = ?, locked_until = ? WHERE attempt_id = ?');
    if ($upd) {
        $attemptId = (int)$row['attempt_id'];
        $upd->bind_param('issi', $attempts, $firstAt, $lockedUntil, $attemptId);
        $upd->execute();
        $upd->close();
    }

    return [
        'attempts'  => $attempts,
        'remaining' => max(0, $maxAttempts - $attempts),
        'locked'    => $locked,
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
        'image/jpeg'    => 'jpg',
        'image/png'     => 'png',
        'image/gif'     => 'gif',
        'image/webp'    => 'webp',
    ];

    $finfo          = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime   = $finfo->file($tmpName);
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

