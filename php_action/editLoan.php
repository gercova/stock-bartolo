<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);
ensure_app_schema($connect);

$data = get_request_data();

$loanId = (int)($data['loanId'] ?? 0);
$clientName = sanitize_clean_text($data['clientName'] ?? '', 'nombre del cliente', 200, true);
$clientContact = sanitize_clean_text($data['clientContact'] ?? '', 'teléfono del cliente', 100, true);
$clientDocument = sanitize_clean_text($data['clientDocument'] ?? '', 'documento', 100, false);
$orderId = !empty($data['orderId']) ? (int)$data['orderId'] : null;
$loanDate = validate_iso_date($data['loanDate'] ?? '', 'fecha de emisión', true);
$dueDate = validate_iso_date($data['dueDate'] ?? '', 'fecha de vencimiento', false);
$principal = round((float)($data['principalAmount'] ?? 0), 2);
$hasInterest = !empty($data['hasInterest']) && $data['hasInterest'] !== '0' && $data['hasInterest'] !== 'false';
$interestRate = $hasInterest ? round((float)($data['interestRate'] ?? 0), 2) : 0.00;
$interestMode = ($data['interestMode'] ?? 'flat') === 'monthly' ? 'monthly' : 'flat';
$termMonths = max(1, min(360, (int)($data['termMonths'] ?? 1)));
$notes = sanitize_clean_text($data['notes'] ?? '', 'notas', 1000, false);

if ($loanId <= 0 || $principal <= 0) {
    send_http_error(400, 'Completa todos los campos obligatorios del préstamo.');
}

if ($hasInterest && ($interestRate <= 0 || $interestRate > 1000)) {
    send_http_error(400, 'Si el préstamo incluye interés, indica una tasa entre 0.01% y 1000%.');
}

if ($dueDate !== null && $dueDate < $loanDate) {
    send_http_error(400, 'La fecha de vencimiento no puede ser anterior a la fecha de emisión.');
}

if ($orderId !== null) {
    $ordCheck = $connect->prepare('SELECT order_id FROM orders WHERE order_id = ? AND order_status = 1 LIMIT 1');
    $ordCheck->bind_param('i', $orderId);
    $ordCheck->execute();
    $ordExists = $ordCheck->get_result()->fetch_assoc();
    $ordCheck->close();
    if (!$ordExists) {
        $connect->close();
        send_http_error(400, 'La orden asociada seleccionada no existe.');
    }
}

$currStmt = $connect->prepare('SELECT paid_amount FROM loans WHERE loan_id = ? AND record_status = 1 LIMIT 1');
$currStmt->bind_param('i', $loanId);
$currStmt->execute();
$currRow = $currStmt->get_result()->fetch_assoc();
$currStmt->close();

if (!$currRow) {
    $connect->close();
    send_http_error(404, 'Préstamo no encontrado.');
}

$paidAmount = round((float)$currRow['paid_amount'], 2);

$interestAmount = 0.00;
if ($hasInterest) {
    if ($interestMode === 'monthly') {
        $interestAmount = round($principal * ($interestRate / 100) * $termMonths, 2);
    } else {
        $interestAmount = round($principal * ($interestRate / 100), 2);
    }
}

$totalAmount = round($principal + $interestAmount, 2);
if ($totalAmount < $paidAmount) {
    $connect->close();
    send_http_error(400, 'El total del préstamo (' . number_format($totalAmount, 2) . ') no puede ser menor al monto ya abonado (' . number_format($paidAmount, 2) . ').');
}

$balanceDue = round(max(0, $totalAmount - $paidAmount), 2);
$loanStatus = 1;
if ($balanceDue <= 0) {
    $loanStatus = 3;
} elseif ($paidAmount > 0) {
    $loanStatus = 2;
}

$hasInterestInt = $hasInterest ? 1 : 0;

$stmt = $connect->prepare('UPDATE loans SET client_name = ?, client_contact = ?, client_document = ?, order_id = ?, loan_date = ?, due_date = ?, principal_amount = ?, has_interest = ?, interest_rate = ?, interest_mode = ?, term_months = ?, interest_amount = ?, total_amount = ?, balance_due = ?, loan_status = ?, notes = ? WHERE loan_id = ? AND record_status = 1');
$stmt->bind_param(
    'sssissdidsidddisi',
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
    $balanceDue,
    $loanStatus,
    $notes,
    $loanId
);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'Error al actualizar el préstamo.');
}

$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Préstamo actualizado exitosamente',
]);
