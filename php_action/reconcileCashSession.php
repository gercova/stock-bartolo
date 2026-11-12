<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$branchId = get_active_branch_id($connect);
$userId = (int)$_SESSION['userId'];
$data = get_request_data();

$sessionId = (int)($data['sessionId'] ?? 0);
$rawCounted = (float)($data['countedCash'] ?? 0);
$cardReported = round(max(0, (float)($data['cardReported'] ?? 0)), 2);
$transferReported = round(max(0, (float)($data['transferReported'] ?? 0)), 2);
$checkReported = round(max(0, (float)($data['checkReported'] ?? 0)), 2);
$denominations = isset($data['denominations']) && is_array($data['denominations']) ? $data['denominations'] : null;
$notes = sanitize_clean_text($data['notes'] ?? '', 'notas de arqueo', 255, false);

if ($sessionId <= 0 || $rawCounted < 0) {
    send_http_error(400, 'Datos inválidos para ejecutar el arqueo de caja.');
}

$connect->begin_transaction();

try {
    $sStmt = $connect->prepare("SELECT session_id, session_code, opening_amount, cash_in_total, cash_out_total, status FROM cash_sessions WHERE session_id = ? AND branch_id = ? LIMIT 1 FOR UPDATE");
    $sStmt->bind_param('ii', $sessionId, $branchId);
    $sStmt->execute();
    $sess = $sStmt->get_result()->fetch_assoc();
    $sStmt->close();

    if (!$sess) {
        throw new InvalidArgumentException('Sesión de caja no encontrada en la sucursal activa.');
    }
    if ($sess['status'] !== 'open') {
        throw new InvalidArgumentException('Solo se puede realizar arqueo sobre una caja abierta.');
    }

    $countedInfo = sb_compute_counted_from_denominations($denominations, $rawCounted);
    $countedCash = $countedInfo['counted'];
    $denomJson = $countedInfo['json'];

    $expectedCash = round((float)$sess['opening_amount'] + (float)$sess['cash_in_total'] - (float)$sess['cash_out_total'], 2);
    $difference = round($countedCash - $expectedCash, 2);

    if (abs($difference) < 0.005) {
        $difference = 0.00;
        $statusLabel = 'exact';
    } elseif ($difference > 0) {
        $statusLabel = 'surplus';
    } else {
        $statusLabel = 'shortage';
    }

    $recType = 'partial';

    $recStmt = $connect->prepare('INSERT INTO cash_reconciliations (branch_id, session_id, user_id, reconciliation_type, reconciled_at, expected_cash, counted_cash, difference_amount, card_reported, transfer_reported, check_reported, status_label, denominations_json, notes) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $recStmt->bind_param('iiisddddddsss', $branchId, $sessionId, $userId, $recType, $expectedCash, $countedCash, $difference, $cardReported, $transferReported, $checkReported, $statusLabel, $denomJson, $notes);
    $recStmt->execute();
    $recId = (int)$connect->insert_id;
    $recStmt->close();

    $updSess = $connect->prepare('UPDATE cash_sessions SET expected_cash = ?, counted_cash = ?, card_reported = ?, transfer_reported = ?, check_reported = ?, difference_amount = ? WHERE session_id = ?');
    $updSess->bind_param('ddddddi', $expectedCash, $countedCash, $cardReported, $transferReported, $checkReported, $difference, $sessionId);
    $updSess->execute();
    $updSess->close();

    $connect->commit();
    $connect->close();

    json_response([
        'success' => true,
        'messages' => 'Arqueo de caja registrado exitosamente',
        'reconciliation' => [
            'reconciliation_id' => $recId,
            'expected_cash' => $expectedCash,
            'counted_cash' => $countedCash,
            'difference_amount' => $difference,
            'status_label' => $statusLabel,
        ],
    ], 201);
} catch (InvalidArgumentException $e) {
    $connect->rollback();
    $connect->close();
    send_http_error(400, $e->getMessage());
} catch (Throwable $e) {
    $connect->rollback();
    $connect->close();
    error_log('[stock-bartolo] reconcileCashSession error: ' . $e->getMessage());
    send_http_error(500, 'No se pudo registrar el arqueo de caja.');
}
