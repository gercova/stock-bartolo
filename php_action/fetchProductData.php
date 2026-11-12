<?php

require_once __DIR__ . '/core.php';
require_method(['GET', 'POST']);

$branchId   = get_active_branch_id($connect);
$includeAll = isset($_GET['all']) && $_GET['all'] === '1';
$sql        = $includeAll
    ? 'SELECT product_id, branch_id, product_name, quantity, rate, active FROM product WHERE branch_id = ? AND status = 1 AND active = 1 ORDER BY product_name ASC'
    : 'SELECT product_id, branch_id, product_name, quantity, rate, active FROM product WHERE branch_id = ? AND status = 1 AND active = 1 AND CAST(quantity AS SIGNED) > 0 ORDER BY product_name ASC';

$stmt = $connect->prepare($sql);
$stmt->bind_param('i', $branchId);
$stmt->execute();
$result = $stmt->get_result();
$products = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'product_id'    => (int)$row['product_id'],
            'branch_id'     => (int)$row['branch_id'],
            'product_name'  => $row['product_name'],
            'quantity'      => (int)$row['quantity'],
            'rate'          => (float)$row['rate'],
            'active'        => (int)$row['active'],
        ];
    }
}

$stmt->close();
$connect->close();

json_response([
    'success'   => true,
    'data'      => $products,
]);
