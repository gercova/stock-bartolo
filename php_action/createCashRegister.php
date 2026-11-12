<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$branchId = get_active_branch_id($connect);
$data = get_request_data();

$registerName = sanitize_clean_text($data['registerName'] ?? '', 'nombre de caja', 120, true);
$rawCode = sanitize_clean_text($data['registerCode'] ?? '', 'código de caja', 32, false);
$location = sanitize_clean_text($data['location'] ?? '', 'ubicación', 150, false);
$active = (int)($data['active'] ?? 1);

if (!in_array($active, [1, 2], true)) {
    send_http_error(400, 'Estado de caja inválido.');
}

$registerCode = $rawCode !== '' ? strtoupper(preg_replace('/[^A-Za-z0-9\-_]/', '', $rawCode)) : '';
if ($registerCode === '') {
    $cntStmt = $connect->prepare('SELECT COUNT(*) + 1 AS next_num FROM cash_registers WHERE branch_id = ?');
    $cntStmt->bind_param('i', $branchId);
    $cntStmt->execute();
    $nextNum = (int)($cntStmt->get_result()->fetch_assoc()['next_num'] ?? 1);
    $cntStmt->close();
    $registerCode = sprintf('CAJA-%02d', $nextNum);
}

$dupStmt = $connect->prepare('SELECT register_id FROM cash_registers WHERE branch_id = ? AND register_code = ? AND record_status = 1 LIMIT 1');
$dupStmt->bind_param('is', $branchId, $registerCode);
$dupStmt->execute();
$dup = $dupStmt->get_result()->fetch_assoc();
$dupStmt->close();
if ($dup) {
    $connect->close();
    send_http_error(400, "Ya existe una caja con el código {$registerCode} en esta sucursal.");
}

$stmt = $connect->prepare('INSERT INTO cash_registers (branch_id, register_code, register_name, location, active, record_status) VALUES (?, ?, ?, ?, ?, 1)');
$stmt->bind_param('isssi', $branchId, $registerCode, $registerName, $location, $active);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'No se pudo crear la caja registradora.');
}
$newId = (int)$connect->insert_id;
$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Caja registradora creada exitosamente',
    'register_id' => $newId,
    'register_code' => $registerCode,
], 201);

