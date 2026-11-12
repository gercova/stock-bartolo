<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

sb_assert_permission($connect, 'operations', 'access');

$branchId = get_active_branch_id($connect);
$category = trim((string)($_GET['category'] ?? ''));

$where = ['record_status = 1'];
$params = [];
$types = '';

if ($category !== '' && $category !== 'all') {
    $where[] = 'category = ?';
    $params[] = $category;
    $types .= 's';
}

$whereSql = implode(' AND ', $where);

// Fetch active reward items
$sql = "SELECT 
            reward_id,
            name,
            description,
            points_cost,
            price_soles,
            stock,
            image AS image_url,
            category,
            created_at
        FROM reward_items
        WHERE {$whereSql}
        ORDER BY points_cost ASC, name ASC";

$stmt = $connect->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$rewards = [];
while ($row = $res->fetch_assoc()) {
    $rewards[] = [
        'reward_id' => (int)$row['reward_id'],
        'name' => (string)$row['name'],
        'description' => (string)($row['description'] ?? ''),
        'points_cost' => (int)$row['points_cost'],
        'price_soles' => (float)$row['price_soles'],
        'stock' => (int)$row['stock'],
        'image_url' => (string)($row['image_url'] ?? ''),
        'category' => (string)($row['category'] ?? 'general'),
        'created_at' => $row['created_at'],
    ];
}
$stmt->close();

// Fetch recent redemptions
$redStmt = $connect->prepare("SELECT 
                                r.redemption_id,
                                r.redemption_code,
                                r.reward_id,
                                r.client_id,
                                r.user_id,
                                r.points_spent,
                                r.quantity,
                                r.points_balance_before,
                                r.points_balance_after,
                                r.notes,
                                r.created_at,
                                rw.name AS reward_name,
                                rw.price_soles,
                                rw.image AS image_url,
                                c.name AS client_name,
                                c.document_type,
                                c.document_number,
                                c.phone AS client_phone,
                                c.points_balance AS client_current_points,
                                COALESCE(u.username, 'Admin') AS cashier_name
                            FROM reward_redemptions r
                            INNER JOIN reward_items rw ON r.reward_id = rw.reward_id
                            INNER JOIN clients c ON r.client_id = c.client_id
                            LEFT JOIN users u ON r.user_id = u.user_id
                            ORDER BY r.redemption_id DESC
                            LIMIT 30");
$redStmt->execute();
$redRes = $redStmt->get_result();

$redemptions = [];
while ($rRow = $redRes->fetch_assoc()) {
    $redemptions[] = [
        'redemption_id' => (int)$rRow['redemption_id'],
        'redemption_code' => (string)$rRow['redemption_code'],
        'reward_id' => (int)$rRow['reward_id'],
        'reward_name' => (string)$rRow['reward_name'],
        'image_url' => (string)($rRow['image_url'] ?? ''),
        'client_id' => (int)$rRow['client_id'],
        'client_name' => (string)$rRow['client_name'],
        'document_type' => (string)$rRow['document_type'],
        'document_number' => (string)$rRow['document_number'],
        'client_phone' => (string)($rRow['client_phone'] ?? ''),
        'client_current_points' => (int)$rRow['client_current_points'],
        'cashier_name' => (string)$rRow['cashier_name'],
        'points_spent' => (int)$rRow['points_spent'],
        'price_soles_value' => (float)$rRow['price_soles'],
        'status' => 'delivered',
        'notes' => (string)($rRow['notes'] ?? ''),
        'redeemed_at' => $rRow['created_at'],
    ];
}
$redStmt->close();

$connect->close();

json_response([
    'success' => true,
    'data' => $rewards,
    'recent_redemptions' => $redemptions,
]);
