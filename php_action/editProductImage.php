<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$branchId   = get_active_branch_id($connect);
$data       = get_request_data();
$productId  = (int)($data['productId'] ?? $_POST['productId'] ?? 0);
if ($productId <= 0 || !isset($_FILES['editProductImage'])) {
    send_http_error(400, 'Se requiere el ID del producto y una imagen válida.');
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

$dbPath = process_uploaded_product_image($_FILES['editProductImage']);

$stmt = $connect->prepare('UPDATE product SET product_image = ? WHERE product_id = ? AND branch_id = ?');
$stmt->bind_param('sii', $dbPath, $productId, $branchId);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'Error no se ha podido actualizar la imagen.');
}

$stmt->close();
$connect->close();

json_response([
    'success'   => true,
    'messages'  => 'Imagen actualizada exitosamente',
    'imageUrl'  => normalize_image_url($dbPath),
]);
