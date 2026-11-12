<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'DELETE']);

$branchId = get_active_branch_id($connect);
$data = array_merge($_GET, get_request_data());
$loanId = (int)($data['loanId'] ?? $data['id'] ?? 0);

if ($loanId <= 0) {
    send_http_error(400, 'ID de préstamo inválido.');
}

$check = $connect->prepare('SELECT loan_id FROM loans WHERE loan_id = ? AND branch_id = ? AND record_status = 1 LIMIT 1');
$check->bind_param('ii', $loanId, $branchId);
$check->execute();
$exists = $check->get_result()->fetch_assoc();
$check->close();

if (!$exists) {
    $connect->close();
    send_http_error(404, 'Préstamo no encontrado en la sucursal activa.');
}

$stmt = $connect->prepare('UPDATE loans SET record_status = 2 WHERE loan_id = ? AND branch_id = ?');
$stmt->bind_param('ii', $loanId, $branchId);

if (!$stmt->execute()) {
    $stmt->close();
    $connect->close();
    send_http_error(500, 'Error al eliminar el préstamo.');
}

$stmt->close();
$connect->close();

json_response([
    'success' => true,
    'messages' => 'Préstamo eliminado exitosamente',
]);
