<?php

require_once __DIR__ . '/core.php';
require_method(['GET', 'POST']);

$branchId       = get_active_branch_id($connect);
$activeBranch   = get_active_branch($connect);
$data           = array_merge($_GET, get_request_data());
$sessionId      = (int)($data['sessionId'] ?? $data['id'] ?? 0);

if ($sessionId <= 0) {
    send_http_error(400, 'ID de sesión de caja inválido.');
}

$stmt = $connect->prepare("SELECT
    cs.*,
    cr.register_code,
    cr.register_name,
    cr.location,
    COALESCE(uo.username, 'Sistema') AS opened_by_name,
    uc.username AS closed_by_name
FROM cash_sessions cs
LEFT JOIN cash_registers cr ON cr.register_id = cs.register_id
LEFT JOIN users uo ON uo.user_id = cs.opened_by
LEFT JOIN users uc ON uc.user_id = cs.closed_by
WHERE cs.session_id = ? AND cs.branch_id = ?
LIMIT 1");
$stmt->bind_param('ii', $sessionId, $branchId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $connect->close();
    send_http_error(404, 'Sesión de caja no encontrada en la sucursal activa.');
}

// Movements
$mStmt = $connect->prepare("SELECT cm.*, COALESCE(u.username, 'Sistema') AS username FROM cash_movements cm LEFT JOIN users u ON u.user_id = cm.user_id WHERE cm.session_id = ? AND cm.branch_id = ? AND cm.record_status = 1 ORDER BY cm.movement_id DESC");
$mStmt->bind_param('ii', $sessionId, $branchId);
$mStmt->execute();
$mRes = $mStmt->get_result();

$movements = [];
$breakdown = [
    'sale_cash'             => 0.0,
    'order_payment_cash'    => 0.0,
    'loan_payment_cash'     => 0.0,
    'manual_in'             => 0.0,
    'loan_disbursement'     => 0.0,
    'manual_out'            => 0.0,
    'expense'               => 0.0,
    'sweep_to_general'      => 0.0,
];

while ($m = $mRes->fetch_assoc()) {
    $amt    = round((float)$m['amount'], 2);
    $type   = $m['movement_type'];
    if (isset($breakdown[$type])) {
        $breakdown[$type] = round($breakdown[$type] + $amt, 2);
    }

    $movements[] = [
        'movement_id'   => (int)$m['movement_id'],
        'session_id'    => (int)$m['session_id'],
        'user_id'       => (int)$m['user_id'],
        'username'      => $m['username'],
        'movement_type' => $type,
        'direction'     => $m['direction'],
        'amount'        => $amt,
        'payment_method' => (int)$m['payment_method'],
        'reference_type' => $m['reference_type'] ?? '',
        'reference_id'  => $m['reference_id'] !== null ? (int)$m['reference_id'] : null,
        'description'   => $m['description'],
        'created_at'    => $m['created_at'],
    ];
}
$mStmt->close();

// Reconciliations
$rStmt = $connect->prepare("SELECT rec.*, COALESCE(u.username, 'Sistema') AS username FROM cash_reconciliations rec LEFT JOIN users u ON u.user_id = rec.user_id WHERE rec.session_id = ? AND rec.branch_id = ? ORDER BY rec.reconciliation_id DESC");
$rStmt->bind_param('ii', $sessionId, $branchId);
$rStmt->execute();
$rRes = $rStmt->get_result();

$reconciliations = [];
while ($rec = $rRes->fetch_assoc()) {
    $denoms = null;
    if (!empty($rec['denominations_json'])) {
        $decoded = json_decode($rec['denominations_json'], true);
        if (is_array($decoded)) {
            $denoms = $decoded;
        }
    }
    $reconciliations[] = [
        'reconciliation_id' => (int)$rec['reconciliation_id'],
        'session_id' => (int)$rec['session_id'],
        'user_id' => (int)$rec['user_id'],
        'username' => $rec['username'],
        'reconciliation_type' => $rec['reconciliation_type'],
        'reconciled_at' => $rec['reconciled_at'],
        'expected_cash' => round((float)$rec['expected_cash'], 2),
        'counted_cash' => round((float)$rec['counted_cash'], 2),
        'difference_amount' => round((float)$rec['difference_amount'], 2),
        'card_reported' => round((float)$rec['card_reported'], 2),
        'transfer_reported' => round((float)$rec['transfer_reported'], 2),
        'check_reported' => round((float)$rec['check_reported'], 2),
        'status_label' => $rec['status_label'],
        'denominations' => $denoms,
        'notes' => $rec['notes'] ?? '',
        'created_at' => $rec['created_at'],
    ];
}
$rStmt->close();
$connect->close();

json_response([
    'success' => true,
    'branch' => $activeBranch,
    'session' => [
        'session_id' => (int)$row['session_id'],
        'branch_id' => (int)$row['branch_id'],
        'register_id' => (int)$row['register_id'],
        'register_code' => $row['register_code'] ?? '',
        'register_name' => $row['register_name'] ?? 'Caja',
        'location' => $row['location'] ?? '',
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
    ],
    'breakdown' => $breakdown,
    'movements' => $movements,
    'reconciliations' => $reconciliations,
]);

