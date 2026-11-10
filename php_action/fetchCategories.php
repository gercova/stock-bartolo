<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$onlyActive = isset($_GET['active']) && $_GET['active'] === '1';
$sql = $onlyActive
    ? 'SELECT categories_id, categories_name, categories_active, categories_status FROM categories WHERE categories_status = 1 AND categories_active = 1 ORDER BY categories_name ASC'
    : 'SELECT categories_id, categories_name, categories_active, categories_status FROM categories WHERE categories_status = 1 ORDER BY categories_id DESC';

$result = $connect->query($sql);
$categories = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = [
            'categories_id' => (int)$row['categories_id'],
            'categories_name' => $row['categories_name'],
            'categories_active' => (int)$row['categories_active'],
            'categories_status' => (int)$row['categories_status'],
        ];
    }
}

$connect->close();

json_response([
    'success' => true,
    'data' => $categories,
]);
