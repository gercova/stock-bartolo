<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$branchId = get_active_branch_id($connect);
$activeBranch = get_active_branch($connect);
sb_ensure_branch_cash_defaults($connect, $branchId, $activeBranch['branch_name'], 0.0, (int)$_SESSION['userId']);

$sql = "SELECT
    cr.*,
    (SELECT cs.session_id FROM cash_sessions cs WHERE cs.register_id = cr.register_id AND cs.status = 'open' ORDER BY cs.session_id DESC LIMIT 1) AS open_session_id,
    (SELECT cs.session_code FROM cash_sessions cs WHERE cs.register_id = cr.register_id AND cs.status = 'open' ORDER BY cs.session_id DESC LIMIT 1) AS open_session_code,
    (SELECT cs.expected_cash FROM cash_sessions cs WHERE cs.register_id = cr.register_id AND cs.status = 'open' ORDER BY cs.session_id DESC LIMIT 1) AS open_expected_cash
FROM cash_registers cr
WHERE cr.branch_id = ? AND cr.record_status = 1
ORDER BY cr.register_id ASC";

$stmt = $connect->prepare($sql);
$stmt->bind_param('i', $branchId);
$stmt->execute();
$res = $stmt->get_result();

$registers = [];
while ($row = $res->fetch_assoc()) {
    $registers[] = [
        'register_id'       => (int)$row['register_id'],
        'branch_id'         => (int)$row['branch_id'],
        'register_code'     => $row['register_code'],
        'register_name'     => $row['register_name'],
        'location'          => $row['location'] ?? '',
        'active'            => (int)$row['active'],
        'open_session_id'   => $row['open_session_id'] !== null ? (int)$row['open_session_id'] : null,
        'open_session_code' => $row['open_session_code'] ?? null,
        'open_expected_cash' => $row['open_expected_cash'] !== null ? round((float)$row['open_expected_cash'], 2) : null,
    ];
}
$stmt->close();
$connect->close();

json_response([
    'success'   => true,
    'data'      => $registers,
]);

