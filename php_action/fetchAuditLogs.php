<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

sb_assert_permission($connect, 'audit_logs', 'access');

$startDate = trim((string)($_GET['startDate'] ?? ''));
$endDate = trim((string)($_GET['endDate'] ?? ''));
$moduleFilter = trim((string)($_GET['module'] ?? ''));
$actionFilter = trim((string)($_GET['action'] ?? ''));
$userIdFilter = isset($_GET['userId']) && $_GET['userId'] !== '' ? (int)$_GET['userId'] : null;
$branchIdFilter = isset($_GET['branchId']) && $_GET['branchId'] !== '' ? (int)$_GET['branchId'] : null;
$search = trim((string)($_GET['search'] ?? ''));
$limit = min(500, max(10, (int)($_GET['limit'] ?? 100)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$where = ['1=1'];
$params = [];
$types = '';

if ($startDate !== '') {
    $where[] = 'a.created_at >= ?';
    $params[] = $startDate . ' 00:00:00';
    $types .= 's';
}

if ($endDate !== '') {
    $where[] = 'a.created_at <= ?';
    $params[] = $endDate . ' 23:59:59';
    $types .= 's';
}

if ($moduleFilter !== '' && $moduleFilter !== 'all') {
    $where[] = 'a.module = ?';
    $params[] = $moduleFilter;
    $types .= 's';
}

if ($actionFilter !== '' && $actionFilter !== 'all') {
    $where[] = 'a.action = ?';
    $params[] = strtoupper($actionFilter);
    $types .= 's';
}

if ($userIdFilter !== null && $userIdFilter > 0) {
    $where[] = 'a.user_id = ?';
    $params[] = $userIdFilter;
    $types .= 'i';
}

if ($branchIdFilter !== null && $branchIdFilter > 0) {
    $where[] = 'a.branch_id = ?';
    $params[] = $branchIdFilter;
    $types .= 'i';
}

if ($search !== '') {
    $where[] = '(a.description LIKE ? OR a.username LIKE ? OR a.ip_address LIKE ? OR a.module LIKE ? OR a.action LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sssss';
}

$whereSql = implode(' AND ', $where);

// Count total matching
$countSql = "SELECT COUNT(*) as total FROM audit_logs a WHERE {$whereSql}";
$cStmt = $connect->prepare($countSql);
if (!empty($params)) {
    $cStmt->bind_param($types, ...$params);
}
$cStmt->execute();
$cRes = $cStmt->get_result()->fetch_assoc();
$totalRecords = (int)($cRes['total'] ?? 0);
$cStmt->close();

// Query page of logs
$sql = "SELECT 
            a.log_id,
            a.user_id,
            a.username,
            a.branch_id,
            a.module,
            a.action,
            a.description,
            a.ip_address,
            a.user_agent,
            a.details,
            a.created_at,
            b.branch_name,
            b.branch_code,
            u.full_name
        FROM audit_logs a
        LEFT JOIN branches b ON a.branch_id = b.branch_id
        LEFT JOIN users u ON a.user_id = u.user_id
        WHERE {$whereSql}
        ORDER BY a.log_id DESC
        LIMIT ? OFFSET ?";

$pageParams = $params;
$pageParams[] = $limit;
$pageParams[] = $offset;
$pageTypes = $types . 'ii';

$stmt = $connect->prepare($sql);
$stmt->bind_param($pageTypes, ...$pageParams);
$stmt->execute();
$res = $stmt->get_result();

$logs = [];
while ($row = $res->fetch_assoc()) {
    $parsedDetails = null;
    if (!empty($row['details'])) {
        $parsedDetails = json_decode((string)$row['details'], true);
    }

    $logs[] = [
        'log_id' => (int)$row['log_id'],
        'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
        'username' => (string)$row['username'],
        'full_name' => (string)($row['full_name'] ?? $row['username']),
        'branch_id' => $row['branch_id'] ? (int)$row['branch_id'] : null,
        'branch_name' => (string)($row['branch_name'] ?? 'Global / N/A'),
        'branch_code' => (string)($row['branch_code'] ?? 'GLOBAL'),
        'module' => (string)$row['module'],
        'action' => (string)$row['action'],
        'description' => (string)$row['description'],
        'ip_address' => (string)($row['ip_address'] ?? '127.0.0.1'),
        'user_agent' => (string)($row['user_agent'] ?? ''),
        'details' => $parsedDetails,
        'created_at' => $row['created_at'],
    ];
}
$stmt->close();

// Summary stats
$statsQuery = "SELECT 
    COUNT(*) as total_all,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_logs,
    COUNT(DISTINCT CASE WHEN DATE(created_at) = CURDATE() AND user_id IS NOT NULL THEN user_id END) as today_users,
    SUM(CASE WHEN action IN ('LOGIN', 'LOGIN_FAILED') AND created_at >= NOW() - INTERVAL 24 HOUR THEN 1 ELSE 0 END) as auth_events_24h,
    SUM(CASE WHEN action = 'ACCESS_DENIED' THEN 1 ELSE 0 END) as security_alerts
    FROM audit_logs";
$sRes = $connect->query($statsQuery);
$sRow = $sRes ? $sRes->fetch_assoc() : [];

$stats = [
    'total_all' => (int)($sRow['total_all'] ?? 0),
    'today_logs' => (int)($sRow['today_logs'] ?? 0),
    'today_users' => (int)($sRow['today_users'] ?? 0),
    'auth_events_24h' => (int)($sRow['auth_events_24h'] ?? 0),
    'security_alerts' => (int)($sRow['security_alerts'] ?? 0),
];

$connect->close();

json_response([
    'success' => true,
    'data' => $logs,
    'total' => $totalRecords,
    'limit' => $limit,
    'offset' => $offset,
    'stats' => $stats,
]);

