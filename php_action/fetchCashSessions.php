<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$branchId = get_active_branch_id($connect);
$activeBranch = get_active_branch($connect);
$config = sb_get_system_config($connect);
sb_ensure_branch_cash_defaults($connect, $branchId, $activeBranch['branch_name'], 0.0, (int)$_SESSION['userId']);

// General cash balance for this branch
$gcStmt = $connect->prepare('SELECT account_id, account_name, current_balance, min_balance_alert, status FROM general_cash_accounts WHERE branch_id = ? LIMIT 1');
$gcStmt->bind_param('i', $branchId);
$gcStmt->execute();
$gcRow = $gcStmt->get_result()->fetch_assoc();
$gcStmt->close();

// Registers in active branch
$regStmt = $connect->prepare("SELECT
    cr.*,
    (SELECT cs.session_id FROM cash_sessions cs WHERE cs.register_id = cr.register_id AND cs.status = 'open' ORDER BY cs.session_id DESC LIMIT 1) AS open_session_id
FROM cash_registers cr
WHERE cr.branch_id = ? AND cr.record_status = 1
ORDER BY cr.register_id ASC");
$regStmt->bind_param('i', $branchId);
$regStmt->execute();
$regRes = $regStmt->get_result();
$registers = [];
while ($r = $regRes->fetch_assoc()) {
    $registers[] = [
        'register_id' => (int)$r['register_id'],
        'branch_id' => (int)$r['branch_id'],
        'register_code' => $r['register_code'],
        'register_name' => $r['register_name'],
        'location' => $r['location'] ?? '',
        'active' => (int)$r['active'],
        'open_session_id' => $r['open_session_id'] !== null ? (int)$r['open_session_id'] : null,
    ];
}
$regStmt->close();

// Sessions in active branch
$sessSql = "SELECT
    cs.*,
    cr.register_code,
    cr.register_name,
    COALESCE(uo.username, 'Sistema') AS opened_by_name,
    uc.username AS closed_by_name,
    (SELECT COUNT(*) FROM cash_movements cm WHERE cm.session_id = cs.session_id AND cm.record_status = 1) AS movements_count,
    (SELECT COUNT(*) FROM cash_reconciliations rec WHERE rec.session_id = cs.session_id) AS reconciliations_count
FROM cash_sessions cs
LEFT JOIN cash_registers cr ON cr.register_id = cs.register_id
LEFT JOIN users uo ON uo.user_id = cs.opened_by
LEFT JOIN users uc ON uc.user_id = cs.closed_by
WHERE cs.branch_id = ?
ORDER BY FIELD(cs.status, 'open', 'closed') ASC, cs.session_id DESC
LIMIT 100";

$sStmt = $connect->prepare($sessSql);
$sStmt->bind_param('i', $branchId);
$sStmt->execute();
$sRes = $sStmt->get_result();

$sessions = [];
$openSessions = [];
while ($row = $sRes->fetch_assoc()) {
    $item = [
        'session_id' => (int)$row['session_id'],
        'branch_id' => (int)$row['branch_id'],
        'register_id' => (int)$row['register_id'],
        'register_code' => $row['register_code'] ?? '',
        'register_name' => $row['register_name'] ?? 'Caja',
        'session_code' => $row['session_code'],
        'opened_by' => (int)$row['opened_by'],
        'opened_by_name' => $row['opened_by_name'],
        'closed_by' => $row['closed_by'] !== null ? (int)$row['closed_by'] : null,
        'closed_by_name' => $row['closed_by_name'] ?? null,
        'opened_at' => $row['opened_at'],
        'closed_at' => $row['closed_at'],
        'opening_amount' => round((float)$row['opening_amount'], 2),
        'funded_from_general_cash' => ((int)$row['funded_from_general_cash']) === 1,
        'cash_in_total' => round((float)$row['cash_in_total'], 2),
        'cash_out_total' => round((float)$row['cash_out_total'], 2),
        'non_cash_sales_total' => round((float)$row['non_cash_sales_total'], 2),
        'expected_cash' => round((float)$row['expected_cash'], 2),
        'counted_cash' => $row['counted_cash'] !== null ? round((float)$row['counted_cash'], 2) : null,
        'card_reported' => round((float)$row['card_reported'], 2),
        'transfer_reported' => round((float)$row['transfer_reported'], 2),
        'check_reported' => round((float)$row['check_reported'], 2),
        'difference_amount' => $row['difference_amount'] !== null ? round((float)$row['difference_amount'], 2) : null,
        'returned_to_general_cash' => ((int)$row['returned_to_general_cash']) === 1,
        'amount_returned_general' => round((float)$row['amount_returned_general'], 2),
        'retained_float' => round((float)$row['retained_float'], 2),
        'status' => $row['status'],
        'opening_notes' => $row['opening_notes'] ?? '',
        'closing_notes' => $row['closing_notes'] ?? '',
        'movements_count' => (int)$row['movements_count'],
        'reconciliations_count' => (int)$row['reconciliations_count'],
    ];
    $sessions[] = $item;
    if ($row['status'] === 'open') {
        $openSessions[] = $item;
    }
}
$sStmt->close();
$connect->close();

json_response([
    'success' => true,
    'activeBranch' => $activeBranch,
    'systemConfig' => $config,
    'generalCash' => $gcRow ? [
        'account_id' => (int)$gcRow['account_id'],
        'account_name' => $gcRow['account_name'],
        'current_balance' => round((float)$gcRow['current_balance'], 2),
        'min_balance_alert' => round((float)$gcRow['min_balance_alert'], 2),
        'status' => (int)$gcRow['status'],
    ] : null,
    'registers' => $registers,
    'openSessions' => $openSessions,
    'sessions' => $sessions,
]);

