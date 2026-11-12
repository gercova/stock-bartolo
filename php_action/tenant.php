<?php

function sb_get_system_config(mysqli $connect): array
{
    ensure_app_schema($connect);

    $res = $connect->query('SELECT * FROM system_settings WHERE setting_id = 1 LIMIT 1');
    $row = $res ? $res->fetch_assoc() : null;

    if (!$row) {
        return [
            'company_name' => 'Stock Bartolo',
            'company_tax_id' => '',
            'company_phone' => '',
            'company_address' => '',
            'receipt_footer' => 'Gracias por su preferencia.',
            'default_tax_rate' => 13.0,
            'default_currency_code' => 'USD',
            'default_currency_symbol' => '$',
            'enforce_open_till_for_cash' => false,
            'auto_fund_till_from_general_cash' => true,
            'auto_return_till_to_general_cash' => true,
            'allow_branch_switching' => true,
        ];
    }

    return [
        'company_name' => (string)$row['company_name'],
        'company_tax_id' => (string)($row['company_tax_id'] ?? ''),
        'company_phone' => (string)($row['company_phone'] ?? ''),
        'company_address' => (string)($row['company_address'] ?? ''),
        'receipt_footer' => (string)($row['receipt_footer'] ?? 'Gracias por su preferencia.'),
        'default_tax_rate' => round((float)$row['default_tax_rate'], 2),
        'default_currency_code' => (string)($row['default_currency_code'] ?? 'USD'),
        'default_currency_symbol' => (string)($row['default_currency_symbol'] ?? '$'),
        'enforce_open_till_for_cash' => ((int)$row['enforce_open_till_for_cash']) === 1,
        'auto_fund_till_from_general_cash' => ((int)$row['auto_fund_till_from_general_cash']) === 1,
        'auto_return_till_to_general_cash' => ((int)$row['auto_return_till_to_general_cash']) === 1,
        'allow_branch_switching' => ((int)$row['allow_branch_switching']) === 1,
    ];
}

function sb_format_branch_row(array $row): array
{
    return [
        'branch_id'     => (int)$row['branch_id'],
        'branch_code'   => (string)$row['branch_code'],
        'branch_name'   => (string)$row['branch_name'],
        'address'       => (string)($row['address'] ?? ''),
        'phone'         => (string)($row['phone'] ?? ''),
        'email'         => (string)($row['email'] ?? ''),
        'manager_name'  => (string)($row['manager_name'] ?? ''),
        'tax_rate'      => round((float)($row['tax_rate'] ?? 13.0), 2),
        'currency_code' => (string)($row['currency_code'] ?? 'USD'),
        'currency_symbol' => (string)($row['currency_symbol'] ?? '$'),
        'is_main'       => ((int)($row['is_main'] ?? 0)) === 1,
        'active'        => (int)($row['active'] ?? 1),
        'record_status' => (int)($row['record_status'] ?? 1),
        'created_at'    => $row['created_at'] ?? null,
    ];
}

function sb_user_can_switch_branches(mysqli $connect, int $userId): bool
{
    $role = $_SESSION['user_role'] ?? null;
    if ($role === null) {
        $stmt = $connect->prepare('SELECT role, branch_id FROM users WHERE user_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($u) {
                $role = (string)($u['role'] ?? 'superadmin');
                $_SESSION['user_role'] = $role;
                $_SESSION['assigned_branch_id'] = (int)($u['branch_id'] ?? 1);
            }
        }
    }
    return in_array($role ?: 'superadmin', ['superadmin', 'admin'], true);
}

function get_active_branch_id(mysqli $connect): int
{
    ensure_app_schema($connect);

    if (empty($_SESSION['userId'])) {
        return 1;
    }

    $userId = (int)$_SESSION['userId'];
    $canSwitch = sb_user_can_switch_branches($connect, $userId);
    $assignedId = max(1, (int)($_SESSION['assigned_branch_id'] ?? 1));
    $candidateId = $canSwitch
        ? (int)($_SESSION['active_branch_id'] ?? $assignedId)
        : $assignedId;

    $stmt = $connect->prepare('SELECT branch_id FROM branches WHERE branch_id = ? AND record_status = 1 AND active = 1 LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $candidateId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $_SESSION['active_branch_id'] = (int)$row['branch_id'];
            return (int)$row['branch_id'];
        }
    }

    // Fallback to main or first active branch
    $fallbackRes    = $connect->query('SELECT branch_id FROM branches WHERE record_status = 1 AND active = 1 ORDER BY is_main DESC, branch_id ASC LIMIT 1');
    $fallbackRow    = $fallbackRes ? $fallbackRes->fetch_assoc() : null;
    $resolved       = $fallbackRow ? (int)$fallbackRow['branch_id'] : 1;
    $_SESSION['active_branch_id'] = $resolved;
    return $resolved;
}

