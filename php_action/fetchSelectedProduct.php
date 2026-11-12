<?php

require_once __DIR__ . '/core.php';
require_method(['GET', 'POST']);

$branchId = get_active_branch_id($connect);
$data = array_merge($_GET, get_request_data());
$productId = (int)($data['productId'] ?? $data['id'] ?? 0);

if ($productId <= 0) {
    send_http_error(400, 'ID de producto inválido.');
}

$stmt = $connect->prepare('SELECT product_id, branch_id, product_name, product_image, brand_id, categories_id, quantity, rate, active, status FROM product WHERE product_id = ? AND branch_id = ? AND status = 1 LIMIT 1');
$stmt->bind_param('ii', $productId, $branchId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();
$connect->close();

if (!$row) {
    send_http_error(404, 'Producto no encontrado en la sucursal activa.');
}

json_response([
    'success' => true,
    'data' => [
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
    ],
]);
