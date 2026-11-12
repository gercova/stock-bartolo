<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

sb_assert_permission($connect, 'users', 'access');

$search = trim((string)($_GET['search'] ?? ''));
$roleId = isset($_GET['role_id']) && $_GET['role_id'] !== '' ? (int)$_GET['role_id'] : null;
$branchId = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? (int)$_GET['branch_id'] : null;
$activeFilter = isset($_GET['active']) && $_GET['active'] !== '' ? (int)$_GET['active'] : null;

$whereClauses = ['1=1'];
$params = [];
$types = '';

if ($search !== '') {
    $whereClauses[] = '(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssss';
}

if ($roleId !== null && $roleId > 0) {
    $whereClauses[] = 'u.role_id = ?';
    $params[] = $roleId;
    $types .= 'i';
}

if ($branchId !== null && $branchId > 0) {
    $whereClauses[] = 'u.branch_id = ?';
    $params[] = $branchId;
    $types .= 'i';
}

if ($activeFilter !== null && in_array($activeFilter, [0, 1], true)) {
    $whereClauses[] = 'u.active = ?';
    $params[] = $activeFilter;
    $types .= 'i';
}

$whereSql = implode(' AND ', $whereClauses);

$sql = "SELECT 
            u.user_id,
            u.username,
            u.full_name,
            u.email,
            u.phone,
            u.role_id,
            u.branch_id,
            u.active,
            u.last_login,
            u.created_at,
            u.updated_at,
            COALESCE(r.role_name, 'Sin Rol') AS role_name,
            COALESCE(r.role_slug, 'custom') AS role_slug,
            COALESCE(r.is_system, 0) AS is_system_role,
            b.branch_name,
            b.branch_code
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.role_id
        LEFT JOIN branches b ON u.branch_id = b.branch_id
        WHERE {$whereSql}
        ORDER BY u.user_id ASC";

$stmt = $connect->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$currentUserId = (int)($_SESSION['userId'] ?? 0);
$users = [];

while ($row = $res->fetch_assoc()) {
    $isSuperadmin = ($row['role_slug'] === 'superadmin');
    $users[] = [
        'user_id' => (int)$row['user_id'],
        'username' => (string)$row['username'],
        'full_name' => (string)($row['full_name'] ?? $row['username']),
        'email' => (string)($row['email'] ?? ''),
        'phone' => (string)($row['phone'] ?? ''),
        'role_id' => $row['role_id'] ? (int)$row['role_id'] : null,
        'role_name' => (string)$row['role_name'],
        'role_slug' => (string)$row['role_slug'],
        'is_system_role' => ((int)$row['is_system_role']) === 1,
        'branch_id' => $row['branch_id'] ? (int)$row['branch_id'] : null,
        'branch_name' => (string)($row['branch_name'] ?? 'Todas las sucursales (Global)'),
        'branch_code' => (string)($row['branch_code'] ?? 'GLOBAL'),
        'active' => (int)$row['active'],
        'last_login' => $row['last_login'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'is_current_user' => ((int)$row['user_id'] === $currentUserId),
        'is_superadmin' => $isSuperadmin,
    ];
}
$stmt->close();

// Global Stats (unfiltered for overview)
$statRes = $connect->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END) as inactive,
    SUM(CASE WHEN r.role_slug = 'superadmin' THEN 1 ELSE 0 END) as superadmins
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.role_id");
$statsRow = $statRes ? $statRes->fetch_assoc() : [];

$stats = [
    'total' => (int)($statsRow['total'] ?? count($users)),
    'active' => (int)($statsRow['active'] ?? 0),
    'inactive' => (int)($statsRow['inactive'] ?? 0),
    'superadmins' => (int)($statsRow['superadmins'] ?? 0),
];

$connect->close();

json_response([
    'success' => true,
    'data' => $users,
    'stats' => $stats,
]);

