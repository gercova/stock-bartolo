<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);

$userId = (int)$_SESSION['userId'];
if (!sb_user_can_switch_branches($connect, $userId)) {
    $connect->close();
    send_http_error(403, 'No tienes permisos para modificar la configuración del sistema.');
}

$data = get_request_data();

$companyName = sanitize_clean_text($data['company_name'] ?? $data['companyName'] ?? '', 'nombre de empresa', 180, true);
$companyTaxId = sanitize_clean_text($data['company_tax_id'] ?? $data['companyTaxId'] ?? '', 'NIT / identificación fiscal', 60, false);
$companyPhone = sanitize_clean_text($data['company_phone'] ?? $data['companyPhone'] ?? '', 'teléfono empresarial', 60, false);
$companyAddress = sanitize_clean_text($data['company_address'] ?? $data['companyAddress'] ?? '', 'dirección principal', 255, false);
$receiptFooter = sanitize_clean_text($data['receipt_footer'] ?? $data['receiptFooter'] ?? 'Gracias por su preferencia.', 'pie de comprobante', 255, false);
$defaultTaxRate = round((float)($data['default_tax_rate'] ?? $data['defaultTaxRate'] ?? 13.0), 2);
$defaultCurrencyCode = strtoupper(sanitize_clean_text($data['default_currency_code'] ?? $data['defaultCurrencyCode'] ?? 'USD', 'moneda predeterminada', 10, true));
$defaultCurrencySymbol = sanitize_clean_text($data['default_currency_symbol'] ?? $data['defaultCurrencySymbol'] ?? '$', 'símbolo de moneda', 8, true);

$enforceOpenTill = !empty($data['enforce_open_till_for_cash']) ? 1 : 0;
$autoFundTill = !empty($data['auto_fund_till_from_general_cash']) ? 1 : 0;
$autoReturnTill = !empty($data['auto_return_till_to_general_cash']) ? 1 : 0;
$allowSwitching = !empty($data['allow_branch_switching']) ? 1 : 0;

if ($defaultTaxRate < 0 || $defaultTaxRate > 100) {
    send_http_error(400, 'La tasa de IVA predeterminada debe estar entre 0% y 100%.');
}

$stmt = $connect->prepare('UPDATE system_settings SET company_name = ?, company_tax_id = ?, company_phone = ?, company_address = ?, receipt_footer = ?, default_tax_rate = ?, default_currency_code = ?, default_currency_symbol = ?, enforce_open_till_for_cash = ?, auto_fund_till_from_general_cash = ?, auto_return_till_to_general_cash = ?, allow_branch_switching = ?, updated_by = ? WHERE setting_id = 1');
$stmt->bind_param(
    'sssssdssiiiii',
    $companyName,
    $companyTaxId,
    $companyPhone,
    $companyAddress,
    $receiptFooter,
    $defaultTaxRate,
    $defaultCurrencyCode,
    $defaultCurrencySymbol,
    $enforceOpenTill,
    $autoFundTill,
    $autoReturnTill,
    $allowSwitching,
    $userId
);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'No se pudo guardar la configuración del sistema.');
}
$stmt->close();

$config = sb_get_system_config($connect);
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Configuración del sistema guardada exitosamente',
    'config' => $config,
]);

