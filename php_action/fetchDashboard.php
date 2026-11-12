<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$branchId       = get_active_branch_id($connect);
$activeBranch   = get_active_branch($connect);

// Products summary
$prodStmt       = $connect->prepare('SELECT COUNT(*) AS cnt FROM product WHERE branch_id = ? AND status = 1');
$prodStmt->bind_param('i', $branchId);
$prodStmt->execute();
$totalProducts  = (int)($prodStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$prodStmt->close();

$lowStmt    = $connect->prepare('SELECT product_id, product_name, quantity, rate FROM product WHERE branch_id = ? AND status = 1 AND CAST(quantity AS SIGNED) <= 3 ORDER BY CAST(quantity AS SIGNED) ASC LIMIT 10');
$lowStmt->bind_param('i', $branchId);
$lowStmt->execute();
$lowRes     = $lowStmt->get_result();
$lowStockProducts = [];
while ($row = $lowRes->fetch_assoc()) {
    $lowStockProducts[] = [
        'product_id'    => (int)$row['product_id'],
        'product_name'  => $row['product_name'],
        'quantity'      => (int)$row['quantity'],
        'rate'          => (float)$row['rate'],
    ];
}
$lowStmt->close();

$lowCountStmt = $connect->prepare('SELECT COUNT(*) AS cnt FROM product WHERE branch_id = ? AND status = 1 AND CAST(quantity AS SIGNED) <= 3');
$lowCountStmt->bind_param('i', $branchId);
$lowCountStmt->execute();
$lowStockCount = (int)($lowCountStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$lowCountStmt->close();

// Orders summary
$orderStmt = $connect->prepare('SELECT COUNT(*) AS cnt, COALESCE(SUM(CAST(paid AS DECIMAL(12,2))), 0) AS revenue, COALESCE(SUM(CAST(due AS DECIMAL(12,2))), 0) AS due FROM orders WHERE branch_id = ? AND order_status = 1');
$orderStmt->bind_param('i', $branchId);
$orderStmt->execute();
$orderStats = $orderStmt->get_result()->fetch_assoc() ?: ['cnt' => 0, 'revenue' => 0, 'due' => 0];
$orderStmt->close();

$recentOrders = [];
$roStmt = $connect->prepare('SELECT order_id, order_date, client_name, grand_total, paid, due, payment_status FROM orders WHERE branch_id = ? AND order_status = 1 ORDER BY order_id DESC LIMIT 5');
$roStmt->bind_param('i', $branchId);
$roStmt->execute();
$roRes = $roStmt->get_result();
while ($row = $roRes->fetch_assoc()) {
    $recentOrders[] = [
        'order_id' => (int)$row['order_id'],
        'order_date' => $row['order_date'],
        'client_name' => $row['client_name'],
        'grand_total' => (float)$row['grand_total'],
        'paid' => (float)$row['paid'],
        'due' => (float)$row['due'],
        'payment_status' => (int)$row['payment_status'],
    ];
}
$roStmt->close();

// Loans summary
$loanSql = "SELECT
    COUNT(*) AS total_loans,
    SUM(CASE WHEN loan_status IN (1, 2) AND balance_due > 0 THEN 1 ELSE 0 END) AS active_loans,
    SUM(CASE WHEN loan_status IN (1, 2) AND balance_due > 0 AND due_date IS NOT NULL AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_loans,
    COALESCE(SUM(principal_amount), 0) AS total_principal,
    COALESCE(SUM(interest_amount), 0) AS total_interest,
    COALESCE(SUM(paid_amount), 0) AS total_collected,
    COALESCE(SUM(balance_due), 0) AS total_pending
    FROM loans WHERE branch_id = ? AND record_status = 1";
$lStmt = $connect->prepare($loanSql);
$lStmt->bind_param('i', $branchId);
$lStmt->execute();
$loanStats = $lStmt->get_result()->fetch_assoc() ?: [];
$lStmt->close();

$recentLoans = [];
$rlStmt = $connect->prepare('SELECT l.loan_id, l.loan_code, l.client_name, l.loan_date, l.due_date, l.principal_amount, l.has_interest, l.interest_rate, l.total_amount, l.balance_due, l.loan_status FROM loans l WHERE l.branch_id = ? AND l.record_status = 1 ORDER BY l.loan_id DESC LIMIT 5');
$rlStmt->bind_param('i', $branchId);
$rlStmt->execute();
$rlRes = $rlStmt->get_result();
while ($row = $rlRes->fetch_assoc()) {
    $isOverdue = ((int)$row['loan_status'] !== 3)
        && (float)$row['balance_due'] > 0
        && !empty($row['due_date'])
        && $row['due_date'] < date('Y-m-d');
    $recentLoans[] = [
        'loan_id' => (int)$row['loan_id'],
        'loan_code' => $row['loan_code'],
        'client_name' => $row['client_name'],
        'loan_date' => $row['loan_date'],
        'due_date' => $row['due_date'],
        'principal_amount' => (float)$row['principal_amount'],
        'has_interest' => (bool)$row['has_interest'],
        'interest_rate' => (float)$row['interest_rate'],
        'total_amount' => (float)$row['total_amount'],
        'balance_due' => (float)$row['balance_due'],
        'loan_status' => $isOverdue ? 4 : (int)$row['loan_status'],
    ];
}
$rlStmt->close();

// General Cash / Operational Cash Till KPIs for active branch
sb_ensure_branch_cash_defaults($connect, $branchId, $activeBranch['branch_name'], 0.0, (int)$_SESSION['userId']);
$gcStmt = $connect->prepare('SELECT account_id, account_name, current_balance, min_balance_alert, status FROM general_cash_accounts WHERE branch_id = ? LIMIT 1');
$gcStmt->bind_param('i', $branchId);
$gcStmt->execute();
$gcRow = $gcStmt->get_result()->fetch_assoc();
$gcStmt->close();

$openSessStmt = $connect->prepare("SELECT cs.session_id, cs.session_code, cs.opening_amount, cs.cash_in_total, cs.cash_out_total, cs.expected_cash, cs.opened_at, cr.register_name, cr.register_code, COALESCE(u.username, 'Sistema') AS opened_by_name
    FROM cash_sessions cs
    LEFT JOIN cash_registers cr ON cr.register_id = cs.register_id
    LEFT JOIN users u ON u.user_id = cs.opened_by
    WHERE cs.branch_id = ? AND cs.status = 'open'
    ORDER BY cs.session_id DESC LIMIT 1");
$openSessStmt->bind_param('i', $branchId);
$openSessStmt->execute();
$openSess = $openSessStmt->get_result()->fetch_assoc();
$openSessStmt->close();

$connect->close();

json_response([
    'success' => true,
    'data' => [
        'branch' => $activeBranch,
        'totalProducts' => $totalProducts,
        'totalOrders' => (int)($orderStats['cnt'] ?? 0),
        'lowStockCount' => $lowStockCount,
        'totalRevenue' => round((float)($orderStats['revenue'] ?? 0), 2),
        'totalOrderDue' => round((float)($orderStats['due'] ?? 0), 2),
        'lowStockProducts' => $lowStockProducts,
        'recentOrders' => $recentOrders,
        'loans' => [
            'totalLoans' => (int)($loanStats['total_loans'] ?? 0),
            'activeLoans' => (int)($loanStats['active_loans'] ?? 0),
            'overdueLoans' => (int)($loanStats['overdue_loans'] ?? 0),
            'totalPrincipal' => round((float)($loanStats['total_principal'] ?? 0), 2),
            'totalInterest' => round((float)($loanStats['total_interest'] ?? 0), 2),
            'totalCollected' => round((float)($loanStats['total_collected'] ?? 0), 2),
            'totalPending' => round((float)($loanStats['total_pending'] ?? 0), 2),
            'recent' => $recentLoans,
        ],
        'generalCash' => $gcRow ? [
            'account_id' => (int)$gcRow['account_id'],
            'account_name' => $gcRow['account_name'],
            'current_balance' => round((float)$gcRow['current_balance'], 2),
            'min_balance_alert' => round((float)$gcRow['min_balance_alert'], 2),
            'is_below_min' => round((float)$gcRow['current_balance'], 2) <= round((float)$gcRow['min_balance_alert'], 2),
            'status' => (int)$gcRow['status'],
        ] : null,
        'activeTillSession' => $openSess ? [
            'session_id' => (int)$openSess['session_id'],
            'session_code' => $openSess['session_code'],
            'register_code' => $openSess['register_code'] ?? '',
            'register_name' => $openSess['register_name'] ?? 'Caja',
            'opened_by_name' => $openSess['opened_by_name'],
            'opened_at' => $openSess['opened_at'],
            'opening_amount' => round((float)$openSess['opening_amount'], 2),
            'cash_in_total' => round((float)$openSess['cash_in_total'], 2),
            'cash_out_total' => round((float)$openSess['cash_out_total'], 2),
            'expected_cash' => round((float)$openSess['expected_cash'], 2),
        ] : null,
    ],
]);
