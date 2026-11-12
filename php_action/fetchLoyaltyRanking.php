<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

sb_assert_permission($connect, 'operations', 'access');

$branchId = get_active_branch_id($connect);
$search = trim((string)($_GET['search'] ?? ''));
$selectedClientId = isset($_GET['client_id']) && $_GET['client_id'] !== '' ? (int)$_GET['client_id'] : 0;

$pagination = sb_get_pagination_params(25, 250);

$where = ['c.record_status = 1'];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(c.name LIKE ? OR c.document_number LIKE ? OR c.phone LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$whereSql = implode(' AND ', $where);

// Count total matching clients
$countSql = "SELECT COUNT(DISTINCT c.client_id) as total FROM clients c WHERE {$whereSql}";
$cStmt = $connect->prepare($countSql);
if (!empty($params)) {
    $cStmt->bind_param($types, ...$params);
}
$cStmt->execute();
$totalRow = $cStmt->get_result()->fetch_assoc();
$total = (int)($totalRow['total'] ?? 0);
$cStmt->close();

// Fetch leaderboard ordered by lifetime_points DESC, points_balance DESC
$sql = "SELECT 
            c.client_id,
            c.name,
            c.document_type,
            c.document_number,
            c.phone,
            c.email,
            c.points_balance,
            c.lifetime_points,
            COUNT(o.operation_id) AS total_ops,
            COALESCE(SUM(o.amount), 0) AS total_volume,
            MAX(o.created_at) AS last_operation_at
        FROM clients c
        LEFT JOIN branch_operations o ON c.client_id = o.client_id AND o.status = 'completed'
        WHERE {$whereSql}
        GROUP BY c.client_id
        ORDER BY c.lifetime_points DESC, c.points_balance DESC, c.client_id ASC";

$dataParams = $params;
$dataTypes = $types;

if ($pagination['enabled']) {
    $sql .= " LIMIT ? OFFSET ?";
    $dataParams[] = $pagination['limit'];
    $dataParams[] = $pagination['offset'];
    $dataTypes .= 'ii';
}

$stmt = $connect->prepare($sql);
if (!empty($dataParams)) {
    $stmt->bind_param($dataTypes, ...$dataParams);
}
$stmt->execute();
$res = $stmt->get_result();

$leaderboard = [];
$rank = $pagination['enabled'] ? ($pagination['offset'] + 1) : 1;
while ($row = $res->fetch_assoc()) {
    $leaderboard[] = [
        'rank' => $rank++,
        'client_id' => (int)$row['client_id'],
        'name' => (string)$row['name'],
        'document_type' => (string)$row['document_type'],
        'document_number' => (string)$row['document_number'],
        'phone' => (string)($row['phone'] ?? ''),
        'email' => (string)($row['email'] ?? ''),
        'points_balance' => (int)$row['points_balance'],
        'lifetime_points' => (int)$row['lifetime_points'],
        'total_ops' => (int)$row['total_ops'],
        'total_volume' => (float)$row['total_volume'],
        'last_operation_at' => $row['last_operation_at'],
    ];
}
$stmt->close();

// If client_id is requested, fetch their point history
$clientHistory = [];
if ($selectedClientId > 0) {
    $hStmt = $connect->prepare('SELECT 
                                    h.history_id,
                                    h.client_id,
                                    h.operation_id,
                                    h.redemption_id,
                                    h.type,
                                    h.points,
                                    h.lifetime_after,
                                    h.balance_after,
                                    h.description,
                                    h.created_at,
                                    o.operation_code,
                                    o.operation_type
                                FROM client_points_history h
                                LEFT JOIN branch_operations o ON h.operation_id = o.operation_id
                                WHERE h.client_id = ?
                                ORDER BY h.history_id DESC
                                LIMIT 50');
    $hStmt->bind_param('i', $selectedClientId);
    $hStmt->execute();
    $hRes = $hStmt->get_result();
    while ($hRow = $hRes->fetch_assoc()) {
        $clientHistory[] = [
            'history_id' => (int)$hRow['history_id'],
            'client_id' => (int)$hRow['client_id'],
            'operation_id' => $hRow['operation_id'] ? (int)$hRow['operation_id'] : null,
            'redemption_id' => $hRow['redemption_id'] ? (int)$hRow['redemption_id'] : null,
            'operation_code' => (string)($hRow['operation_code'] ?? ''),
            'operation_type' => (string)($hRow['operation_type'] ?? ''),
            'type' => (string)$hRow['type'],
            'points' => (int)$hRow['points'],
            'lifetime_after' => (int)$hRow['lifetime_after'],
            'balance_after' => (int)$hRow['balance_after'],
            'description' => (string)$hRow['description'],
            'created_at' => $hRow['created_at'],
        ];
    }
    $hStmt->close();
}

$connect->close();

$meta = sb_build_pagination_meta($total, $pagination);

json_response([
    'success' => true,
    'data' => $leaderboard,
    'pagination' => $meta,
    'client_history' => $clientHistory,
]);
