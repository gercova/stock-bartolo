<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$branchId = get_active_branch_id($connect);
$userId   = (int)($_SESSION['userId'] ?? 1);
$data     = get_request_data();

// Target business branch
$targetBranchId = !empty($data['branch_id']) ? (int)$data['branch_id'] : (!empty($data['branchId']) ? (int)$data['branchId'] : $branchId);
$canSwitch = sb_user_can_switch_branches($connect, $userId);
if ($targetBranchId !== $branchId && !$canSwitch) {
    $targetBranchId = $branchId;
}

// Ensure branch exists
$bStmt = $connect->prepare('SELECT branch_id, branch_name FROM branches WHERE branch_id = ? AND record_status = 1 LIMIT 1');
$bStmt->bind_param('i', $targetBranchId);
$bStmt->execute();
$branchRow = $bStmt->get_result()->fetch_assoc();
$bStmt->close();

if (!$branchRow) {
    $connect->close();
    send_http_error(404, 'Sucursal de la empresa no encontrada.');
}

// Form fields
$bankName        = sanitize_clean_text($data['bank_name'] ?? $data['bankName'] ?? '', 'entidad bancaria', 60, true);
$bankBranch      = sanitize_clean_text($data['bank_branch'] ?? $data['bankBranch'] ?? '', 'sucursal o agencia bancaria', 150, true);
$accountNumber   = sanitize_clean_text($data['account_number'] ?? $data['accountNumber'] ?? '', 'número de cuenta', 60, false);
$voucherNumber   = sanitize_clean_text($data['voucher_number'] ?? $data['voucherNumber'] ?? '', 'número de operación o voucher', 60, false);
$fundingSource   = sanitize_clean_text($data['funding_source'] ?? $data['fundingSource'] ?? 'Efectivo en Ventanilla', 'origen de fondos', 60, false);
$depositDateRaw  = sanitize_clean_text($data['deposit_date'] ?? $data['depositDate'] ?? '', 'fecha de depósito', 35, false);
$amount          = round(max(0.0, (float)($data['amount'] ?? 0.0)), 2);
$notes           = sanitize_clean_text($data['notes'] ?? '', 'observaciones', 500, false);
$deductFromGc    = !empty($data['deduct_from_general_cash']) || !empty($data['deductFromGeneralCash']);

if ($bankName === '') {
    $connect->close();
    send_http_error(400, 'Debes seleccionar una entidad bancaria.');
}

if ($bankBranch === '' || mb_strlen($bankBranch) < 3) {
    $connect->close();
    send_http_error(400, 'Debes ingresar la sucursal o agencia específica de la entidad bancaria donde se depositó el capital (mínimo 3 caracteres).');
}

if ($amount <= 0.0) {
    $connect->close();
    send_http_error(400, 'El monto a ingresar debe ser mayor a cero (S/ 0.00).');
}

// Parse or format deposit date
$depositDate = date('Y-m-d H:i:s');
if ($depositDateRaw !== '') {
    $ts = strtotime($depositDateRaw);
    if ($ts !== false && $ts > 0) {
        $depositDate = date('Y-m-d H:i:s', $ts);
    }
}

$connect->begin_transaction();

