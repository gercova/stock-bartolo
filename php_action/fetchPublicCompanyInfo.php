<?php

define('SKIP_AUTH_CHECK', true);
require_once __DIR__ . '/core.php';

require_method(['GET']);
issue_csrf_cookie();

$config = sb_get_system_config($connect);
$connect->close();

json_response([
    'success'               => true,
    'company_name'          => $config['company_name'] ?? 'Stock Bartolo',
    'company_legal_name'    => $config['company_legal_name'] ?? '',
    'company_logo'          => $config['company_logo'] ?? '',
    'company_tax_id'        => $config['company_tax_id'] ?? '',
    'receipt_footer'        => $config['receipt_footer'] ?? 'Gracias por su preferencia.',
]);

