<?php

define('SKIP_AUTH_CHECK', true);
require_once __DIR__ . '/core.php';

require_method(['POST', 'GET']);

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
session_destroy();
$connect->close();

json_response([
    'success' => true,
    'authenticated' => false,
    'messages' => 'Sesión cerrada correctamente',
]);
