<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);
ensure_loan_schema($connect);

$data = get_request_data();

$userId = (int)$_SESSION['userId'];
$loanId = (int)($data['loanId'] ?? 0);
$amount = round((float)($data['amount'] ?? 0), 2);
$paymentDateRaw = trim((string)($data['paymentDate'] ?? date('Y-m-d')));
$paymentMethod = (int)($data['paymentMethod'] ?? 2);
$notes = trim((string)($data['notes'] ?? ''));

if ($loanId <= 0 || $amount <= 0) {
    send_http_error(400, 'El préstamo y un monto de abono mayor a 0 son obligatorios.');
}

$paymentDate = date('Y-m-d', strtotime($paymentDateRaw));

$connect->begin_transaction();

try {
    $stmt = $connect->prepare('SELECT total_amount, paid_amount, balance_due FROM loans WHERE loan_id = ? AND record_status = 1 FOR UPDATE');
    $stmt->bind_param('i', $loanId);
    $stmt->execute();
    $loan = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$loan) {
        $connect->rollback();
        $connect->close();
        send_http_error(404, 'Préstamo no encontrado.');
    }

    $balance = round((float)$loan['balance_due'], 2);
    if ($balance <= 0) {
        $connect->rollback();
        $connect->close();
        send_http_error(400, 'Este préstamo ya está pagado en su totalidad.');
    }

    if ($amount > $balance + 0.001) {
        $connect->rollback();
        $connect->close();
        send_http_error(400, 'El abono no puede superar el saldo pendiente ($' . number_format($balance, 2) . ').');
    }

    $payStmt = $connect->prepare('INSERT INTO loan_payments (loan_id, user_id, payment_date, amount, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)');
    $payStmt->bind_param('iisdis', $loanId, $userId, $paymentDate, $amount, $paymentMethod, $notes);
    $payStmt->execute();
    $payStmt->close();

    $newPaid = round((float)$loan['paid_amount'] + $amount, 2);
    $totalAmount = round((float)$loan['total_amount'], 2);
    $newBalance = round(max(0, $totalAmount - $newPaid), 2);
    $newStatus = $newBalance <= 0 ? 3 : 2;

    $updStmt = $connect->prepare('UPDATE loans SET paid_amount = ?, balance_due = ?, loan_status = ? WHERE loan_id = ?');
    $updStmt->bind_param('ddii', $newPaid, $newBalance, $newStatus, $loanId);
    $updStmt->execute();
    $updStmt->close();

    $connect->commit();
    $connect->close();

    json_response([
        'success' => true,
        'messages' => $newBalance <= 0 ? 'Préstamo liquidado exitosamente' : 'Abono registrado exitosamente',
        'paid_amount' => $newPaid,
        'balance_due' => $newBalance,
        'loan_status' => $newStatus,
    ]);
} catch (Throwable $e) {
    $connect->rollback();
    $connect->close();
    send_http_error(500, 'No se pudo registrar el abono: ' . $e->getMessage());
}
