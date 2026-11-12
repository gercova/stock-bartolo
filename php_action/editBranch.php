<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);

$userId = (int)$_SESSION['userId'];
if (!sb_user_can_switch_branches($connect, $userId)) {
    $connect->close();
    send_http_error(403, 'No tienes permisos para editar sucursales.');
}

$data = get_request_data();
$branchId = (int)($data['branchId'] ?? 0);
$branchName = sanitize_clean_text($data['branchName'] ?? '', 'nombre de la sucursal', 150, true);
$rawCode = sanitize_clean_text($data['branchCode'] ?? '', 'código de sucursal', 32, true);
$address = sanitize_clean_text($data['address'] ?? '', 'dirección', 255, false);
$phone = sanitize_clean_text($data['phone'] ?? '', 'teléfono', 60, false);
$email = sanitize_clean_text($data['email'] ?? '', 'correo electrónico', 120, false);
$managerName = sanitize_clean_text($data['managerName'] ?? '', 'responsable', 120, false);
$taxRate = round((float)($data['taxRate'] ?? 13.00), 2);
$currencyCode = strtoupper(sanitize_clean_text($data['currencyCode'] ?? 'USD', 'código de moneda', 10, true));
$currencySymbol = sanitize_clean_text($data['currencySymbol'] ?? '$', 'símbolo de moneda', 8, true);
$active = (int)($data['active'] ?? 1);

if ($branchId <= 0 || !in_array($active, [1, 2], true)) {
    send_http_error(400, 'Datos inválidos para actualizar la sucursal.');
}

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    send_http_error(400, 'El correo electrónico de la sucursal no es válido.');
}

if ($taxRate < 0 || $taxRate > 100) {
    send_http_error(400, 'El porcentaje de impuesto (IVA) debe estar entre 0% y 100%.');
}

$branchCode = strtoupper(preg_replace('/[^A-Za-z0-9\-_]/', '', $rawCode));
if ($branchCode === '') {
    send_http_error(400, 'El código de sucursal es obligatorio.');
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

if ((int)$existing['is_main'] === 1 && $active !== 1) {
    $connect->close();
    send_http_error(400, 'La sucursal principal debe permanecer activa.');
}

if ($active !== 1) {
    $openChk = $connect->prepare("SELECT COUNT(*) AS cnt FROM cash_sessions WHERE branch_id = ? AND status = 'open'");
    $openChk->bind_param('i', $branchId);
    $openChk->execute();
    $openCnt = (int)($openChk->get_result()->fetch_assoc()['cnt'] ?? 0);
    $openChk->close();
    if ($openCnt > 0) {
        $connect->close();
        send_http_error(400, 'No puedes desactivar la sucursal mientras tenga cajas registradoras abiertas.');
    }
}

$dupStmt = $connect->prepare('SELECT branch_id FROM branches WHERE branch_code = ? AND branch_id != ? AND record_status = 1 LIMIT 1');
$dupStmt->bind_param('si', $branchCode, $branchId);
$dupStmt->execute();
$dup = $dupStmt->get_result()->fetch_assoc();
$dupStmt->close();
if ($dup) {
    $connect->close();
    send_http_error(400, "El código {$branchCode} ya pertenece a otra sucursal.");
}

$stmt = $connect->prepare('UPDATE branches SET branch_code = ?, branch_name = ?, address = ?, phone = ?, email = ?, manager_name = ?, tax_rate = ?, currency_code = ?, currency_symbol = ?, active = ? WHERE branch_id = ? AND record_status = 1');
$stmt->bind_param('ssssssdssii', $branchCode, $branchName, $address, $phone, $email, $managerName, $taxRate, $currencyCode, $currencySymbol, $active, $branchId);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'No se pudo actualizar la sucursal.');
}
$stmt->close();

$activeBranch = get_active_branch($connect);
$accessible = sb_get_accessible_branches($connect);
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Sucursal actualizada exitosamente',
    'activeBranch' => $activeBranch,
    'branches' => $accessible,
]);

