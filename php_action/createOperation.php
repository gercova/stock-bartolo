<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

sb_assert_permission($connect, 'operations', 'create');

$data = get_request_data();
$branchId = get_active_branch_id($connect);
$userId = (int)($_SESSION['userId'] ?? 1);
$clientId = (int)($data['client_id'] ?? $data['clientId'] ?? 0);
$opType = strtolower(trim((string)($data['operation_type'] ?? $data['operationType'] ?? '')));
$amount = round(max(0.0, (float)($data['amount'] ?? 0.0)), 2);
$commissionIncluded = !empty($data['commission_included']) || !empty($data['commissionIncluded']);
$notes = trim((string)($data['notes'] ?? ''));

if ($clientId <= 0) {
    send_http_error(400, 'Debes seleccionar un cliente válido para la operación.');
}

if (!in_array($opType, ['deposit', 'withdrawal'], true)) {
    send_http_error(400, "El tipo de operación debe ser 'deposit' (Depósito) o 'withdrawal' (Retiro).");
}

if ($amount <= 0.0) {
    send_http_error(400, 'El monto de la operación debe ser mayor a cero.');
}

// Fetch client
$cStmt = $connect->prepare('SELECT client_id, name, document_type, document_number, phone, points_balance, lifetime_points FROM clients WHERE client_id = ? AND record_status = 1 LIMIT 1');
$cStmt->bind_param('i', $clientId);
$cStmt->execute();
$client = $cStmt->get_result()->fetch_assoc();
$cStmt->close();

if (!$client) {
    send_http_error(404, 'El cliente seleccionado no existe o ha sido eliminado.');
}

// Fetch system settings
$sysConfig = sb_get_system_config($connect);
$pointsRatioSoles = max(1.0, (float)($sysConfig['points_ratio_soles'] ?? 10.0));
$pointsRatioPoints = max(1, (int)($sysConfig['points_ratio_points'] ?? 1));
$commissionRatePer100 = max(0.1, (float)($sysConfig['commission_rate_per_100'] ?? 1.0));

// Calculate commission: 1 sol per each 100 soles (rate = commissionRatePer100 / 100)
$commissionAmount = round(($amount / 100.0) * $commissionRatePer100, 2);

$netAmount = 0.0;
$totalCustomerAmount = 0.0;
$cashDrawerDelta = 0.0;

if ($opType === 'deposit') {
    if ($commissionIncluded) {
        $netAmount = round($amount - $commissionAmount, 2);
        if ($netAmount <= 0) {
            send_http_error(400, 'El monto es insuficiente para cubrir la comisión.');
        }
        $totalCustomerAmount = $amount;
        $cashDrawerDelta = $amount;
    } else {
        $netAmount = $amount;
        $totalCustomerAmount = round($amount + $commissionAmount, 2);
        $cashDrawerDelta = $totalCustomerAmount;
    }
} else {
    // Withdrawal (Retiro)
    if ($commissionIncluded) {
        $netAmount = round($amount - $commissionAmount, 2);
        if ($netAmount <= 0) {
            send_http_error(400, 'El monto solicitado es insuficiente para descontar la comisión.');
        }
        $totalCustomerAmount = $netAmount;
        $cashDrawerDelta = -$netAmount;
    } else {
        $netAmount = $amount;
        $totalCustomerAmount = $amount;
        // Customer takes $amount and pays $commissionAmount
        $cashDrawerDelta = -round($amount - $commissionAmount, 2);
    }
}

// Start database transaction
$connect->begin_transaction();

