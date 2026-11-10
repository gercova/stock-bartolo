<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);

$data = get_request_data();
$username = trim((string)($data['username'] ?? ''));
$requestedUserId = (int)($data['user_id'] ?? $_SESSION['userId']);
$sessionUserId = (int)$_SESSION['userId'];

if ($requestedUserId !== $sessionUserId) {
    send_http_error(403, 'No tienes permisos para modificar el nombre de usuario de otra cuenta.');
}

if ($username === '') {
    send_http_error(400, 'El nombre de usuario es obligatorio.');
}

$checkStmt = $connect->prepare('SELECT user_id FROM users WHERE username = ? AND user_id != ? LIMIT 1');
$checkStmt->bind_param('si', $username, $sessionUserId);
$checkStmt->execute();
$exists = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($exists) {
    $connect->close();
    send_http_error(400, 'Ese nombre de usuario ya está en uso.');
}

$stmt = $connect->prepare('UPDATE users SET username = ? WHERE user_id = ?');
$stmt->bind_param('si', $username, $sessionUserId);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'Error al actualizar el nombre de usuario.');
}

$_SESSION['username'] = $username;
$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Nombre de usuario actualizado exitosamente',
    'username' => $username,
]);
