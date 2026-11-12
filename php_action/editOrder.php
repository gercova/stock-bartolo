<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);

$branchId = get_active_branch_id($connect);
$activeBranch = get_active_branch($connect);
$userId = (int)$_SESSION['userId'];
$data = get_request_data();

$orderId = (int)($data['orderId'] ?? 0);
$orderDate = validate_iso_date($data['orderDate'] ?? '', 'fecha de orden', true);
$clientName = sanitize_clean_text($data['clientName'] ?? '', 'nombre del cliente', 200, true);
$clientContact = sanitize_clean_text($data['clientContact'] ?? '', 'teléfono del cliente', 100, true);
$discount = round((float)($data['discount'] ?? 0), 2);
$paid = round((float)($data['paid'] ?? 0), 2);
$paymentType = (int)($data['paymentType'] ?? 0);
$paymentStatus = (int)($data['paymentStatus'] ?? 0);
$items = $data['items'] ?? [];

// Enforce open cash register for all commercial/business operations across all branches
$openSession = sb_get_open_cash_session($connect, $branchId);
if (!$openSession) {
    send_http_error(400, 'Para modificar órdenes u operaciones comerciales, la caja registradora de la sucursal debe estar abierta. Por favor, abre un turno de caja antes de continuar.');
}

if ($orderId <= 0 || $discount < 0 || $paid < 0 || !in_array($paymentType, [1, 2, 3], true) || !in_array($paymentStatus, [1, 2, 3], true) || !is_array($items) || count($items) === 0) {
    send_http_error(400, 'Completa todos los campos obligatorios de la orden con valores válidos.');
}

$taxRatePercent = (float)($activeBranch['tax_rate'] ?? 13.0);

$connect->begin_transaction();

try {
    $checkStmt = $connect->prepare('SELECT order_id, paid FROM orders WHERE order_id = ? AND branch_id = ? AND order_status = 1 FOR UPDATE');
    $checkStmt->bind_param('ii', $orderId, $branchId);
    $checkStmt->execute();
    $existingOrder = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$existingOrder) {
        $connect->rollback();
        $connect->close();
        send_http_error(404, 'Orden no encontrada en la sucursal activa.');
    }

    $previousPaid = round((float)$existingOrder['paid'], 2);

    // Restore previous quantities to stock
    $oldStmt = $connect->prepare('SELECT product_id, quantity FROM order_item WHERE order_id = ? AND order_item_status = 1');
    $oldStmt->bind_param('i', $orderId);
    $oldStmt->execute();
    $oldRes = $oldStmt->get_result();

    $restoreStmt = $connect->prepare('UPDATE product SET quantity = CAST(CAST(quantity AS SIGNED) + ? AS CHAR) WHERE product_id = ? AND branch_id = ?');
    while ($oldItem = $oldRes->fetch_assoc()) {
        $oldQty = (int)$oldItem['quantity'];
        $oldProdId = (int)$oldItem['product_id'];
        if ($oldProdId > 0 && $oldQty > 0) {
            $restoreStmt->bind_param('iii', $oldQty, $oldProdId, $branchId);
            $restoreStmt->execute();
        }
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

    $prodStmt = $connect->prepare('SELECT product_id, product_name, quantity, rate FROM product WHERE product_id = ? AND branch_id = ? AND status = 1 FOR UPDATE');
    $updProdStmt = $connect->prepare('UPDATE product SET quantity = ? WHERE product_id = ? AND branch_id = ?');

    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Formato de fila de producto inválido.');
        }
        $productId = (int)($item['productId'] ?? $item['product_id'] ?? 0);
        $qty = (int)($item['quantity'] ?? 0);
        $isCustom = !empty($item['isCustom']) || !empty($item['is_custom']) || $productId === 0;

        if ($qty <= 0) {
            throw new InvalidArgumentException('Cada producto debe tener una cantidad mayor a 0.');
        }

        if ($isCustom) {
            $customName = sanitize_clean_text($item['customName'] ?? $item['item_name'] ?? $item['product_name'] ?? 'Ítem personalizado', 'concepto del ítem', 255, true);
            $rate = round(max(0.0, (float)($item['rate'] ?? 0)), 2);
            if ($rate <= 0) {
                throw new InvalidArgumentException("El ítem personalizado '{$customName}' debe tener un precio mayor a 0.");
            }
            $lineTotal = round($rate * $qty, 2);
            $subTotal += $lineTotal;

            $resolvedItems[] = [
                'product_id' => 0,
                'item_name'  => $customName,
                'quantity'   => (string)$qty,
                'rate'       => number_format($rate, 2, '.', ''),
                'total'      => number_format($lineTotal, 2, '.', ''),
            ];
        } else {
            $prodStmt->bind_param('ii', $productId, $branchId);
            $prodStmt->execute();
            $prodRes = $prodStmt->get_result();
            $product = $prodRes ? $prodRes->fetch_assoc() : null;

            if (!$product) {
                throw new InvalidArgumentException('Producto #' . $productId . ' no encontrado en la sucursal activa.');
            }

            $currentStock = (int)$product['quantity'];
            if ($qty > $currentStock) {
                throw new InvalidArgumentException("Stock insuficiente para {$product['product_name']} (disponible: {$currentStock}).");
            }

            $rate = round((float)$product['rate'], 2);
            $lineTotal = round($rate * $qty, 2);
            $subTotal += $lineTotal;

            $newStock = (string)($currentStock - $qty);
            $updProdStmt->bind_param('sii', $newStock, $productId, $branchId);
            $updProdStmt->execute();

            $resolvedItems[] = [
                'product_id' => $productId,
                'item_name'  => $product['product_name'],
                'quantity'   => (string)$qty,
                'rate'       => number_format($rate, 2, '.', ''),
                'total'      => number_format($lineTotal, 2, '.', ''),
            ];
        }
    }

    $prodStmt->close();
    $updProdStmt->close();

    $subTotal = round($subTotal, 2);
    $vat = round($subTotal * ($taxRatePercent / 100), 2);
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

    $orderStmt = $connect->prepare('UPDATE orders SET order_date = ?, client_name = ?, client_contact = ?, sub_total = ?, vat = ?, total_amount = ?, discount = ?, grand_total = ?, paid = ?, due = ?, payment_type = ?, payment_status = ?, order_status = 1 WHERE order_id = ? AND branch_id = ?');
    $orderStmt->bind_param(
        'ssssssssssiiii',
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
        $orderId,
        $branchId
    );
    $orderStmt->execute();
    $orderStmt->close();

    $itemStmt = $connect->prepare('INSERT INTO order_item (order_id, product_id, item_name, quantity, rate, total, order_item_status) VALUES (?, ?, ?, ?, ?, ?, 1)');
    foreach ($resolvedItems as $ri) {
        $itemStmt->bind_param('iissss', $orderId, $ri['product_id'], $ri['item_name'], $ri['quantity'], $ri['rate'], $ri['total']);
        $itemStmt->execute();
    }
    $itemStmt->close();

    $paidDelta = round($paid - $previousPaid, 2);
    if ($paidDelta > 0) {
        sb_record_pos_cash_impact(
            $connect,
            $branchId,
            $userId,
            'order_payment_cash',
            'in',
            $paidDelta,
            $paymentType,
            'order',
            $orderId,
            "Ajuste de cobro en Orden #{$orderId} ({$clientName})"
        );
    }

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
    error_log('[stock-bartolo] editOrder error: ' . $e->getMessage());
    send_http_error(500, 'No se pudo actualizar la orden.');
}
