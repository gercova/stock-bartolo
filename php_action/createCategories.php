<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$branchId = get_active_branch_id($connect);
$data = get_request_data();
$categoriesName = sanitize_clean_text($data['categoriesName'] ?? '', 'nombre de la categoría', 150, true);
$categoriesStatus = (int)($data['categoriesStatus'] ?? 0);

if (!in_array($categoriesStatus, [1, 2], true)) {
    send_http_error(400, 'Estado válido es obligatorio para crear la categoría.');
}

$stmt = $connect->prepare('INSERT INTO categories (branch_id, categories_name, categories_active, categories_status) VALUES (?, ?, ?, 1)');
$stmt->bind_param('isi', $branchId, $categoriesName, $categoriesStatus);

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
