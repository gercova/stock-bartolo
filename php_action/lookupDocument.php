<?php

require_once __DIR__ . '/core.php';
require_once __DIR__ . '/env.php';
require_method(['GET', 'POST']);

$requestData = get_request_data();
$docType = strtoupper(trim((string)($requestData['type'] ?? $_GET['type'] ?? 'DNI')));
$docNumber = trim((string)($requestData['number'] ?? $requestData['document_number'] ?? $_GET['number'] ?? $_GET['document_number'] ?? ''));

if ($docNumber === '') {
    send_http_error(400, 'Debes ingresar el número de documento a consultar.');
}

// Clean document number
$docNumber = preg_replace('/[^0-9]/', '', $docNumber);

if ($docType === 'DNI' && strlen($docNumber) !== 8) {
    send_http_error(400, 'El DNI debe contener exactamente 8 dígitos numéricos.');
}

if ($docType === 'RUC' && strlen($docNumber) !== 11) {
    send_http_error(400, 'El RUC debe contener exactamente 11 dígitos numéricos.');
}

// 1. Check if client is already registered in local database
$stmtLocal = $connect->prepare('SELECT * FROM clients WHERE document_number = ? AND record_status = 1 LIMIT 1');
$stmtLocal->bind_param('s', $docNumber);
$stmtLocal->execute();
$localClient = $stmtLocal->get_result()->fetch_assoc();
$stmtLocal->close();

$apiKey = sb_env('DECOLETA_API_KEY', '');
$baseUrl = rtrim((string)sb_env('DECOLETA_BASE_URL', 'https://api.decolecta.com'), '/');

if ($apiKey === '') {
    // If no API key, return local data if found or instructions
    if ($localClient) {
        json_response([
            'success' => true,
            'source' => 'local_database',
            'already_registered' => true,
            'data' => [
                'document_type' => $localClient['document_type'],
                'document_number' => $localClient['document_number'],
                'name' => $localClient['name'],
                'first_name' => $localClient['first_name'],
                'last_name' => $localClient['last_name'],
                'birth_date' => $localClient['birth_date'] ?? '',
                'gender' => $localClient['gender'] ?? '',
                'email' => $localClient['email'],
                'phone' => $localClient['phone'],
                'address' => $localClient['address'],
                'department' => $localClient['department'],
                'province' => $localClient['province'],
                'district' => $localClient['district'],
                'ubigeo' => $localClient['ubigeo'],
                'client_type' => $localClient['client_type'],
                'client_id' => (int)$localClient['client_id'],
            ],
            'messages' => 'Información recuperada de la base de datos local.',
        ]);
    }

    send_http_error(503, 'La clave de integración Decolecta (DECOLETA_API_KEY) no está configurada en el archivo .env.');
}

// 2. Query Decolecta API
$endpoint = ($docType === 'DNI')
    ? "{$baseUrl}/v1/reniec/dni?numero={$docNumber}"
    : "{$baseUrl}/v1/sunat/ruc?numero={$docNumber}";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 12,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        "Authorization: Bearer {$apiKey}",
        'User-Agent: StockBartolo-POS/2.2.0',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
]);

$responseBody = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($responseBody === false || $curlError !== '') {
    if ($localClient) {
        json_response([
            'success' => true,
            'source' => 'local_fallback',
            'already_registered' => true,
            'data' => $localClient,
            'messages' => 'Servicio Decolecta no disponible. Se retornaron datos locales.',
        ]);
    }
    send_http_error(502, "Error de comunicación con la API de Decolecta: {$curlError}");
}

$decoded = json_decode($responseBody, true);

if ($httpCode === 404 || !empty($decoded['message']) && str_contains(strtolower((string)$decoded['message']), 'not found')) {
    if ($localClient) {
        json_response([
            'success' => true,
            'source' => 'local_database',
            'already_registered' => true,
            'data' => $localClient,
        ]);
    }
    send_http_error(404, "El documento {$docType} {$docNumber} no fue encontrado en el padrón de RENIEC / SUNAT.");
}

