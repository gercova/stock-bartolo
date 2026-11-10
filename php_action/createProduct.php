<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$data = $_POST ?: get_request_data();

$productName = trim((string)($data['productName'] ?? ''));
$quantity = isset($data['quantity']) ? (string)(int)$data['quantity'] : '';
$rate = isset($data['rate']) ? (string)round((float)$data['rate'], 2) : '';
$brandId = (int)($data['brandName'] ?? $data['brandId'] ?? 0);
$categoryId = (int)($data['categoryName'] ?? $data['categoriesId'] ?? 0);
$productStatus = (int)($data['productStatus'] ?? 0);

if ($productName === '' || $quantity === '' || !is_numeric($data['quantity'] ?? null) || !is_numeric($data['rate'] ?? null) || $brandId <= 0 || $categoryId <= 0 || !in_array($productStatus, [1, 2], true)) {
    send_http_error(400, 'Todos los campos del producto son obligatorios y deben tener valores válidos.');
}

$imagePath = '../assests/images/photo_default.png';

if (isset($_FILES['productImage']) && $_FILES['productImage']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['productImage']['error'] !== UPLOAD_ERR_OK) {
        send_http_error(400, 'Error al subir la imagen del producto.');
    }
    $ext = strtolower(pathinfo($_FILES['productImage']['name'], PATHINFO_EXTENSION));
    $allowed = ['gif', 'jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        send_http_error(400, 'Formato de imagen no permitido. Usa JPG, PNG, GIF o WEBP.');
    }
    if ($_FILES['productImage']['size'] > 5 * 1024 * 1024) {
        send_http_error(400, 'La imagen supera el tamaño máximo permitido de 5 MB.');
    }

    $uploadDir = dirname(__DIR__) . '/assests/images/stock';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $filename = uniqid((string)rand(), true) . '.' . $ext;
    $filename = str_replace('.', '_', pathinfo($filename, PATHINFO_FILENAME)) . '.' . $ext;
    $destDisk = $uploadDir . '/' . $filename;

    if (!is_uploaded_file($_FILES['productImage']['tmp_name']) || !move_uploaded_file($_FILES['productImage']['tmp_name'], $destDisk)) {
        send_http_error(500, 'No se pudo almacenar la imagen subida en el servidor.');
    }

    $imagePath = '../assests/images/stock/' . $filename;
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
