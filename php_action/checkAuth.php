<?php

define('SKIP_AUTH_CHECK', true);
require_once __DIR__ . '/core.php';

require_method(['GET']);

if (empty($_SESSION['userId'])) {
    $connect->close();
    json_response([
        'success' => true,
        'authenticated' => false,
        'user' => null,
    ]);
}

$userId = (int)$_SESSION['userId'];
$stmt = $connect->prepare('SELECT user_id, username, email FROM users WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;
$stmt->close();
$connect->close();

if (!$user) {
    $_SESSION = [];
    json_response([
        'success' => true,
        'authenticated' => false,
        'user' => null,
    ]);
}

json_response([
    'success' => true,
    'authenticated' => true,
    'user' => [
        'user_id' => (int)$user['user_id'],
        'username' => $user['username'],
        'email' => $user['email'] ?? '',
    ],
]);
