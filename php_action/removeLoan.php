<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'DELETE']);
ensure_loan_schema($connect);

$data = array_merge($_GET, get_request_data());
$loanId = (int)($data['loanId'] ?? $data['id'] ?? 0);

if ($loanId <= 0) {
    send_http_error(400, 'ID de préstamo inválido.');
}

$stmt = $connect->prepare('UPDATE loans SET record_status = 2 WHERE loan_id = ?');
$stmt->bind_param('i', $loanId);

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
