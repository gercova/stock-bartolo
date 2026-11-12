<?php

require_once __DIR__ . '/core.php';
require_method(['GET', 'POST']);

$branchId = get_active_branch_id($connect);
$data = array_merge($_GET, get_request_data());
$startDate = validate_iso_date($data['startDate'] ?? '', 'fecha inicial', true);
$endDate = validate_iso_date($data['endDate'] ?? '', 'fecha final', true);

if ($startDate > $endDate) {
    send_http_error(400, 'La fecha inicial no puede ser posterior a la fecha final.');
}

$stmt = $connect->prepare('SELECT order_id, order_date, client_name, client_contact, grand_total, paid, due, payment_status FROM orders WHERE branch_id = ? AND order_date >= ? AND order_date <= ? AND order_status = 1 ORDER BY order_date DESC, order_id DESC');
$stmt->bind_param('iss', $branchId, $startDate, $endDate);
$stmt->execute();
$res = $stmt->get_result();

$orders = [];
$totalAmount = 0.0;
$totalPaid = 0.0;
$totalDue = 0.0;

while ($row = $res->fetch_assoc()) {
    $grand = (float)$row['grand_total'];
    $paid = (float)$row['paid'];
    $due = (float)$row['due'];
    $totalAmount += $grand;
    $totalPaid += $paid;
    $totalDue += $due;

    $orders[] = [
        'order_id' => (int)$row['order_id'],
        'order_date' => $row['order_date'],
        'client_name' => $row['client_name'],
        'client_contact' => $row['client_contact'],
        'grand_total' => round($grand, 2),
        'paid' => round($paid, 2),
        'due' => round($due, 2),
        'payment_status' => (int)$row['payment_status'],
    ];
}
$stmt->close();

$loanStmt = $connect->prepare('SELECT l.loan_id, l.loan_code, l.loan_date, l.due_date, l.client_name, l.client_contact, l.principal_amount, l.has_interest, l.interest_rate, l.interest_amount, l.total_amount, l.paid_amount, l.balance_due, l.loan_status, COALESCE(u.username, "Sistema") AS issued_by FROM loans l LEFT JOIN users u ON l.user_id = u.user_id WHERE l.branch_id = ? AND l.loan_date >= ? AND l.loan_date <= ? AND l.record_status = 1 ORDER BY l.loan_date DESC, l.loan_id DESC');
$loanStmt->bind_param('iss', $branchId, $startDate, $endDate);
$loanStmt->execute();
$loanRes = $loanStmt->get_result();

$loans = [];
$loanPrincipal = 0.0;
$loanInterest = 0.0;
$loanTotal = 0.0;
$loanPaid = 0.0;
$loanBalance = 0.0;

while ($row = $loanRes->fetch_assoc()) {
    $p = (float)$row['principal_amount'];
    $i = (float)$row['interest_amount'];
    $t = (float)$row['total_amount'];
    $paidAmt = (float)$row['paid_amount'];
    $bal = (float)$row['balance_due'];
    $loanPrincipal += $p;
    $loanInterest += $i;
    $loanTotal += $t;
    $loanPaid += $paidAmt;
    $loanBalance += $bal;

    $loans[] = [
        'loan_id' => (int)$row['loan_id'],
        'loan_code' => $row['loan_code'],
        'loan_date' => $row['loan_date'],
        'due_date' => $row['due_date'],
        'client_name' => $row['client_name'],
        'client_contact' => $row['client_contact'],
        'principal_amount' => round($p, 2),
        'has_interest' => (bool)$row['has_interest'],
        'interest_rate' => (float)$row['interest_rate'],
        'interest_amount' => round($i, 2),
        'total_amount' => round($t, 2),
        'paid_amount' => round($paidAmt, 2),
        'balance_due' => round($bal, 2),
        'loan_status' => (int)$row['loan_status'],
        'issued_by' => $row['issued_by'],
    ];
}
$loanStmt->close();
$connect->close();

json_response([
    'success' => true,
    'range' => [
        'startDate' => $startDate,
        'endDate' => $endDate,
    ],
    'orders' => $orders,
    'summary' => [
        'count' => count($orders),
        'totalAmount' => round($totalAmount, 2),
        'totalPaid' => round($totalPaid, 2),
        'totalDue' => round($totalDue, 2),
    ],
    'loans' => $loans,
    'loanSummary' => [
        'count' => count($loans),
        'totalPrincipal' => round($loanPrincipal, 2),
        'totalInterest' => round($loanInterest, 2),
        'totalAmount' => round($loanTotal, 2),
        'totalPaid' => round($loanPaid, 2),
        'totalBalance' => round($loanBalance, 2),
    ],
]);
