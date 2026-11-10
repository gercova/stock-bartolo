<?php

require_once __DIR__ . '/http_error.php';

mysqli_report(MYSQLI_REPORT_OFF);

$localhost = getenv('DB_HOST') ?: '127.0.0.1';
$username  = getenv('DB_USER') ?: 'admin';
$password  = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '011998$*';
$dbname    = getenv('DB_NAME') ?: 'pos_stock';
$port      = (int)(getenv('DB_PORT') ?: 3306);

$connect = @new mysqli($localhost, $username, $password, $dbname, $port);

if ($connect->connect_error) {
    // Fallback check in case an environment uses legacy 'stock' schema name
    if (getenv('DB_NAME') === false && $dbname === 'pos_stock') {
        $fallback = @new mysqli($localhost, $username, $password, 'stock', $port);
        if (!$fallback->connect_error) {
            $connect = $fallback;
        }
    }
}

if ($connect->connect_error) {
    send_http_error(
        503,
        'No se pudo establecer conexión con la base de datos. El servicio está temporalmente no disponible.',
        ['reason' => 'database_connection_failed']
    );
}

$connect->set_charset('utf8mb4');
