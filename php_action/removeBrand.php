<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'DELETE']);

$branchId   = get_active_branch_id($connect);
$data       = array_merge($_GET, get_request_data());
$brandId    = (int)($data['brandId'] ?? $data['id'] ?? 0);

if ($brandId <= 0) {
    send_http_error(400, 'ID de fabricante inválido.');
}

$check  = $connect->prepare('SELECT brand_id FROM brands WHERE brand_id = ? AND branch_id = ? AND brand_status = 1 LIMIT 1');
$check->bind_param('ii', $brandId, $branchId);
$check->execute();
$exists = $check->get_result()->fetch_assoc();
$check->close();

if (!$exists) {
    $connect->close();
    send_http_error(404, 'Fabricante no encontrado en la sucursal activa.');
}

$stmt = $connect->prepare('UPDATE brands SET brand_status = 2 WHERE brand_id = ? AND branch_id = ?');
$stmt->bind_param('ii', $brandId, $branchId);

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
