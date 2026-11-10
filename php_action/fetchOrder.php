<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$sql = "SELECT
    o.order_id,
    o.order_date,
    o.client_name,
    o.client_contact,
    o.sub_total,
    o.vat,
    o.total_amount,
    o.discount,
    o.grand_total,
    o.paid,
    o.due,
    o.payment_type,
    o.payment_status,
    o.order_status,
    (SELECT COUNT(*) FROM order_item oi WHERE oi.order_id = o.order_id AND oi.order_item_status = 1) AS item_count
FROM orders o
WHERE o.order_status = 1
ORDER BY o.order_id DESC";

$result = $connect->query($sql);
$orders = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = [
            'order_id' => (int)$row['order_id'],
            'order_date' => $row['order_date'],
            'client_name' => $row['client_name'],
            'client_contact' => $row['client_contact'],
            'sub_total' => (float)$row['sub_total'],
            'vat' => (float)$row['vat'],
            'total_amount' => (float)$row['total_amount'],
            'discount' => (float)$row['discount'],
            'grand_total' => (float)$row['grand_total'],
            'paid' => (float)$row['paid'],
            'due' => (float)$row['due'],
            'payment_type' => (int)$row['payment_type'],
            'payment_status' => (int)$row['payment_status'],
            'item_count' => (int)$row['item_count'],
        ];
    }
}

$connect->close();

json_response([
    'success' => true,
    'data' => $orders,
]);
