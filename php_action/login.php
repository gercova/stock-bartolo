<?php

define('SKIP_AUTH_CHECK', true);
require_once __DIR__ . '/core.php';

require_method(['POST']);
$data = get_request_data();

$username = trim((string)($data['username'] ?? ''));
$password = (string)($data['password'] ?? '');

if ($username === '' || $password === '') {
    send_http_error(400, 'Se requiere nombre de usuario y contraseña.', [
        'username' => $username === '' ? 'El usuario es obligatorio' : null,
        'password' => $password === '' ? 'La contraseña es obligatoria' : null,
    ]);
}

$stmt = $connect->prepare('SELECT user_id, username, password, email FROM users WHERE username = ? LIMIT 1');
if (!$stmt) {
    send_http_error(500, 'Error al preparar la consulta de autenticación.');
}

$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    send_http_error(401, 'El nombre de usuario no existe.');
}

$storedHash = (string)$user['password'];
$validPassword = password_verify($password, $storedHash) || hash_equals($storedHash, md5($password));

if (!$validPassword) {
    send_http_error(401, 'Combinación incorrecta de nombre de usuario y/o contraseña.');
}

session_regenerate_id(true);
$_SESSION['userId'] = (int)$user['user_id'];
$_SESSION['username'] = $user['username'];

ensure_loan_schema($connect);
$connect->close();

json_response([
    'success' => true,
    'authenticated' => true,
    'messages' => 'Inicio de sesión exitoso',
    'user' => [
        'user_id' => (int)$user['user_id'],
        'username' => $user['username'],
        'email' => $user['email'] ?? '',
    ],
]);
