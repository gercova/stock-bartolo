<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'DELETE']);

sb_assert_permission($connect, 'clients', 'delete');

$data = get_request_data();
$clientId = (int)($data['client_id'] ?? $data['clientId'] ?? 0);

if ($clientId <= 0) {
    send_http_error(400, 'Identificador de cliente no válido.');
}

// Fetch client
$stmt = $connect->prepare('SELECT client_id, name, document_type, document_number, client_type FROM clients WHERE client_id = ? AND record_status = 1 LIMIT 1');
$stmt->bind_param('i', $clientId);
$stmt->execute();
$clientRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$clientRow) {
    send_http_error(404, 'El cliente o proveedor no existe o ya ha sido eliminado.');
}

$name = (string)$clientRow['name'];
$docType = (string)$clientRow['document_type'];
$docNum = (string)$clientRow['document_number'];

// Execute SOFT DELETE (record_status = 2 and deleted_at = NOW())
$softDelStmt = $connect->prepare('UPDATE clients SET record_status = 2, deleted_at = NOW(), updated_at = NOW() WHERE client_id = ? AND record_status = 1');
$softDelStmt->bind_param('i', $clientId);

if (!$softDelStmt->execute()) {
    $softDelStmt->close();
    $connect->close();
    send_http_error(500, 'Error al ejecutar el borrado lógico del cliente.');
}
$softDelStmt->close();

sb_log_audit(
    $connect,
    'clients',
    'DELETE',
    "Cliente/Proveedor '{$name}' ({$docType} {$docNum}) eliminado mediante borrado lógico (soft delete)",
    [
        'client_id' => $clientId,
        'name' => $name,
        'document_type' => $docType,
        'document_number' => $docNum,
        'client_type' => $clientRow['client_type'],
        'soft_deleted' => true,
    ]
);

$connect->close();

json_response([
    'success' => true,
    'messages' => "El cliente '{$name}' fue eliminado correctamente (borrado lógico aplicado en base de datos).",
]);

