<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$branchId   = get_active_branch_id($connect);
$userId     = (int)$_SESSION['userId'];
$data       = get_request_data();

$sessionId          = (int)($data['sessionId'] ?? 0);
$rawCounted         = (float)($data['countedCash'] ?? 0);
$cardReported       = round(max(0, (float)($data['cardReported'] ?? 0)), 2);
$transferReported   = round(max(0, (float)($data['transferReported'] ?? 0)), 2);
$checkReported      = round(max(0, (float)($data['checkReported'] ?? 0)), 2);
$transferToGeneralCash = !empty($data['transferToGeneralCash']);
$requestedReturn    = isset($data['returnAmount']) && $data['returnAmount'] !== '' ? round(max(0, (float)$data['returnAmount']), 2) : null;
$denominations      = isset($data['denominations']) && is_array($data['denominations']) ? $data['denominations'] : null;
$closingNotes       = sanitize_clean_text($data['closingNotes'] ?? '', 'notas de cierre', 255, false);

if ($sessionId <= 0 || $rawCounted < 0) {
    send_http_error(400, 'Datos inválidos para ejecutar el cierre de caja.');
}

$connect->begin_transaction();

try {
    $sStmt = $connect->prepare("SELECT cs.session_id, cs.register_id, cs.session_code, cs.opening_amount, cs.cash_in_total, cs.cash_out_total, cs.status, COALESCE(cr.register_name, 'Caja') AS register_name
        FROM cash_sessions cs
        LEFT JOIN cash_registers cr ON cr.register_id = cs.register_id
        WHERE cs.session_id = ? AND cs.branch_id = ?
        LIMIT 1 FOR UPDATE");
    $sStmt->bind_param('ii', $sessionId, $branchId);
    $sStmt->execute();
    $sess = $sStmt->get_result()->fetch_assoc();
    $sStmt->close();

    if (!$sess) {
        throw new InvalidArgumentException('Sesión de caja no encontrada en la sucursal activa.');
    }
    if ($sess['status'] !== 'open') {
        throw new InvalidArgumentException('Esta sesión de caja ya se encuentra cerrada.');
    }

    $countedInfo    = sb_compute_counted_from_denominations($denominations, $rawCounted);
    $countedCash    = $countedInfo['counted'];
    $denomJson      = $countedInfo['json'];

    $expectedCash   = round((float)$sess['opening_amount'] + (float)$sess['cash_in_total'] - (float)$sess['cash_out_total'], 2);
    $difference     = round($countedCash - $expectedCash, 2);

    if (abs($difference) < 0.005) {
        $difference = 0.00;
        $statusLabel = 'exact';
    } elseif ($difference > 0) {
        $statusLabel = 'surplus';
    } else {
        $statusLabel = 'shortage';
    }

    $returnAmount = 0.00;
    if ($transferToGeneralCash) {
        $returnAmount = $requestedReturn !== null ? $requestedReturn : $countedCash;
        if ($returnAmount > $countedCash + 0.0001) {
            throw new InvalidArgumentException('El monto a transferir a Caja General no puede superar el efectivo contado ($' . number_format($countedCash, 2) . ').');
        }
    }
    $retainedFloat  = round(max(0, $countedCash - $returnAmount), 2);
    $returnedFlag   = ($transferToGeneralCash && $returnAmount > 0) ? 1 : 0;

    if ($returnedFlag === 1) {
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
        $balAfter   = round($balBefore + $returnAmount, 2);

        $updAcc     = $connect->prepare('UPDATE general_cash_accounts SET current_balance = ?, updated_by = ? WHERE account_id = ?');
        $updAcc->bind_param('dii', $balAfter, $userId, $accId);
        $updAcc->execute();
        $updAcc->close();

        $gcType = 'till_close_return';
        $gcDir  = 'in';
        $gcDesc = "Entrega de cierre de {$sess['register_name']} ({$sess['session_code']})";
        $gcMov  = $connect->prepare('INSERT INTO general_cash_movements (branch_id, account_id, cash_session_id, user_id, movement_type, direction, amount, balance_before, balance_after, reference, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $gcMov->bind_param('iiiissdddss', $branchId, $accId, $sessionId, $userId, $gcType, $gcDir, $returnAmount, $balBefore, $balAfter, $sess['session_code'], $gcDesc);
        $gcMov->execute();
        $gcMov->close();
    }

    $recType = 'closing';
    $recStmt = $connect->prepare('INSERT INTO cash_reconciliations (branch_id, session_id, user_id, reconciliation_type, reconciled_at, expected_cash, counted_cash, difference_amount, card_reported, transfer_reported, check_reported, status_label, denominations_json, notes) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $recStmt->bind_param('iiisddddddsss', $branchId, $sessionId, $userId, $recType, $expectedCash, $countedCash, $difference, $cardReported, $transferReported, $checkReported, $statusLabel, $denomJson, $closingNotes);
    $recStmt->execute();
    $recStmt->close();

    $updSess = $connect->prepare("UPDATE cash_sessions SET closed_by = ?, closed_at = NOW(), expected_cash = ?, counted_cash = ?, card_reported = ?, transfer_reported = ?, check_reported = ?, difference_amount = ?, returned_to_general_cash = ?, amount_returned_general = ?, retained_float = ?, status = 'closed', closing_notes = ? WHERE session_id = ? AND branch_id = ?");
    $updSess->bind_param('iddddddiddsii', $userId, $expectedCash, $countedCash, $cardReported, $transferReported, $checkReported, $difference, $returnedFlag, $returnAmount, $retainedFloat, $closingNotes, $sessionId, $branchId);
    $updSess->execute();
    $updSess->close();

    // Fetch operations summary and breakdown by banking entity for closing report
    $opStmt = $connect->prepare("SELECT
        operation_type,
        source_bank_entity,
        destination_bank_entity,
        amount,
        commission_amount,
        total_customer_amount
    FROM branch_operations
    WHERE session_id = ? AND branch_id = ?");
    $opStmt->bind_param('ii', $sessionId, $branchId);
    $opStmt->execute();
    $opRes = $opStmt->get_result();

    $opSummary = [
        'total_count'           => 0,
        'deposit_count'         => 0,
        'deposit_amount'        => 0.0,
        'withdrawal_count'      => 0,
        'withdrawal_amount'     => 0.0,
        'commission_total'      => 0.0,
        'net_customer_flow'     => 0.0,
    ];
    $banksMap = [];

    while ($op = $opRes->fetch_assoc()) {
        $type = $op['operation_type'];
        $amt = round((float)$op['amount'], 2);
        $comm = round((float)$op['commission_amount'], 2);
        $totCust = round((float)$op['total_customer_amount'], 2);
        $sourceBank = $op['source_bank_entity'] ?: 'BCP';

        $opSummary['total_count']++;
        $opSummary['commission_total'] = round($opSummary['commission_total'] + $comm, 2);

        if ($type === 'deposit') {
            $opSummary['deposit_count']++;
            $opSummary['deposit_amount'] = round($opSummary['deposit_amount'] + $amt, 2);
            $opSummary['net_customer_flow'] = round($opSummary['net_customer_flow'] + $totCust, 2);
        } else {
            $opSummary['withdrawal_count']++;
            $opSummary['withdrawal_amount'] = round($opSummary['withdrawal_amount'] + $amt, 2);
            $opSummary['net_customer_flow'] = round($opSummary['net_customer_flow'] - $totCust, 2);
        }

        if (!isset($banksMap[$sourceBank])) {
            $banksMap[$sourceBank] = [
                'bank_entity'           => $sourceBank,
                'deposit_count'         => 0,
                'deposit_amount'        => 0.0,
                'withdrawal_count'      => 0,
                'withdrawal_amount'     => 0.0,
                'commission_total'      => 0.0,
                'net_flow'              => 0.0,
            ];
        }
        $banksMap[$sourceBank]['commission_total'] = round($banksMap[$sourceBank]['commission_total'] + $comm, 2);
        if ($type === 'deposit') {
            $banksMap[$sourceBank]['deposit_count']++;
            $banksMap[$sourceBank]['deposit_amount'] = round($banksMap[$sourceBank]['deposit_amount'] + $amt, 2);
            $banksMap[$sourceBank]['net_flow'] = round($banksMap[$sourceBank]['net_flow'] + $totCust, 2);
        } else {
            $banksMap[$sourceBank]['withdrawal_count']++;
            $banksMap[$sourceBank]['withdrawal_amount'] = round($banksMap[$sourceBank]['withdrawal_amount'] + $amt, 2);
            $banksMap[$sourceBank]['net_flow'] = round($banksMap[$sourceBank]['net_flow'] - $totCust, 2);
        }
    }
    $opStmt->close();

    $connect->commit();
    $connect->close();

    json_response([
        'success' => true,
        'messages' => "Cierre de caja {$sess['session_code']} completado exitosamente",
        'summary' => [
            'session_id' => $sessionId,
            'session_code' => $sess['session_code'],
            'register_name' => $sess['register_name'],
            'expected_cash' => $expectedCash,
            'counted_cash' => $countedCash,
            'difference_amount' => $difference,
            'status_label' => $statusLabel,
            'amount_returned_general' => $returnAmount,
            'retained_float' => $retainedFloat,
            'operations_summary' => $opSummary,
            'bank_breakdown' => array_values($banksMap),
        ],
    ]);
} catch (InvalidArgumentException $e) {
    $connect->rollback();
    $connect->close();
    send_http_error(400, $e->getMessage());
} catch (Throwable $e) {
    $connect->rollback();
    $connect->close();
    error_log('[stock-bartolo] closeCashSession error: ' . $e->getMessage());
    send_http_error(500, 'No se pudo completar el cierre de caja.');
}
