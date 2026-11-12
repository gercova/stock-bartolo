<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'DELETE']);

$branchId = get_active_branch_id($connect);
$data = array_merge($_GET, get_request_data());
$registerId = (int)($data['registerId'] ?? $data['id'] ?? 0);

if ($registerId <= 0) {
    send_http_error(400, 'ID de caja inválido.');
}

$chk = $connect->prepare('SELECT register_id FROM cash_registers WHERE register_id = ? AND branch_id = ? AND record_status = 1 LIMIT 1');
$chk->bind_param('ii', $registerId, $branchId);
$chk->execute();
$exists = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$exists) {
    $connect->close();
    send_http_error(404, 'Caja registradora no encontrada en la sucursal activa.');
}

$openChk = $connect->prepare("SELECT session_id FROM cash_sessions WHERE register_id = ? AND branch_id = ? AND status = 'open' LIMIT 1");
$openChk->bind_param('ii', $registerId, $branchId);
$openChk->execute();
$openRow = $openChk->get_result()->fetch_assoc();
$openChk->close();
if ($openRow) {
    $connect->close();
    send_http_error(400, 'Esta caja tiene un turno abierto. Realiza el cierre antes de eliminarla.');
}

$stmt = $connect->prepare('UPDATE cash_registers SET record_status = 2, active = 2 WHERE register_id = ? AND branch_id = ?');
$stmt->bind_param('ii', $registerId, $branchId);
if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'No se pudo eliminar la caja registradora.');
}
$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Caja registradora eliminada exitosamente',
]);

