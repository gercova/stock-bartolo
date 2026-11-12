<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$branchId   = get_active_branch_id($connect);

// Enforce open cash register for all commercial operations across all branches
$openSession = sb_get_open_cash_session($connect, $branchId);
if (!$openSession) {
    send_http_error(400, 'Para registrar cobros o pagos de préstamos, la caja registradora de la sucursal debe estar abierta. Por favor, abre un turno de caja antes de continuar.');
}

$data       = get_request_data();

$userId         = (int)$_SESSION['userId'];
$loanId         = (int)($data['loanId'] ?? 0);
$amount         = round((float)($data['amount'] ?? 0), 2);
$paymentDate    = validate_iso_date($data['paymentDate'] ?? date('Y-m-d'), 'fecha de abono', true);
$paymentMethod  = (int)($data['paymentMethod'] ?? 2);
$notes          = sanitize_clean_text($data['notes'] ?? '', 'nota de abono', 255, false);

if ($loanId <= 0 || $amount <= 0) {
    send_http_error(400, 'El préstamo y un monto de abono mayor a 0 son obligatorios.');
}

if (!in_array($paymentMethod, [1, 2, 3], true)) {
    send_http_error(400, 'Método de pago inválido.');
}

$connect->begin_transaction();

try {
    $stmt = $connect->prepare('SELECT loan_code, client_name, total_amount, paid_amount, balance_due FROM loans WHERE loan_id = ? AND branch_id = ? AND record_status = 1 FOR UPDATE');
    $stmt->bind_param('ii', $loanId, $branchId);
    $stmt->execute();
    $loan = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$loan) {
        $connect->rollback();
        $connect->close();
        send_http_error(404, 'Préstamo no encontrado en la sucursal activa.');
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

    $payStmt = $connect->prepare('INSERT INTO loan_payments (branch_id, loan_id, user_id, payment_date, amount, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $payStmt->bind_param('iiisdis', $branchId, $loanId, $userId, $paymentDate, $amount, $paymentMethod, $notes);
    $payStmt->execute();
    $payId = $connect->insert_id;
    $payStmt->close();

    $newPaid        = round((float)$loan['paid_amount'] + $amount, 2);
    $totalAmount    = round((float)$loan['total_amount'], 2);
    $newBalance     = round(max(0, $totalAmount - $newPaid), 2);
    $newStatus      = $newBalance <= 0 ? 3 : 2;

    $updStmt = $connect->prepare('UPDATE loans SET paid_amount = ?, balance_due = ?, loan_status = ? WHERE loan_id = ? AND branch_id = ?');
    $updStmt->bind_param('ddiii', $newPaid, $newBalance, $newStatus, $loanId, $branchId);
    $updStmt->execute();
    $updStmt->close();

    sb_record_pos_cash_impact(
        $connect,
        $branchId,
        $userId,
        'loan_payment_cash',
        'in',
        $amount,
        $paymentMethod,
        'loan_payment',
        $payId,
        "Abono préstamo {$loan['loan_code']} ({$loan['client_name']})"
    );

    $connect->commit();
    $connect->close();

    json_response([
        'success'       => true,
        'messages'      => $newBalance <= 0 ? 'Préstamo liquidado exitosamente' : 'Abono registrado exitosamente',
        'paid_amount'   => $newPaid,
        'balance_due'   => $newBalance,
        'loan_status'   => $newStatus,
    ]);
} catch (InvalidArgumentException $e) {
    $connect->rollback();
    $connect->close();
    send_http_error(400, $e->getMessage());
} catch (Throwable $e) {
    $connect->rollback();
    $connect->close();
    error_log('[stock-bartolo] addLoanPayment error: ' . $e->getMessage());
    send_http_error(500, 'No se pudo registrar el abono.');
}
