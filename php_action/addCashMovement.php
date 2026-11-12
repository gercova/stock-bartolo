<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$branchId   = get_active_branch_id($connect);
$userId     = (int)$_SESSION['userId'];
$data       = get_request_data();

$sessionId      = (int)($data['sessionId'] ?? 0);
$movementType   = sanitize_clean_text($data['movementType'] ?? '', 'tipo de movimiento', 40, true);
$amount         = round((float)($data['amount'] ?? 0), 2);
$description    = sanitize_clean_text($data['description'] ?? '', 'descripción', 255, true);

$allowedTypes   = [
    'manual_in'     => 'in',
    'manual_out'    => 'out',
    'expense'       => 'out',
    'sweep_to_general' => 'out',
];

if ($sessionId <= 0 || !isset($allowedTypes[$movementType]) || $amount <= 0) {
    send_http_error(400, 'Datos inválidos para el movimiento de caja.');
}

$direction = $allowedTypes[$movementType];

$connect->begin_transaction();

try {
    $sStmt = $connect->prepare("SELECT session_id, session_code, opening_amount, cash_in_total, cash_out_total, expected_cash, status FROM cash_sessions WHERE session_id = ? AND branch_id = ? LIMIT 1 FOR UPDATE");
    $sStmt->bind_param('ii', $sessionId, $branchId);
    $sStmt->execute();
    $sess = $sStmt->get_result()->fetch_assoc();
    $sStmt->close();

    if (!$sess) {
        throw new InvalidArgumentException('Sesión de caja no encontrada en la sucursal activa.');
    }
    if ($sess['status'] !== 'open') {
        throw new InvalidArgumentException('No puedes registrar movimientos en una caja cerrada.');
    }

    $expectedNow = round((float)$sess['opening_amount'] + (float)$sess['cash_in_total'] - (float)$sess['cash_out_total'], 2);
    if ($direction === 'out' && $amount > $expectedNow + 0.0001) {
        throw new InvalidArgumentException('El efectivo esperado en gaveta ($' . number_format($expectedNow, 2) . ') es menor al retiro solicitado.');
    }

    $paymentMethod  = 2; // Cash
    $refType        = $movementType === 'sweep_to_general' ? 'general_cash' : 'manual';
    $refId          = null;

    if ($movementType === 'sweep_to_general') {
        $accStmt = $connect->prepare('SELECT account_id, current_balance, status FROM general_cash_accounts WHERE branch_id = ? LIMIT 1 FOR UPDATE');
        $accStmt->bind_param('i', $branchId);
        $accStmt->execute();
        $acc = $accStmt->get_result()->fetch_assoc();
        $accStmt->close();

        if (!$acc || (int)$acc['status'] !== 1) {
            throw new InvalidArgumentException('La Caja General de esta sucursal está inactiva.');
        }

        $accId      = (int)$acc['account_id'];
        $balBefore  = round((float)$acc['current_balance'], 2);
        $balAfter   = round($balBefore + $amount, 2);

        $updAcc     = $connect->prepare('UPDATE general_cash_accounts SET current_balance = ?, updated_by = ? WHERE account_id = ?');
        $updAcc->bind_param('dii', $balAfter, $userId, $accId);
        $updAcc->execute();
        $updAcc->close();

        $gcType = 'till_close_return';
        $gcDir  = 'in';
        $gcDesc = "Retiro parcial desde caja {$sess['session_code']}: {$description}";
        $gcMov  = $connect->prepare('INSERT INTO general_cash_movements (branch_id, account_id, cash_session_id, user_id, movement_type, direction, amount, balance_before, balance_after, reference, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $gcMov->bind_param('iiiissdddss', $branchId, $accId, $sessionId, $userId, $gcType, $gcDir, $amount, $balBefore, $balAfter, $sess['session_code'], $gcDesc);
        $gcMov->execute();
        $refId  = (int)$connect->insert_id;
        $gcMov->close();
    }

    $insMov = $connect->prepare('INSERT INTO cash_movements (branch_id, session_id, user_id, movement_type, direction, amount, payment_method, reference_type, reference_id, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insMov->bind_param('iiissdisis', $branchId, $sessionId, $userId, $movementType, $direction, $amount, $paymentMethod, $refType, $refId, $description);
    $insMov->execute();
    $movId = (int)$connect->insert_id;
    $insMov->close();

    $cashIn     = round((float)$sess['cash_in_total'] + ($direction === 'in' ? $amount : 0), 2);
    $cashOut    = round((float)$sess['cash_out_total'] + ($direction === 'out' ? $amount : 0), 2);
    $newExpected = round((float)$sess['opening_amount'] + $cashIn - $cashOut, 2);

    $updSess = $connect->prepare('UPDATE cash_sessions SET cash_in_total = ?, cash_out_total = ?, expected_cash = ? WHERE session_id = ?');
    $updSess->bind_param('dddi', $cashIn, $cashOut, $newExpected, $sessionId);
    $updSess->execute();
    $updSess->close();

    $connect->commit();
    $connect->close();

    json_response([
        'success'       => true,
        'messages'      => 'Movimiento de caja registrado exitosamente',
        'movement_id'   => $movId,
        'expected_cash' => $newExpected,
    ], 201);
} catch (InvalidArgumentException $e) {
    $connect->rollback();
    $connect->close();
    send_http_error(400, $e->getMessage());
} catch (Throwable $e) {
    $connect->rollback();
    $connect->close();
    error_log('[stock-bartolo] addCashMovement error: ' . $e->getMessage());
    send_http_error(500, 'No se pudo registrar el movimiento de caja.');
}

