<?php

require_once __DIR__ . '/core.php';
require_method(['GET', 'POST']);

$branchId = get_active_branch_id($connect);
$activeBranch = get_active_branch($connect);
$data = array_merge($_GET, get_request_data());
$orderId = (int)($data['orderId'] ?? $data['id'] ?? 0);

if ($orderId <= 0) {
    send_http_error(400, 'ID de orden inválido.');
}

$stmt = $connect->prepare('SELECT order_id, branch_id, order_date, client_name, client_contact, sub_total, vat, total_amount, discount, grand_total, paid, due, payment_type, payment_status FROM orders WHERE order_id = ? AND branch_id = ? AND order_status = 1 LIMIT 1');
$stmt->bind_param('ii', $orderId, $branchId);
$stmt->execute();
$res = $stmt->get_result();
$order = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$order) {
    $connect->close();
    send_http_error(404, 'Orden no encontrada en la sucursal activa.');
}

$itemStmt = $connect->prepare('SELECT oi.product_id, oi.rate, oi.quantity, oi.total, COALESCE(p.product_name, "Producto") AS product_name FROM order_item oi LEFT JOIN product p ON oi.product_id = p.product_id WHERE oi.order_id = ? AND oi.order_item_status = 1');
$itemStmt->bind_param('i', $orderId);
$itemStmt->execute();
$itemRes = $itemStmt->get_result();
$items = [];
while ($row = $itemRes->fetch_assoc()) {
    $items[] = [
        'product_id'    => (int)$row['product_id'],
        'product_name'  => $row['product_name'],
        'rate'          => (float)$row['rate'],
        'quantity'      => (int)$row['quantity'],
        'total'         => (float)$row['total'],
    ];
}
$itemStmt->close();
$connect->close();

json_response([
    'success'   => true,
    'branch'    => $activeBranch,
    'order'     => [
        'order_id' => (int)$order['order_id'],
        'branch_id' => (int)$order['branch_id'],
        'order_date' => $order['order_date'],
        'client_name' => $order['client_name'],
        'client_contact' => $order['client_contact'],
        'sub_total' => (float)$order['sub_total'],
        'vat' => (float)$order['vat'],
        'total_amount' => (float)$order['total_amount'],
        'discount' => (float)$order['discount'],
        'grand_total' => (float)$order['grand_total'],
        'paid' => (float)$order['paid'],
        'due' => (float)$order['due'],
        'payment_type' => (int)$order['payment_type'],
        'payment_status' => (int)$order['payment_status'],
    ],
    'items' => $items,
]);
