<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'DELETE']);

$data = array_merge($_GET, get_request_data());
$brandId = (int)($data['brandId'] ?? $data['id'] ?? 0);

if ($brandId <= 0) {
    send_http_error(400, 'ID de fabricante inválido.');
}

$stmt = $connect->prepare('UPDATE brands SET brand_status = 2 WHERE brand_id = ?');
$stmt->bind_param('i', $brandId);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'Error al eliminar el fabricante.');
}

$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Eliminado exitosamente',
]);
