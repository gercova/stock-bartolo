<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);
ensure_loan_schema($connect);

$prodRes = $connect->query('SELECT COUNT(*) AS cnt FROM product WHERE status = 1');
$totalProducts = (int)($prodRes ? $prodRes->fetch_assoc()['cnt'] : 0);

$lowRes = $connect->query('SELECT product_id, product_name, quantity, rate FROM product WHERE status = 1 AND CAST(quantity AS SIGNED) <= 3 ORDER BY CAST(quantity AS SIGNED) ASC LIMIT 10');
$lowStockProducts = [];
if ($lowRes) {
    while ($row = $lowRes->fetch_assoc()) {
        $lowStockProducts[] = [
            'product_id' => (int)$row['product_id'],
            'product_name' => $row['product_name'],
            'quantity' => (int)$row['quantity'],
            'rate' => (float)$row['rate'],
        ];
    }
}

$lowCountRes = $connect->query('SELECT COUNT(*) AS cnt FROM product WHERE status = 1 AND CAST(quantity AS SIGNED) <= 3');
$lowStockCount = (int)($lowCountRes ? $lowCountRes->fetch_assoc()['cnt'] : 0);

$orderRes = $connect->query('SELECT COUNT(*) AS cnt, COALESCE(SUM(CAST(paid AS DECIMAL(12,2))), 0) AS revenue, COALESCE(SUM(CAST(due AS DECIMAL(12,2))), 0) AS due FROM orders WHERE order_status = 1');
$orderStats = $orderRes ? $orderRes->fetch_assoc() : ['cnt' => 0, 'revenue' => 0, 'due' => 0];

$recentOrders = [];
$recentOrderRes = $connect->query('SELECT order_id, order_date, client_name, grand_total, paid, due, payment_status FROM orders WHERE order_status = 1 ORDER BY order_id DESC LIMIT 5');
if ($recentOrderRes) {
    while ($row = $recentOrderRes->fetch_assoc()) {
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
}

$loanSql = "SELECT
    COUNT(*) AS total_loans,
    SUM(CASE WHEN loan_status IN (1, 2) AND balance_due > 0 THEN 1 ELSE 0 END) AS active_loans,
    SUM(CASE WHEN loan_status IN (1, 2) AND balance_due > 0 AND due_date IS NOT NULL AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_loans,
    COALESCE(SUM(principal_amount), 0) AS total_principal,
    COALESCE(SUM(interest_amount), 0) AS total_interest,
    COALESCE(SUM(paid_amount), 0) AS total_collected,
    COALESCE(SUM(balance_due), 0) AS total_pending
    FROM loans WHERE record_status = 1";
$loanRes = $connect->query($loanSql);
$loanStats = $loanRes ? $loanRes->fetch_assoc() : [];

$recentLoans = [];
$recentLoanRes = $connect->query('SELECT l.loan_id, l.loan_code, l.client_name, l.loan_date, l.due_date, l.principal_amount, l.has_interest, l.interest_rate, l.total_amount, l.balance_due, l.loan_status FROM loans l WHERE l.record_status = 1 ORDER BY l.loan_id DESC LIMIT 5');
if ($recentLoanRes) {
    while ($row = $recentLoanRes->fetch_assoc()) {
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
}

$connect->close();

json_response([
    'success' => true,
    'data' => [
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
    ],
]);
