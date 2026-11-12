<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$userId = (int)$_SESSION['userId'];
$canSwitch = sb_user_can_switch_branches($connect, $userId);
$activeBranchId = get_active_branch_id($connect);
$targetBranchId = isset($_GET['branchId']) && (int)$_GET['branchId'] > 0
    ? (int)$_GET['branchId']
    : $activeBranchId;

if ($targetBranchId !== $activeBranchId && !$canSwitch) {
    $connect->close();
    send_http_error(403, 'No tienes permisos para consultar la Caja General de otra sucursal.');
}

$bStmt = $connect->prepare('SELECT * FROM branches WHERE branch_id = ? AND record_status = 1 LIMIT 1');
$bStmt->bind_param('i', $targetBranchId);
$bStmt->execute();
$branchRow = $bStmt->get_result()->fetch_assoc();
$bStmt->close();

if (!$branchRow) {
    $connect->close();
    send_http_error(404, 'Sucursal no encontrada.');
}

sb_ensure_branch_cash_defaults($connect, $targetBranchId, (string)$branchRow['branch_name'], 0.0, $userId);

$accStmt = $connect->prepare('SELECT gca.*, COALESCE(u.username, "Sistema") AS updated_by_name FROM general_cash_accounts gca LEFT JOIN users u ON u.user_id = gca.updated_by WHERE gca.branch_id = ? LIMIT 1');
$accStmt->bind_param('i', $targetBranchId);
$accStmt->execute();
$accRow = $accStmt->get_result()->fetch_assoc();
$accStmt->close();

$movStmt = $connect->prepare('SELECT gcm.*, COALESCE(u.username, "Sistema") AS username, cs.session_code FROM general_cash_movements gcm LEFT JOIN users u ON u.user_id = gcm.user_id LEFT JOIN cash_sessions cs ON cs.session_id = gcm.cash_session_id WHERE gcm.branch_id = ? AND gcm.record_status = 1 ORDER BY gcm.movement_id DESC LIMIT 200');
$movStmt->bind_param('i', $targetBranchId);
$movStmt->execute();
$movRes = $movStmt->get_result();

$movements = [];
$totalDeposits = 0.0;
$totalWithdrawals = 0.0;
$totalAllocatedToTills = 0.0;
$totalReturnedFromTills = 0.0;

while ($m = $movRes->fetch_assoc()) {
    $amt = round((float)$m['amount'], 2);
    $type = $m['movement_type'];
    if (in_array($type, ['initial_capital', 'deposit', 'adjustment_in'], true)) {
        $totalDeposits += $amt;
    } elseif (in_array($type, ['withdrawal', 'adjustment_out'], true)) {
        $totalWithdrawals += $amt;
    } elseif ($type === 'till_open_allocation') {
        $totalAllocatedToTills += $amt;
    } elseif ($type === 'till_close_return') {
        $totalReturnedFromTills += $amt;
    }

    $movements[] = [
        'movement_id' => (int)$m['movement_id'],
        'branch_id' => (int)$m['branch_id'],
        'account_id' => (int)$m['account_id'],
        'cash_session_id' => $m['cash_session_id'] !== null ? (int)$m['cash_session_id'] : null,
        'session_code' => $m['session_code'] ?? null,
        'user_id' => (int)$m['user_id'],
        'username' => $m['username'],
        'movement_type' => $type,
        'direction' => $m['direction'],
        'amount' => $amt,
        'balance_before' => round((float)$m['balance_before'], 2),
        'balance_after' => round((float)$m['balance_after'], 2),
        'reference' => $m['reference'] ?? '',
        'description' => $m['description'],
        'can_modify' => !in_array($type, ['till_open_allocation', 'till_close_return'], true),
        'created_at' => $m['created_at'],
    ];
}
$movStmt->close();

// Multi-branch vaults summary
$vaultSql = 'SELECT b.branch_id, b.branch_code, b.branch_name, b.currency_code, b.currency_symbol, b.active, COALESCE(gca.account_id, 0) AS account_id, COALESCE(gca.account_name, CONCAT("Caja General — ", b.branch_name)) AS account_name, COALESCE(gca.current_balance, 0) AS current_balance, COALESCE(gca.min_balance_alert, 0) AS min_balance_alert, COALESCE(gca.status, 1) AS vault_status, (SELECT COUNT(*) FROM cash_sessions cs WHERE cs.branch_id = b.branch_id AND cs.status = "open") AS open_sessions_count FROM branches b LEFT JOIN general_cash_accounts gca ON gca.branch_id = b.branch_id WHERE b.record_status = 1 ' . ($canSwitch ? '' : 'AND b.branch_id = ? ') . 'ORDER BY b.is_main DESC, b.branch_id ASC';

if ($canSwitch) {
    $vRes = $connect->query($vaultSql);
    $vStmt = null;
} else {
    $vStmt = $connect->prepare($vaultSql);
    $vStmt->bind_param('i', $activeBranchId);
    $vStmt->execute();
    $vRes = $vStmt->get_result();
}

$branchVaults = [];
if ($vRes) {
    while ($v = $vRes->fetch_assoc()) {
        $bal = round((float)$v['current_balance'], 2);
        $min = round((float)$v['min_balance_alert'], 2);
        $branchVaults[] = [
            'branch_id' => (int)$v['branch_id'],
            'branch_code' => $v['branch_code'],
            'branch_name' => $v['branch_name'],
            'currency_code' => $v['currency_code'],
            'currency_symbol' => $v['currency_symbol'],
            'branch_active' => (int)$v['active'],
            'account_id' => (int)$v['account_id'],
            'account_name' => $v['account_name'],
            'current_balance' => $bal,
            'min_balance_alert' => $min,
            'is_below_min' => $bal <= $min,
            'status' => (int)$v['vault_status'],
            'open_sessions_count' => (int)$v['open_sessions_count'],
            'is_active_tenant' => ((int)$v['branch_id'] === $activeBranchId),
        ];
    }
}
if ($vStmt) {
    $vStmt->close();
}

$currentBal = round((float)($accRow['current_balance'] ?? 0), 2);
$minAlert = round((float)($accRow['min_balance_alert'] ?? 0), 2);

$connect->close();

json_response([
    'success' => true,
    'branch' => sb_format_branch_row($branchRow),
    'account' => [
        'account_id' => (int)$accRow['account_id'],
        'branch_id' => (int)$accRow['branch_id'],
        'account_name' => $accRow['account_name'],
        'current_balance' => $currentBal,
        'initial_capital' => round((float)$accRow['initial_capital'], 2),
        'min_balance_alert' => $minAlert,
        'status' => (int)$accRow['status'],
        'notes' => $accRow['notes'] ?? '',
        'updated_by_name' => $accRow['updated_by_name'] ?? 'Sistema',
        'updated_at' => $accRow['updated_at'],
    ],
    'summary' => [
        'currentBalance' => $currentBal,
        'initialCapital' => round((float)$accRow['initial_capital'], 2),
        'totalDeposits' => round($totalDeposits, 2),
        'totalWithdrawals' => round($totalWithdrawals, 2),
        'totalAllocatedToTills' => round($totalAllocatedToTills, 2),
        'totalReturnedFromTills' => round($totalReturnedFromTills, 2),
        'isBelowMinAlert' => $currentBal <= $minAlert,
        'movementsCount' => count($movements),
    ],
    'movements' => $movements,
    'branchVaults' => $branchVaults,
]);

