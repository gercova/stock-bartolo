<?php

define('SKIP_AUTH_CHECK', true);
require_once __DIR__ . '/core.php';

require_method(['POST']);

$userId = !empty($_SESSION['userId']) ? (int)$_SESSION['userId'] : null;
$username = !empty($_SESSION['username']) ? (string)$_SESSION['username'] : null;
$branchId = !empty($_SESSION['active_branch_id']) ? (int)$_SESSION['active_branch_id'] : null;

if ($userId) {
    sb_log_audit(
        $connect,
        'auth',
        'LOGOUT',
        "Cierre de sesión del usuario '{$username}'",
        ['user_id' => $userId, 'username' => $username],
        $branchId,
        $userId,
        $username
    );
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'] ?? '/',
        'domain'   => $params['domain'] ?? '',
        'secure'   => $params['secure'] ?? is_https_request(),
        'httponly' => $params['httponly'] ?? true,
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}
setcookie('XSRF-TOKEN', '', [
    'expires'  => time() - 42000,
    'path'     => '/',
    'secure'   => is_https_request(),
    'httponly' => false,
    'samesite' => 'Lax',
]);
session_destroy();
$connect->close();

json_response([
    'success'       => true,
    'authenticated' => false,
    'messages'      => 'Sesión cerrada correctamente',
]);
