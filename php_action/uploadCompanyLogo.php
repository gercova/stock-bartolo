<?php

require_once __DIR__ . '/core.php';
require_method(['POST']);

$userId = (int)($_SESSION['userId'] ?? 0);
if (!sb_user_can_switch_branches($connect, $userId)) {
    $connect->close();
    send_http_error(403, 'No tienes permisos para modificar el logo de la empresa.');
}

$file = $_FILES['logo'] ?? $_FILES['company_logo'] ?? null;
if (!$file || !is_array($file) || empty($file['tmp_name'])) {
    $connect->close();
    send_http_error(400, 'Debes seleccionar un archivo de imagen válido para el logo.');
}

$logoPath = process_uploaded_company_logo($file);

$stmt = $connect->prepare('UPDATE system_settings SET company_logo = ?, updated_by = ? WHERE setting_id = 1');
if ($stmt) {
    $stmt->bind_param('si', $logoPath, $userId);
    $stmt->execute();
    $stmt->close();
}

sb_log_audit(
    $connect,
    'system_config',
    'UPDATE_LOGO',
    'Logo corporativo de la empresa actualizado',
    ['logo' => $logoPath]
);

$config = sb_get_system_config($connect);
$connect->close();

json_response([
    'success'   => true,
    'messages'  => 'Logo de la empresa actualizado exitosamente',
    'logo_url'  => $logoPath,
    'config'    => $config,
]);

