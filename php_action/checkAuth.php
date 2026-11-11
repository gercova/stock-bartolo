<?php

define('SKIP_AUTH_CHECK', true);
require_once __DIR__ . '/core.php';

require_method(['GET']);
$csrfToken = issue_csrf_cookie();

if (empty($_SESSION['userId'])) {
    $connect->close();
    json_response([
        'success' => true,
        'authenticated' => false,
        'csrfToken' => $csrfToken,
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
    $csrfToken = rotate_csrf_token();
    json_response([
        'success' => true,
        'authenticated' => false,
        'csrfToken' => $csrfToken,
        'user' => null,
    ]);
}

json_response([
    'success' => true,
    'authenticated' => true,
    'csrfToken' => $csrfToken,
    'user' => [
        'user_id' => (int)$user['user_id'],
        'username' => $user['username'],
        'email' => $user['email'] ?? '',
    ],
]);
