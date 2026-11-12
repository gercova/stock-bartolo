<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);

$userId = (int)$_SESSION['userId'];
if (!sb_user_can_switch_branches($connect, $userId)) {
    $connect->close();
    send_http_error(403, 'No tienes permisos para modificar la configuración del sistema.');
}

$data = get_request_data();

$companyName        = sanitize_clean_text($data['company_name'] ?? $data['companyName'] ?? '', 'nombre de empresa', 180, true);
$companyLegalName   = sanitize_clean_text($data['company_legal_name'] ?? $data['companyLegalName'] ?? '', 'razón social', 200, false);
$companyTaxId       = sanitize_clean_text($data['company_tax_id'] ?? $data['companyTaxId'] ?? '', 'NIT / identificación fiscal', 60, false);
$companyPhone       = sanitize_clean_text($data['company_phone'] ?? $data['companyPhone'] ?? '', 'teléfono empresarial', 60, false);
$companyAddress     = sanitize_clean_text($data['company_address'] ?? $data['companyAddress'] ?? '', 'dirección principal', 255, false);
$receiptFooter      = sanitize_clean_text($data['receipt_footer'] ?? $data['receiptFooter'] ?? 'Gracias por su preferencia.', 'pie de comprobante', 255, false);

$currentConfig = sb_get_system_config($connect);
$companyLogo = $currentConfig['company_logo'] ?? '';
if (!empty($_FILES['company_logo']['tmp_name'])) {
    $companyLogo = process_uploaded_company_logo($_FILES['company_logo']);
} elseif (!empty($_FILES['logo']['tmp_name'])) {
    $companyLogo = process_uploaded_company_logo($_FILES['logo']);
} elseif (isset($data['company_logo']) || isset($data['companyLogo'])) {
    $companyLogo = sanitize_logo_path_or_url((string)($data['company_logo'] ?? $data['companyLogo'] ?? ''));
}

$defaultTaxRate     = round((float)($data['default_tax_rate'] ?? $data['defaultTaxRate'] ?? 13.0), 2);
$defaultCurrencyCode = strtoupper(sanitize_clean_text($data['default_currency_code'] ?? $data['defaultCurrencyCode'] ?? 'USD', 'moneda predeterminada', 10, true));
$defaultCurrencySymbol = sanitize_clean_text($data['default_currency_symbol'] ?? $data['defaultCurrencySymbol'] ?? '$', 'símbolo de moneda', 8, true);

$enforceOpenTill    = !empty($data['enforce_open_till_for_cash']) ? 1 : 0;
$autoFundTill       = !empty($data['auto_fund_till_from_general_cash']) ? 1 : 0;
$autoReturnTill     = !empty($data['auto_return_till_to_general_cash']) ? 1 : 0;
$allowSwitching     = !empty($data['allow_branch_switching']) ? 1 : 0;

$pointsRatioSoles   = round(max(0.5, (float)($data['points_ratio_soles'] ?? $data['pointsRatioSoles'] ?? 10.0)), 2);
$pointsRatioPoints  = max(1, (int)($data['points_ratio_points'] ?? $data['pointsRatioPoints'] ?? 1));
$printDoubleTicket  = isset($data['print_double_ticket']) || isset($data['printDoubleTicket']) ? (!empty($data['print_double_ticket']) || !empty($data['printDoubleTicket']) ? 1 : 0) : 1;
$commissionRate100  = round(max(0.0, (float)($data['commission_rate_per_100'] ?? $data['commissionRatePer100'] ?? 1.0)), 2);

if ($defaultTaxRate < 0 || $defaultTaxRate > 100) {
    send_http_error(400, 'La tasa de IVA predeterminada debe estar entre 0% y 100%.');
}

$stmt = $connect->prepare('UPDATE system_settings SET company_name = ?, company_legal_name = ?, company_logo = ?, company_tax_id = ?, company_phone = ?, company_address = ?, receipt_footer = ?, default_tax_rate = ?, default_currency_code = ?, default_currency_symbol = ?, enforce_open_till_for_cash = ?, auto_fund_till_from_general_cash = ?, auto_return_till_to_general_cash = ?, allow_branch_switching = ?, points_ratio_soles = ?, points_ratio_points = ?, print_double_ticket = ?, commission_rate_per_100 = ?, updated_by = ? WHERE setting_id = 1');
$stmt->bind_param(
    'sssssssdssiiiidiidi',
    $companyName,
    $companyLegalName,
    $companyLogo,
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
    $pointsRatioSoles,
    $pointsRatioPoints,
    $printDoubleTicket,
    $commissionRate100,
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
    'success'   => true,
    'messages'  => 'Configuración del sistema guardada exitosamente',
    'config'    => $config,
]);

