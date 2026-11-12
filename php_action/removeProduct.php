<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'DELETE']);

$branchId = get_active_branch_id($connect);
$data = array_merge($_GET, get_request_data());
$productId = (int)($data['productId'] ?? $data['id'] ?? 0);

if ($productId <= 0) {
    send_http_error(400, 'ID de producto inválido.');
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

$stmt = $connect->prepare('UPDATE product SET active = 2, status = 2 WHERE product_id = ? AND branch_id = ?');
$stmt->bind_param('ii', $productId, $branchId);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'Error no se ha podido eliminar el producto.');
}

$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Eliminado exitosamente',
]);
