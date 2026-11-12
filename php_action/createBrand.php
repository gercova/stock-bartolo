<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$branchId   = get_active_branch_id($connect);
$data       = get_request_data();
$brandName  = sanitize_clean_text($data['brandName'] ?? '', 'nombre del fabricante', 150, true);
$brandStatus = (int)($data['brandStatus'] ?? 0);

if (!in_array($brandStatus, [1, 2], true)) {
    send_http_error(400, 'Estado válido es obligatorio para crear el fabricante.');
}

$stmt = $connect->prepare('INSERT INTO brands (branch_id, brand_name, brand_active, brand_status) VALUES (?, ?, ?, 1)');
$stmt->bind_param('isi', $branchId, $brandName, $brandStatus);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'No se pudo guardar el fabricante.');
}

$newId = $connect->insert_id;
$stmt->close();
$connect->close();

json_response([
    'success'   => true,
    'messages'  => 'Creado exitosamente',
    'brand_id'  => $newId,
], 201);
