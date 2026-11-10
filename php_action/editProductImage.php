<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$productId = (int)($_POST['productId'] ?? 0);
if ($productId <= 0 || !isset($_FILES['editProductImage'])) {
    send_http_error(400, 'Se requiere el ID del producto y una imagen válida.');
}

if ($_FILES['editProductImage']['error'] !== UPLOAD_ERR_OK) {
    send_http_error(400, 'Error al subir la nueva imagen.');
}

$ext = strtolower(pathinfo($_FILES['editProductImage']['name'], PATHINFO_EXTENSION));
$allowed = ['gif', 'jpg', 'jpeg', 'png', 'webp'];
if (!in_array($ext, $allowed, true)) {
    send_http_error(400, 'Formato de imagen no permitido. Usa JPG, PNG, GIF o WEBP.');
}

$uploadDir = dirname(__DIR__) . '/assests/images/stock';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$filename = str_replace('.', '_', uniqid((string)rand(), true)) . '.' . $ext;
$destDisk = $uploadDir . '/' . $filename;
$dbPath = '../assests/images/stock/' . $filename;

if (!is_uploaded_file($_FILES['editProductImage']['tmp_name']) || !move_uploaded_file($_FILES['editProductImage']['tmp_name'], $destDisk)) {
    send_http_error(500, 'No se pudo almacenar la imagen en el servidor.');
}

$stmt = $connect->prepare('UPDATE product SET product_image = ? WHERE product_id = ?');
$stmt->bind_param('si', $dbPath, $productId);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'Error no se ha podido actualizar la imagen.');
}

$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Imagen actualizada exitosamente',
    'imageUrl' => normalize_image_url($dbPath),
]);
