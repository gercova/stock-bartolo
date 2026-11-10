<?php

require_once __DIR__ . '/core.php';
require_method(['GET', 'POST']);
ensure_loan_schema($connect);

$data = array_merge($_GET, get_request_data());
$loanId = (int)($data['loanId'] ?? $data['id'] ?? 0);

if ($loanId <= 0) {
    send_http_error(400, 'ID de préstamo inválido.');
}

$stmt = $connect->prepare("SELECT l.*, COALESCE(u.username, 'Sistema') AS issued_by FROM loans l LEFT JOIN users u ON l.user_id = u.user_id WHERE l.loan_id = ? AND l.record_status = 1 LIMIT 1");
$stmt->bind_param('i', $loanId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    $connect->close();
    send_http_error(404, 'Préstamo no encontrado.');
}

$payStmt = $connect->prepare("SELECT lp.payment_id, lp.loan_id, lp.user_id, lp.payment_date, lp.amount, lp.payment_method, lp.notes, lp.created_at, COALESCE(u.username, 'Sistema') AS recorded_by FROM loan_payments lp LEFT JOIN users u ON lp.user_id = u.user_id WHERE lp.loan_id = ? ORDER BY lp.payment_date DESC, lp.payment_id DESC");
$payStmt->bind_param('i', $loanId);
$payStmt->execute();
$payRes = $payStmt->get_result();
$payments = [];
while ($p = $payRes->fetch_assoc()) {
    $payments[] = [
        'payment_id' => (int)$p['payment_id'],
        'loan_id' => (int)$p['loan_id'],
        'user_id' => (int)$p['user_id'],
        'recorded_by' => $p['recorded_by'],
        'payment_date' => $p['payment_date'],
        'amount' => round((float)$p['amount'], 2),
        'payment_method' => (int)$p['payment_method'],
        'notes' => $p['notes'] ?? '',
        'created_at' => $p['created_at'],
    ];
}
$payStmt->close();
$connect->close();

$today = date('Y-m-d');
$balance = round((float)$row['balance_due'], 2);
$rawStatus = (int)$row['loan_status'];
$isOverdue = ($rawStatus !== 3) && ($balance > 0) && !empty($row['due_date']) && ($row['due_date'] < $today);

json_response([
    'success' => true,
    'loan' => [
        'loan_id' => (int)$row['loan_id'],
        'loan_code' => $row['loan_code'],
        'user_id' => (int)$row['user_id'],
        'issued_by' => $row['issued_by'],
        'client_name' => $row['client_name'],
        'client_contact' => $row['client_contact'],
        'client_document' => $row['client_document'] ?? '',
        'order_id' => $row['order_id'] !== null ? (int)$row['order_id'] : null,
        'loan_date' => $row['loan_date'],
        'due_date' => $row['due_date'],
        'principal_amount' => round((float)$row['principal_amount'], 2),
        'has_interest' => ((int)$row['has_interest']) === 1,
        'interest_rate' => round((float)$row['interest_rate'], 2),
        'interest_mode' => $row['interest_mode'],
        'term_months' => (int)$row['term_months'],
        'interest_amount' => round((float)$row['interest_amount'], 2),
        'total_amount' => round((float)$row['total_amount'], 2),
        'paid_amount' => round((float)$row['paid_amount'], 2),
        'balance_due' => $balance,
        'loan_status' => $isOverdue ? 4 : $rawStatus,
        'is_overdue' => $isOverdue,
        'notes' => $row['notes'] ?? '',
        'created_at' => $row['created_at'],
    ],
    'payments' => $payments,
]);
