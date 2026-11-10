<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$data = get_request_data();
$categoriesName = trim((string)($data['categoriesName'] ?? ''));
$categoriesStatus = (int)($data['categoriesStatus'] ?? 0);

if ($categoriesName === '' || !in_array($categoriesStatus, [1, 2], true)) {
    send_http_error(400, 'Nombre y estado válido son obligatorios para crear la categoría.');
}

$stmt = $connect->prepare('INSERT INTO categories (categories_name, categories_active, categories_status) VALUES (?, ?, 1)');
$stmt->bind_param('si', $categoriesName, $categoriesStatus);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'No se pudo guardar la categoría.');
}

$newId = $connect->insert_id;
$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Creado exitosamente',
    'categories_id' => $newId,
], 201);
