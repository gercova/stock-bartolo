<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);

$branchId       = get_active_branch_id($connect);
$data           = get_request_data();

$productId      = (int)($data['productId'] ?? 0);
$productName    = sanitize_clean_text($data['editProductName'] ?? $data['productName'] ?? '', 'nombre del producto', 200, true);
$rawQuantity    = $data['editQuantity'] ?? $data['quantity'] ?? null;
$rawRate        = $data['editRate'] ?? $data['rate'] ?? null;
$brandId        = (int)($data['editBrandName'] ?? $data['brandId'] ?? 0);
$categoryId     = (int)($data['editCategoryName'] ?? $data['categoriesId'] ?? 0);
$productStatus  = (int)($data['editProductStatus'] ?? $data['productStatus'] ?? 0);

if ($productId <= 0 || !is_numeric($rawQuantity) || !is_numeric($rawRate) || (int)$rawQuantity < 0 || (float)$rawRate < 0 || $brandId <= 0 || $categoryId <= 0 || !in_array($productStatus, [1, 2], true)) {
    send_http_error(400, 'Datos inválidos para actualizar el producto.');
}

$pCheck = $connect->prepare('SELECT product_id FROM product WHERE product_id = ? AND branch_id = ? AND status = 1 LIMIT 1');
$pCheck->bind_param('ii', $productId, $branchId);
$pCheck->execute();
$prodOk = $pCheck->get_result()->fetch_assoc();
$pCheck->close();
if (!$prodOk) {
    $connect->close();
    send_http_error(404, 'Producto no encontrado en la sucursal activa.');
}

$bStmt = $connect->prepare('SELECT brand_id FROM brands WHERE brand_id = ? AND branch_id = ? AND brand_status = 1 LIMIT 1');
$bStmt->bind_param('ii', $brandId, $branchId);
$bStmt->execute();
$brandOk = $bStmt->get_result()->fetch_assoc();
$bStmt->close();
if (!$brandOk) {
    $connect->close();
    send_http_error(400, 'El fabricante seleccionado no pertenece a la sucursal activa.');
}

$cStmt = $connect->prepare('SELECT categories_id FROM categories WHERE categories_id = ? AND branch_id = ? AND categories_status = 1 LIMIT 1');
$cStmt->bind_param('ii', $categoryId, $branchId);
$cStmt->execute();
$catOk = $cStmt->get_result()->fetch_assoc();
$cStmt->close();
if (!$catOk) {
    $connect->close();
    send_http_error(400, 'La categoría seleccionada no pertenece a la sucursal activa.');
}

$quantity = (string)(int)$rawQuantity;
$rate = number_format(round((float)$rawRate, 2), 2, '.', '');

$stmt = $connect->prepare('UPDATE product SET product_name = ?, brand_id = ?, categories_id = ?, quantity = ?, rate = ?, active = ?, status = 1 WHERE product_id = ? AND branch_id = ?');
$stmt->bind_param('siissiii', $productName, $brandId, $categoryId, $quantity, $rate, $productStatus, $productId, $branchId);

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
