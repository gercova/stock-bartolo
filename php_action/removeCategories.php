<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'DELETE']);

$branchId       = get_active_branch_id($connect);
$data           = array_merge($_GET, get_request_data());
$categoriesId   = (int)($data['categoriesId'] ?? $data['id'] ?? 0);

if ($categoriesId <= 0) {
    send_http_error(400, 'ID de categoría inválido.');
}

$check = $connect->prepare('SELECT categories_id FROM categories WHERE categories_id = ? AND branch_id = ? AND categories_status = 1 LIMIT 1');
$check->bind_param('ii', $categoriesId, $branchId);
$check->execute();
$exists = $check->get_result()->fetch_assoc();
$check->close();

if (!$exists) {
    $connect->close();
    send_http_error(404, 'Categoría no encontrada en la sucursal activa.');
}

$stmt = $connect->prepare('UPDATE categories SET categories_status = 2 WHERE categories_id = ? AND branch_id = ?');
$stmt->bind_param('ii', $categoriesId, $branchId);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'Error al eliminar la categoría.');
}

$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Eliminado exitosamente',
]);
