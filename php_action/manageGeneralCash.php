<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT', 'DELETE']);

$userId = (int)$_SESSION['userId'];
$canSwitch = sb_user_can_switch_branches($connect, $userId);
$activeBranchId = get_active_branch_id($connect);

$data = get_request_data();
$action = sanitize_clean_text($data['action'] ?? 'add_movement', 'acción', 40, true);
$targetBranchId = !empty($data['branchId']) ? (int)$data['branchId'] : $activeBranchId;

if ($targetBranchId !== $activeBranchId && !$canSwitch) {
    $connect->close();
    send_http_error(403, 'No tienes permisos para administrar la Caja General de otra sucursal.');
}

$bStmt = $connect->prepare('SELECT branch_id, branch_name FROM branches WHERE branch_id = ? AND record_status = 1 LIMIT 1');
$bStmt->bind_param('i', $targetBranchId);
$bStmt->execute();
$branchRow = $bStmt->get_result()->fetch_assoc();
$bStmt->close();

if (!$branchRow) {
    $connect->close();
    send_http_error(404, 'Sucursal no encontrada.');
}

sb_ensure_branch_cash_defaults($connect, $targetBranchId, (string)$branchRow['branch_name'], 0.0, $userId);

if ($action === 'update_account') {
    $accountName = sanitize_clean_text($data['accountName'] ?? '', 'nombre de Caja General', 150, true);
    $minBalanceAlert = round((float)($data['minBalanceAlert'] ?? 0), 2);
    $status = (int)($data['status'] ?? 1);
    $notes = sanitize_clean_text($data['notes'] ?? '', 'notas', 255, false);

    if ($minBalanceAlert < 0 || !in_array($status, [1, 2], true)) {
        $connect->close();
        send_http_error(400, 'Parámetros inválidos para configurar la Caja General.');
    }

    $upd = $connect->prepare('UPDATE general_cash_accounts SET account_name = ?, min_balance_alert = ?, status = ?, notes = ?, updated_by = ? WHERE branch_id = ?');
    $upd->bind_param('sdisii', $accountName, $minBalanceAlert, $status, $notes, $userId, $targetBranchId);
    if (!$upd->execute()) {
        $upd->close();
        $connect->close();
        send_http_error(500, 'No se pudo actualizar la configuración de Caja General.');
    }
    $upd->close();
    $connect->close();

    json_response([
        'success' => true,
        'messages' => 'Configuración de Caja General actualizada exitosamente',
    ]);
}

if ($action === 'update_movement') {
    $movementId = (int)($data['movementId'] ?? 0);
    $reference = sanitize_clean_text($data['reference'] ?? '', 'referencia', 100, false);
    $description = sanitize_clean_text($data['description'] ?? '', 'descripción', 255, true);

    if ($movementId <= 0) {
        $connect->close();
        send_http_error(400, 'ID de movimiento inválido.');
    }

    $chk = $connect->prepare('SELECT movement_id, movement_type FROM general_cash_movements WHERE movement_id = ? AND branch_id = ? AND record_status = 1 LIMIT 1');
    $chk->bind_param('ii', $movementId, $targetBranchId);
    $chk->execute();
    $mov = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$mov) {
        $connect->close();
        send_http_error(404, 'Movimiento de Caja General no encontrado.');
    }

    if (in_array($mov['movement_type'], ['till_open_allocation', 'till_close_return'], true)) {
        $connect->close();
        send_http_error(400, 'Los movimientos generados automáticamente por apertura/cierre de caja no pueden editarse.');
    }

    $upd = $connect->prepare('UPDATE general_cash_movements SET reference = ?, description = ? WHERE movement_id = ? AND branch_id = ?');
    $upd->bind_param('ssii', $reference, $description, $movementId, $targetBranchId);
    $upd->execute();
    $upd->close();
    $connect->close();

    json_response([
        'success' => true,
        'messages' => 'Detalle de movimiento actualizado',
    ]);
}

