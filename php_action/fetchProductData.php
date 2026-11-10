<?php

require_once __DIR__ . '/core.php';
require_method(['GET', 'POST']);

$includeAll = isset($_GET['all']) && $_GET['all'] === '1';
$sql = $includeAll
    ? 'SELECT product_id, product_name, quantity, rate, active FROM product WHERE status = 1 AND active = 1 ORDER BY product_name ASC'
    : 'SELECT product_id, product_name, quantity, rate, active FROM product WHERE status = 1 AND active = 1 AND CAST(quantity AS SIGNED) > 0 ORDER BY product_name ASC';

$result = $connect->query($sql);
$products = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'product_id' => (int)$row['product_id'],
            'product_name' => $row['product_name'],
            'quantity' => (int)$row['quantity'],
            'rate' => (float)$row['rate'],
            'active' => (int)$row['active'],
        ];
    }
}

$connect->close();

json_response([
    'success' => true,
    'data' => $products,
]);
