<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$data = get_request_data();
$brandName = trim((string)($data['brandName'] ?? ''));
$brandStatus = (int)($data['brandStatus'] ?? 0);

if ($brandName === '' || !in_array($brandStatus, [1, 2], true)) {
    send_http_error(400, 'Nombre y estado válido son obligatorios para crear el fabricante.');
}

$stmt = $connect->prepare('INSERT INTO brands (brand_name, brand_active, brand_status) VALUES (?, ?, 1)');
$stmt->bind_param('si', $brandName, $brandStatus);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'No se pudo guardar el fabricante.');
}

$newId = $connect->insert_id;
$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Creado exitosamente',
    'brand_id' => $newId,
], 201);