function get_active_branch(mysqli $connect): array
{
    $branchId   = get_active_branch_id($connect);
    $stmt       = $connect->prepare('SELECT * FROM branches WHERE branch_id = ? AND record_status = 1 LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $branchId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return sb_format_branch_row($row);
        }
    }

    return [
        'branch_id'     => 1,
        'branch_code'   => 'SUC-001',
        'branch_name'   => 'Sucursal Principal',
        'address'       => '',
        'phone'         => '',
        'email'         => '',
        'manager_name'  => '',
        'tax_rate'      => 13.0,
        'currency_code' => 'USD',
        'currency_symbol' => '$',
        'is_main'       => true,
        'active'        => 1,
        'record_status' => 1,
    ];
}

function sb_get_accessible_branches(mysqli $connect): array
{
    ensure_app_schema($connect);
    $userId = (int)($_SESSION['userId'] ?? 0);
    $canSwitch = $userId > 0 ? sb_user_can_switch_branches($connect, $userId) : false;

    if ($canSwitch) {
        $sql    = 'SELECT b.*, COALESCE(gca.current_balance, 0) AS general_cash_balance, (SELECT COUNT(*) FROM cash_sessions cs WHERE cs.branch_id = b.branch_id AND cs.status = "open") AS open_sessions_count FROM branches b LEFT JOIN general_cash_accounts gca ON gca.branch_id = b.branch_id WHERE b.record_status = 1 AND b.active = 1 ORDER BY b.is_main DESC, b.branch_id ASC';
        $res    = $connect->query($sql);
        $list   = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $item = sb_format_branch_row($row);
                $item['general_cash_balance'] = round((float)$row['general_cash_balance'], 2);
                $item['open_sessions_count'] = (int)$row['open_sessions_count'];
                $list[] = $item;
            }
        }
        return $list;
    }

    $branchId   = get_active_branch_id($connect);
    $stmt       = $connect->prepare('SELECT b.*, COALESCE(gca.current_balance, 0) AS general_cash_balance, (SELECT COUNT(*) FROM cash_sessions cs WHERE cs.branch_id = b.branch_id AND cs.status = "open") AS open_sessions_count FROM branches b LEFT JOIN general_cash_accounts gca ON gca.branch_id = b.branch_id WHERE b.branch_id = ? AND b.record_status = 1 AND b.active = 1 LIMIT 1');
    $list       = [];
    if ($stmt) {
        $stmt->bind_param('i', $branchId);
        $stmt->execute();
        $res        = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $item   = sb_format_branch_row($row);
            $item['general_cash_balance'] = round((float)$row['general_cash_balance'], 2);
            $item['open_sessions_count'] = (int)$row['open_sessions_count'];
            $list[] = $item;
        }
        $stmt->close();
    }
    return $list;
}

