<?php

require_once __DIR__ . '/core.php';
require_method(['POST', 'PUT']);

$userId   = (int)$_SESSION['userId'];
$branchId = get_active_branch_id($connect);
$data     = get_request_data();

$accountId     = (int)($data['account_id'] ?? $data['accountId'] ?? 0);
$accountNumber = sanitize_clean_text($data['account_number'] ?? $data['accountNumber'] ?? '', 'número de cuenta', 60, false);
$accountType   = sanitize_clean_text($data['account_type'] ?? $data['accountType'] ?? 'corriente', 'tipo de cuenta', 40, false);
$status        = isset($data['status']) ? (int)$data['status'] : 1;
$notes         = sanitize_clean_text($data['notes'] ?? '', 'notas', 500, false);

if ($accountId <= 0) {
    $connect->close();
    send_http_error(400, 'ID de cuenta bancaria inválido.');
}

$chk = $connect->prepare('SELECT account_id, bank_name, branch_id FROM bank_capital_accounts WHERE account_id = ? LIMIT 1');
$chk->bind_param('i', $accountId);
$chk->execute();
$acc = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$acc) {
    $connect->close();
    send_http_error(404, 'Cuenta de entidad bancaria no encontrada.');
}

$upd = $connect->prepare('UPDATE bank_capital_accounts SET account_number = ?, account_type = ?, status = ?, notes = ?, updated_at = NOW() WHERE account_id = ?');
$upd->bind_param('ssisi', $accountNumber, $accountType, $status, $notes, $accountId);

if (!$upd->execute()) {
    $upd->close();
    $connect->close();
    send_http_error(500, 'No se pudo actualizar la información de la cuenta bancaria.');
}
$upd->close();
$connect->close();

json_response([
    'success'  => true,
    'messages' => "Cuenta de {$acc['bank_name']} actualizada exitosamente.",
]);
