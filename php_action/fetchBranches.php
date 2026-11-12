<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$userId = (int)$_SESSION['userId'];
$activeBranchId = get_active_branch_id($connect);
$canSwitch = sb_user_can_switch_branches($connect, $userId);

$sql = "SELECT
    b.*,
    COALESCE(gca.account_id, 0) AS account_id,
    COALESCE(gca.account_name, CONCAT('Caja General — ', b.branch_name)) AS account_name,
    COALESCE(gca.current_balance, 0.00) AS general_cash_balance,
    COALESCE(gca.initial_capital, 0.00) AS initial_capital,
    COALESCE(gca.min_balance_alert, 0.00) AS min_balance_alert,
    COALESCE(gca.status, 1) AS general_cash_status,
    (SELECT COUNT(*) FROM cash_registers cr WHERE cr.branch_id = b.branch_id AND cr.record_status = 1) AS registers_count,
    (SELECT COUNT(*) FROM cash_sessions cs WHERE cs.branch_id = b.branch_id AND cs.status = 'open') AS open_sessions_count,
    (SELECT COUNT(*) FROM product p WHERE p.branch_id = b.branch_id AND p.status = 1) AS products_count,
    (SELECT COUNT(*) FROM orders o WHERE o.branch_id = b.branch_id AND o.order_status = 1) AS orders_count,
    (SELECT COUNT(*) FROM loans l WHERE l.branch_id = b.branch_id AND l.record_status = 1) AS loans_count
FROM branches b
LEFT JOIN general_cash_accounts gca ON gca.branch_id = b.branch_id
WHERE b.record_status = 1 " . ($canSwitch ? '' : 'AND b.branch_id = ? ') . "
ORDER BY b.is_main DESC, b.branch_id ASC";

if ($canSwitch) {
    $res = $connect->query($sql);
    $stmt = null;
} else {
    $stmt = $connect->prepare($sql);
    $stmt->bind_param('i', $activeBranchId);
    $stmt->execute();
    $res = $stmt->get_result();
}

$branches = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $item = sb_format_branch_row($row);
        $item['is_active_tenant'] = ((int)$row['branch_id'] === $activeBranchId);
        $item['general_cash'] = [
            'account_id' => (int)$row['account_id'],
            'account_name' => (string)$row['account_name'],
            'current_balance' => round((float)$row['general_cash_balance'], 2),
            'initial_capital' => round((float)$row['initial_capital'], 2),
            'min_balance_alert' => round((float)$row['min_balance_alert'], 2),
            'status' => (int)$row['general_cash_status'],
        ];
        $item['registers_count'] = (int)$row['registers_count'];
        $item['open_sessions_count'] = (int)$row['open_sessions_count'];
        $item['products_count'] = (int)$row['products_count'];
        $item['orders_count'] = (int)$row['orders_count'];
        $item['loans_count'] = (int)$row['loans_count'];
        $branches[] = $item;
    }
}

if ($stmt) {
    $stmt->close();
}

$activeBranch = get_active_branch($connect);
$connect->close();

json_response([
    'success' => true,
    'activeBranchId' => $activeBranchId,
    'activeBranch' => $activeBranch,
    'canSwitch' => $canSwitch,
    'data' => $branches,
]);

