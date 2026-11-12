<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$branchId = get_active_branch_id($connect);
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
WHERE p.branch_id = ? AND p.status = 1
ORDER BY p.product_id DESC";

$stmt = $connect->prepare($sql);
$stmt->bind_param('i', $branchId);
$stmt->execute();
$result = $stmt->get_result();
$products = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'product_id' => (int)$row['product_id'],
            'branch_id' => (int)$row['branch_id'],
            'product_name' => $row['product_name'],
            'product_image' => normalize_image_url($row['product_image']),
            'brand_id' => (int)$row['brand_id'],
            'categories_id' => (int)$row['categories_id'],
            'quantity' => (int)$row['quantity'],
            'rate' => (float)$row['rate'],
            'active' => (int)$row['active'],
            'status' => (int)$row['status'],
            'brand_name' => $row['brand_name'],
            'categories_name' => $row['categories_name'],
        ];
    }
}

$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'data' => $products,
]);
