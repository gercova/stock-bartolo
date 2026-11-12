<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);

sb_assert_permission($connect, 'clients', 'update');

$data = get_request_data();
$clientId = (int)($data['client_id'] ?? $data['clientId'] ?? 0);

if ($clientId <= 0) {
    send_http_error(400, 'Identificador de cliente no válido.');
}

// Fetch existing client
$stmt = $connect->prepare('SELECT * FROM clients WHERE client_id = ? AND record_status = 1 LIMIT 1');
$stmt->bind_param('i', $clientId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existing) {
    send_http_error(404, 'El cliente o proveedor solicitado no existe o ha sido eliminado.');
}

$docType = strtoupper(trim((string)($data['document_type'] ?? $existing['document_type'])));
$docNumber = trim((string)($data['document_number'] ?? $existing['document_number']));
$name = trim((string)($data['name'] ?? $existing['name']));
$firstName = trim((string)($data['first_name'] ?? $existing['first_name']));
$lastName = trim((string)($data['last_name'] ?? $existing['last_name']));
$email = strtolower(trim((string)($data['email'] ?? $existing['email'])));
$phone = trim((string)($data['phone'] ?? $existing['phone']));
$address = trim((string)($data['address'] ?? $existing['address']));
$department = trim((string)($data['department'] ?? $existing['department']));
$province = trim((string)($data['province'] ?? $existing['province']));
$district = trim((string)($data['district'] ?? $existing['district']));
$ubigeo = trim((string)($data['ubigeo'] ?? $existing['ubigeo']));
$clientType = strtolower(trim((string)($data['client_type'] ?? $existing['client_type'])));
$status = isset($data['status']) ? ((int)$data['status'] === 1 ? 1 : 0) : (int)$existing['status'];
$branchId = isset($data['branch_id']) && $data['branch_id'] !== '' && (int)$data['branch_id'] > 0 ? (int)$data['branch_id'] : null;
$notes = trim((string)($data['notes'] ?? $existing['notes']));

$validDocTypes = ['DNI', 'RUC', 'CE', 'PASAPORTE', 'OTRO'];
if (!in_array($docType, $validDocTypes, true)) {
    send_http_error(400, 'Tipo de documento no válido.');
}

if ($docNumber === '') {
    send_http_error(400, 'El número de documento es obligatorio.');
}

if ($name === '' || strlen($name) < 2 || strlen($name) > 255) {
    send_http_error(400, 'El nombre o razón social debe tener entre 2 y 255 caracteres.');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_http_error(400, 'El correo electrónico no tiene un formato válido.');
}

$validClientTypes = ['client', 'supplier', 'both'];
if (!in_array($clientType, $validClientTypes, true)) {
    $clientType = 'client';
}

// Check document duplicate against other records
if ($docNumber !== (string)$existing['document_number']) {
    $dupCheck = $connect->prepare('SELECT client_id, name FROM clients WHERE document_number = ? AND client_id != ? AND record_status = 1 LIMIT 1');
    $dupCheck->bind_param('si', $docNumber, $clientId);
    $dupCheck->execute();
    $dupRow = $dupCheck->get_result()->fetch_assoc();
    $dupCheck->close();
    if ($dupRow) {
        send_http_error(409, "El documento {$docType} {$docNumber} ya pertenece a otro registro activo ('{$dupRow['name']}').");
    }
}

$updateSql = 'UPDATE clients SET 
    branch_id = ?,
    document_type = ?,
    document_number = ?,
    name = ?,
    first_name = ?,
    last_name = ?,
    email = ?,
    phone = ?,
    address = ?,
    department = ?,
    province = ?,
    district = ?,
    ubigeo = ?,
    client_type = ?,
    status = ?,
    notes = ?,
    updated_at = NOW()
WHERE client_id = ? AND record_status = 1';

$updStmt = $connect->prepare($updateSql);
$types = 'isssssssssssssisi';
$updStmt->bind_param(
    $types,
    $branchId,
    $docType,
    $docNumber,
    $name,
    $firstName,
    $lastName,
    $email,
    $phone,
    $address,
    $department,
    $province,
    $district,
    $ubigeo,
    $clientType,
    $status,
    $notes,
    $clientId
);

if (!$updStmt->execute()) {
    $updStmt->close();
    $connect->close();
    send_http_error(500, 'Error al actualizar los datos del cliente.');
}
$updStmt->close();

sb_log_audit(
    $connect,
    'clients',
    'UPDATE',
    "Cliente/Proveedor '{$name}' (#{$clientId}) actualizado exitosamente",
    [
        'client_id' => $clientId,
        'document_type' => $docType,
        'document_number' => $docNumber,
        'name' => $name,
        'client_type' => $clientType,
        'status' => $status,
        'branch_id' => $branchId,
    ]
);

$connect->close();

json_response([
    'success' => true,
    'messages' => "Registro de '{$name}' actualizado exitosamente.",
]);
