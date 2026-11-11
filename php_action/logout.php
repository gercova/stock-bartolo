<?php

define('SKIP_AUTH_CHECK', true);
require_once __DIR__ . '/core.php';

require_method(['POST']);

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
setcookie('XSRF-TOKEN', '', time() - 42000, '/', '', is_https_request(), false);
session_destroy();
$connect->close();

json_response([
    'success' => true,
    'authenticated' => false,
    'messages' => 'Sesión cerrada correctamente',
]);
