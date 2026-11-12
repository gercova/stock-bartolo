<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$branchId = get_active_branch_id($connect);
$activeBranch = get_active_branch($connect);
$userId = (int)$_SESSION['userId'];
$data = get_request_data();

$registerId = (int)($data['registerId'] ?? 0);
$openingAmount = round((float)($data['openingAmount'] ?? 0), 2);
$fundFromGeneralCash = !empty($data['fundFromGeneralCash']);
$openingNotes = sanitize_clean_text($data['openingNotes'] ?? '', 'notas de apertura', 255, false);

if ($registerId <= 0 || $openingAmount < 0) {
    send_http_error(400, 'Selecciona una caja válida e indica un monto de apertura mayor o igual a $0.00.');
}

sb_ensure_branch_cash_defaults($connect, $branchId, $activeBranch['branch_name'], 0.0, $userId);

$connect->begin_transaction();

try {
    $regStmt = $connect->prepare('SELECT register_id, register_code, register_name, active FROM cash_registers WHERE register_id = ? AND branch_id = ? AND record_status = 1 LIMIT 1 FOR UPDATE');
    $regStmt->bind_param('ii', $registerId, $branchId);
    $regStmt->execute();
    $reg = $regStmt->get_result()->fetch_assoc();
    $regStmt->close();

    if (!$reg) {
        throw new InvalidArgumentException('La caja registradora seleccionada no pertenece a la sucursal activa.');
    }
    if ((int)$reg['active'] !== 1) {
        throw new InvalidArgumentException('La caja registradora seleccionada está inactiva.');
    }

    $openChk = $connect->prepare("SELECT session_id FROM cash_sessions WHERE register_id = ? AND branch_id = ? AND status = 'open' LIMIT 1 FOR UPDATE");
    $openChk->bind_param('ii', $registerId, $branchId);
    $openChk->execute();
    $alreadyOpen = $openChk->get_result()->fetch_assoc();
    $openChk->close();

    if ($alreadyOpen) {
        throw new InvalidArgumentException("La caja {$reg['register_name']} ya tiene un turno abierto.");
    }

    $accRow = null;
    $balanceBefore = 0.00;
    if ($fundFromGeneralCash && $openingAmount > 0) {
        $accStmt = $connect->prepare('SELECT account_id, current_balance, status FROM general_cash_accounts WHERE branch_id = ? LIMIT 1 FOR UPDATE');
        $accStmt->bind_param('i', $branchId);
        $accStmt->execute();
        $accRow = $accStmt->get_result()->fetch_assoc();
        $accStmt->close();

        if (!$accRow || (int)$accRow['status'] !== 1) {
            throw new InvalidArgumentException('La Caja General de esta sucursal no está disponible para fondear aperturas.');
        }
        $balanceBefore = round((float)$accRow['current_balance'], 2);
        if ($openingAmount > $balanceBefore + 0.0001) {
            throw new InvalidArgumentException('Saldo insuficiente en Caja General ($' . number_format($balanceBefore, 2) . ' disponible) para asignar $' . number_format($openingAmount, 2) . '.');
        }
    }

    $tempCode = 'TURN-TMP';
    $fundedInt = ($fundFromGeneralCash && $openingAmount > 0) ? 1 : 0;

    $insSess = $connect->prepare("INSERT INTO cash_sessions (branch_id, register_id, session_code, opened_by, opened_at, opening_amount, funded_from_general_cash, cash_in_total, cash_out_total, non_cash_sales_total, expected_cash, status, opening_notes) VALUES (?, ?, ?, ?, NOW(), ?, ?, 0.00, 0.00, 0.00, ?, 'open', ?)");
    $insSess->bind_param('iisidids', $branchId, $registerId, $tempCode, $userId, $openingAmount, $fundedInt, $openingAmount, $openingNotes);
    $insSess->execute();
    $sessionId = (int)$connect->insert_id;
    $insSess->close();

    $sessionCode = sprintf('TURN-%04d', $sessionId);
    $updCode = $connect->prepare('UPDATE cash_sessions SET session_code = ? WHERE session_id = ?');
    $updCode->bind_param('si', $sessionCode, $sessionId);
    $updCode->execute();
    $updCode->close();

    if ($fundedInt === 1 && $accRow) {
        $accId = (int)$accRow['account_id'];
        $balanceAfter = round($balanceBefore - $openingAmount, 2);

        $updAcc = $connect->prepare('UPDATE general_cash_accounts SET current_balance = ?, updated_by = ? WHERE account_id = ?');
        $updAcc->bind_param('dii', $balanceAfter, $userId, $accId);
        $updAcc->execute();
        $updAcc->close();

        $movType = 'till_open_allocation';
        $dir = 'out';
        $desc = "Fondo inicial asignado a {$reg['register_name']} ({$sessionCode})";
        $movStmt = $connect->prepare('INSERT INTO general_cash_movements (branch_id, account_id, cash_session_id, user_id, movement_type, direction, amount, balance_before, balance_after, reference, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $movStmt->bind_param('iiiissdddss', $branchId, $accId, $sessionId, $userId, $movType, $dir, $openingAmount, $balanceBefore, $balanceAfter, $sessionCode, $desc);
        $movStmt->execute();
        $movStmt->close();
    }

    $connect->commit();
    $connect->close();

    json_response([
        'success' => true,
        'messages' => "Caja {$reg['register_name']} abierta exitosamente ({$sessionCode})",
        'session_id' => $sessionId,
        'session_code' => $sessionCode,
    ], 201);
} catch (InvalidArgumentException $e) {
    $connect->rollback();
    $connect->close();
    send_http_error(400, $e->getMessage());
} catch (Throwable $e) {
    $connect->rollback();
    $connect->close();
    error_log('[stock-bartolo] openCashSession error: ' . $e->getMessage());
    send_http_error(500, 'No se pudo abrir el turno de caja.');
}
