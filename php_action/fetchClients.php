<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$userId = (int)($_SESSION['userId'] ?? 0);
if (!sb_has_permission($connect, $userId, 'clients', 'access') && !sb_has_permission($connect, $userId, 'orders', 'access')) {
    send_http_error(403, 'No tienes permiso para consultar clientes.');
}

$search     = trim((string)($_GET['search'] ?? ''));
$clientType = trim((string)($_GET['client_type'] ?? ''));
$docType    = trim((string)($_GET['document_type'] ?? ''));
$status     = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
$branchId   = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? (int)$_GET['branch_id'] : null;

// Mandatory soft delete filter: only records with record_status = 1 are active
$where  = ['c.record_status = 1'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]    = '(c.name LIKE ? OR c.document_number LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)';
    $like       = '%' . $search . '%';
    $params[]   = $like;
    $params[]   = $like;
    $params[]   = $like;
    $params[]   = $like;
    $params[]   = $like;
    $params[]   = $like;
    $types      .= 'ssssss';
}

if ($clientType !== '' && $clientType !== 'all') {
    if ($clientType === 'client') {
        $where[] = "c.client_type IN ('client', 'both')";
    } elseif ($clientType === 'supplier') {
        $where[] = "c.client_type IN ('supplier', 'both')";
    } else {
        $where[] = 'c.client_type = ?';
        $params[] = $clientType;
        $types .= 's';
    }
}

if ($docType !== '' && $docType !== 'all') {
    $where[]    = 'c.document_type = ?';
    $params[]   = $docType;
    $types      .= 's';
}

if ($status !== null && in_array($status, [0, 1], true)) {
    $where[]    = 'c.status = ?';
    $params[]   = $status;
    $types      .= 'i';
}

if ($branchId !== null && $branchId > 0) {
    $where[]    = '(c.branch_id = ? OR c.branch_id IS NULL)';
    $params[]   = $branchId;
    $types      .= 'i';
}

$whereSql   = implode(' AND ', $where);
$sql        = "SELECT 
            c.client_id,
            c.branch_id,
            c.document_type,
            c.document_number,
            c.name,
            c.first_name,
            c.last_name,
            c.birth_date,
            c.gender,
            c.email,
            c.phone,
            c.address,
            c.department,
            c.province,
            c.district,
            c.ubigeo,
            c.client_type,
            c.status,
            c.points_balance,
            c.lifetime_points,
            c.record_status,
            c.notes,
            c.created_at,
            c.updated_at,
            b.branch_name,
            b.branch_code
        FROM clients c
        LEFT JOIN branches b ON c.branch_id = b.branch_id
        WHERE {$whereSql}
        ORDER BY c.client_id DESC";

$stmt = $connect->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$clients = [];
while ($row = $res->fetch_assoc()) {
    $clients[] = [
        'client_id'         => (int)$row['client_id'],
        'branch_id'         => $row['branch_id'] ? (int)$row['branch_id'] : null,
        'branch_name'       => (string)($row['branch_name'] ?? 'Acceso Global'),
        'branch_code'       => (string)($row['branch_code'] ?? 'GLOBAL'),
        'document_type'     => (string)$row['document_type'],
        'document_number'   => (string)$row['document_number'],
        'name'              => (string)$row['name'],
        'first_name'        => (string)($row['first_name'] ?? ''),
        'last_name'         => (string)($row['last_name'] ?? ''),
        'birth_date'        => $row['birth_date'] ? (string)$row['birth_date'] : '',
        'gender'            => (string)($row['gender'] ?? ''),
        'email'             => (string)($row['email'] ?? ''),
        'phone'             => (string)($row['phone'] ?? ''),
        'address'           => (string)($row['address'] ?? ''),
        'department'        => (string)($row['department'] ?? ''),
        'province'          => (string)($row['province'] ?? ''),
        'district'          => (string)($row['district'] ?? ''),
        'ubigeo'            => (string)($row['ubigeo'] ?? ''),
        'client_type'       => (string)$row['client_type'],
        'status'            => (int)$row['status'],
        'points_balance'    => (int)($row['points_balance'] ?? 0),
        'lifetime_points'   => (int)($row['lifetime_points'] ?? 0),
        'notes'             => (string)($row['notes'] ?? ''),
        'created_at'        => $row['created_at'],
        'updated_at'        => $row['updated_at'],
    ];
}
$stmt->close();

// Stats calculation over all active (non-soft-deleted) records
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN client_type IN ('client', 'both') THEN 1 ELSE 0 END) as clients_count,
    SUM(CASE WHEN client_type IN ('supplier', 'both') THEN 1 ELSE 0 END) as suppliers_count,
    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive_count
    FROM clients WHERE record_status = 1";
$sRes = $connect->query($statsQuery);
$sRow = $sRes ? $sRes->fetch_assoc() : [];

$stats = [
    'total'             => (int)($sRow['total'] ?? count($clients)),
    'clients_count'     => (int)($sRow['clients_count'] ?? 0),
    'suppliers_count'   => (int)($sRow['suppliers_count'] ?? 0),
    'active_count'      => (int)($sRow['active_count'] ?? 0),
    'inactive_count'    => (int)($sRow['inactive_count'] ?? 0),
];

$connect->close();

json_response([
    'success'   => true,
    'data'      => $clients,
    'stats'     => $stats,
]);

