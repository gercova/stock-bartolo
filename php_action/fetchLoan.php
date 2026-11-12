<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$branchId   = get_active_branch_id($connect);
$sql        = "SELECT
    l.*,
    COALESCE(u.username, 'Sistema') AS issued_by,
    (SELECT COUNT(*) FROM loan_payments lp WHERE lp.loan_id = l.loan_id) AS payments_count
FROM loans l
LEFT JOIN users u ON l.user_id = u.user_id
WHERE l.branch_id = ? AND l.record_status = 1
ORDER BY l.loan_id DESC";

$stmt   = $connect->prepare($sql);
$stmt->bind_param('i', $branchId);
$stmt->execute();
$result = $stmt->get_result();
$loans  = [];
$today  = date('Y-m-d');

$summary = [
    'totalLoans'            => 0,
    'activeLoans'           => 0,
    'paidLoans'             => 0,
    'overdueLoans'          => 0,
    'withInterestCount'     => 0,
    'withoutInterestCount'  => 0,
    'totalPrincipal'        => 0.0,
    'totalInterest'         => 0.0,
    'totalAmount'           => 0.0,
    'totalCollected'        => 0.0,
    'totalPending'          => 0.0,
];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $principal      = round((float)$row['principal_amount'], 2);
        $interest       = round((float)$row['interest_amount'], 2);
        $total          = round((float)$row['total_amount'], 2);
        $paid           = round((float)$row['paid_amount'], 2);
        $balance        = round((float)$row['balance_due'], 2);
        $hasInterest    = ((int)$row['has_interest']) === 1;
        $rawStatus      = (int)$row['loan_status'];
        $isOverdue      = ($rawStatus !== 3) && ($balance > 0) && !empty($row['due_date']) && ($row['due_date'] < $today);
        $effectiveStatus = $isOverdue ? 4 : $rawStatus;

        $summary['totalLoans']++;
        if ($effectiveStatus === 3) {
            $summary['paidLoans']++;
        } else {
            $summary['activeLoans']++;
        }
        if ($isOverdue) {
            $summary['overdueLoans']++;
        }
        if ($hasInterest) {
            $summary['withInterestCount']++;
        } else {
            $summary['withoutInterestCount']++;
        }
        $summary['totalPrincipal']  += $principal;
        $summary['totalInterest']   += $interest;
        $summary['totalAmount']     += $total;
        $summary['totalCollected']  += $paid;
        $summary['totalPending']    += $balance;

        $loans[] = [
            'loan_id'           => (int)$row['loan_id'],
            'branch_id'         => (int)$row['branch_id'],
            'loan_code'         => $row['loan_code'],
            'user_id'           => (int)$row['user_id'],
            'issued_by'         => $row['issued_by'],
            'client_name'       => $row['client_name'],
            'client_contact'    => $row['client_contact'],
            'client_document'   => $row['client_document'] ?? '',
            'order_id'          => $row['order_id'] !== null ? (int)$row['order_id'] : null,
            'loan_date'         => $row['loan_date'],
            'due_date'          => $row['due_date'],
            'principal_amount'  => $principal,
            'has_interest'      => $hasInterest,
            'interest_rate'     => round((float)$row['interest_rate'], 2),
            'interest_mode'     => $row['interest_mode'],
            'term_months'       => (int)$row['term_months'],
            'interest_amount'   => $interest,
            'total_amount'      => $total,
            'paid_amount'       => $paid,
            'balance_due'       => $balance,
            'loan_status'       => $effectiveStatus,
            'is_overdue'        => $isOverdue,
            'notes'             => $row['notes'] ?? '',
            'payments_count'    => (int)$row['payments_count'],
            'created_at'        => $row['created_at'],
        ];
    }
}

$stmt->close();

foreach (['totalPrincipal', 'totalInterest', 'totalAmount', 'totalCollected', 'totalPending'] as $k) {
    $summary[$k] = round($summary[$k], 2);
}

$connect->close();

json_response([
    'success'   => true,
    'data'      => $loans,
    'summary'   => $summary,
]);
