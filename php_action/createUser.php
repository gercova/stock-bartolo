<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

sb_assert_permission($connect, 'users', 'create');

$data = get_request_data();
$username = strtolower(trim((string)($data['username'] ?? '')));
$password = (string)($data['password'] ?? '');
$fullName = trim((string)($data['full_name'] ?? ''));
$email = strtolower(trim((string)($data['email'] ?? '')));
$phone = trim((string)($data['phone'] ?? ''));
$roleId = isset($data['role_id']) && $data['role_id'] !== '' ? (int)$data['role_id'] : 0;
$branchId = isset($data['branch_id']) && $data['branch_id'] !== '' && (int)$data['branch_id'] > 0 ? (int)$data['branch_id'] : null;
$active = isset($data['active']) ? ((int)$data['active'] === 1 ? 1 : 0) : 1;

if ($username === '' || strlen($username) < 3 || strlen($username) > 50) {
    send_http_error(400, 'El nombre de usuario debe tener entre 3 y 50 caracteres.');
}

if (!preg_match('/^[a-z0-9._-]+$/i', $username)) {
    send_http_error(400, 'El nombre de usuario solo puede contener letras, números, puntos, guiones y guiones bajos.');
}

if (strlen($password) < 6 || strlen($password) > 128) {
    send_http_error(400, 'La contraseña debe tener entre 6 y 128 caracteres.');
}

if ($fullName === '' || strlen($fullName) > 100) {
    send_http_error(400, 'El nombre completo es obligatorio y debe tener como máximo 100 caracteres.');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_http_error(400, 'El correo electrónico no tiene un formato válido.');
}

if ($roleId <= 0) {
    send_http_error(400, 'Debes seleccionar un rol válido para el usuario.');
}

// Validate role exists
$roleStmt = $connect->prepare('SELECT role_id, role_name, role_slug FROM roles WHERE role_id = ? LIMIT 1');
$roleStmt->bind_param('i', $roleId);
$roleStmt->execute();
$roleRow = $roleStmt->get_result()->fetch_assoc();
$roleStmt->close();

if (!$roleRow) {
    send_http_error(404, 'El rol seleccionado no existe.');
}

// If assigning superadmin role, verify that current user is a superadmin
$currentPerms = sb_get_user_permissions($connect, (int)$_SESSION['userId']);
if ($roleRow['role_slug'] === 'superadmin' && empty($currentPerms['is_superadmin'])) {
    send_http_error(403, 'Solo un superadministrador puede asignar el rol de superadministrador.');
}

// Check username uniqueness
$uCheck = $connect->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1');
$uCheck->bind_param('s', $username);
$uCheck->execute();
if ($uCheck->get_result()->fetch_assoc()) {
    $uCheck->close();
    send_http_error(409, "El nombre de usuario '{$username}' ya se encuentra registrado.");
}
$uCheck->close();

// Check branch if provided
if ($branchId !== null) {
    $bCheck = $connect->prepare('SELECT branch_id, branch_name FROM branches WHERE branch_id = ? AND record_status = 1 LIMIT 1');
    $bCheck->bind_param('i', $branchId);
    $bCheck->execute();
    if (!$bCheck->get_result()->fetch_assoc()) {
        $bCheck->close();
        send_http_error(404, 'La sucursal seleccionada no existe o está deshabilitada.');
    }
    $bCheck->close();
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$roleSlug = (string)$roleRow['role_slug'];

$insertSql = 'INSERT INTO users (username, password, email, role, role_id, branch_id, full_name, phone, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';
$insStmt = $connect->prepare($insertSql);
$insStmt->bind_param(
    'ssssiissi',
    $username,
    $hash,
    $email,
    $roleSlug,
    $roleId,
    $branchId,
    $fullName,
    $phone,
    $active
);

if (!$insStmt->execute()) {
    $insStmt->close();
    $connect->close();
    send_http_error(500, 'Error al registrar el nuevo usuario en el sistema.');
}

$newUserId = (int)$insStmt->insert_id;
$insStmt->close();

sb_log_audit(
    $connect,
    'users',
    'CREATE',
    "Usuario '{$username}' (#{$newUserId}) creado exitosamente con rol '{$roleRow['role_name']}'",
    [
        'user_id' => $newUserId,
        'username' => $username,
        'full_name' => $fullName,
        'role_id' => $roleId,
        'role_slug' => $roleSlug,
        'branch_id' => $branchId,
        'active' => $active,
    ]
);

$connect->close();

json_response([
    'success' => true,
    'messages' => "Usuario '{$username}' creado exitosamente.",
    'userId' => $newUserId,
]);

