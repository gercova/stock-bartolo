<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$branchId = get_active_branch_id($connect);
$onlyActive = isset($_GET['active']) && $_GET['active'] === '1';

$sql = $onlyActive
    ? 'SELECT categories_id, branch_id, categories_name, categories_active, categories_status FROM categories WHERE branch_id = ? AND categories_status = 1 AND categories_active = 1 ORDER BY categories_name ASC'
    : 'SELECT categories_id, branch_id, categories_name, categories_active, categories_status FROM categories WHERE branch_id = ? AND categories_status = 1 ORDER BY categories_id DESC';

$stmt = $connect->prepare($sql);
$stmt->bind_param('i', $branchId);
$stmt->execute();
$result = $stmt->get_result();

$categories = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = [
            'categories_id' => (int)$row['categories_id'],
            'branch_id' => (int)$row['branch_id'],
            'categories_name' => $row['categories_name'],
            'categories_active' => (int)$row['categories_active'],
            'categories_status' => (int)$row['categories_status'],
        ];
    }
}

$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'data' => $categories,
]);
