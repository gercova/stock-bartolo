<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$branchId      = get_active_branch_id($connect);
$search        = trim((string)($_GET['search'] ?? ''));
$paymentStatus = trim((string)($_GET['payment_status'] ?? 'all'));
$startDate     = trim((string)($_GET['startDate'] ?? ''));
$endDate       = trim((string)($_GET['endDate'] ?? ''));

$pagination = sb_get_pagination_params(25, 250);

$where  = ['o.branch_id = ?', 'o.order_status = 1'];
$params = [$branchId];
$types  = 'i';

if ($search !== '') {
    $where[]  = '(o.client_name LIKE ? OR o.client_contact LIKE ? OR CAST(o.order_id AS CHAR) LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types    .= 'sss';
}

if ($paymentStatus !== '' && $paymentStatus !== 'all') {
    $where[]  = 'o.payment_status = ?';
    $params[] = (int)$paymentStatus;
    $types    .= 'i';
}

if ($startDate !== '') {
    $where[]  = 'o.order_date >= ?';
    $params[] = $startDate;
    $types    .= 's';
}

if ($endDate !== '') {
    $where[]  = 'o.order_date <= ?';
    $params[] = $endDate;
    $types    .= 's';
}

$whereSql = implode(' AND ', $where);

// Count total
$countSql = "SELECT COUNT(*) AS total FROM orders o WHERE {$whereSql}";
$cStmt = $connect->prepare($countSql);
$cStmt->bind_param($types, ...$params);
$cStmt->execute();
$totalRow = $cStmt->get_result()->fetch_assoc();
$total = (int)($totalRow['total'] ?? 0);
$cStmt->close();

$sql = "SELECT
    o.order_id,
    o.branch_id,
    o.order_date,
    o.client_name,
    o.client_contact,
    o.sub_total,
    o.vat,
    o.total_amount,
    o.discount,
    o.grand_total,
    o.paid,
    o.due,
    o.payment_type,
    o.payment_status,
    o.order_status,
    (SELECT COUNT(*) FROM order_item oi WHERE oi.order_id = o.order_id AND oi.order_item_status = 1) AS item_count
FROM orders o
WHERE {$whereSql}
ORDER BY o.order_id DESC";

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
$orders = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = [
            'order_id'       => (int)$row['order_id'],
            'branch_id'      => (int)$row['branch_id'],
            'order_date'     => $row['order_date'],
            'client_name'    => $row['client_name'],
            'client_contact' => $row['client_contact'],
            'sub_total'      => (float)$row['sub_total'],
            'vat'            => (float)$row['vat'],
            'total_amount'   => (float)$row['total_amount'],
            'discount'       => (float)$row['discount'],
            'grand_total'    => (float)$row['grand_total'],
            'paid'           => (float)$row['paid'],
            'due'            => (float)$row['due'],
            'payment_type'   => (int)$row['payment_type'],
            'payment_status' => (int)$row['payment_status'],
            'item_count'     => (int)$row['item_count'],
        ];
    }
}

$stmt->close();
$connect->close();

$meta = sb_build_pagination_meta($total, $pagination);

json_response([
    'success'    => true,
    'data'       => $orders,
    'pagination' => $meta,
]);
