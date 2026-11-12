<?php

define('SKIP_AUTH_CHECK', true);
require_once __DIR__ . '/core.php';

require_method(['POST']);
$data = get_request_data();

$username = sanitize_clean_text($data['username'] ?? '', 'usuario', 120, false);
$password = (string)($data['password'] ?? '');

if ($username === '' || $password === '') {
    send_http_error(400, 'Se requiere nombre de usuario y contraseña.', [
        'username' => $username === '' ? 'El usuario es obligatorio' : null,
        'password' => $password === '' ? 'La contraseña es obligatoria' : null,
    ]);
}

$clientIp = get_client_ip();
check_login_rate_limit($connect, $clientIp, $username);

$stmt = $connect->prepare('SELECT user_id, username, password, email, role, branch_id, active FROM users WHERE username = ? LIMIT 1');
if (!$stmt) {
    send_http_error(500, 'Error al preparar la consulta de autenticación.');
}

$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;
$stmt->close();

$validPassword = false;
$storedHash = $user ? (string)$user['password'] : '';
if ($user) {
    $validPassword = password_verify($password, $storedHash) || hash_equals($storedHash, md5($password));
}

if (!$user || !$validPassword) {
    $attemptInfo = record_failed_login($connect, $clientIp, $username);
    $connect->close();

    if (!empty($attemptInfo['locked'])) {
        if (!headers_sent()) {
            header('Retry-After: ' . (int)$attemptInfo['retryAfter']);
        }
        send_http_error(
            429,
            'Demasiados intentos fallidos de inicio de sesión. Por seguridad, espera 15 minuto(s) antes de volver a intentarlo.',
            ['retryAfter' => (int)$attemptInfo['retryAfter']]
        );
    }

    send_http_error(
        401,
        'Credenciales incorrectas. Verifica tu usuario y contraseña. (Intentos restantes: ' . (int)$attemptInfo['remaining'] . ')'
    );
}

if (isset($user['active']) && (int)$user['active'] !== 1) {
    $connect->close();
    send_http_error(403, 'Esta cuenta de usuario se encuentra inactiva.');
}

clear_login_attempts($connect, $clientIp, $username);

if (password_needs_rehash($storedHash, PASSWORD_DEFAULT) && !password_verify($password, $storedHash)) {
    $upgradedHash = password_hash($password, PASSWORD_DEFAULT);
    $rehashStmt = $connect->prepare('UPDATE users SET password = ? WHERE user_id = ?');
    if ($rehashStmt) {
        $uid = (int)$user['user_id'];
        $rehashStmt->bind_param('si', $upgradedHash, $uid);
        $rehashStmt->execute();
        $rehashStmt->close();
    }
}

session_regenerate_id(true);
$_SESSION['userId'] = (int)$user['user_id'];
$_SESSION['username'] = $user['username'];
$_SESSION['user_role'] = (string)($user['role'] ?? 'superadmin');
$_SESSION['assigned_branch_id'] = max(1, (int)($user['branch_id'] ?? 1));
$_SESSION['active_branch_id'] = $_SESSION['assigned_branch_id'];
$_SESSION['last_activity'] = time();

$newCsrf = rotate_csrf_token();
ensure_app_schema($connect);
$activeBranch = get_active_branch($connect);
$accessibleBranches = sb_get_accessible_branches($connect);
$systemConfig = sb_get_system_config($connect);
$connect->close();

json_response([
    'success' => true,
    'authenticated' => true,
    'messages' => 'Inicio de sesión exitoso',
    'csrfToken' => $newCsrf,
    'user' => [
        'user_id' => (int)$user['user_id'],
        'username' => $user['username'],
        'email' => $user['email'] ?? '',
        'role' => $_SESSION['user_role'],
        'branch_id' => $_SESSION['assigned_branch_id'],
        'can_switch_branches' => in_array($_SESSION['user_role'], ['superadmin', 'admin'], true),
    ],
    'activeBranch' => $activeBranch,
    'branches' => $accessibleBranches,
    'systemConfig' => $systemConfig,
]);
