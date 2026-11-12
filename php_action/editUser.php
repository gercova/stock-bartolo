<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);

sb_assert_permission($connect, 'users', 'update');

$data           = get_request_data();
$targetUserId   = (int)($data['user_id'] ?? $data['userId'] ?? 0);
$currentUserId  = (int)($_SESSION['userId'] ?? 0);

if ($targetUserId <= 0) {
    send_http_error(400, 'Identificador de usuario no válido.');
}

// Fetch existing user data
$stmt       = $connect->prepare('SELECT u.user_id, u.username, u.email, u.phone, u.full_name, u.role, u.role_id, u.branch_id, u.active, r.role_slug, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ? LIMIT 1');
$stmt->bind_param('i', $targetUserId);
$stmt->execute();
$existing   = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existing) {
    send_http_error(404, 'El usuario solicitado no existe.');
}

$username   = strtolower(trim((string)($data['username'] ?? $existing['username'])));
$fullName   = trim((string)($data['full_name'] ?? $existing['full_name']));
$email      = strtolower(trim((string)($data['email'] ?? $existing['email'])));
$phone      = trim((string)($data['phone'] ?? $existing['phone']));
$roleId     = isset($data['role_id']) && $data['role_id'] !== '' ? (int)$data['role_id'] : (int)$existing['role_id'];
$branchId   = isset($data['branch_id']) && $data['branch_id'] !== '' && (int)$data['branch_id'] > 0 ? (int)$data['branch_id'] : null;
$active     = isset($data['active']) ? ((int)$data['active'] === 1 ? 1 : 0) : (int)$existing['active'];
$newPassword = (string)($data['password'] ?? '');

if ($username === '' || strlen($username) < 3 || strlen($username) > 50) {
    send_http_error(400, 'El nombre de usuario debe tener entre 3 y 50 caracteres.');
}

if (!preg_match('/^[a-z0-9._-]+$/i', $username)) {
    send_http_error(400, 'El nombre de usuario contiene caracteres no permitidos.');
}

if ($fullName === '' || strlen($fullName) > 100) {
    send_http_error(400, 'El nombre completo debe tener entre 1 y 100 caracteres.');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_http_error(400, 'El correo electrónico no tiene un formato válido.');
}

if ($newPassword !== '' && (strlen($newPassword) < 6 || strlen($newPassword) > 128)) {
    send_http_error(400, 'La nueva contraseña debe tener entre 6 y 128 caracteres.');
}

// Protection 1: Do not allow self-deactivation
if ($targetUserId === $currentUserId && $active === 0) {
    send_http_error(400, 'No puedes desactivar tu propia cuenta mientras te encuentras conectado.');
}

// Protection 2: Do not allow self role-change (prevents accidentally stripping own superadmin/admin)
if ($targetUserId === $currentUserId && $roleId !== (int)$existing['role_id']) {
    send_http_error(400, 'No puedes modificar tu propio rol de usuario para evitar bloqueos accidentales.');
}

// Fetch target role details
$roleStmt = $connect->prepare('SELECT role_id, role_name, role_slug FROM roles WHERE role_id = ? LIMIT 1');
$roleStmt->bind_param('i', $roleId);
$roleStmt->execute();
$targetRole = $roleStmt->get_result()->fetch_assoc();
$roleStmt->close();

if (!$targetRole) {
    send_http_error(404, 'El rol seleccionado no existe.');
}

$currentPerms = sb_get_user_permissions($connect, $currentUserId);

// Protection 3: Only superadmins can assign or remove superadmin
$isTargetBecomingSuperadmin = ($targetRole['role_slug'] === 'superadmin');
$wasSuperadmin = ($existing['role_slug'] === 'superadmin' || (string)$existing['role'] === 'superadmin');

if (($isTargetBecomingSuperadmin || $wasSuperadmin) && empty($currentPerms['is_superadmin'])) {
    send_http_error(403, 'Solo un superadministrador puede gestionar usuarios con rol Superadministrador.');
}

// Protection 4: Check last active superadmin
if ($wasSuperadmin && (!$isTargetBecomingSuperadmin || $active === 0)) {
    $saCheck = $connect->prepare("SELECT COUNT(*) as sa_count FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_slug = 'superadmin' AND u.active = 1 AND u.user_id != ?");
    $saCheck->bind_param('i', $targetUserId);
    $saCheck->execute();
    $saRes = $saCheck->get_result()->fetch_assoc();
    $saCheck->close();
    $otherSuperadmins = (int)($saRes['sa_count'] ?? 0);

    if ($otherSuperadmins === 0) {
        send_http_error(400, 'Operación denegada: no es posible desactivar o degradar al único Superadministrador activo del sistema.');
    }
}

// Check username uniqueness if changed
if ($username !== strtolower((string)$existing['username'])) {
    $uCheck = $connect->prepare('SELECT user_id FROM users WHERE username = ? AND user_id != ? LIMIT 1');
    $uCheck->bind_param('si', $username, $targetUserId);
    $uCheck->execute();
    if ($uCheck->get_result()->fetch_assoc()) {
        $uCheck->close();
        send_http_error(409, "El nombre de usuario '{$username}' ya está en uso por otro registro.");
    }
    $uCheck->close();
}

// Check branch if provided
if ($branchId !== null) {
    $bCheck = $connect->prepare('SELECT branch_id FROM branches WHERE branch_id = ? AND record_status = 1 LIMIT 1');
    $bCheck->bind_param('i', $branchId);
    $bCheck->execute();
    if (!$bCheck->get_result()->fetch_assoc()) {
        $bCheck->close();
        send_http_error(404, 'La sucursal seleccionada no existe o no se encuentra habilitada.');
    }
    $bCheck->close();
}

$roleSlug   = (string)$targetRole['role_slug'];

if ($newPassword !== '') {
    $hash   = password_hash($newPassword, PASSWORD_DEFAULT);
    $upd    = $connect->prepare('UPDATE users SET username = ?, password = ?, email = ?, phone = ?, full_name = ?, role = ?, role_id = ?, branch_id = ?, active = ?, updated_at = NOW() WHERE user_id = ?');
    $upd->bind_param('ssssssiiii', $username, $hash, $email, $phone, $fullName, $roleSlug, $roleId, $branchId, $active, $targetUserId);
} else {
    $upd = $connect->prepare('UPDATE users SET username = ?, email = ?, phone = ?, full_name = ?, role = ?, role_id = ?, branch_id = ?, active = ?, updated_at = NOW() WHERE user_id = ?');
    $upd->bind_param('sssssiiii', $username, $email, $phone, $fullName, $roleSlug, $roleId, $branchId, $active, $targetUserId);
}

if (!$upd->execute()) {
    $upd->close();
    $connect->close();
    send_http_error(500, 'Error al actualizar el usuario en la base de datos.');
}
$upd->close();

sb_log_audit(
    $connect,
    'users',
    'UPDATE',
    "Usuario '{$username}' (#{$targetUserId}) actualizado. Rol: '{$targetRole['role_name']}', Estado: " . ($active ? 'Activo' : 'Inactivo'),
    [
        'user_id'   => $targetUserId,
        'username'  => $username,
        'full_name' => $fullName,
        'role_id'   => $roleId,
        'role_slug' => $roleSlug,
        'branch_id' => $branchId,
        'active'    => $active,
        'password_changed' => ($newPassword !== ''),
    ]
);

$connect->close();

json_response([
    'success'   => true,
    'messages'  => "Usuario '{$username}' actualizado exitosamente.",
]);