try {
    // 1. Lock or create the bank capital account for this branch
    $accStmt = $connect->prepare('SELECT account_id, current_balance, total_deposited, account_number, account_type FROM bank_capital_accounts WHERE branch_id = ? AND bank_name = ? LIMIT 1 FOR UPDATE');
    $accStmt->bind_param('is', $targetBranchId, $bankName);
    $accStmt->execute();
    $accRow = $accStmt->get_result()->fetch_assoc();
    $accStmt->close();

    $accountId = null;
    $balanceBefore = 0.00;
    $totalDepositedBefore = 0.00;

    if ($accRow) {
        $accountId            = (int)$accRow['account_id'];
        $balanceBefore        = round((float)$accRow['current_balance'], 2);
        $totalDepositedBefore = round((float)$accRow['total_deposited'], 2);
        if ($accountNumber === '' && !empty($accRow['account_number'])) {
            $accountNumber = $accRow['account_number'];
        }
    } else {
        // Create new account
        $bankCode   = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $bankName), 0, 10));
        $accType    = 'corriente';
        $insAcc     = $connect->prepare('INSERT INTO bank_capital_accounts (branch_id, bank_name, bank_code, account_number, account_type, current_balance, total_deposited, status) VALUES (?, ?, ?, ?, ?, 0.00, 0.00, 1)');
        $insAcc->bind_param('issss', $targetBranchId, $bankName, $bankCode, $accountNumber, $accType);
        $insAcc->execute();
        $accountId            = (int)$connect->insert_id;
        $insAcc->close();
        $balanceBefore        = 0.00;
        $totalDepositedBefore = 0.00;
    }

    $balanceAfter       = round($balanceBefore + $amount, 2);
    $newTotalDeposited  = round($totalDepositedBefore + $amount, 2);

    // 2. If deducting from General Cash, validate and adjust
    if ($deductFromGc) {
        $gcStmt = $connect->prepare('SELECT account_id, current_balance, status FROM general_cash_accounts WHERE branch_id = ? LIMIT 1 FOR UPDATE');
        $gcStmt->bind_param('i', $targetBranchId);
        $gcStmt->execute();
        $gcRow = $gcStmt->get_result()->fetch_assoc();
        $gcStmt->close();

        if (!$gcRow || (int)$gcRow['status'] !== 1) {
            throw new InvalidArgumentException('La Caja General de esta sucursal no está disponible para deducir fondos.');
        }

        $gcBalBefore = round((float)$gcRow['current_balance'], 2);
        if ($amount > $gcBalBefore + 0.0001) {
            throw new InvalidArgumentException("Saldo insuficiente en Caja General (S/ " . number_format($gcBalBefore, 2) . " disponible) para fondear S/ " . number_format($amount, 2) . ".");
        }

        $gcBalAfter = round($gcBalBefore - $amount, 2);
        $gcAccId    = (int)$gcRow['account_id'];

        $updGc      = $connect->prepare('UPDATE general_cash_accounts SET current_balance = ?, updated_by = ?, updated_at = NOW() WHERE account_id = ?');
        $updGc->bind_param('dii', $gcBalAfter, $userId, $gcAccId);
        $updGc->execute();
        $updGc->close();

        $movType    = 'bank_capital_funding';
        $dir        = 'out';
        $movDesc    = "Fondeo de capital bancario a {$bankName} (Sede: {$bankBranch})";
        $insGcMov   = $connect->prepare('INSERT INTO general_cash_movements (branch_id, account_id, user_id, movement_type, direction, amount, balance_before, balance_after, reference, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insGcMov->bind_param('iiissdddss', $targetBranchId, $gcAccId, $userId, $movType, $dir, $amount, $gcBalBefore, $gcBalAfter, $voucherNumber, $movDesc);
        $insGcMov->execute();
        $insGcMov->close();
    }

    // 3. Update bank capital account
    $updAcc = $connect->prepare('UPDATE bank_capital_accounts SET current_balance = ?, total_deposited = ?, account_number = IF(? != "", ?, account_number), updated_at = NOW() WHERE account_id = ?');
    $updAcc->bind_param('ddssi', $balanceAfter, $newTotalDeposited, $accountNumber, $accountNumber, $accountId);
    $updAcc->execute();
    $updAcc->close();

    // 4. Generate sequential deposit code
    $cntStmt = $connect->prepare('SELECT COUNT(*) AS c FROM bank_capital_deposits WHERE branch_id = ?');
    $cntStmt->bind_param('i', $targetBranchId);
    $cntStmt->execute();
    $depCount = (int)($cntStmt->get_result()->fetch_assoc()['c'] ?? 0);
    $cntStmt->close();

    $depositCode = sprintf('CAP-%03d-%06d', $targetBranchId, $depCount + 1);

    // 5. Insert deposit audit record
    $insDep = $connect->prepare('INSERT INTO bank_capital_deposits 
        (deposit_code, branch_id, bank_name, bank_branch, account_number, voucher_number, amount, balance_before, balance_after, funding_source, deposit_date, user_id, notes, record_status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
    $insDep->bind_param(
        'sissssdddssis',
        $depositCode,
        $targetBranchId,
        $bankName,
        $bankBranch,
        $accountNumber,
        $voucherNumber,
        $amount,
        $balanceBefore,
        $balanceAfter,
        $fundingSource,
        $depositDate,
        $userId,
        $notes
    );

    if (!$insDep->execute()) {
        throw new RuntimeException('Error al registrar el depósito de capital bancario: ' . $insDep->error);
    }
    $newDepositId = (int)$connect->insert_id;
    $insDep->close();

    // 6. Record in system audit log
    if (function_exists('sb_record_audit_log')) {
        sb_record_audit_log(
            $connect,
            $userId,
            'create',
            'general_cash',
            $newDepositId,
            "Ingreso de capital de S/ " . number_format($amount, 2) . " a {$bankName} en {$bankBranch} (Voucher: {$voucherNumber}, Código: {$depositCode})",
            ['balance_before' => $balanceBefore],
            ['balance_after' => $balanceAfter, 'deposit_code' => $depositCode]
        );
    }

    $connect->commit();

    // Fetch user display name
    $uStmt = $connect->prepare('SELECT username FROM users WHERE user_id = ? LIMIT 1');
    $uStmt->bind_param('i', $userId);
    $uStmt->execute();
    $userName = $uStmt->get_result()->fetch_assoc()['username'] ?? 'admin';
    $uStmt->close();

    $connect->close();

    json_response([
        'success'  => true,
        'messages' => "Capital de S/ " . number_format($amount, 2) . " ingresado exitosamente a {$bankName} ({$bankBranch})",
        'deposit'  => [
            'deposit_id'     => $newDepositId,
            'deposit_code'   => $depositCode,
            'branch_id'      => $targetBranchId,
            'branch_name'    => $branchRow['branch_name'],
            'bank_name'      => $bankName,
            'bank_branch'    => $bankBranch,
            'account_number' => $accountNumber,
            'voucher_number' => $voucherNumber,
            'amount'         => $amount,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
            'funding_source' => $fundingSource,
            'deposit_date'   => $depositDate,
            'user_id'        => $userId,
            'username'       => $userName,
            'notes'          => $notes,
            'record_status'  => 1,
        ],
    ], 201);
} catch (InvalidArgumentException $e) {
    $connect->rollback();
    $connect->close();
    send_http_error(400, $e->getMessage());
} catch (Throwable $e) {
    $connect->rollback();
    $connect->close();
    send_http_error(500, 'Error interno al registrar el ingreso de capital: ' . $e->getMessage());
}