function sb_ensure_branch_cash_defaults(mysqli $connect, int $branchId, string $branchName, float $initialCapital = 0.0, int $userId = 1): void
{
    $accName    = 'Caja General — ' . $branchName;
    $notes      = 'Fondo operativo independiente de ' . $branchName;
    $stmtAcc    = $connect->prepare('SELECT account_id, current_balance FROM general_cash_accounts WHERE branch_id = ? LIMIT 1');
    $stmtAcc->bind_param('i', $branchId);
    $stmtAcc->execute();
    $existingAcc = $stmtAcc->get_result()->fetch_assoc();
    $stmtAcc->close();

    if (!$existingAcc) {
        $initCap    = round(max(0, $initialCapital), 2);
        $insAcc     = $connect->prepare('INSERT INTO general_cash_accounts (branch_id, account_name, current_balance, initial_capital, min_balance_alert, status, notes, updated_by) VALUES (?, ?, ?, ?, 100.00, 1, ?, ?)');
        $insAcc->bind_param('isddsi', $branchId, $accName, $initCap, $initCap, $notes, $userId);
        $insAcc->execute();
        $accountId  = (int)$connect->insert_id;
        $insAcc->close();

        if ($initCap > 0) {
            $movType    = 'initial_capital';
            $dir        = 'in';
            $zero       = 0.00;
            $ref        = sprintf('SUC-%03d', $branchId);
            $desc       = 'Capital inicial al crear sucursal ' . $branchName;
            $movStmt    = $connect->prepare('INSERT INTO general_cash_movements (branch_id, account_id, user_id, movement_type, direction, amount, balance_before, balance_after, reference, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $movStmt->bind_param('iiissdddss', $branchId, $accountId, $userId, $movType, $dir, $initCap, $zero, $initCap, $ref, $desc);
            $movStmt->execute();
            $movStmt->close();
        }
    }

    $regStmt    = $connect->prepare('SELECT COUNT(*) AS cnt FROM cash_registers WHERE branch_id = ? AND record_status = 1');
    $regStmt->bind_param('i', $branchId);
    $regStmt->execute();
    $regCnt     = (int)($regStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $regStmt->close();

    if ($regCnt === 0) {
        $regCode    = 'CAJA-01';
        $regName    = 'Caja Principal';
        $location   = 'Mostrador';
        $insReg     = $connect->prepare('INSERT INTO cash_registers (branch_id, register_code, register_name, location, active, record_status) VALUES (?, ?, ?, ?, 1, 1)');
        $insReg->bind_param('isss', $branchId, $regCode, $regName, $location);
        $insReg->execute();
        $insReg->close();
    }
}

function sb_get_open_cash_session(mysqli $connect, int $branchId): ?array
{
    $stmt = $connect->prepare("SELECT cs.*, cr.register_code, cr.register_name, COALESCE(u.username, 'Sistema') AS opened_by_name
        FROM cash_sessions cs
        INNER JOIN cash_registers cr ON cr.register_id = cs.register_id
        LEFT JOIN users u ON u.user_id = cs.opened_by
        WHERE cs.branch_id = ? AND cs.status = 'open'
        ORDER BY cs.session_id DESC
        LIMIT 1 FOR UPDATE");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $branchId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Records a cash movement in the branch's active open till (when available),
 * or tracks non-cash sales volume on the open session.
 * Intended to be called inside an existing DB transaction.
 */
function sb_record_pos_cash_impact(
    mysqli $connect,
    int $branchId,
    int $userId,
    string $movementType,
    string $direction,
    float $amount,
    int $paymentMethod,
    string $referenceType,
    int $referenceId,
    string $description
): void {
    $amount = round($amount, 2);
    if ($amount <= 0) {
        return;
    }

    $config = sb_get_system_config($connect);
    $session = sb_get_open_cash_session($connect, $branchId);

    if (!$session) {
        if ($paymentMethod === 2 && !empty($config['enforce_open_till_for_cash'])) {
            throw new InvalidArgumentException('Esta sucursal no tiene una caja abierta. Abre una caja antes de procesar operaciones en efectivo.');
        }
        return;
    }

    $sessionId = (int)$session['session_id'];

    // Non-cash sales (card / cheque) only increment informative non_cash_sales_total for inflows
    if ($paymentMethod !== 2) {
        if ($direction === 'in') {
            $newNonCash = round((float)$session['non_cash_sales_total'] + $amount, 2);
            $upd = $connect->prepare('UPDATE cash_sessions SET non_cash_sales_total = ? WHERE session_id = ?');
            $upd->bind_param('di', $newNonCash, $sessionId);
            $upd->execute();
            $upd->close();
        }
        return;
    }

    $movStmt = $connect->prepare('INSERT INTO cash_movements (branch_id, session_id, user_id, movement_type, direction, amount, payment_method, reference_type, reference_id, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $movStmt->bind_param('iiissdisis', $branchId, $sessionId, $userId, $movementType, $direction, $amount, $paymentMethod, $referenceType, $referenceId, $description);
    $movStmt->execute();
    $movStmt->close();

    $cashIn = (float)$session['cash_in_total'];
    $cashOut = (float)$session['cash_out_total'];
    if ($direction === 'in') {
        $cashIn = round($cashIn + $amount, 2);
    } else {
        $cashOut = round($cashOut + $amount, 2);
    }
    $expected = round((float)$session['opening_amount'] + $cashIn - $cashOut, 2);

    $updSess = $connect->prepare('UPDATE cash_sessions SET cash_in_total = ?, cash_out_total = ?, expected_cash = ? WHERE session_id = ?');
    $updSess->bind_param('dddi', $cashIn, $cashOut, $expected, $sessionId);
    $updSess->execute();
    $updSess->close();
}

function sb_compute_counted_from_denominations(mixed $rawDenoms, float $fallbackCounted): array
{
    $denomValuesCents = [
        '100' => 10000,
        '50' => 5000,
        '20' => 2000,
        '10' => 1000,
        '5' => 500,
        '2' => 200,
        '1' => 100,
        '0.50' => 50,
        '0.25' => 25,
        '0.10' => 10,
        '0.05' => 5,
        '0.01' => 1,
    ];

    if (is_array($rawDenoms) && count($rawDenoms) > 0) {
        $cleaned = [];
        $totalCents = 0;
        $hasAnyCount = false;

        foreach ($denomValuesCents as $label => $cents) {
            $qty = isset($rawDenoms[$label]) ? (int)$rawDenoms[$label] : 0;
            if ($qty < 0 || $qty > 1000000) {
                throw new InvalidArgumentException("Cantidad inválida para denominación {$label}.");
            }
            if ($qty > 0) {
                $hasAnyCount = true;
            }
            $cleaned[$label] = $qty;
            $totalCents += $qty * $cents;
        }

        if ($hasAnyCount || $fallbackCounted == 0.0) {
            return [
                'counted' => round($totalCents / 100, 2),
                'json' => json_encode($cleaned, JSON_UNESCAPED_UNICODE),
            ];
        }
    }

    return [
        'counted' => round(max(0, $fallbackCounted), 2),
        'json' => null,
    ];
}
