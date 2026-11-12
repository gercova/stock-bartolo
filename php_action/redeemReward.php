<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

sb_assert_permission($connect, 'operations', 'create');

$data = get_request_data();
$branchId = get_active_branch_id($connect);
$userId = (int)($_SESSION['userId'] ?? 1);

$clientId = (int)($data['client_id'] ?? $data['clientId'] ?? 0);
$rewardId = (int)($data['reward_id'] ?? $data['rewardId'] ?? 0);
$notes = trim((string)($data['notes'] ?? ''));
$resetBalance = isset($data['reset_balance']) ? (bool)$data['reset_balance'] : true;

if ($clientId <= 0) {
    send_http_error(400, 'Debes seleccionar un cliente válido para el canje.');
}

if ($rewardId <= 0) {
    send_http_error(400, 'Debes seleccionar un premio válido del catálogo.');
}

$connect->begin_transaction();

try {
    // Lock client row FOR UPDATE
    $cStmt = $connect->prepare('SELECT client_id, name, document_type, document_number, phone, points_balance, lifetime_points FROM clients WHERE client_id = ? AND record_status = 1 LIMIT 1 FOR UPDATE');
    $cStmt->bind_param('i', $clientId);
    $cStmt->execute();
    $client = $cStmt->get_result()->fetch_assoc();
    $cStmt->close();

    if (!$client) {
        throw new RuntimeException('El cliente seleccionado no existe o está inactivo.');
    }

    $currentBalance = (int)$client['points_balance'];
    $lifetimePoints = (int)$client['lifetime_points'];

    // Lock reward row FOR UPDATE
    $rStmt = $connect->prepare('SELECT reward_id, name, description, points_cost, price_soles, stock, image AS image_url FROM reward_items WHERE reward_id = ? AND record_status = 1 LIMIT 1 FOR UPDATE');
    $rStmt->bind_param('i', $rewardId);
    $rStmt->execute();
    $reward = $rStmt->get_result()->fetch_assoc();
    $rStmt->close();

    if (!$reward) {
        throw new RuntimeException('El premio seleccionado no existe o no está disponible.');
    }

    $pointsCost = (int)$reward['points_cost'];
    $priceSoles = (float)$reward['price_soles'];
    $stock = (int)$reward['stock'];

    if ($stock <= 0) {
        throw new RuntimeException("El premio '{$reward['name']}' no cuenta con stock disponible en este momento.");
    }

    if ($currentBalance < $pointsCost) {
        throw new RuntimeException("Puntos insuficientes. El cliente posee {$currentBalance} puntos y el premio '{$reward['name']}' requiere {$pointsCost} puntos.");
    }

    // Points calculation: reset balance to 0 (per specification) or deduct cost if partial
    $newBalance = $resetBalance ? 0 : ($currentBalance - $pointsCost);
    $pointsSpent = $resetBalance ? $currentBalance : $pointsCost;

    // Decrement reward stock
    $updReward = $connect->prepare('UPDATE reward_items SET stock = stock - 1, updated_at = NOW() WHERE reward_id = ?');
    $updReward->bind_param('i', $rewardId);
    $updReward->execute();
    $updReward->close();

    // Generate redemption code
    $seqStmt = $connect->prepare('SELECT COUNT(*) AS c FROM reward_redemptions WHERE branch_id = ?');
    $seqStmt->bind_param('i', $branchId);
    $seqStmt->execute();
    $redSeq = ((int)($seqStmt->get_result()->fetch_assoc()['c'] ?? 0)) + 1;
    $seqStmt->close();
    $redCode = sprintf('CANJE-%03d-%06d', $branchId, $redSeq);

    // Insert redemption record
    $insRed = $connect->prepare('INSERT INTO reward_redemptions (
        redemption_code, branch_id, client_id, user_id, reward_id, points_spent, quantity, points_balance_before, points_balance_after, notes, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW())');
    $insRed->bind_param('siiiiiiis', $redCode, $branchId, $clientId, $userId, $rewardId, $pointsSpent, $currentBalance, $newBalance, $notes);
    if (!$insRed->execute()) {
        throw new RuntimeException('Error al registrar el canje: ' . $insRed->error);
    }
    $redemptionId = (int)$insRed->insert_id;
    $insRed->close();

    // Update client points_balance (reset/deducted) while lifetime_points is strictly preserved
    $updClient = $connect->prepare('UPDATE clients SET points_balance = ?, updated_at = NOW() WHERE client_id = ?');
    $updClient->bind_param('ii', $newBalance, $clientId);
    $updClient->execute();
    $updClient->close();

    // Record in client_points_history
    $desc = "Canje de premio: {$reward['name']} (Valor: S/ " . number_format($priceSoles, 2) . ", costo: {$pointsCost} pts). Saldo reiniciado de {$currentBalance} a {$newBalance} pts.";
    $hType = 'redeemed';
    $hPoints = -$pointsSpent;
    $insHist = $connect->prepare('INSERT INTO client_points_history (
        client_id, redemption_id, type, points, lifetime_after, balance_after, description, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    $insHist->bind_param('iisiiss', $clientId, $redemptionId, $hType, $hPoints, $lifetimePoints, $newBalance, $desc);
    $insHist->execute();
    $insHist->close();

    // Log audit
    sb_log_audit(
        $connect,
        'operations',
        'REDEEM_REWARD',
        "Canje de premio '{$reward['name']}' para cliente '{$client['name']}' ({$pointsSpent} puntos utilizados)",
        [
            'redemption_id' => $redemptionId,
            'client_id' => $clientId,
            'client_name' => $client['name'],
            'reward_id' => $rewardId,
            'reward_name' => $reward['name'],
            'points_spent' => $pointsSpent,
            'points_balance_before' => $currentBalance,
            'points_balance_after' => $newBalance,
            'lifetime_points_preserved' => $lifetimePoints,
            'stock_remaining' => $stock - 1,
        ],
        $branchId,
        $userId
    );

    $connect->commit();
    $connect->close();

    json_response([
        'success' => true,
        'messages' => "¡Premio '{$reward['name']}' canjeado exitosamente para {$client['name']}!",
        'redemption' => [
            'redemption_id' => $redemptionId,
            'reward_name' => $reward['name'],
            'client_name' => $client['name'],
            'points_spent' => $pointsSpent,
            'new_points_balance' => $newBalance,
            'lifetime_points' => $lifetimePoints,
        ],
    ]);

} catch (Throwable $e) {
    $connect->rollback();
    $connect->close();
    send_http_error(400, $e->getMessage());
}
