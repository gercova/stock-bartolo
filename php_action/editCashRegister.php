<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);

$branchId = get_active_branch_id($connect);
$data = get_request_data();

$registerId = (int)($data['registerId'] ?? 0);
$registerCode = strtoupper(preg_replace('/[^A-Za-z0-9\-_]/', '', sanitize_clean_text($data['registerCode'] ?? '', 'código de caja', 32, true)));
$registerName = sanitize_clean_text($data['registerName'] ?? '', 'nombre de caja', 120, true);
$location = sanitize_clean_text($data['location'] ?? '', 'ubicación', 150, false);
$active = (int)($data['active'] ?? 1);

if ($registerId <= 0 || $registerCode === '' || !in_array($active, [1, 2], true)) {
    send_http_error(400, 'Datos inválidos para actualizar la caja registradora.');
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

if ($active !== 1) {
    $openChk = $connect->prepare("SELECT session_id FROM cash_sessions WHERE register_id = ? AND branch_id = ? AND status = 'open' LIMIT 1");
    $openChk->bind_param('ii', $registerId, $branchId);
    $openChk->execute();
    $openRow = $openChk->get_result()->fetch_assoc();
    $openChk->close();
    if ($openRow) {
        $connect->close();
        send_http_error(400, 'No puedes desactivar una caja con turno abierto. Cierra el turno primero.');
    }
}

$dup = $connect->prepare('SELECT register_id FROM cash_registers WHERE branch_id = ? AND register_code = ? AND register_id != ? AND record_status = 1 LIMIT 1');
$dup->bind_param('isi', $branchId, $registerCode, $registerId);
$dup->execute();
$dupRow = $dup->get_result()->fetch_assoc();
$dup->close();
if ($dupRow) {
    $connect->close();
    send_http_error(400, "El código {$registerCode} ya pertenece a otra caja en esta sucursal.");
}

$stmt = $connect->prepare('UPDATE cash_registers SET register_code = ?, register_name = ?, location = ?, active = ? WHERE register_id = ? AND branch_id = ?');
$stmt->bind_param('sssiii', $registerCode, $registerName, $location, $active, $registerId, $branchId);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'No se pudo actualizar la caja registradora.');
}
$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Caja registradora actualizada exitosamente',
]);

