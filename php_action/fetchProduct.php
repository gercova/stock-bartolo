<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$branchId   = get_active_branch_id($connect);
$search     = trim((string)($_GET['search'] ?? ''));
$status     = trim((string)($_GET['status'] ?? 'all'));
$brandId    = isset($_GET['brand_id']) && $_GET['brand_id'] !== '' ? (int)$_GET['brand_id'] : null;
$categoryId = isset($_GET['categories_id']) && $_GET['categories_id'] !== '' ? (int)$_GET['categories_id'] : null;

$pagination = sb_get_pagination_params(25, 250);

$where  = ['p.branch_id = ?', 'p.status = 1'];
$params = [$branchId];
$types  = 'i';

if ($search !== '') {
    $where[]  = '(p.product_name LIKE ? OR b.brand_name LIKE ? OR c.categories_name LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types    .= 'sss';
}

if ($status === '1') {
    $where[] = 'p.active = 1';
} elseif ($status === '2') {
    $where[] = 'p.active = 2';
} elseif ($status === 'low') {
    $where[] = 'p.quantity <= 3';
}

if ($brandId !== null && $brandId > 0) {
    $where[]  = 'p.brand_id = ?';
    $params[] = $brandId;
    $types    .= 'i';
}

if ($categoryId !== null && $categoryId > 0) {
    $where[]  = 'p.categories_id = ?';
    $params[] = $categoryId;
    $types    .= 'i';
}

$whereSql = implode(' AND ', $where);

// Total Count Query
$countSql = "SELECT COUNT(*) AS total
             FROM product p
             LEFT JOIN brands b ON p.brand_id = b.brand_id AND b.branch_id = p.branch_id
             LEFT JOIN categories c ON p.categories_id = c.categories_id AND c.branch_id = p.branch_id
             WHERE {$whereSql}";

$cStmt = $connect->prepare($countSql);
$cStmt->bind_param($types, ...$params);
$cStmt->execute();
$totalRow = $cStmt->get_result()->fetch_assoc();
$total = (int)($totalRow['total'] ?? 0);
$cStmt->close();

// Data Query
$sql = "SELECT
    p.product_id,
    p.branch_id,
    p.product_name,
    p.product_image,
    p.brand_id,
    p.categories_id,
    p.quantity,
    p.rate,
    p.active,
    p.status,
    COALESCE(b.brand_name, 'Sin fabricante') AS brand_name,
    COALESCE(c.categories_name, 'Sin categoría') AS categories_name
FROM product p
LEFT JOIN brands b ON p.brand_id = b.brand_id AND b.branch_id = p.branch_id
LEFT JOIN categories c ON p.categories_id = c.categories_id AND c.branch_id = p.branch_id
WHERE {$whereSql}
ORDER BY p.product_id DESC";

if ($pagination['enabled']) {
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $pagination['limit'];
    $params[] = $pagination['offset'];
    $types .= 'ii';
}

$stmt = $connect->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$products = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'product_id'      => (int)$row['product_id'],
            'branch_id'       => (int)$row['branch_id'],
            'product_name'    => $row['product_name'],
            'product_image'   => normalize_image_url($row['product_image']),
            'brand_id'        => (int)$row['brand_id'],
            'categories_id'   => (int)$row['categories_id'],
            'quantity'        => (int)$row['quantity'],
            'rate'            => (float)$row['rate'],
            'active'          => (int)$row['active'],
            'status'          => (int)$row['status'],
            'brand_name'      => $row['brand_name'],
            'categories_name' => $row['categories_name'],
        ];
    }
}

$stmt->close();
$connect->close();

$meta = sb_build_pagination_meta($total, $pagination);

json_response([
    'success'    => true,
    'data'       => $products,
    'pagination' => $meta,
]);
