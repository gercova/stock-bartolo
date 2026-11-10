<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);

$data = $_POST ?: get_request_data();

$productId = (int)($data['productId'] ?? 0);
$productName = trim((string)($data['editProductName'] ?? $data['productName'] ?? ''));
$rawQuantity = $data['editQuantity'] ?? $data['quantity'] ?? null;
$rawRate = $data['editRate'] ?? $data['rate'] ?? null;
$brandId = (int)($data['editBrandName'] ?? $data['brandId'] ?? 0);
$categoryId = (int)($data['editCategoryName'] ?? $data['categoriesId'] ?? 0);
$productStatus = (int)($data['editProductStatus'] ?? $data['productStatus'] ?? 0);

if ($productId <= 0 || $productName === '' || !is_numeric($rawQuantity) || !is_numeric($rawRate) || $brandId <= 0 || $categoryId <= 0 || !in_array($productStatus, [1, 2], true)) {
    send_http_error(400, 'Datos inválidos para actualizar el producto.');
}

$quantity = (string)(int)$rawQuantity;
$rate = (string)round((float)$rawRate, 2);

$stmt = $connect->prepare('UPDATE product SET product_name = ?, brand_id = ?, categories_id = ?, quantity = ?, rate = ?, active = ?, status = 1 WHERE product_id = ?');
$stmt->bind_param('siissii', $productName, $brandId, $categoryId, $quantity, $rate, $productStatus, $productId);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'Error no se ha podido actualizar el producto.');
}

$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Actualizado exitosamente',
]);
