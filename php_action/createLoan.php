<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);
ensure_loan_schema($connect);

$data = get_request_data();

$userId = (int)$_SESSION['userId'];
$clientName = trim((string)($data['clientName'] ?? ''));
$clientContact = trim((string)($data['clientContact'] ?? ''));
$clientDocument = trim((string)($data['clientDocument'] ?? ''));
$orderId = !empty($data['orderId']) ? (int)$data['orderId'] : null;
$loanDateRaw = trim((string)($data['loanDate'] ?? date('Y-m-d')));
$dueDateRaw = trim((string)($data['dueDate'] ?? ''));
$principal = round((float)($data['principalAmount'] ?? 0), 2);
$hasInterest = !empty($data['hasInterest']) && $data['hasInterest'] !== '0' && $data['hasInterest'] !== 'false';
$interestRate = $hasInterest ? round((float)($data['interestRate'] ?? 0), 2) : 0.00;
$interestMode = ($data['interestMode'] ?? 'flat') === 'monthly' ? 'monthly' : 'flat';
$termMonths = max(1, (int)($data['termMonths'] ?? 1));
$initialPayment = round((float)($data['initialPayment'] ?? 0), 2);
$paymentMethod = (int)($data['paymentMethod'] ?? 2);
$notes = trim((string)($data['notes'] ?? ''));

if ($clientName === '' || $clientContact === '' || $principal <= 0) {
    send_http_error(400, 'El nombre del cliente, teléfono y monto mayor a 0 son obligatorios.');
}

if ($hasInterest && $interestRate <= 0) {
    send_http_error(400, 'Si activas interés para este préstamo, debes indicar una tasa mayor a 0%.');
}

if ($interestRate < 0 || $interestRate > 1000) {
    send_http_error(400, 'La tasa de interés debe estar entre 0% y 1000%.');
}

$loanDate = date('Y-m-d', strtotime($loanDateRaw));
$dueDate = $dueDateRaw !== '' ? date('Y-m-d', strtotime($dueDateRaw)) : null;

if ($dueDate !== null && $dueDate < $loanDate) {
    send_http_error(400, 'La fecha de vencimiento no puede ser anterior a la fecha de emisión.');
}

$interestAmount = 0.00;
if ($hasInterest) {
    if ($interestMode === 'monthly') {
        $interestAmount = round($principal * ($interestRate / 100) * $termMonths, 2);
    } else {
        $interestAmount = round($principal * ($interestRate / 100), 2);
    }
}

$totalAmount = round($principal + $interestAmount, 2);

if ($initialPayment < 0 || $initialPayment > $totalAmount) {
    send_http_error(400, 'El abono inicial no puede ser negativo ni superar el total del préstamo.');
}

$paidAmount = $initialPayment;
$balanceDue = round(max(0, $totalAmount - $paidAmount), 2);
$loanStatus = 1; // 1: Activo
if ($balanceDue <= 0) {
    $loanStatus = 3; // 3: Pagado
} elseif ($paidAmount > 0) {
    $loanStatus = 2; // 2: Parcial
}

$hasInterestInt = $hasInterest ? 1 : 0;
$tempCode = 'PREST-TMP';

$connect->begin_transaction();

try {
    $stmt = $connect->prepare('INSERT INTO loans (loan_code, user_id, client_name, client_contact, client_document, order_id, loan_date, due_date, principal_amount, has_interest, interest_rate, interest_mode, term_months, interest_amount, total_amount, paid_amount, balance_due, loan_status, record_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
    $stmt->bind_param(
        'sisssissdidsiddddis',
        $tempCode,
        $userId,
        $clientName,
        $clientContact,
        $clientDocument,
        $orderId,
        $loanDate,
        $dueDate,
        $principal,
        $hasInterestInt,
        $interestRate,
        $interestMode,
        $termMonths,
        $interestAmount,
        $totalAmount,
        $paidAmount,
        $balanceDue,
        $loanStatus,
        $notes
    );
    $stmt->execute();
    $loanId = $connect->insert_id;
    $stmt->close();

    $loanCode = sprintf('PREST-%04d', $loanId);
    $codeStmt = $connect->prepare('UPDATE loans SET loan_code = ? WHERE loan_id = ?');
    $codeStmt->bind_param('si', $loanCode, $loanId);
    $codeStmt->execute();
    $codeStmt->close();

    if ($initialPayment > 0) {
        $payNote = 'Abono inicial al emitir préstamo';
        $payStmt = $connect->prepare('INSERT INTO loan_payments (loan_id, user_id, payment_date, amount, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)');
        $payStmt->bind_param('iisdis', $loanId, $userId, $loanDate, $initialPayment, $paymentMethod, $payNote);
        $payStmt->execute();
        $payStmt->close();
    }

    $connect->commit();
    $connect->close();

    json_response([
        'success' => true,
        'messages' => 'Préstamo emitido exitosamente',
        'loan_id' => $loanId,
        'loan_code' => $loanCode,
    ], 201);
} catch (Throwable $e) {
    $connect->rollback();
    $connect->close();
    send_http_error(500, 'No se pudo emitir el préstamo: ' . $e->getMessage());
}
