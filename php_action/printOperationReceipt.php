<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

sb_assert_permission($connect, 'operations', 'access');

$opId = (int)($_GET['operation_id'] ?? $_GET['id'] ?? 0);
$opCode = trim((string)($_GET['code'] ?? ''));

if ($opId <= 0 && $opCode === '') {
    send_http_error(400, 'Debes proporcionar el ID o código de la operación.');
}

$where = $opId > 0 ? 'o.operation_id = ?' : 'o.operation_code = ?';
$param = $opId > 0 ? $opId : $opCode;
$type = $opId > 0 ? 'i' : 's';

$sql = "SELECT 
            o.*,
            c.name AS client_name,
            c.document_type,
            c.document_number,
            c.phone AS client_phone,
            c.points_balance AS client_current_points,
            c.lifetime_points AS client_lifetime_points,
            b.branch_name,
            b.branch_code,
            b.address AS branch_address,
            b.phone AS branch_phone,
            u.username AS cashier_name
        FROM branch_operations o
        INNER JOIN clients c ON o.client_id = c.client_id
        LEFT JOIN branches b ON o.branch_id = b.branch_id
        LEFT JOIN users u ON o.user_id = u.user_id
        WHERE {$where}
        LIMIT 1";

$stmt = $connect->prepare($sql);
$stmt->bind_param($type, $param);
$stmt->execute();
$op = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$op) {
    send_http_error(404, 'La operación solicitada no existe.');
}

$sysConfig = sb_get_system_config($connect);
$connect->close();

$data = [
    'operation_id' => (int)$op['operation_id'],
    'operation_code' => (string)$op['operation_code'],
    'operation_type' => (string)$op['operation_type'],
    'operation_type_label' => $op['operation_type'] === 'deposit' ? 'DEPÓSITO' : 'RETIRO',
    'amount' => (float)$op['amount'],
    'commission_amount' => (float)$op['commission_amount'],
    'commission_included' => ((int)$op['commission_included']) === 1,
    'net_amount' => (float)$op['net_amount'],
    'total_customer_amount' => (float)$op['total_customer_amount'],
    'points_earned' => (int)$op['points_earned'],
    'client_new_points' => (int)$op['client_current_points'],
    'client_lifetime_points' => (int)$op['client_lifetime_points'],
    'notes' => (string)($op['notes'] ?? ''),
    'created_at' => $op['created_at'],
    'client' => [
        'client_id' => (int)$op['client_id'],
        'name' => (string)$op['client_name'],
        'document_type' => (string)$op['document_type'],
        'document_number' => (string)$op['document_number'],
        'phone' => (string)($op['client_phone'] ?? ''),
    ],
    'branch' => [
        'branch_id' => (int)$op['branch_id'],
        'branch_name' => (string)($op['branch_name'] ?? 'Sucursal'),
        'branch_code' => (string)($op['branch_code'] ?? 'SUC-001'),
        'address' => (string)($op['branch_address'] ?? ''),
        'phone' => (string)($op['branch_phone'] ?? ''),
    ],
    'company' => [
        'name' => (string)($sysConfig['company_name'] ?? 'Stock Bartolo'),
        'tax_id' => (string)($sysConfig['company_tax_id'] ?? ''),
        'address' => (string)($sysConfig['company_address'] ?? ''),
        'phone' => (string)($sysConfig['company_phone'] ?? ''),
        'receipt_footer' => (string)($sysConfig['receipt_footer'] ?? 'Gracias por su preferencia.'),
    ],
    'cashier' => (string)($op['cashier_name'] ?? 'Cajero'),
    'print_double_ticket' => (bool)($sysConfig['print_double_ticket'] ?? true),
];

json_response([
    'success' => true,
    'data' => $data,
]);

