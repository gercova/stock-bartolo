<?php

require_once __DIR__ . '/core.php';
require_once __DIR__ . '/pagination.php';
require_method(['GET']);

$userId         = (int)$_SESSION['userId'];
$canSwitch      = sb_user_can_switch_branches($connect, $userId);
$activeBranchId = get_active_branch_id($connect);

$targetBranchId = isset($_GET['branchId']) && (int)$_GET['branchId'] > 0
    ? (int)$_GET['branchId']
    : (isset($_GET['branch_id']) && (int)$_GET['branch_id'] > 0 ? (int)$_GET['branch_id'] : $activeBranchId);

if ($targetBranchId !== $activeBranchId && !$canSwitch) {
    $targetBranchId = $activeBranchId;
}

// Ensure branch exists
$bStmt = $connect->prepare('SELECT branch_id, branch_code, branch_name FROM branches WHERE branch_id = ? AND record_status = 1 LIMIT 1');
$bStmt->bind_param('i', $targetBranchId);
$bStmt->execute();
$branchRow = $bStmt->get_result()->fetch_assoc();
$bStmt->close();

if (!$branchRow) {
    $connect->close();
    send_http_error(404, 'Sucursal no encontrada.');
}

// Standard Peruvian banking institutions list
$standardBanks = [
    ['name' => 'BCP', 'code' => '002', 'type' => 'corriente'],
    ['name' => 'Banco de la Nación', 'code' => '018', 'type' => 'corriente'],
    ['name' => 'BBVA', 'code' => '011', 'type' => 'corriente'],
    ['name' => 'Interbank', 'code' => '003', 'type' => 'corriente'],
    ['name' => 'Scotiabank', 'code' => '009', 'type' => 'corriente'],
    ['name' => 'BanBif', 'code' => '038', 'type' => 'corriente'],
    ['name' => 'Caja Arequipa', 'code' => '801', 'type' => 'ahorros'],
    ['name' => 'Caja Huancayo', 'code' => '802', 'type' => 'ahorros'],
    ['name' => 'Caja Piura', 'code' => '803', 'type' => 'ahorros'],
    ['name' => 'Caja Cusco', 'code' => '804', 'type' => 'ahorros'],
    ['name' => 'Banco Pichincha', 'code' => '053', 'type' => 'corriente'],
    ['name' => 'Yape', 'code' => 'YAPE', 'type' => 'billetera'],
    ['name' => 'Plin', 'code' => 'PLIN', 'type' => 'billetera'],
];

// Ensure all standard banks exist in bank_capital_accounts for this branch
$insAcc = $connect->prepare('INSERT IGNORE INTO bank_capital_accounts (branch_id, bank_name, bank_code, account_type, current_balance, total_deposited, status) VALUES (?, ?, ?, ?, 0.00, 0.00, 1)');
foreach ($standardBanks as $sb) {
    $insAcc->bind_param('isss', $targetBranchId, $sb['name'], $sb['code'], $sb['type']);
    $insAcc->execute();
}
$insAcc->close();

// Fetch accounts for this branch
$accStmt = $connect->prepare('SELECT account_id, branch_id, bank_name, bank_code, account_number, account_type, current_balance, total_deposited, total_withdrawn, status, notes, updated_at FROM bank_capital_accounts WHERE branch_id = ? ORDER BY current_balance DESC, bank_name ASC');
$accStmt->bind_param('i', $targetBranchId);
$accStmt->execute();
$accRes = $accStmt->get_result();

$accounts = [];
$totalCapital = 0.0;
$totalDeposited = 0.0;
$activeCount = 0;
$byBank = [];

while ($row = $accRes->fetch_assoc()) {
    $bal = round((float)$row['current_balance'], 2);
    $dep = round((float)$row['total_deposited'], 2);
    $with = round((float)$row['total_withdrawn'], 2);
    $status = (int)$row['status'];

    if ($status === 1) {
        $activeCount++;
        $totalCapital += $bal;
        $totalDeposited += $dep;
    }

    $byBank[$row['bank_name']] = $bal;

    $accounts[] = [
        'account_id'      => (int)$row['account_id'],
        'branch_id'       => (int)$row['branch_id'],
        'bank_name'       => $row['bank_name'],
        'bank_code'       => $row['bank_code'],
        'account_number'  => $row['account_number'] ?? '',
        'account_type'    => $row['account_type'],
        'current_balance' => $bal,
        'total_deposited' => $dep,
        'total_withdrawn' => $with,
        'status'          => $status,
        'notes'           => $row['notes'] ?? '',
        'updated_at'      => $row['updated_at'],
    ];
}
$accStmt->close();

// Build filters for audit deposits table
$bankFilter   = trim((string)($_GET['bank_name'] ?? $_GET['bankName'] ?? ''));
$search       = trim((string)($_GET['search'] ?? ''));
$startDate    = trim((string)($_GET['startDate'] ?? ''));
$endDate      = trim((string)($_GET['endDate'] ?? ''));
$allBranches  = isset($_GET['allBranches']) && $_GET['allBranches'] === '1' && $canSwitch;

$whereClauses = ['bcd.record_status = 1'];
$params       = [];
$types        = '';

if (!$allBranches) {
    $whereClauses[] = 'bcd.branch_id = ?';
    $params[] = $targetBranchId;
    $types .= 'i';
}

