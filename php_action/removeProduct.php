<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'DELETE']);

$data = array_merge($_GET, get_request_data());
$productId = (int)($data['productId'] ?? $data['id'] ?? 0);

if ($productId <= 0) {
    send_http_error(400, 'ID de producto inválido.');
}

$stmt = $connect->prepare('UPDATE product SET active = 2, status = 2 WHERE product_id = ?');
$stmt->bind_param('i', $productId);

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
