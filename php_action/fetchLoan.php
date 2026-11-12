<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$branchId   = get_active_branch_id($connect);
$search     = trim((string)($_GET['search'] ?? ''));
$status     = trim((string)($_GET['status'] ?? 'all'));
$hasInterestFilter = trim((string)($_GET['has_interest'] ?? 'all'));

$pagination = sb_get_pagination_params(25, 250);

$where  = ['l.branch_id = ?', 'l.record_status = 1'];
$params = [$branchId];
$types  = 'i';

if ($search !== '') {
    $where[]  = '(l.loan_code LIKE ? OR l.client_name LIKE ? OR l.client_contact LIKE ? OR l.client_document LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types    .= 'ssss';
}

if ($hasInterestFilter === '1') {
    $where[] = 'l.has_interest = 1';
} elseif ($hasInterestFilter === '0') {
    $where[] = 'l.has_interest = 0';
}

$today = date('Y-m-d');
if ($status === '1') {
    $where[] = "l.loan_status = 1 AND (l.due_date IS NULL OR l.due_date >= '{$today}')";
} elseif ($status === '2') {
    $where[] = "l.loan_status = 2 AND (l.due_date IS NULL OR l.due_date >= '{$today}')";
} elseif ($status === '3') {
    $where[] = 'l.loan_status = 3';
} elseif ($status === '4') {
    $where[] = "l.loan_status != 3 AND l.balance_due > 0 AND l.due_date IS NOT NULL AND l.due_date < '{$today}'";
}

$whereSql = implode(' AND ', $where);

// Count total matching
$countSql = "SELECT COUNT(*) AS total FROM loans l WHERE {$whereSql}";
$cStmt = $connect->prepare($countSql);
$cStmt->bind_param($types, ...$params);
$cStmt->execute();
$totalRow = $cStmt->get_result()->fetch_assoc();
$total = (int)($totalRow['total'] ?? 0);
$cStmt->close();

// Branch summary over all active branch loans
$sumSql = "SELECT
    COUNT(*) as totalLoans,
    SUM(CASE WHEN loan_status = 3 THEN 1 ELSE 0 END) as paidLoans,
    SUM(CASE WHEN loan_status != 3 AND (due_date IS NULL OR due_date >= '{$today}') THEN 1 ELSE 0 END) as activeLoans,
    SUM(CASE WHEN loan_status != 3 AND balance_due > 0 AND due_date IS NOT NULL AND due_date < '{$today}' THEN 1 ELSE 0 END) as overdueLoans,
    SUM(CASE WHEN has_interest = 1 THEN 1 ELSE 0 END) as withInterestCount,
    SUM(CASE WHEN has_interest = 0 THEN 1 ELSE 0 END) as withoutInterestCount,
    SUM(principal_amount) as totalPrincipal,
    SUM(interest_amount) as totalInterest,
    SUM(total_amount) as totalAmount,
    SUM(paid_amount) as totalCollected,
    SUM(balance_due) as totalPending
    FROM loans WHERE branch_id = ? AND record_status = 1";
$sStmt = $connect->prepare($sumSql);
$sStmt->bind_param('i', $branchId);
$sStmt->execute();
$sRow = $sStmt->get_result()->fetch_assoc();
$sStmt->close();

$summary = [
    'totalLoans'           => (int)($sRow['totalLoans'] ?? 0),
    'activeLoans'          => (int)($sRow['activeLoans'] ?? 0),
    'paidLoans'            => (int)($sRow['paidLoans'] ?? 0),
    'overdueLoans'         => (int)($sRow['overdueLoans'] ?? 0),
    'withInterestCount'    => (int)($sRow['withInterestCount'] ?? 0),
    'withoutInterestCount' => (int)($sRow['withoutInterestCount'] ?? 0),
    'totalPrincipal'       => (float)($sRow['totalPrincipal'] ?? 0.0),
    'totalInterest'        => (float)($sRow['totalInterest'] ?? 0.0),
    'totalAmount'          => (float)($sRow['totalAmount'] ?? 0.0),
    'totalCollected'       => (float)($sRow['totalCollected'] ?? 0.0),
    'totalPending'         => (float)($sRow['totalPending'] ?? 0.0),
];

// Query paginated data
$sql = "SELECT
    l.*,
    COALESCE(u.username, 'Sistema') AS issued_by,
    (SELECT COUNT(*) FROM loan_payments lp WHERE lp.loan_id = l.loan_id) AS payments_count
FROM loans l
LEFT JOIN users u ON l.user_id = u.user_id
WHERE {$whereSql}
ORDER BY l.loan_id DESC";

$dataParams = $params;
$dataTypes = $types;

if ($pagination['enabled']) {
    $sql .= " LIMIT ? OFFSET ?";
    $dataParams[] = $pagination['limit'];
    $dataParams[] = $pagination['offset'];
    $dataTypes .= 'ii';
}

$stmt = $connect->prepare($sql);
$stmt->bind_param($dataTypes, ...$dataParams);
$stmt->execute();
$result = $stmt->get_result();
$loans  = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $principal       = round((float)$row['principal_amount'], 2);
        $interest        = round((float)$row['interest_amount'], 2);
        $totalAmt        = round((float)$row['total_amount'], 2);
        $paid            = round((float)$row['paid_amount'], 2);
        $balance         = round((float)$row['balance_due'], 2);
        $hasInterest     = ((int)$row['has_interest']) === 1;
        $rawStatus       = (int)$row['loan_status'];
        $isOverdue       = ($rawStatus !== 3) && ($balance > 0) && !empty($row['due_date']) && ($row['due_date'] < $today);
        $effectiveStatus = $isOverdue ? 4 : $rawStatus;

        $loans[] = [
            'loan_id'          => (int)$row['loan_id'],
            'branch_id'        => (int)$row['branch_id'],
            'loan_code'        => $row['loan_code'],
            'user_id'          => (int)$row['user_id'],
            'issued_by'        => $row['issued_by'],
            'client_name'      => $row['client_name'],
            'client_contact'   => $row['client_contact'],
            'client_document'  => $row['client_document'] ?? '',
            'order_id'         => $row['order_id'] !== null ? (int)$row['order_id'] : null,
            'loan_date'        => $row['loan_date'],
            'due_date'         => $row['due_date'],
            'principal_amount' => $principal,
            'has_interest'     => $hasInterest,
            'interest_rate'    => round((float)$row['interest_rate'], 2),
            'interest_mode'    => $row['interest_mode'],
            'term_months'      => (int)$row['term_months'],
            'interest_amount'  => $interest,
            'total_amount'     => $totalAmt,
            'paid_amount'      => $paid,
            'balance_due'      => $balance,
            'loan_status'      => $effectiveStatus,
            'is_overdue'       => $isOverdue,
            'notes'            => $row['notes'] ?? '',
            'payments_count'   => (int)$row['payments_count'],
            'created_at'       => $row['created_at'],
        ];
    }
}

$stmt->close();
$connect->close();

$meta = sb_build_pagination_meta($total, $pagination);

json_response([
    'success'    => true,
    'data'       => $loans,
    'pagination' => $meta,
    'summary'    => $summary,
]);