if ($bankFilter !== '' && $bankFilter !== 'all') {
    $whereClauses[] = 'bcd.bank_name = ?';
    $params[] = $bankFilter;
    $types .= 's';
}

if ($search !== '') {
    $whereClauses[] = '(bcd.voucher_number LIKE ? OR bcd.bank_branch LIKE ? OR bcd.account_number LIKE ? OR bcd.deposit_code LIKE ? OR bcd.notes LIKE ?)';
    $sLike = '%' . $search . '%';
    $params[] = $sLike;
    $params[] = $sLike;
    $params[] = $sLike;
    $params[] = $sLike;
    $params[] = $sLike;
    $types .= 'sssss';
}

if ($startDate !== '') {
    $whereClauses[] = 'DATE(bcd.deposit_date) >= ?';
    $params[] = $startDate;
    $types .= 's';
}

if ($endDate !== '') {
    $whereClauses[] = 'DATE(bcd.deposit_date) <= ?';
    $params[] = $endDate;
    $types .= 's';
}

$whereSql = implode(' AND ', $whereClauses);

// Count total matching deposits
$cntSql = "SELECT COUNT(*) AS total_count FROM bank_capital_deposits bcd WHERE {$whereSql}";
$cntStmt = $connect->prepare($cntSql);
if (!empty($params)) {
    $cntStmt->bind_param($types, ...$params);
}
$cntStmt->execute();
$totalDeposits = (int)($cntStmt->get_result()->fetch_assoc()['total_count'] ?? 0);
$cntStmt->close();

// Pagination params
$pagination = sb_get_pagination_params(25, 250);

$querySql = "SELECT bcd.*, b.branch_name, COALESCE(u.username, 'admin') AS username 
             FROM bank_capital_deposits bcd 
             LEFT JOIN branches b ON b.branch_id = bcd.branch_id 
             LEFT JOIN users u ON u.user_id = bcd.user_id 
             WHERE {$whereSql} 
             ORDER BY bcd.deposit_date DESC, bcd.deposit_id DESC";

if ($pagination['enabled'] && $pagination['limit'] !== null) {
    $querySql .= ' LIMIT ? OFFSET ?';
    $params[] = $pagination['limit'];
    $params[] = $pagination['offset'];
    $types .= 'ii';
}

$depStmt = $connect->prepare($querySql);
if (!empty($params)) {
    $depStmt->bind_param($types, ...$params);
}
$depStmt->execute();
$depRes = $depStmt->get_result();

$deposits = [];
while ($d = $depRes->fetch_assoc()) {
    $deposits[] = [
        'deposit_id'     => (int)$d['deposit_id'],
        'deposit_code'   => $d['deposit_code'],
        'branch_id'      => (int)$d['branch_id'],
        'branch_name'    => $d['branch_name'] ?? 'Sucursal',
        'bank_name'      => $d['bank_name'],
        'bank_branch'    => $d['bank_branch'],
        'account_number' => $d['account_number'] ?? '',
        'voucher_number' => $d['voucher_number'] ?? '',
        'amount'         => round((float)$d['amount'], 2),
        'balance_before' => round((float)$d['balance_before'], 2),
        'balance_after'  => round((float)$d['balance_after'], 2),
        'funding_source' => $d['funding_source'],
        'deposit_date'   => $d['deposit_date'],
        'user_id'        => (int)$d['user_id'],
        'username'       => $d['username'] ?? 'Sistema',
        'notes'          => $d['notes'] ?? '',
        'record_status'  => (int)$d['record_status'],
        'created_at'     => $d['created_at'],
    ];
}
$depStmt->close();

// Branches list for filter/selector
$branchesList = [];
$brRes = $connect->query('SELECT branch_id, branch_code, branch_name, is_main, active FROM branches WHERE record_status = 1 ORDER BY is_main DESC, branch_id ASC');
if ($brRes) {
    while ($br = $brRes->fetch_assoc()) {
        $branchesList[] = [
            'branch_id'   => (int)$br['branch_id'],
            'branch_code' => $br['branch_code'],
            'branch_name' => $br['branch_name'],
            'is_main'     => (int)$br['is_main'],
            'active'      => (int)$br['active'],
        ];
    }
}

// Available General Cash balance for current branch (for optional funding source)
$gcBalance = 0.00;
$gcStmt = $connect->prepare('SELECT current_balance FROM general_cash_accounts WHERE branch_id = ? LIMIT 1');
$gcStmt->bind_param('i', $targetBranchId);
$gcStmt->execute();
$gcRow = $gcStmt->get_result()->fetch_assoc();
$gcStmt->close();
if ($gcRow) {
    $gcBalance = round((float)$gcRow['current_balance'], 2);
}

$connect->close();

$meta = sb_build_pagination_meta($totalDeposits, $pagination);

json_response([
    'success'      => true,
    'branch'       => [
        'branch_id'   => (int)$branchRow['branch_id'],
        'branch_code' => $branchRow['branch_code'],
        'branch_name' => $branchRow['branch_name'],
    ],
    'accounts'     => $accounts,
    'summary'      => [
        'total_capital'        => round($totalCapital, 2),
        'total_deposited'      => round($totalDeposited, 2),
        'active_accounts_count'=> $activeCount,
        'total_deposits_count' => $totalDeposits,
        'general_cash_balance' => $gcBalance,
        'by_bank'              => $byBank,
    ],
    'deposits'     => $deposits,
    'pagination'   => $meta,
    'branches'     => $branchesList,
]);
