<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$branchId       = get_active_branch_id($connect);
$userId         = (int)$_SESSION['userId'];
$data           = get_request_data();
$orderId        = (int)($data['orderId'] ?? 0);
$payAmount      = round((float)($data['payAmount'] ?? 0), 2);
$paymentType    = (int)($data['paymentType'] ?? 0);
$paymentStatus  = (int)($data['paymentStatus'] ?? 0);

if ($orderId <= 0 || $payAmount < 0 || !in_array($paymentType, [1, 2, 3], true) || !in_array($paymentStatus, [1, 2, 3], true)) {
    send_http_error(400, 'Datos de pago inválidos.');
}

$connect->begin_transaction();

try {
    $stmt   = $connect->prepare('SELECT client_name, grand_total, paid FROM orders WHERE order_id = ? AND branch_id = ? AND order_status = 1 LIMIT 1 FOR UPDATE');
    $stmt->bind_param('ii', $orderId, $branchId);
    $stmt->execute();
    $res    = $stmt->get_result();
    $order  = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$order) {
        $connect->rollback();
        $connect->close();
        send_http_error(404, 'Orden no encontrada en la sucursal activa.');
    }

    $grandTotal     = (float)$order['grand_total'];
    $currentPaid    = (float)$order['paid'];
    $newPaid        = round($currentPaid + $payAmount, 2);
    $newDue         = round(max(0, $grandTotal - $newPaid), 2);

    if ($newDue <= 0) {
        $paymentStatus = 1; // Pago completo
    }

    $paidStr    = number_format($newPaid, 2, '.', '');
    $dueStr     = number_format($newDue, 2, '.', '');

    $updStmt    = $connect->prepare('UPDATE orders SET paid = ?, due = ?, payment_type = ?, payment_status = ? WHERE order_id = ? AND branch_id = ?');
    $updStmt->bind_param('ssiiii', $paidStr, $dueStr, $paymentType, $paymentStatus, $orderId, $branchId);
    $updStmt->execute();
    $updStmt->close();

    if ($payAmount > 0) {
        sb_record_pos_cash_impact(
            $connect,
            $branchId,
            $userId,
            'order_payment_cash',
            'in',
            $payAmount,
            $paymentType,
            'order',
            $orderId,
            "Abono a Orden #{$orderId} ({$order['client_name']})"
        );
    }

    $connect->commit();
    $connect->close();

    json_response([
        'success'   => true,
        'messages'  => 'Pago actualizado exitosamente',
        'paid'      => $newPaid,
        'due'       => $newDue,
    ]);
} catch (InvalidArgumentException $e) {
    $connect->rollback();
    $connect->close();
    send_http_error(400, $e->getMessage());
} catch (Throwable $e) {
    $connect->rollback();
    $connect->close();
    error_log('[stock-bartolo] editPayment error: ' . $e->getMessage());
    send_http_error(500, 'No se pudo actualizar el pago.');
}
