<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);

$data = get_request_data();
$categoriesId = (int)($data['editCategoriesId'] ?? $data['categoriesId'] ?? 0);
$categoriesName = sanitize_clean_text($data['editCategoriesName'] ?? $data['categoriesName'] ?? '', 'nombre de la categoría', 150, true);
$categoriesStatus = (int)($data['editCategoriesStatus'] ?? $data['categoriesStatus'] ?? 0);

if ($categoriesId <= 0 || !in_array($categoriesStatus, [1, 2], true)) {
    send_http_error(400, 'Datos inválidos para actualizar la categoría.');
}

$stmt = $connect->prepare('UPDATE categories SET categories_name = ?, categories_active = ? WHERE categories_id = ? AND categories_status = 1');
$stmt->bind_param('sii', $categoriesName, $categoriesStatus, $categoriesId);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'No se pudo actualizar la categoría.');
}

$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Actualizado exitosamente',
]);
