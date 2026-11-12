<?php

require_once __DIR__ . '/http_error.php';
require_once __DIR__ . '/env.php';

mysqli_report(MYSQLI_REPORT_OFF);

// Read database configuration strictly from environment variables (.env)
$localhost = sb_env('DB_HOST', '127.0.0.1');
$username  = sb_env('DB_USER', 'root');
$password  = sb_env('DB_PASS', '');
$dbname    = sb_env('DB_NAME', 'pos_stock');
$port      = (int)sb_env('DB_PORT', 3306);

$connect = @new mysqli($localhost, $username, $password, $dbname, $port);

if ($connect->connect_error) {
    // Check fallback schema name if DB_NAME is pos_stock
    if ($dbname === 'pos_stock') {
        $fallback = @new mysqli($localhost, $username, $password, 'stock', $port);
        if (!$fallback->connect_error) {
            $connect = $fallback;
        }
    }
}

if ($connect->connect_error) {
    send_http_error(
        503,
        'No se pudo establecer conexión con la base de datos. Verifica la configuración en el archivo .env.',
        ['reason' => 'database_connection_failed']
    );
}

$connect->set_charset('utf8mb4');
