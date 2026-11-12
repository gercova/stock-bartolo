<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

sb_assert_permission($connect, 'clients', 'create');

$data = get_request_data();
$docType = strtoupper(trim((string)($data['document_type'] ?? 'DNI')));
$docNumber = trim((string)($data['document_number'] ?? ''));
$name = trim((string)($data['name'] ?? ''));
$firstName = trim((string)($data['first_name'] ?? ''));
$lastName = trim((string)($data['last_name'] ?? ''));
$email = strtolower(trim((string)($data['email'] ?? '')));
$phone = trim((string)($data['phone'] ?? ''));
$address = trim((string)($data['address'] ?? ''));
$department = trim((string)($data['department'] ?? ''));
$province = trim((string)($data['province'] ?? ''));
$district = trim((string)($data['district'] ?? ''));
$ubigeo = trim((string)($data['ubigeo'] ?? ''));
$clientType = strtolower(trim((string)($data['client_type'] ?? 'client')));
$status = isset($data['status']) ? ((int)$data['status'] === 1 ? 1 : 0) : 1;
$branchId = isset($data['branch_id']) && $data['branch_id'] !== '' && (int)$data['branch_id'] > 0 ? (int)$data['branch_id'] : null;
$notes = trim((string)($data['notes'] ?? ''));

$validDocTypes = ['DNI', 'RUC', 'CE', 'PASAPORTE', 'OTRO'];
if (!in_array($docType, $validDocTypes, true)) {
    send_http_error(400, 'Tipo de documento no válido. Opciones: DNI, RUC, CE, PASAPORTE, OTRO.');
}

if ($docNumber === '') {
    send_http_error(400, 'El número de documento es obligatorio.');
}

if ($name === '' || strlen($name) < 2 || strlen($name) > 255) {
    send_http_error(400, 'La razón social o nombre completo es obligatorio y debe tener entre 2 y 255 caracteres.');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_http_error(400, 'El correo electrónico no tiene un formato válido.');
}

$validClientTypes = ['client', 'supplier', 'both'];
if (!in_array($clientType, $validClientTypes, true)) {
    $clientType = 'client';
}

// Check for active duplicate document
$dupStmt = $connect->prepare('SELECT client_id, name FROM clients WHERE document_number = ? AND record_status = 1 LIMIT 1');
$dupStmt->bind_param('s', $docNumber);
$dupStmt->execute();
$dupRow = $dupStmt->get_result()->fetch_assoc();
$dupStmt->close();

if ($dupRow) {
    send_http_error(409, "Ya existe un registro activo con el documento {$docType} {$docNumber} ('{$dupRow['name']}').");
}

// Check if a soft-deleted record exists with same document to reuse or re-activate
$softStmt = $connect->prepare('SELECT client_id FROM clients WHERE document_number = ? AND record_status = 2 ORDER BY client_id DESC LIMIT 1');
$softStmt->bind_param('s', $docNumber);
$softStmt->execute();
$softRow = $softStmt->get_result()->fetch_assoc();
$softStmt->close();

$clientId = null;
if ($softRow) {
    $reuseId = (int)$softRow['client_id'];
    $updRevive = $connect->prepare('UPDATE clients SET 
        branch_id = ?, document_type = ?, document_number = ?, name = ?, first_name = ?, last_name = ?,
        email = ?, phone = ?, address = ?, department = ?, province = ?, district = ?, ubigeo = ?,
        client_type = ?, status = ?, record_status = 1, deleted_at = NULL, notes = ?, updated_at = NOW()
        WHERE client_id = ?');
    $updRevive->bind_param(
        'issssssssssssisisi',
        $branchId, $docType, $docNumber, $name, $firstName, $lastName,
        $email, $phone, $address, $department, $province, $district, $ubigeo,
        $clientType, $status, $notes, $reuseId
    );
    // Bind types correction:
    // branch_id (i), docType (s), docNumber (s), name (s), firstName (s), lastName (s),
    // email (s), phone (s), address (s), department (s), province (s), district (s), ubigeo (s),
    // clientType (s), status (i), notes (s), reuseId (i)
    // 17 params: 'issssssssssssisii' -> total 17 chars: i (1) + s*12 (12) + s (1) + i (1) + s (1) + i (1) = 17
    $typesRevive = 'issssssssssssisi';
}

$insertSql = 'INSERT INTO clients (branch_id, document_type, document_number, name, first_name, last_name, email, phone, address, department, province, district, ubigeo, client_type, status, record_status, notes, created_at, updated_at) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())';

$insStmt = $connect->prepare($insertSql);
$insTypes = 'isssssssssssssis';
$insStmt->bind_param(
    $insTypes,
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
    $notes
);

if (!$insStmt->execute()) {
    $insStmt->close();
    $connect->close();
    send_http_error(500, 'Error al registrar el cliente en el sistema.');
}

$newClientId = (int)$insStmt->insert_id;
$insStmt->close();

$typeLabel = match ($clientType) {
    'supplier' => 'Proveedor',
    'both' => 'Cliente y Proveedor',
    default => 'Cliente',
};

sb_log_audit(
    $connect,
    'clients',
    'CREATE',
    "{$typeLabel} '{$name}' ({$docType} {$docNumber}) registrado exitosamente (#{$newClientId})",
    [
        'client_id' => $newClientId,
        'document_type' => $docType,
        'document_number' => $docNumber,
        'name' => $name,
        'client_type' => $clientType,
        'phone' => $phone,
        'branch_id' => $branchId,
    ]
);

$connect->close();

json_response([
    'success' => true,
    'messages' => "{$typeLabel} '{$name}' registrado exitosamente.",
    'clientId' => $newClientId,
]);
