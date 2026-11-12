<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$branchId = get_active_branch_id($connect);
$onlyActive = isset($_GET['active']) && $_GET['active'] === '1';
$search = trim((string)($_GET['search'] ?? ''));

$pagination = sb_get_pagination_params(25, 250);
if ($onlyActive) {
    $pagination['enabled'] = false;
}

$where = ['branch_id = ?', 'brand_status = 1'];
$params = [$branchId];
$types = 'i';

if ($onlyActive) {
    $where[] = 'brand_active = 1';
}

if ($search !== '') {
    $where[] = 'brand_name LIKE ?';
    $params[] = '%' . $search . '%';
    $types .= 's';
}

$whereSql = implode(' AND ', $where);

// Count
$countSql = "SELECT COUNT(*) as total FROM brands WHERE {$whereSql}";
$cStmt = $connect->prepare($countSql);
$cStmt->bind_param($types, ...$params);
$cStmt->execute();
$totalRow = $cStmt->get_result()->fetch_assoc();
$total = (int)($totalRow['total'] ?? 0);
$cStmt->close();

$sql = "SELECT brand_id, branch_id, brand_name, brand_active, brand_status FROM brands WHERE {$whereSql}";
$sql .= $onlyActive ? " ORDER BY brand_name ASC" : " ORDER BY brand_id DESC";

$dataParams = $params;
$dataTypes = $types;

if ($pagination['enabled']) {
    $sql .= " LIMIT ? OFFSET ?";
    $dataParams[] = $pagination['limit'];
    $dataParams[] = $pagination['offset'];
    $dataTypes .= 'ii';
}

$stmt = $connect->prepare($sql);
$stmt->bind_param($dataTypes, ...$dataParams);
$stmt->execute();
$result = $stmt->get_result();

$brands = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $brands[] = [
            'brand_id' => (int)$row['brand_id'],
            'branch_id' => (int)$row['branch_id'],
            'brand_name' => $row['brand_name'],
            'brand_active' => (int)$row['brand_active'],
            'brand_status' => (int)$row['brand_status'],
        ];
    }
}

$stmt->close();
$connect->close();

$meta = sb_build_pagination_meta($total, $pagination);

json_response([
    'success' => true,
    'data' => $brands,
    'pagination' => $meta,
]);