try {
    // Lock branch central cash account
    $accStmt = $connect->prepare('SELECT account_id, current_balance, min_balance_alert FROM general_cash_accounts WHERE branch_id = ? LIMIT 1 FOR UPDATE');
    $accStmt->bind_param('i', $branchId);
    $accStmt->execute();
    $cashAcc = $accStmt->get_result()->fetch_assoc();
    $accStmt->close();

    if (!$cashAcc) {
        throw new RuntimeException('No se encontró la cuenta de Caja General para esta sucursal.');
    }

    $accountId = (int)$cashAcc['account_id'];
    $balanceBefore = (float)$cashAcc['current_balance'];

    // Verify sufficient balance for withdrawal
    if ($opType === 'withdrawal') {
        $cashToDispense = $commissionIncluded ? $netAmount : $amount;
        if ($balanceBefore < $cashToDispense) {
            throw new RuntimeException("Saldo insuficiente en la Caja General de la sucursal. Saldo disponible: S/ " . number_format($balanceBefore, 2) . ", requerido para retiro: S/ " . number_format($cashToDispense, 2));
        }
    }

    $balanceAfter = round($balanceBefore + $cashDrawerDelta, 2);
    if ($balanceAfter < 0) {
        throw new RuntimeException("La operación generaría un saldo negativo en la Caja General de la sucursal.");
    }

    // Update central cash balance
    $updAcc = $connect->prepare('UPDATE general_cash_accounts SET current_balance = ?, updated_by = ?, updated_at = NOW() WHERE account_id = ?');
    $updAcc->bind_param('dii', $balanceAfter, $userId, $accountId);
    $updAcc->execute();
    $updAcc->close();

    // Generate Operation Code
    $codeStmt = $connect->prepare('SELECT COUNT(*) as c FROM branch_operations WHERE branch_id = ?');
    $codeStmt->bind_param('i', $branchId);
    $codeStmt->execute();
    $opSeq = ((int)($codeStmt->get_result()->fetch_assoc()['c'] ?? 0)) + 1;
    $codeStmt->close();
    $opCode = sprintf('OP-%03d-%06d', $branchId, $opSeq);

    // Calculate loyalty points earned
    $pointsEarned = (int)(floor($amount / $pointsRatioSoles) * $pointsRatioPoints);

    // Insert into branch_operations
    $includedFlag = $commissionIncluded ? 1 : 0;
    $insOp = $connect->prepare('INSERT INTO branch_operations (
        branch_id, operation_code, client_id, user_id, operation_type,
        amount, commission_amount, commission_included, net_amount,
        total_customer_amount, points_earned, central_balance_before,
        central_balance_after, status, notes, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "completed", ?, NOW(), NOW())');

    $insOp->bind_param(
        'isisssdididdds',
        $branchId,
        $opCode,
        $clientId,
        $userId,
        $opType,
        $amount,
        $commissionAmount,
        $includedFlag,
        $netAmount,
        $totalCustomerAmount,
        $pointsEarned,
        $balanceBefore,
        $balanceAfter,
        $notes
    );

    if (!$insOp->execute()) {
        throw new RuntimeException('Error al registrar la operación en la base de datos: ' . $insOp->error);
    }

    $newOpId = (int)$insOp->insert_id;
    $insOp->close();

    // Log movement in general_cash_movements
    $movDir = $cashDrawerDelta >= 0 ? 'in' : 'out';
    $movType = $opType === 'deposit' ? 'deposit_operation' : 'withdrawal_operation';
    $movDesc = ($opType === 'deposit' ? 'Depósito de efectivo' : 'Retiro de efectivo') . " - Cliente: {$client['name']} ({$client['document_type']} {$client['document_number']})";
    $absDelta = abs($cashDrawerDelta);

    $movStmt = $connect->prepare('INSERT INTO general_cash_movements (
        branch_id, account_id, user_id, movement_type, direction,
        amount, balance_before, balance_after, reference, description
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $movStmt->bind_param(
        'iiissdddss',
        $branchId,
        $accountId,
        $userId,
        $movType,
        $movDir,
        $absDelta,
        $balanceBefore,
        $balanceAfter,
        $opCode,
        $movDesc
    );
    $movStmt->execute();
    $movStmt->close();

    // Update Client Loyalty Points
    $clientPointsBefore = (int)$client['points_balance'];
    $clientLifetimeBefore = (int)$client['lifetime_points'];
    $clientPointsAfter = $clientPointsBefore + $pointsEarned;
    $clientLifetimeAfter = $clientLifetimeBefore + $pointsEarned;

    $updClient = $connect->prepare('UPDATE clients SET points_balance = ?, lifetime_points = ?, updated_at = NOW() WHERE client_id = ?');
    $updClient->bind_param('iii', $clientPointsAfter, $clientLifetimeAfter, $clientId);
    $updClient->execute();
    $updClient->close();

    // Insert Point History
    if ($pointsEarned > 0) {
        $ptDesc = "Puntos acumulados por " . ($opType === 'deposit' ? 'depósito' : 'retiro') . " de S/ " . number_format($amount, 2) . " ({$opCode})";
        $ptType = 'earned';
        $ptHist = $connect->prepare('INSERT INTO client_points_history (
            client_id, operation_id, type, points, lifetime_after, balance_after, description, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        $ptHist->bind_param('iisiiis', $clientId, $newOpId, $ptType, $pointsEarned, $clientLifetimeAfter, $clientPointsAfter, $ptDesc);
        $ptHist->execute();
        $ptHist->close();
    }

    // Audit Log
    sb_log_audit(
        $connect,
        'operations',
        'CREATE',
        "Operación {$opCode} ({$opType}) de S/ " . number_format($amount, 2) . " - Cliente '{$client['name']}'",
        [
            'operation_id' => $newOpId,
            'operation_code' => $opCode,
            'operation_type' => $opType,
            'amount' => $amount,
            'commission_amount' => $commissionAmount,
            'commission_included' => $commissionIncluded,
            'net_amount' => $netAmount,
            'total_customer_amount' => $totalCustomerAmount,
            'points_earned' => $pointsEarned,
            'client_id' => $clientId,
            'client_name' => $client['name'],
            'central_balance_after' => $balanceAfter,
        ],
        $branchId,
        $userId
    );

    $connect->commit();

    // Fetch branch info for receipt printing
    $bInfoStmt = $connect->prepare('SELECT * FROM branches WHERE branch_id = ? LIMIT 1');
    $bInfoStmt->bind_param('i', $branchId);
    $bInfoStmt->execute();
    $branchInfo = $bInfoStmt->get_result()->fetch_assoc();
    $bInfoStmt->close();

    $receiptData = [
        'operation_id' => $newOpId,
        'operation_code' => $opCode,
        'operation_type' => $opType,
        'operation_type_label' => $opType === 'deposit' ? 'DEPÓSITO' : 'RETIRO',
        'amount' => $amount,
        'commission_amount' => $commissionAmount,
        'commission_included' => $commissionIncluded,
        'net_amount' => $netAmount,
        'total_customer_amount' => $totalCustomerAmount,
        'points_earned' => $pointsEarned,
        'client_new_points' => $clientPointsAfter,
        'client_lifetime_points' => $clientLifetimeAfter,
        'client' => [
            'client_id' => $clientId,
            'name' => $client['name'],
            'document_type' => $client['document_type'],
            'document_number' => $client['document_number'],
            'phone' => $client['phone'],
        ],
        'branch' => [
            'branch_id' => $branchId,
            'branch_name' => $branchInfo['branch_name'] ?? 'Sucursal',
            'branch_code' => $branchInfo['branch_code'] ?? 'SUC-001',
            'address' => $branchInfo['address'] ?? '',
            'phone' => $branchInfo['phone'] ?? '',
        ],
        'company' => [
            'name' => $sysConfig['company_name'] ?? 'Stock Bartolo',
            'tax_id' => $sysConfig['company_tax_id'] ?? '',
            'address' => $sysConfig['company_address'] ?? '',
            'phone' => $sysConfig['company_phone'] ?? '',
            'receipt_footer' => $sysConfig['receipt_footer'] ?? 'Gracias por su preferencia.',
        ],
        'cashier' => $_SESSION['username'] ?? 'Cajero',
        'created_at' => date('Y-m-d H:i:s'),
        'print_double_ticket' => (bool)($sysConfig['print_double_ticket'] ?? true),
    ];

    $connect->close();

    json_response([
        'success' => true,
        'messages' => "Operación {$opCode} (" . ($opType === 'deposit' ? 'Depósito' : 'Retiro') . ") registrada exitosamente.",
        'operation' => $receiptData,
    ]);

} catch (Throwable $e) {
    $connect->rollback();
    $connect->close();
    send_http_error(400, $e->getMessage());
}

