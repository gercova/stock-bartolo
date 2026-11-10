<?php

require_once __DIR__ . '/core.php';
require_method(['GET', 'POST']);

$data = array_merge($_GET, get_request_data());
$categoriesId = (int)($data['categoriesId'] ?? $data['id'] ?? 0);

if ($categoriesId <= 0) {
    send_http_error(400, 'ID de categoría inválido.');
}

$stmt = $connect->prepare('SELECT categories_id, categories_name, categories_active, categories_status FROM categories WHERE categories_id = ? AND categories_status = 1 LIMIT 1');
$stmt->bind_param('i', $categoriesId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();
$connect->close();

if (!$row) {
    send_http_error(404, 'Categoría no encontrada.');
}

json_response([
    'success' => true,
    'data' => [
        'categories_id' => (int)$row['categories_id'],
        'categories_name' => $row['categories_name'],
        'categories_active' => (int)$row['categories_active'],
        'categories_status' => (int)$row['categories_status'],
    ],
]);
