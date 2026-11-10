<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$onlyActive = isset($_GET['active']) && $_GET['active'] === '1';
$sql = $onlyActive
    ? 'SELECT brand_id, brand_name, brand_active, brand_status FROM brands WHERE brand_status = 1 AND brand_active = 1 ORDER BY brand_name ASC'
    : 'SELECT brand_id, brand_name, brand_active, brand_status FROM brands WHERE brand_status = 1 ORDER BY brand_id DESC';

$result = $connect->query($sql);
$brands = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $brands[] = [
            'brand_id' => (int)$row['brand_id'],
            'brand_name' => $row['brand_name'],
            'brand_active' => (int)$row['brand_active'],
            'brand_status' => (int)$row['brand_status'],
        ];
    }
}

$connect->close();

json_response([
    'success' => true,
    'data' => $brands,
]);
