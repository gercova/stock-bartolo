<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$userId = (int)$_SESSION['userId'];
if (!sb_user_can_switch_branches($connect, $userId)) {
    $connect->close();
    send_http_error(403, 'No tienes permisos para crear sucursales.');
}

$data           = get_request_data();
$branchName     = sanitize_clean_text($data['branchName'] ?? '', 'nombre de la sucursal', 150, true);
$rawCode        = sanitize_clean_text($data['branchCode'] ?? '', 'código de sucursal', 32, false);
$address        = sanitize_clean_text($data['address'] ?? '', 'dirección', 255, false);
$phone          = sanitize_clean_text($data['phone'] ?? '', 'teléfono', 60, false);
$email          = sanitize_clean_text($data['email'] ?? '', 'correo electrónico', 120, false);
$managerName    = sanitize_clean_text($data['managerName'] ?? '', 'responsable', 120, false);
$taxRate        = round((float)($data['taxRate'] ?? 13.00), 2);
$currencyCode   = strtoupper(sanitize_clean_text($data['currencyCode'] ?? 'USD', 'código de moneda', 10, true));
$currencySymbol = sanitize_clean_text($data['currencySymbol'] ?? '$', 'símbolo de moneda', 8, true);
$active         = (int)($data['active'] ?? 1);
$initialCapital = round((float)($data['initialCapital'] ?? 0), 2);

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    send_http_error(400, 'El correo electrónico de la sucursal no es válido.');
}

if ($taxRate < 0 || $taxRate > 100) {
    send_http_error(400, 'El porcentaje de impuesto (IVA) debe estar entre 0% y 100%.');
}

if ($initialCapital < 0) {
    send_http_error(400, 'El capital inicial de Caja General no puede ser negativo.');
}

if (!in_array($active, [1, 2], true)) {
    send_http_error(400, 'Estado de sucursal inválido.');
}

$branchCode = $rawCode !== '' ? strtoupper(preg_replace('/[^A-Za-z0-9\-_]/', '', $rawCode)) : '';
if ($branchCode === '') {
    $cntRes     = $connect->query('SELECT COALESCE(MAX(branch_id), 0) + 1 AS next_id FROM branches');
    $nextNum    = (int)($cntRes ? ($cntRes->fetch_assoc()['next_id'] ?? 1) : 1);
    $branchCode = sprintf('SUC-%03d', $nextNum);
}

$dupStmt = $connect->prepare('SELECT branch_id FROM branches WHERE branch_code = ? AND record_status = 1 LIMIT 1');
$dupStmt->bind_param('s', $branchCode);
$dupStmt->execute();
$dup = $dupStmt->get_result()->fetch_assoc();
$dupStmt->close();
if ($dup) {
    $connect->close();
    send_http_error(400, "Ya existe una sucursal activa con el código {$branchCode}.");
}

$connect->begin_transaction();

try {
    $stmt = $connect->prepare('INSERT INTO branches (branch_code, branch_name, address, phone, email, manager_name, tax_rate, currency_code, currency_symbol, is_main, active, record_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 1)');
    $stmt->bind_param('ssssssdssi', $branchCode, $branchName, $address, $phone, $email, $managerName, $taxRate, $currencyCode, $currencySymbol, $active);
    $stmt->execute();
    $newBranchId = (int)$connect->insert_id;
    $stmt->close();

    sb_ensure_branch_cash_defaults($connect, $newBranchId, $branchName, $initialCapital, $userId);

    $connect->commit();
    $accessible = sb_get_accessible_branches($connect);
    $connect->close();

    json_response([
        'success'       => true,
        'messages'      => 'Sucursal creada con Caja General y Caja Registradora independientes',
        'branch_id'     => $newBranchId,
        'branch_code'   => $branchCode,
        'branches'      => $accessible,
    ], 201);
} catch (Throwable $e) {
    $connect->rollback();
    $connect->close();
    error_log('[stock-bartolo] createBranch error: ' . $e->getMessage());
    send_http_error(500, 'No se pudo crear la sucursal.');
}

