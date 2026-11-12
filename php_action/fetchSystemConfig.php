<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$activeBranch   = get_active_branch($connect);
$config         = sb_get_system_config($connect);
$connect->close();

json_response([
    'success'       => true,
    'config'        => $config,
    'activeBranch'  => $activeBranch,
]);

