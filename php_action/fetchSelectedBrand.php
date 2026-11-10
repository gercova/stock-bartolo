<?php

require_once __DIR__ . '/core.php';
require_method(['GET', 'POST']);

$data = array_merge($_GET, get_request_data());
$brandId = (int)($data['brandId'] ?? $data['id'] ?? 0);

if ($brandId <= 0) {
    send_http_error(400, 'ID de fabricante inválido.');
}

$stmt = $connect->prepare('SELECT brand_id, brand_name, brand_active, brand_status FROM brands WHERE brand_id = ? AND brand_status = 1 LIMIT 1');
$stmt->bind_param('i', $brandId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();
$connect->close();

if (!$row) {
    send_http_error(404, 'Fabricante no encontrado.');
}

json_response([
    'success' => true,
    'data' => [
        'brand_id' => (int)$row['brand_id'],
        'brand_name' => $row['brand_name'],
        'brand_active' => (int)$row['brand_active'],
        'brand_status' => (int)$row['brand_status'],
    ],
]);
