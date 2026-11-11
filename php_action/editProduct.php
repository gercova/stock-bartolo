<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);

$data = get_request_data();

$productId = (int)($data['productId'] ?? 0);
$productName = sanitize_clean_text($data['editProductName'] ?? $data['productName'] ?? '', 'nombre del producto', 200, true);
$rawQuantity = $data['editQuantity'] ?? $data['quantity'] ?? null;
$rawRate = $data['editRate'] ?? $data['rate'] ?? null;
$brandId = (int)($data['editBrandName'] ?? $data['brandId'] ?? 0);
$categoryId = (int)($data['editCategoryName'] ?? $data['categoriesId'] ?? 0);
$productStatus = (int)($data['editProductStatus'] ?? $data['productStatus'] ?? 0);

if ($productId <= 0 || !is_numeric($rawQuantity) || !is_numeric($rawRate) || (int)$rawQuantity < 0 || (float)$rawRate < 0 || $brandId <= 0 || $categoryId <= 0 || !in_array($productStatus, [1, 2], true)) {
    send_http_error(400, 'Datos inválidos para actualizar el producto.');
}

$quantity = (string)(int)$rawQuantity;
$rate = number_format(round((float)$rawRate, 2), 2, '.', '');

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
