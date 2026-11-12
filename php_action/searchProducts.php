<?php

require_once __DIR__ . '/core.php';
require_method(['GET', 'POST']);

$branchId = get_active_branch_id($connect);
$query = trim((string)($_GET['query'] ?? $_GET['q'] ?? $_GET['search'] ?? ''));

// Strictly configured to return a maximum of 5 results to avoid overloading server and database
$limit = 5;
$products = [];

if ($query !== '') {
    $like = '%' . $query . '%';
    $isNumeric = is_numeric($query);

    if ($isNumeric) {
        $num = (int)$query;
        $stmt = $connect->prepare('SELECT p.product_id, p.product_name, p.quantity, p.rate, b.brand_name, c.categories_name
            FROM product p
            LEFT JOIN brands b ON p.brand_id = b.brand_id
            LEFT JOIN categories c ON p.categories_id = c.categories_id
            WHERE p.branch_id = ? AND p.status = 1 AND p.active = 1 AND (p.product_name LIKE ? OR p.product_id = ?)
            ORDER BY (p.product_id = ?) DESC, p.product_name ASC
            LIMIT 5');
        $stmt->bind_param('isii', $branchId, $like, $num, $num);
    } else {
        $stmt = $connect->prepare('SELECT p.product_id, p.product_name, p.quantity, p.rate, b.brand_name, c.categories_name
            FROM product p
            LEFT JOIN brands b ON p.brand_id = b.brand_id
            LEFT JOIN categories c ON p.categories_id = c.categories_id
            WHERE p.branch_id = ? AND p.status = 1 AND p.active = 1 AND p.product_name LIKE ?
            ORDER BY p.product_name ASC
            LIMIT 5');
        $stmt->bind_param('is', $branchId, $like);
    }
} else {
    // If empty query, return initial top 5 products
    $stmt = $connect->prepare('SELECT p.product_id, p.product_name, p.quantity, p.rate, b.brand_name, c.categories_name
        FROM product p
        LEFT JOIN brands b ON p.brand_id = b.brand_id
        LEFT JOIN categories c ON p.categories_id = c.categories_id
        WHERE p.branch_id = ? AND p.status = 1 AND p.active = 1
        ORDER BY p.product_name ASC
        LIMIT 5');
    $stmt->bind_param('i', $branchId);
}

$stmt->execute();
$res = $stmt->get_result();
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $products[] = [
            'product_id'      => (int)$row['product_id'],
            'product_name'    => $row['product_name'],
            'quantity'        => (int)$row['quantity'],
            'rate'            => (float)$row['rate'],
            'brand_name'      => $row['brand_name'] ?? '',
            'categories_name' => $row['categories_name'] ?? '',
        ];
    }
}
$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'count'   => count($products),
    'data'    => $products,
]);

