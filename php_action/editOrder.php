<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);

$data = get_request_data();

$orderId = (int)($data['orderId'] ?? 0);
$rawDate = trim((string)($data['orderDate'] ?? ''));
$clientName = trim((string)($data['clientName'] ?? ''));
$clientContact = trim((string)($data['clientContact'] ?? ''));
$discount = round((float)($data['discount'] ?? 0), 2);
$paid = round((float)($data['paid'] ?? 0), 2);
$paymentType = (int)($data['paymentType'] ?? 0);
$paymentStatus = (int)($data['paymentStatus'] ?? 0);
$items = $data['items'] ?? [];

if ($orderId <= 0 || $rawDate === '' || $clientName === '' || $clientContact === '' || $paymentType <= 0 || $paymentStatus <= 0 || !is_array($items) || count($items) === 0) {
    send_http_error(400, 'Completa todos los campos obligatorios de la orden.');
}

$orderDate = date('Y-m-d', strtotime($rawDate));

$connect->begin_transaction();

try {
    // Restore previous quantities to stock
    $oldStmt = $connect->prepare('SELECT product_id, quantity FROM order_item WHERE order_id = ? AND order_item_status = 1');
    $oldStmt->bind_param('i', $orderId);
    $oldStmt->execute();
    $oldRes = $oldStmt->get_result();

    $restoreStmt = $connect->prepare('UPDATE product SET quantity = CAST(CAST(quantity AS SIGNED) + ? AS CHAR) WHERE product_id = ?');
    while ($oldItem = $oldRes->fetch_assoc()) {
        $oldQty = (int)$oldItem['quantity'];
        $oldProdId = (int)$oldItem['product_id'];
        $restoreStmt->bind_param('ii', $oldQty, $oldProdId);
        $restoreStmt->execute();
    }
    $oldStmt->close();
    $restoreStmt->close();

    // Remove existing items
    $delStmt = $connect->prepare('DELETE FROM order_item WHERE order_id = ?');
    $delStmt->bind_param('i', $orderId);
    $delStmt->execute();
    $delStmt->close();

    $subTotal = 0.0;
    $resolvedItems = [];

    $prodStmt = $connect->prepare('SELECT product_id, product_name, quantity, rate FROM product WHERE product_id = ? AND status = 1 FOR UPDATE');
    $updProdStmt = $connect->prepare('UPDATE product SET quantity = ? WHERE product_id = ?');

    foreach ($items as $item) {
        $productId = (int)($item['productId'] ?? $item['product_id'] ?? 0);
        $qty = (int)($item['quantity'] ?? 0);
        if ($productId <= 0 || $qty <= 0) {
            throw new InvalidArgumentException('Cada producto debe tener una cantidad mayor a 0.');
        }

        $prodStmt->bind_param('i', $productId);
        $prodStmt->execute();
        $prodRes = $prodStmt->get_result();
        $product = $prodRes ? $prodRes->fetch_assoc() : null;

        if (!$product) {
            throw new RuntimeException('Producto #' . $productId . ' no encontrado.');
        }

        $currentStock = (int)$product['quantity'];
        if ($qty > $currentStock) {
            throw new InvalidArgumentException("Stock insuficiente para {$product['product_name']} (disponible: {$currentStock}).");
        }

        $rate = round((float)$product['rate'], 2);
        $lineTotal = round($rate * $qty, 2);
        $subTotal += $lineTotal;

        $newStock = (string)($currentStock - $qty);
        $updProdStmt->bind_param('si', $newStock, $productId);
        $updProdStmt->execute();

        $resolvedItems[] = [
            'product_id' => $productId,
            'quantity' => (string)$qty,
            'rate' => number_format($rate, 2, '.', ''),
            'total' => number_format($lineTotal, 2, '.', ''),
        ];
    }

    $prodStmt->close();
    $updProdStmt->close();

    $subTotal = round($subTotal, 2);
    $vat = round($subTotal * 0.13, 2);
    $totalAmount = round($subTotal + $vat, 2);
    $grandTotal = round(max(0, $totalAmount - $discount), 2);
    $due = round(max(0, $grandTotal - $paid), 2);

    $subTotalStr = number_format($subTotal, 2, '.', '');
    $vatStr = number_format($vat, 2, '.', '');
    $totalAmountStr = number_format($totalAmount, 2, '.', '');
    $discountStr = number_format($discount, 2, '.', '');
    $grandTotalStr = number_format($grandTotal, 2, '.', '');
    $paidStr = number_format($paid, 2, '.', '');
    $dueStr = number_format($due, 2, '.', '');

    $orderStmt = $connect->prepare('UPDATE orders SET order_date = ?, client_name = ?, client_contact = ?, sub_total = ?, vat = ?, total_amount = ?, discount = ?, grand_total = ?, paid = ?, due = ?, payment_type = ?, payment_status = ?, order_status = 1 WHERE order_id = ?');
    $orderStmt->bind_param(
        'ssssssssssiii',
        $orderDate,
        $clientName,
        $clientContact,
        $subTotalStr,
        $vatStr,
        $totalAmountStr,
        $discountStr,
        $grandTotalStr,
        $paidStr,
        $dueStr,
        $paymentType,
        $paymentStatus,
        $orderId
    );
    $orderStmt->execute();
    $orderStmt->close();

    $itemStmt = $connect->prepare('INSERT INTO order_item (order_id, product_id, quantity, rate, total, order_item_status) VALUES (?, ?, ?, ?, ?, 1)');
    foreach ($resolvedItems as $ri) {
        $itemStmt->bind_param('iisss', $orderId, $ri['product_id'], $ri['quantity'], $ri['rate'], $ri['total']);
        $itemStmt->execute();
    }
    $itemStmt->close();

    $connect->commit();
    $connect->close();

    json_response([
        'success' => true,
        'messages' => 'Orden actualizada exitosamente',
    ]);
} catch (InvalidArgumentException $e) {
    $connect->rollback();
    $connect->close();
    send_http_error(400, $e->getMessage());
} catch (Throwable $e) {
    $connect->rollback();
    $connect->close();
    send_http_error(500, 'No se pudo actualizar la orden: ' . $e->getMessage());
}
