<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'DELETE']);

$data = array_merge($_GET, get_request_data());
$categoriesId = (int)($data['categoriesId'] ?? $data['id'] ?? 0);

if ($categoriesId <= 0) {
    send_http_error(400, 'ID de categoría inválido.');
}

$stmt = $connect->prepare('UPDATE categories SET categories_status = 2 WHERE categories_id = ?');
$stmt->bind_param('i', $categoriesId);

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
