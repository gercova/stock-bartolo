<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'DELETE']);

$userId = (int)$_SESSION['userId'];
if (!sb_user_can_switch_branches($connect, $userId)) {
    $connect->close();
    send_http_error(403, 'No tienes permisos para eliminar sucursales.');
}

$data = array_merge($_GET, get_request_data());
$branchId = (int)($data['branchId'] ?? $data['id'] ?? 0);

if ($branchId <= 0) {
    send_http_error(400, 'ID de sucursal inválido.');
}

$check = $connect->prepare('SELECT branch_id, is_main FROM branches WHERE branch_id = ? AND record_status = 1 LIMIT 1');
$check->bind_param('i', $branchId);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if (!$existing) {
    $connect->close();
    send_http_error(404, 'Sucursal no encontrada.');
}

if ((int)$existing['is_main'] === 1 || $branchId === 1) {
    $connect->close();
    send_http_error(400, 'No es posible eliminar la sucursal principal del sistema.');
}

$openChk = $connect->prepare("SELECT COUNT(*) AS cnt FROM cash_sessions WHERE branch_id = ? AND status = 'open'");
$openChk->bind_param('i', $branchId);
$openChk->execute();
$openCnt = (int)($openChk->get_result()->fetch_assoc()['cnt'] ?? 0);
$openChk->close();
if ($openCnt > 0) {
    $connect->close();
    send_http_error(400, 'Esta sucursal tiene turnos de caja abiertos. Ciérralos antes de eliminarla.');
}

$stmt = $connect->prepare('UPDATE branches SET record_status = 2, active = 2 WHERE branch_id = ?');
$stmt->bind_param('i', $branchId);
if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'No se pudo eliminar la sucursal.');
}
$stmt->close();

if ((int)($_SESSION['active_branch_id'] ?? 1) === $branchId) {
    $_SESSION['active_branch_id'] = 1;
}

$activeBranch   = get_active_branch($connect);
$accessible     = sb_get_accessible_branches($connect);
$connect->close();

json_response([
    'success'       => true,
    'messages'      => 'Sucursal eliminada exitosamente',
    'activeBranch'  => $activeBranch,
    'branches'      => $accessible,
]);

