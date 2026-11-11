<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$data = get_request_data();

$productName = sanitize_clean_text($data['productName'] ?? '', 'nombre del producto', 200, true);
$rawQuantity = $data['quantity'] ?? null;
$rawRate = $data['rate'] ?? null;
$brandId = (int)($data['brandName'] ?? $data['brandId'] ?? 0);
$categoryId = (int)($data['categoryName'] ?? $data['categoriesId'] ?? 0);
$productStatus = (int)($data['productStatus'] ?? 0);

if (!is_numeric($rawQuantity) || !is_numeric($rawRate) || (int)$rawQuantity < 0 || (float)$rawRate < 0 || $brandId <= 0 || $categoryId <= 0 || !in_array($productStatus, [1, 2], true)) {
    send_http_error(400, 'Todos los campos del producto son obligatorios y deben tener valores válidos.');
}

$quantity = (string)(int)$rawQuantity;
$rate = number_format(round((float)$rawRate, 2), 2, '.', '');
$imagePath = '../assests/images/photo_default.png';

if (isset($_FILES['productImage']) && $_FILES['productImage']['error'] !== UPLOAD_ERR_NO_FILE) {
    $imagePath = process_uploaded_product_image($_FILES['productImage']);
}

$stmt = $connect->prepare('INSERT INTO product (product_name, product_image, brand_id, categories_id, quantity, rate, active, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
$stmt->bind_param('ssiissi', $productName, $imagePath, $brandId, $categoryId, $quantity, $rate, $productStatus);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'Error no se ha podido guardar el producto.');
}

$newId = $connect->insert_id;
$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Creado exitosamente',
    'product_id' => $newId,
], 201);
