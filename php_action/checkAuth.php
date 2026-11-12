<?php

define('SKIP_AUTH_CHECK', true);
require_once __DIR__ . '/core.php';

require_method(['GET']);
$csrfToken = issue_csrf_cookie();

if (empty($_SESSION['userId'])) {
    $connect->close();
    json_response([
        'success'       => true,
        'authenticated' => false,
        'csrfToken'     => $csrfToken,
        'user'          => null,
    ]);
}

$userId = (int)$_SESSION['userId'];
$stmt   = $connect->prepare('SELECT user_id, username, full_name, email, phone, role_id, role, branch_id, active FROM users WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$user || (isset($user['active']) && (int)$user['active'] !== 1)) {
    $connect->close();
    $_SESSION   = [];
    $csrfToken  = rotate_csrf_token();
    json_response([
        'success'       => true,
        'authenticated' => false,
        'csrfToken'     => $csrfToken,
        'user'          => null,
        'activeBranch'  => null,
        'branches'      => [],
        'systemConfig'  => null,
    ]);
}

$role                           = (string)($user['role'] ?? 'superadmin');
$_SESSION['user_role']          = $role;
$_SESSION['assigned_branch_id'] = max(1, (int)($user['branch_id'] ?? 1));

$activeBranch       = get_active_branch($connect);
$accessibleBranches = sb_get_accessible_branches($connect);
$systemConfig       = sb_get_system_config($connect);
$permissions        = sb_get_user_permissions($connect, $userId);
$connect->close();

json_response([
    'success'       => true,
    'authenticated' => true,
    'csrfToken'     => $csrfToken,
    'user'  => [
        'user_id'   => (int)$user['user_id'],
        'username'  => $user['username'],
        'full_name' => (string)($user['full_name'] ?? ''),
        'email'     => $user['email'] ?? '',
        'phone'     => $user['phone'] ?? '',
        'role'      => $role,
        'role_id'   => (int)($user['role_id'] ?? 1),
        'branch_id' => $_SESSION['assigned_branch_id'],
        'can_switch_branches' => in_array($role, ['superadmin', 'admin'], true),
    ],
    'permissions'   => $permissions,
    'activeBranch'  => $activeBranch,
    'branches'      => $accessibleBranches,
    'systemConfig'  => $systemConfig,
]);