if ($httpCode !== 200 || !is_array($decoded)) {
    $msg = $decoded['message'] ?? 'Respuesta inesperada del proveedor de identidad.';
    send_http_error($httpCode >= 400 && $httpCode < 500 ? $httpCode : 502, "Error Decolecta ({$httpCode}): {$msg}");
}

// 3. Normalize Result
$normalized = [];

if ($docType === 'DNI') {
    $firstName = trim((string)($decoded['first_name'] ?? ''));
    $firstLastName = trim((string)($decoded['first_last_name'] ?? ''));
    $secondLastName = trim((string)($decoded['second_last_name'] ?? ''));
    $fullName = trim((string)($decoded['full_name'] ?? ''));

    if ($fullName === '') {
        $fullName = trim("{$firstName} {$firstLastName} {$secondLastName}");
    }

    $normalized = [
        'document_type' => 'DNI',
        'document_number' => $docNumber,
        'name' => $fullName,
        'first_name' => $firstName,
        'last_name' => trim("{$firstLastName} {$secondLastName}"),
        'birth_date' => $localClient['birth_date'] ?? ($decoded['birth_date'] ?? ($decoded['fecha_nacimiento'] ?? '')),
        'gender' => $localClient['gender'] ?? ($decoded['gender'] ?? ($decoded['sexo'] ?? '')),
        'email' => $localClient['email'] ?? '',
        'phone' => $localClient['phone'] ?? '',
        'address' => $localClient['address'] ?? '',
        'department' => $localClient['department'] ?? '',
        'province' => $localClient['province'] ?? '',
        'district' => $localClient['district'] ?? '',
        'ubigeo' => $localClient['ubigeo'] ?? '',
        'client_type' => $localClient['client_type'] ?? 'client',
        'status_text' => 'Identidad validada con RENIEC',
        'already_registered' => (bool)$localClient,
        'client_id' => $localClient ? (int)$localClient['client_id'] : null,
    ];
} else {
    // RUC / SUNAT
    $razonSocial = trim((string)($decoded['razon_social'] ?? ''));
    $direccion = trim((string)($decoded['direccion'] ?? ''));
    $departamento = trim((string)($decoded['departamento'] ?? ''));
    $provincia = trim((string)($decoded['provincia'] ?? ''));
    $distrito = trim((string)($decoded['distrito'] ?? ''));
    $ubigeo = trim((string)($decoded['ubigeo'] ?? ''));
    $estado = strtoupper(trim((string)($decoded['estado'] ?? 'ACTIVO')));
    $condicion = strtoupper(trim((string)($decoded['condicion'] ?? 'HABIDO')));

    $fullAddress = $direccion;
    if ($distrito !== '' || $departamento !== '') {
        $locParts = array_filter([$distrito, $provincia, $departamento]);
        if (!empty($locParts) && !str_contains($fullAddress, $distrito)) {
            $fullAddress .= ($fullAddress !== '' ? ', ' : '') . implode(' - ', $locParts);
        }
    }

    $normalized = [
        'document_type' => 'RUC',
        'document_number' => $docNumber,
        'name' => $razonSocial,
        'first_name' => '',
        'last_name' => '',
        'birth_date' => $localClient['birth_date'] ?? '',
        'gender' => $localClient['gender'] ?? '',
        'email' => $localClient['email'] ?? '',
        'phone' => $localClient['phone'] ?? '',
        'address' => $fullAddress,
        'department' => $departamento,
        'province' => $provincia,
        'district' => $distrito,
        'ubigeo' => $ubigeo,
        'client_type' => $localClient['client_type'] ?? 'both',
        'estado' => $estado,
        'condicion' => $condicion,
        'status_text' => "SUNAT: {$estado} / {$condicion}",
        'already_registered' => (bool)$localClient,
        'client_id' => $localClient ? (int)$localClient['client_id'] : null,
    ];
}

$connect->close();

json_response([
    'success' => true,
    'source' => 'decolecta',
    'already_registered' => (bool)$localClient,
    'data' => $normalized,
    'messages' => "Datos de {$docType} {$docNumber} obtenidos correctamente.",
]);

