<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);

sb_assert_permission($connect, 'roles', 'update');

$data = get_request_data();
$roleId = (int)($data['role_id'] ?? $data['roleId'] ?? 0);
$roleName = trim((string)($data['role_name'] ?? ''));
$description = trim((string)($data['description'] ?? ''));
$permissionsInput = is_array($data['permissions'] ?? null) ? $data['permissions'] : [];

if ($roleId <= 0) {
    send_http_error(400, 'Identificador de rol no válido.');
}

if ($roleName === '' || strlen($roleName) < 2 || strlen($roleName) > 50) {
    send_http_error(400, 'El nombre del rol debe tener entre 2 y 50 caracteres.');
}

if (strlen($description) > 255) {
    send_http_error(400, 'La descripción del rol no puede exceder 255 caracteres.');
}

// Check role exists
$stmt = $connect->prepare('SELECT role_id, role_name, role_slug, is_system FROM roles WHERE role_id = ? LIMIT 1');
$stmt->bind_param('i', $roleId);
$stmt->execute();
$roleRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$roleRow) {
    send_http_error(404, 'El rol seleccionado no existe.');
}

$isSuperadminRole = ($roleRow['role_slug'] === 'superadmin');

// Update role header
$upd = $connect->prepare('UPDATE roles SET role_name = ?, description = ?, updated_at = NOW() WHERE role_id = ?');
$upd->bind_param('ssi', $roleName, $description, $roleId);
if (!$upd->execute()) {
    $upd->close();
    $connect->close();
    send_http_error(500, 'Error al actualizar la información del rol.');
}
$upd->close();

$allModules = sb_get_security_modules();
$totalGranted = 0;

if (!$isSuperadminRole) {
    // Process permissions
    $inputMap = [];
    foreach ($permissionsInput as $item) {
        if (is_array($item) && !empty($item['module'])) {
            $mKey = (string)$item['module'];
            $inputMap[$mKey] = [
                'can_access' => !empty($item['can_access']) ? 1 : 0,
                'can_create' => !empty($item['can_create']) ? 1 : 0,
                'can_update' => !empty($item['can_update']) ? 1 : 0,
                'can_delete' => !empty($item['can_delete']) ? 1 : 0,
            ];
        }
    }

    $upsertStmt = $connect->prepare('INSERT INTO role_permissions (role_id, module, can_access, can_create, can_update, can_delete, created_at, updated_at) 
                                      VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                                      ON DUPLICATE KEY UPDATE 
                                        can_access = VALUES(can_access),
                                        can_create = VALUES(can_create),
                                        can_update = VALUES(can_update),
                                        can_delete = VALUES(can_delete),
                                        updated_at = NOW()');

    foreach ($allModules as $mod) {
        $key = $mod['key'];
        $access = $inputMap[$key]['can_access'] ?? 0;
        $create = $inputMap[$key]['can_create'] ?? 0;
        $update = $inputMap[$key]['can_update'] ?? 0;
        $delete = $inputMap[$key]['can_delete'] ?? 0;

        if ($upsertStmt) {
            $upsertStmt->bind_param('isiiii', $roleId, $key, $access, $create, $update, $delete);
            $upsertStmt->execute();
        }

        $totalGranted += ($access + $create + $update + $delete);
    }

    if ($upsertStmt) {
        $upsertStmt->close();
    }
} else {
    $totalGranted = count($allModules) * 4;
}

sb_log_audit(
    $connect,
    'roles',
    'UPDATE',
    "Rol '{$roleName}' (#{$roleId}) actualizado ({$totalGranted} permisos configurados)",
    [
        'role_id' => $roleId,
        'role_name' => $roleName,
        'role_slug' => $roleRow['role_slug'],
        'is_system' => (int)$roleRow['is_system'],
        'granted_permissions_count' => $totalGranted,
    ]
);

$connect->close();

json_response([
    'success' => true,
    'messages' => "Rol '{$roleName}' actualizado exitosamente.",
]);

