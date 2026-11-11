<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$data = get_request_data();
$orderId = (int)($data['orderId'] ?? 0);
$payAmount = round((float)($data['payAmount'] ?? 0), 2);
$paymentType = (int)($data['paymentType'] ?? 0);
$paymentStatus = (int)($data['paymentStatus'] ?? 0);

if ($orderId <= 0 || $payAmount < 0 || !in_array($paymentType, [1, 2, 3], true) || !in_array($paymentStatus, [1, 2, 3], true)) {
    send_http_error(400, 'Datos de pago inválidos.');
}

$stmt = $connect->prepare('SELECT grand_total, paid FROM orders WHERE order_id = ? AND order_status = 1 LIMIT 1');
$stmt->bind_param('i', $orderId);
$stmt->execute();
$res = $stmt->get_result();
$order = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$order) {
    $connect->close();
    send_http_error(404, 'Orden no encontrada.');
}

$grandTotal = (float)$order['grand_total'];
$currentPaid = (float)$order['paid'];
$newPaid = round($currentPaid + $payAmount, 2);
$newDue = round(max(0, $grandTotal - $newPaid), 2);

if ($newDue <= 0) {
    $paymentStatus = 1; // Pago completo
}

$paidStr = number_format($newPaid, 2, '.', '');
$dueStr = number_format($newDue, 2, '.', '');

$updStmt = $connect->prepare('UPDATE orders SET paid = ?, due = ?, payment_type = ?, payment_status = ? WHERE order_id = ?');
$updStmt->bind_param('ssiii', $paidStr, $dueStr, $paymentType, $paymentStatus, $orderId);

if (!$updStmt->execute()) {
    $updStmt->close();
    $connect->close();
    send_http_error(500, 'No se pudo actualizar el pago.');
}

$updStmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Pago actualizado exitosamente',
    'paid' => $newPaid,
    'due' => $newDue,
]);
