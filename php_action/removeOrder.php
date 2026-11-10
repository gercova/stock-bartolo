<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'DELETE']);

$data = array_merge($_GET, get_request_data());
$orderId = (int)($data['orderId'] ?? $data['id'] ?? 0);

if ($orderId <= 0) {
    send_http_error(400, 'ID de orden inválido.');
}

$stmt1 = $connect->prepare('UPDATE orders SET order_status = 2 WHERE order_id = ?');
$stmt1->bind_param('i', $orderId);
$ok1 = $stmt1->execute();
$stmt1->close();

$stmt2 = $connect->prepare('UPDATE order_item SET order_item_status = 2 WHERE order_id = ?');
$stmt2->bind_param('i', $orderId);
$ok2 = $stmt2->execute();
$stmt2->close();

$connect->close();

if (!$ok1 || !$ok2) {
    send_http_error(500, 'Error al eliminar la orden.');
}

json_response([
    'success' => true,
    'messages' => 'Orden eliminada exitosamente',
]);