if ($action === 'void_movement') {
    $movementId = (int)($data['movementId'] ?? 0);
    if ($movementId <= 0) {
        $connect->close();
        send_http_error(400, 'ID de movimiento inválido.');
    }

    $connect->begin_transaction();
    try {
        $accStmt = $connect->prepare('SELECT account_id, current_balance FROM general_cash_accounts WHERE branch_id = ? LIMIT 1 FOR UPDATE');
        $accStmt->bind_param('i', $targetBranchId);
        $accStmt->execute();
        $acc = $accStmt->get_result()->fetch_assoc();
        $accStmt->close();

        $mStmt = $connect->prepare('SELECT * FROM general_cash_movements WHERE movement_id = ? AND branch_id = ? AND record_status = 1 LIMIT 1 FOR UPDATE');
        $mStmt->bind_param('ii', $movementId, $targetBranchId);
        $mStmt->execute();
        $mov = $mStmt->get_result()->fetch_assoc();
        $mStmt->close();

        if (!$acc || !$mov) {
            throw new InvalidArgumentException('Movimiento o cuenta no encontrada.');
        }

        if (in_array($mov['movement_type'], ['till_open_allocation', 'till_close_return'], true)) {
            throw new InvalidArgumentException('No se pueden anular movimientos vinculados a sesiones de caja.');
        }

        $currBal = round((float)$acc['current_balance'], 2);
        $amt = round((float)$mov['amount'], 2);

        if ($mov['direction'] === 'in') {
            if ($currBal < $amt) {
                throw new InvalidArgumentException('No hay saldo suficiente en Caja General para revertir este ingreso ya utilizado.');
            }
            $newBal = round($currBal - $amt, 2);
        } else {
            $newBal = round($currBal + $amt, 2);
        }

        $updAcc = $connect->prepare('UPDATE general_cash_accounts SET current_balance = ?, updated_by = ? WHERE account_id = ?');
        $accId = (int)$acc['account_id'];
        $updAcc->bind_param('dii', $newBal, $userId, $accId);
        $updAcc->execute();
        $updAcc->close();

        $updMov = $connect->prepare('UPDATE general_cash_movements SET record_status = 2 WHERE movement_id = ?');
        $updMov->bind_param('i', $movementId);
        $updMov->execute();
        $updMov->close();

        $connect->commit();
        $connect->close();

        json_response([
            'success' => true,
            'messages' => 'Movimiento anulado y saldo recalculado',
            'current_balance' => $newBal,
        ]);
    } catch (InvalidArgumentException $e) {
        $connect->rollback();
        $connect->close();
        send_http_error(400, $e->getMessage());
    } catch (Throwable $e) {
        $connect->rollback();
        $connect->close();
        error_log('[stock-bartolo] manageGeneralCash void error: ' . $e->getMessage());
        send_http_error(500, 'No se pudo anular el movimiento.');
    }
}

if ($action === 'add_movement') {
    $movementType = sanitize_clean_text($data['movementType'] ?? '', 'tipo de movimiento', 40, true);
    $amount = round((float)($data['amount'] ?? 0), 2);
    $reference = sanitize_clean_text($data['reference'] ?? '', 'referencia', 100, false);
    $description = sanitize_clean_text($data['description'] ?? '', 'descripción', 255, true);

    $allowed = [
        'initial_capital' => 'in',
        'deposit' => 'in',
        'adjustment_in' => 'in',
        'withdrawal' => 'out',
        'adjustment_out' => 'out',
    ];

    if (!isset($allowed[$movementType]) || $amount <= 0) {
        $connect->close();
        send_http_error(400, 'Tipo de movimiento inválido o monto menor/igual a 0.');
    }

    $direction = $allowed[$movementType];

    $connect->begin_transaction();
    try {
        $accStmt = $connect->prepare('SELECT account_id, current_balance, initial_capital, status FROM general_cash_accounts WHERE branch_id = ? LIMIT 1 FOR UPDATE');
        $accStmt->bind_param('i', $targetBranchId);
        $accStmt->execute();
        $acc = $accStmt->get_result()->fetch_assoc();
        $accStmt->close();

        if (!$acc) {
            throw new InvalidArgumentException('Cuenta de Caja General no encontrada.');
        }

        if ((int)$acc['status'] !== 1) {
            throw new InvalidArgumentException('La Caja General de esta sucursal está bloqueada/inactiva.');
        }

        $balanceBefore = round((float)$acc['current_balance'], 2);
        if ($direction === 'out' && $amount > $balanceBefore + 0.0001) {
            throw new InvalidArgumentException('Saldo insuficiente en Caja General ($' . number_format($balanceBefore, 2) . ' disponible).');
        }

        $balanceAfter = $direction === 'in'
            ? round($balanceBefore + $amount, 2)
            : round($balanceBefore - $amount, 2);

        $newInitialCap = round((float)$acc['initial_capital'], 2);
        if ($movementType === 'initial_capital') {
            $newInitialCap = round($newInitialCap + $amount, 2);
        }

        $accId = (int)$acc['account_id'];
        $updAcc = $connect->prepare('UPDATE general_cash_accounts SET current_balance = ?, initial_capital = ?, updated_by = ? WHERE account_id = ?');
        $updAcc->bind_param('ddii', $balanceAfter, $newInitialCap, $userId, $accId);
        $updAcc->execute();
        $updAcc->close();

        $movStmt = $connect->prepare('INSERT INTO general_cash_movements (branch_id, account_id, user_id, movement_type, direction, amount, balance_before, balance_after, reference, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $movStmt->bind_param('iiissdddss', $targetBranchId, $accId, $userId, $movementType, $direction, $amount, $balanceBefore, $balanceAfter, $reference, $description);
        $movStmt->execute();
        $movementId = (int)$connect->insert_id;
        $movStmt->close();

        $connect->commit();
        $connect->close();

        json_response([
            'success' => true,
            'messages' => 'Movimiento de Caja General registrado exitosamente',
            'movement_id' => $movementId,
            'current_balance' => $balanceAfter,
        ], 201);
    } catch (InvalidArgumentException $e) {
        $connect->rollback();
        $connect->close();
        send_http_error(400, $e->getMessage());
    } catch (Throwable $e) {
        $connect->rollback();
        $connect->close();
        error_log('[stock-bartolo] manageGeneralCash add error: ' . $e->getMessage());
        send_http_error(500, 'No se pudo registrar el movimiento de Caja General.');
    }
}

$connect->close();
send_http_error(400, 'Acción no reconocida.');

