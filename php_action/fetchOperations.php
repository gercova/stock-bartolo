<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

sb_assert_permission($connect, 'operations', 'access');

$branchId = get_active_branch_id($connect);
$startDate = trim((string)($_GET['startDate'] ?? ''));
$endDate = trim((string)($_GET['endDate'] ?? ''));
$opType = trim((string)($_GET['operation_type'] ?? 'all'));
$clientId = isset($_GET['client_id']) && $_GET['client_id'] !== '' ? (int)$_GET['client_id'] : null;
$search = trim((string)($_GET['search'] ?? ''));
$limit = min(200, max(10, (int)($_GET['limit'] ?? 50)));

// Ensure branch central cash account exists
$branchStmt = $connect->prepare('SELECT branch_id, branch_name FROM branches WHERE branch_id = ? LIMIT 1');
$branchStmt->bind_param('i', $branchId);
$branchStmt->execute();
$branchRow = $branchStmt->get_result()->fetch_assoc();
$branchStmt->close();
sb_ensure_branch_cash_defaults($connect, $branchId, (string)($branchRow['branch_name'] ?? 'Sucursal'), 0.0, (int)$_SESSION['userId']);

// Fetch central cash drawer balance
$cashAccStmt = $connect->prepare('SELECT account_id, current_balance, min_balance_alert FROM general_cash_accounts WHERE branch_id = ? LIMIT 1');
$cashAccStmt->bind_param('i', $branchId);
$cashAccStmt->execute();
$cashAcc = $cashAccStmt->get_result()->fetch_assoc();
$cashAccStmt->close();

$centralCashBalance = (float)($cashAcc['current_balance'] ?? 0.0);

// Fetch system settings for loyalty and operations
$sysConfig = sb_get_system_config($connect);

$where = ['o.branch_id = ?'];
$params = [$branchId];
$types = 'i';

if ($startDate !== '') {
    $where[] = 'o.created_at >= ?';
    $params[] = $startDate . ' 00:00:00';
    $types .= 's';
}

if ($endDate !== '') {
    $where[] = 'o.created_at <= ?';
    $params[] = $endDate . ' 23:59:59';
    $types .= 's';
}

if ($opType !== '' && $opType !== 'all') {
    $where[] = 'o.operation_type = ?';
    $params[] = $opType;
    $types .= 's';
}

if ($clientId !== null && $clientId > 0) {
    $where[] = 'o.client_id = ?';
    $params[] = $clientId;
    $types .= 'i';
}

if ($search !== '') {
    $where[] = '(o.operation_code LIKE ? OR c.name LIKE ? OR c.document_number LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$whereSql = implode(' AND ', $where);

$sql = "SELECT 
            o.operation_id,
            o.branch_id,
            o.operation_code,
            o.client_id,
            o.user_id,
            o.operation_type,
            o.amount,
            o.commission_amount,
            o.commission_included,
            o.net_amount,
            o.total_customer_amount,
            o.points_earned,
            o.central_balance_before,
            o.central_balance_after,
            o.status,
            o.notes,
            o.created_at,
            c.name AS client_name,
            c.document_type,
            c.document_number,
            c.phone AS client_phone,
            c.points_balance AS client_current_points,
            COALESCE(u.username, 'Cajero') AS cashier_name
        FROM branch_operations o
        INNER JOIN clients c ON o.client_id = c.client_id
        LEFT JOIN users u ON o.user_id = u.user_id
        WHERE {$whereSql}
        ORDER BY o.operation_id DESC
        LIMIT ?";

$pageParams = $params;
$pageParams[] = $limit;
$pageTypes = $types . 'i';

$stmt = $connect->prepare($sql);
$stmt->bind_param($pageTypes, ...$pageParams);
$stmt->execute();
$res = $stmt->get_result();

$operations = [];
while ($row = $res->fetch_assoc()) {
    $operations[] = [
        'operation_id' => (int)$row['operation_id'],
        'branch_id' => (int)$row['branch_id'],
        'operation_code' => (string)$row['operation_code'],
        'client_id' => (int)$row['client_id'],
        'client_name' => (string)$row['client_name'],
        'document_type' => (string)$row['document_type'],
        'document_number' => (string)$row['document_number'],
        'client_phone' => (string)($row['client_phone'] ?? ''),
        'client_current_points' => (int)$row['client_current_points'],
        'cashier_name' => (string)$row['cashier_name'],
        'operation_type' => (string)$row['operation_type'],
        'amount' => (float)$row['amount'],
        'commission_amount' => (float)$row['commission_amount'],
        'commission_included' => ((int)$row['commission_included']) === 1,
        'net_amount' => (float)$row['net_amount'],
        'total_customer_amount' => (float)$row['total_customer_amount'],
        'points_earned' => (int)$row['points_earned'],
        'central_balance_before' => (float)$row['central_balance_before'],
        'central_balance_after' => (float)$row['central_balance_after'],
        'status' => (string)$row['status'],
        'notes' => (string)($row['notes'] ?? ''),
        'created_at' => $row['created_at'],
    ];
}
$stmt->close();

// Compute branch operations summary stats
$statSql = "SELECT 
    COUNT(*) as total_ops,
    SUM(CASE WHEN operation_type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
    SUM(CASE WHEN operation_type = 'withdrawal' THEN amount ELSE 0 END) as total_withdrawals,
    SUM(commission_amount) as total_commissions,
    SUM(points_earned) as total_points_earned
    FROM branch_operations WHERE branch_id = ? AND status = 'completed'";
$sStmt = $connect->prepare($statSql);
$sStmt->bind_param('i', $branchId);
$sStmt->execute();
$sRow = $sStmt->get_result()->fetch_assoc();
$sStmt->close();

$stats = [
    'total_ops' => (int)($sRow['total_ops'] ?? count($operations)),
    'total_deposits' => (float)($sRow['total_deposits'] ?? 0.0),
    'total_withdrawals' => (float)($sRow['total_withdrawals'] ?? 0.0),
    'total_commissions' => (float)($sRow['total_commissions'] ?? 0.0),
    'total_points_earned' => (int)($sRow['total_points_earned'] ?? 0),
];

$connect->close();

json_response([
    'success' => true,
    'data' => $operations,
    'stats' => $stats,
    'central_cash_balance' => $centralCashBalance,
    'settings' => [
        'points_ratio_soles' => (float)($sysConfig['points_ratio_soles'] ?? 10.0),
        'points_ratio_points' => (int)($sysConfig['points_ratio_points'] ?? 1),
        'print_double_ticket' => (bool)($sysConfig['print_double_ticket'] ?? true),
        'commission_rate_per_100' => (float)($sysConfig['commission_rate_per_100'] ?? 1.00),
    ],
]);

