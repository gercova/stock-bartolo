<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);

$data = get_request_data();
$brandId = (int)($data['brandId'] ?? 0);
$brandName = sanitize_clean_text($data['editBrandName'] ?? $data['brandName'] ?? '', 'nombre del fabricante', 150, true);
$brandStatus = (int)($data['editBrandStatus'] ?? $data['brandStatus'] ?? 0);

if ($brandId <= 0 || !in_array($brandStatus, [1, 2], true)) {
    send_http_error(400, 'Datos inválidos para actualizar el fabricante.');
}

$stmt = $connect->prepare('UPDATE brands SET brand_name = ?, brand_active = ? WHERE brand_id = ? AND brand_status = 1');
$stmt->bind_param('sii', $brandName, $brandStatus, $brandId);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'No se pudo actualizar el fabricante.');
}

$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Actualizado exitosamente',
]);
