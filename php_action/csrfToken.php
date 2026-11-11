<?php

define('SKIP_AUTH_CHECK', true);
require_once __DIR__ . '/core.php';

require_method(['GET']);
$token = issue_csrf_cookie();
$connect->close();

json_response([
    'success' => true,
    'csrfToken' => $token,
]);

