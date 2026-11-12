<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

sb_assert_permission($connect, 'roles', 'access');

$modules = sb_get_security_modules();

// Fetch roles with count of assigned users
$sql = "SELECT 
            r.role_id,
            r.role_name,
            r.role_slug,
            r.description,
            r.is_system,
            r.created_at,
            r.updated_at,
            (SELECT COUNT(*) FROM users u WHERE u.role_id = r.role_id) AS user_count
        FROM roles r
        ORDER BY r.role_id ASC";

$res = $connect->query($sql);
$roles = [];

// Prepare query for permissions of each role
$permStmt = $connect->prepare('SELECT module, can_access, can_create, can_update, can_delete FROM role_permissions WHERE role_id = ?');

while ($r = $res->fetch_assoc()) {
    $roleId = (int)$r['role_id'];
    $roleSlug = (string)$r['role_slug'];
    $isSuperadmin = ($roleSlug === 'superadmin');

    $permissionsMap = [];
    foreach ($modules as $m) {
        $key = $m['key'];
        $permissionsMap[$key] = [
            'module' => $key,
            'can_access' => $isSuperadmin,
            'can_create' => $isSuperadmin,
            'can_update' => $isSuperadmin,
            'can_delete' => $isSuperadmin,
        ];
    }

    if (!$isSuperadmin && $permStmt) {
        $permStmt->bind_param('i', $roleId);
        $permStmt->execute();
        $pRes = $permStmt->get_result();
        while ($p = $pRes->fetch_assoc()) {
            $mKey = (string)$p['module'];
            if (isset($permissionsMap[$mKey])) {
                $permissionsMap[$mKey]['can_access'] = ((int)$p['can_access']) === 1;
                $permissionsMap[$mKey]['can_create'] = ((int)$p['can_create']) === 1;
                $permissionsMap[$mKey]['can_update'] = ((int)$p['can_update']) === 1;
                $permissionsMap[$mKey]['can_delete'] = ((int)$p['can_delete']) === 1;
            }
        }
    }

    $roles[] = [
        'role_id' => $roleId,
        'role_name' => (string)$r['role_name'],
        'role_slug' => $roleSlug,
        'description' => (string)($r['description'] ?? ''),
        'is_system' => ((int)$r['is_system']) === 1,
        'user_count' => (int)$r['user_count'],
        'created_at' => $r['created_at'],
        'updated_at' => $r['updated_at'],
        'permissions' => array_values($permissionsMap),
    ];
}

if ($permStmt) {
    $permStmt->close();
}
$connect->close();

json_response([
    'success' => true,
    'data' => $roles,
    'available_modules' => $modules,
]);

