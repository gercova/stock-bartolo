<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);

$data = get_request_data();
$currentPassword = (string)($data['password'] ?? '');
$newPassword = (string)($data['npassword'] ?? '');
$confirmPassword = (string)($data['cpassword'] ?? '');
$requestedUserId = (int)($data['user_id'] ?? $_SESSION['userId']);
$sessionUserId = (int)$_SESSION['userId'];

if ($requestedUserId !== $sessionUserId) {
    send_http_error(403, 'No tienes permisos para cambiar la contraseña de otro usuario.');
}

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    send_http_error(400, 'Todos los campos de contraseña son obligatorios.');
}

if ($newPassword !== $confirmPassword) {
    send_http_error(400, 'La nueva contraseña no coincide con la confirmación.');
}

if (strlen($newPassword) < 4) {
    send_http_error(400, 'La nueva contraseña debe tener al menos 4 caracteres.');
}

$stmt = $connect->prepare('SELECT password FROM users WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $sessionUserId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $connect->close();
    send_http_error(404, 'Usuario no encontrado.');
}

$storedHash = (string)$row['password'];
$matchesCurrent = password_verify($currentPassword, $storedHash) || hash_equals($storedHash, md5($currentPassword));

if (!$matchesCurrent) {
    $connect->close();
    send_http_error(400, 'La contraseña actual es incorrecta.');
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$updStmt = $connect->prepare('UPDATE users SET password = ? WHERE user_id = ?');
$updStmt->bind_param('si', $newHash, $sessionUserId);

if (!$updStmt->execute()) {
    $updStmt->close();
    $connect->close();
    send_http_error(500, 'No se pudo actualizar la contraseña.');
}

$updStmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Contraseña actualizada exitosamente',
]);
