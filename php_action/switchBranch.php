<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$userId = (int)$_SESSION['userId'];
if (!sb_user_can_switch_branches($connect, $userId)) {
    $connect->close();
    send_http_error(403, 'Tu perfil está restringido a una sola sucursal.');
}

$data = get_request_data();
$branchId = (int)($data['branchId'] ?? 0);

if ($branchId <= 0) {
    send_http_error(400, 'Selecciona una sucursal válida.');
}

$stmt = $connect->prepare('SELECT * FROM branches WHERE branch_id = ? AND record_status = 1 AND active = 1 LIMIT 1');
$stmt->bind_param('i', $branchId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $connect->close();
    send_http_error(404, 'La sucursal seleccionada no existe o está inactiva.');
}

$_SESSION['active_branch_id'] = (int)$row['branch_id'];
sb_ensure_branch_cash_defaults($connect, (int)$row['branch_id'], (string)$row['branch_name'], 0.0, $userId);

sb_log_audit(
    $connect,
    'branches',
    'SWITCH_BRANCH',
    "Cambio de sucursal activa a '{$row['branch_name']}' (#{$row['branch_id']})",
    ['branch_id' => (int)$row['branch_id'], 'branch_code' => $row['branch_code'], 'branch_name' => $row['branch_name']],
    (int)$row['branch_id']
);

$activeBranch = sb_format_branch_row($row);
$accessible = sb_get_accessible_branches($connect);
$systemConfig = sb_get_system_config($connect);
$connect->close();

json_response([
    'success' => true,
    'messages' => "Sucursal cambiada a {$activeBranch['branch_name']}",
    'activeBranch' => $activeBranch,
    'branches' => $accessible,
    'systemConfig' => $systemConfig,
]);

