<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'DELETE']);

$branchId   = get_active_branch_id($connect);
$data       = array_merge($_GET, get_request_data());
$orderId    = (int)($data['orderId'] ?? $data['id'] ?? 0);

if ($orderId <= 0) {
    send_http_error(400, 'ID de orden inválido.');
}

$connect->begin_transaction();

try {
    $checkStmt = $connect->prepare('SELECT order_id FROM orders WHERE order_id = ? AND branch_id = ? AND order_status = 1 FOR UPDATE');
    $checkStmt->bind_param('ii', $orderId, $branchId);
    $checkStmt->execute();
    $exists = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$exists) {
        $connect->rollback();
        $connect->close();
        send_http_error(404, 'Orden no encontrada en la sucursal activa.');
    }

    // Restore stock for active items of this order
    $itemsStmt = $connect->prepare('SELECT product_id, quantity FROM order_item WHERE order_id = ? AND order_item_status = 1');
    $itemsStmt->bind_param('i', $orderId);
    $itemsStmt->execute();
    $itemsRes = $itemsStmt->get_result();

    $restoreStmt = $connect->prepare('UPDATE product SET quantity = CAST(CAST(quantity AS SIGNED) + ? AS CHAR) WHERE product_id = ? AND branch_id = ?');
    while ($item = $itemsRes->fetch_assoc()) {
        $qty = (int)$item['quantity'];
        $pid = (int)$item['product_id'];
        if ($qty > 0 && $pid > 0) {
            $restoreStmt->bind_param('iii', $qty, $pid, $branchId);
            $restoreStmt->execute();
        }
    }
    $itemsStmt->close();
    $restoreStmt->close();

    $stmt1 = $connect->prepare('UPDATE orders SET order_status = 2 WHERE order_id = ? AND branch_id = ?');
    $stmt1->bind_param('ii', $orderId, $branchId);
    $stmt1->execute();
    $stmt1->close();

    $stmt2 = $connect->prepare('UPDATE order_item SET order_item_status = 2 WHERE order_id = ?');
    $stmt2->bind_param('i', $orderId);
    $stmt2->execute();
    $stmt2->close();

    $connect->commit();
    $connect->close();

    json_response([
        'success' => true,
        'messages' => 'Orden eliminada exitosamente',
    ]);
} catch (Throwable $e) {
    $connect->rollback();
    $connect->close();
    error_log('[stock-bartolo] removeOrder error: ' . $e->getMessage());
    send_http_error(500, 'Error al eliminar la orden.');
}
