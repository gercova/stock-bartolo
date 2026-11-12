<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'DELETE']);

sb_assert_permission($connect, 'roles', 'delete');

$data = get_request_data();
$roleId = (int)($data['role_id'] ?? $data['roleId'] ?? 0);

if ($roleId <= 0) {
    send_http_error(400, 'Identificador de rol no válido.');
}

// Fetch role
$stmt = $connect->prepare('SELECT role_id, role_name, role_slug, is_system FROM roles WHERE role_id = ? LIMIT 1');
$stmt->bind_param('i', $roleId);
$stmt->execute();
$roleRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$roleRow) {
    send_http_error(404, 'El rol seleccionado no existe.');
}

// Protection 1: Cannot delete system roles
if (((int)$roleRow['is_system']) === 1) {
    send_http_error(400, "Los roles predeterminados del sistema ('{$roleRow['role_name']}') son protegidos y no pueden eliminarse.");
}

// Protection 2: Cannot delete roles with assigned users
$uCountStmt = $connect->prepare('SELECT COUNT(*) as c FROM users WHERE role_id = ?');
$uCountStmt->bind_param('i', $roleId);
$uCountStmt->execute();
$uCountRes = $uCountStmt->get_result()->fetch_assoc();
$uCountStmt->close();

$assignedUsers = (int)($uCountRes['c'] ?? 0);
if ($assignedUsers > 0) {
    send_http_error(400, "No se puede eliminar el rol '{$roleRow['role_name']}' porque tiene {$assignedUsers} usuario(s) asignado(s). Reasigna los usuarios a otro rol antes de proceder.");
}

// Delete permissions and role
$delPerm = $connect->prepare('DELETE FROM role_permissions WHERE role_id = ?');
$delPerm->bind_param('i', $roleId);
$delPerm->execute();
$delPerm->close();

$delRole = $connect->prepare('DELETE FROM roles WHERE role_id = ?');
$delRole->bind_param('i', $roleId);
if (!$delRole->execute()) {
    $delRole->close();
    $connect->close();
    send_http_error(500, 'Error al eliminar el rol.');
}
$delRole->close();

sb_log_audit(
    $connect,
    'roles',
    'DELETE',
    "Rol personalizado '{$roleRow['role_name']}' (#{$roleId}) eliminado definitivamente",
    [
        'role_id' => $roleId,
        'role_name' => $roleRow['role_name'],
        'role_slug' => $roleRow['role_slug'],
    ]
);

$connect->close();

json_response([
    'success' => true,
    'messages' => "Rol '{$roleRow['role_name']}' eliminado exitosamente.",
]);

