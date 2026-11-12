<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

sb_assert_permission($connect, 'roles', 'create');

$data = get_request_data();
$roleName = trim((string)($data['role_name'] ?? ''));
$description = trim((string)($data['description'] ?? ''));
$permissionsInput = is_array($data['permissions'] ?? null) ? $data['permissions'] : [];

if ($roleName === '' || strlen($roleName) < 2 || strlen($roleName) > 50) {
    send_http_error(400, 'El nombre del rol debe tener entre 2 y 50 caracteres.');
}

if (strlen($description) > 255) {
    send_http_error(400, 'La descripción del rol no puede exceder 255 caracteres.');
}

// Generate slug
$slugBase = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $roleName), '-'));
if ($slugBase === '') {
    $slugBase = 'rol-' . time();
}

$roleSlug = $slugBase;
$slugIndex = 1;

while (true) {
    $check = $connect->prepare('SELECT role_id FROM roles WHERE role_slug = ? LIMIT 1');
    $check->bind_param('s', $roleSlug);
    $check->execute();
    if (!$check->get_result()->fetch_assoc()) {
        $check->close();
        break;
    }
    $check->close();
    $slugIndex++;
    $roleSlug = $slugBase . '-' . $slugIndex;
}

// Insert role
$stmt = $connect->prepare('INSERT INTO roles (role_name, role_slug, description, is_system, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW())');
$stmt->bind_param('sss', $roleName, $roleSlug, $description);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'Error al guardar el nuevo rol.');
}

$newRoleId = (int)$stmt->insert_id;
$stmt->close();

// Process permissions map
$allModules = sb_get_security_modules();
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

$permStmt = $connect->prepare('INSERT INTO role_permissions (role_id, module, can_access, can_create, can_update, can_delete, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');

$totalGranted = 0;
foreach ($allModules as $mod) {
    $key = $mod['key'];
    $access = $inputMap[$key]['can_access'] ?? 0;
    $create = $inputMap[$key]['can_create'] ?? 0;
    $update = $inputMap[$key]['can_update'] ?? 0;
    $delete = $inputMap[$key]['can_delete'] ?? 0;

    if ($permStmt) {
        $permStmt->bind_param('isiiii', $newRoleId, $key, $access, $create, $update, $delete);
        $permStmt->execute();
    }

    $totalGranted += ($access + $create + $update + $delete);
}

if ($permStmt) {
    $permStmt->close();
}

sb_log_audit(
    $connect,
    'roles',
    'CREATE',
    "Rol '{$roleName}' (#{$newRoleId}) creado con {$totalGranted} permisos habilitados",
    [
        'role_id' => $newRoleId,
        'role_name' => $roleName,
        'role_slug' => $roleSlug,
        'description' => $description,
        'granted_permissions_count' => $totalGranted,
    ]
);

$connect->close();

json_response([
    'success' => true,
    'messages' => "Rol '{$roleName}' creado exitosamente.",
    'roleId' => $newRoleId,
]);

