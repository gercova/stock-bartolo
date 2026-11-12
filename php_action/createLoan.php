<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$branchId = get_active_branch_id($connect);
$data = get_request_data();

$userId = (int)$_SESSION['userId'];
$clientName = sanitize_clean_text($data['clientName'] ?? '', 'nombre del cliente', 200, true);
$clientContact = sanitize_clean_text($data['clientContact'] ?? '', 'teléfono del cliente', 100, true);
$clientDocument = sanitize_clean_text($data['clientDocument'] ?? '', 'documento', 100, false);
$orderId = !empty($data['orderId']) ? (int)$data['orderId'] : null;
$loanDate = validate_iso_date($data['loanDate'] ?? date('Y-m-d'), 'fecha de emisión', true);
$dueDate = validate_iso_date($data['dueDate'] ?? '', 'fecha de vencimiento', false);
$principal = round((float)($data['principalAmount'] ?? 0), 2);
$hasInterest = !empty($data['hasInterest']) && $data['hasInterest'] !== '0' && $data['hasInterest'] !== 'false';
$interestRate = $hasInterest ? round((float)($data['interestRate'] ?? 0), 2) : 0.00;
$interestMode = ($data['interestMode'] ?? 'flat') === 'monthly' ? 'monthly' : 'flat';
$termMonths = max(1, min(360, (int)($data['termMonths'] ?? 1)));
$initialPayment = round((float)($data['initialPayment'] ?? 0), 2);
$paymentMethod = (int)($data['paymentMethod'] ?? 2);
$notes = sanitize_clean_text($data['notes'] ?? '', 'notas', 1000, false);

if ($principal <= 0) {
    send_http_error(400, 'El monto del préstamo debe ser mayor a 0.');
}

if (!in_array($paymentMethod, [1, 2, 3], true)) {
    send_http_error(400, 'Método de pago inválido.');
}

if ($hasInterest && ($interestRate <= 0 || $interestRate > 1000)) {
    send_http_error(400, 'La tasa de interés debe estar entre 0.01% y 1000%.');
}

if ($dueDate !== null && $dueDate < $loanDate) {
    send_http_error(400, 'La fecha de vencimiento no puede ser anterior a la fecha de emisión.');
}

if ($orderId !== null) {
    $ordCheck = $connect->prepare('SELECT order_id FROM orders WHERE order_id = ? AND branch_id = ? AND order_status = 1 LIMIT 1');
    $ordCheck->bind_param('ii', $orderId, $branchId);
    $ordCheck->execute();
    $ordExists = $ordCheck->get_result()->fetch_assoc();
    $ordCheck->close();
    if (!$ordExists) {
        $connect->close();
        send_http_error(400, 'La orden asociada seleccionada no pertenece a la sucursal activa.');
    }
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
    $stmt = $connect->prepare('INSERT INTO loans (branch_id, loan_code, user_id, client_name, client_contact, client_document, order_id, loan_date, due_date, principal_amount, has_interest, interest_rate, interest_mode, term_months, interest_amount, total_amount, paid_amount, balance_due, loan_status, record_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
    $stmt->bind_param(
        'isisssissdidsiddddis',
        $branchId,
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

    if ($paymentMethod === 2 && $orderId === null) {
        sb_record_pos_cash_impact(
            $connect,
            $branchId,
            $userId,
            'loan_disbursement',
            'out',
            $principal,
            2,
            'loan',
            $loanId,
            "Desembolso de préstamo {$loanCode} ({$clientName})"
        );
    }

    if ($initialPayment > 0) {
        $payNote = 'Abono inicial al emitir préstamo';
        $payStmt = $connect->prepare('INSERT INTO loan_payments (branch_id, loan_id, user_id, payment_date, amount, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $payStmt->bind_param('iiisdis', $branchId, $loanId, $userId, $loanDate, $initialPayment, $paymentMethod, $payNote);
        $payStmt->execute();
        $payId = $connect->insert_id;
        $payStmt->close();

        sb_record_pos_cash_impact(
            $connect,
            $branchId,
            $userId,
            'loan_payment_cash',
            'in',
            $initialPayment,
            $paymentMethod,
            'loan_payment',
            $payId,
            "Abono inicial préstamo {$loanCode} ({$clientName})"
        );
    }

    $connect->commit();
    $connect->close();

    json_response([
        'success' => true,
        'messages' => 'Préstamo emitido exitosamente',
        'loan_id' => $loanId,
        'loan_code' => $loanCode,
    ], 201);
} catch (InvalidArgumentException $e) {
    $connect->rollback();
    $connect->close();
    send_http_error(400, $e->getMessage());
} catch (Throwable $e) {
    $connect->rollback();
    $connect->close();
    error_log('[stock-bartolo] createLoan error: ' . $e->getMessage());
    send_http_error(500, 'No se pudo emitir el préstamo.');
}
