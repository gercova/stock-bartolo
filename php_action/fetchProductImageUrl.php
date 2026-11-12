<?php

require_once __DIR__ . '/core.php';
require_method(['GET', 'POST']);

$branchId   = get_active_branch_id($connect);
$productId  = (int)($_GET['i'] ?? $_GET['productId'] ?? $_POST['productId'] ?? 0);
if ($productId <= 0) {
    send_http_error(400, 'ID de producto inválido.');
}

$stmt = $connect->prepare('SELECT product_image FROM product WHERE product_id = ? AND branch_id = ? LIMIT 1');
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
    'imageUrl' => normalize_image_url($row['product_image']),
]);
