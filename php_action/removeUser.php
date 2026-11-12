<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'DELETE']);

sb_assert_permission($connect, 'users', 'delete');

$data = get_request_data();
$targetUserId = (int)($data['user_id'] ?? $data['userId'] ?? 0);
$currentUserId = (int)($_SESSION['userId'] ?? 0);

if ($targetUserId <= 0) {
    send_http_error(400, 'Identificador de usuario no válido.');
}

// Protection 1: Cannot delete self
if ($targetUserId === $currentUserId) {
    send_http_error(400, 'No puedes eliminar tu propia cuenta de usuario en uso.');
}

// Fetch user data
$stmt = $connect->prepare('SELECT u.user_id, u.username, u.role, u.role_id, u.active, r.role_slug, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ? LIMIT 1');
$stmt->bind_param('i', $targetUserId);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userRow) {
    send_http_error(404, 'El usuario solicitado no existe.');
}

$username = (string)$userRow['username'];
$isSuperadmin = ($userRow['role_slug'] === 'superadmin' || (string)$userRow['role'] === 'superadmin');
$currentPerms = sb_get_user_permissions($connect, $currentUserId);

// Protection 2: Only superadmin can delete a superadmin
if ($isSuperadmin && empty($currentPerms['is_superadmin'])) {
    send_http_error(403, 'Solo un superadministrador puede eliminar a otro superadministrador.');
}

// Protection 3: Check if this is the last active superadmin
if ($isSuperadmin) {
    $saCheck = $connect->prepare("SELECT COUNT(*) as sa_count FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_slug = 'superadmin' AND u.active = 1 AND u.user_id != ?");
    $saCheck->bind_param('i', $targetUserId);
    $saCheck->execute();
    $saRes = $saCheck->get_result()->fetch_assoc();
    $saCheck->close();
    $otherSuperadmins = (int)($saRes['sa_count'] ?? 0);

    if ($otherSuperadmins === 0) {
        send_http_error(400, 'Operación denegada: no es posible eliminar al único Superadministrador del sistema.');
    }
}

// Check linked records in orders, cash_sessions, loans, audit_logs
$hasOrders = 0;
$orderCheck = $connect->prepare('SELECT COUNT(*) as c FROM orders WHERE user_id = ?');
if ($orderCheck) {
    $orderCheck->bind_param('i', $targetUserId);
    $orderCheck->execute();
    $hasOrders = (int)($orderCheck->get_result()->fetch_assoc()['c'] ?? 0);
    $orderCheck->close();
}

$hasCashSessions = 0;
$cashCheck = $connect->prepare('SELECT COUNT(*) as c FROM cash_sessions WHERE cashier_user_id = ?');
if ($cashCheck) {
    $cashCheck->bind_param('i', $targetUserId);
    $cashCheck->execute();
    $hasCashSessions = (int)($cashCheck->get_result()->fetch_assoc()['c'] ?? 0);
    $cashCheck->close();
}

$hasLoans = 0;
$loanCheck = $connect->prepare('SELECT COUNT(*) as c FROM loans WHERE user_id = ?');
if ($loanCheck) {
    $loanCheck->bind_param('i', $targetUserId);
    $loanCheck->execute();
    $hasLoans = (int)($loanCheck->get_result()->fetch_assoc()['c'] ?? 0);
    $loanCheck->close();
}

$totalLinked = $hasOrders + $hasCashSessions + $hasLoans;

if ($totalLinked > 0) {
    // Soft delete / deactivate
    $deactStmt = $connect->prepare('UPDATE users SET active = 0, updated_at = NOW() WHERE user_id = ?');
    $deactStmt->bind_param('i', $targetUserId);
    $deactStmt->execute();
    $deactStmt->close();

    sb_log_audit(
        $connect,
        'users',
        'DEACTIVATE',
        "Usuario '{$username}' (#{$targetUserId}) desactivado permanentemente por registros vinculados ({$totalLinked} operaciones históricas)",
        [
            'user_id' => $targetUserId,
            'username' => $username,
            'reason' => 'has_historical_records',
            'orders_count' => $hasOrders,
            'cash_sessions_count' => $hasCashSessions,
            'loans_count' => $hasLoans,
        ]
    );

    $connect->close();

    json_response([
        'success' => true,
        'action' => 'deactivated',
        'messages' => "El usuario '{$username}' tiene {$totalLinked} transacciones históricas registradas en el sistema (ventas, cajas o préstamos); ha sido desactivado permanentemente para preservar la integridad de datos.",
    ]);
} else {
    // Hard delete
    $delStmt = $connect->prepare('DELETE FROM users WHERE user_id = ?');
    $delStmt->bind_param('i', $targetUserId);
    if (!$delStmt->execute()) {
        $delStmt->close();
        $connect->close();
        send_http_error(500, 'Error al eliminar el usuario.');
    }
    $delStmt->close();

    sb_log_audit(
        $connect,
        'users',
        'DELETE',
        "Usuario '{$username}' (#{$targetUserId}) eliminado definitivamente del sistema",
        [
            'user_id' => $targetUserId,
            'username' => $username,
        ]
    );

    $connect->close();

    json_response([
        'success' => true,
        'action' => 'deleted',
        'messages' => "Usuario '{$username}' eliminado definitivamente.",
    ]);
}

