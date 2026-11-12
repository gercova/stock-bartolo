<?php

require_once __DIR__ . '/core.php';
require_method(['GET']);

$year = isset($_GET['year']) && is_numeric($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$branchFilter = trim((string)($_GET['branch_id'] ?? 'all'));
$activeBranchId = get_active_branch_id($connect);

$branchWhereOp = '';
$branchWhereJoin = '';
$branchParams = [];
$branchTypes = '';

if ($branchFilter !== 'all' && is_numeric($branchFilter)) {
    $targetBranchId = (int)$branchFilter;
    $branchWhereOp = ' AND branch_id = ' . $targetBranchId;
    $branchWhereJoin = ' AND o.branch_id = ' . $targetBranchId;
}

// 1. Available Years
$yearsRes = $connect->query("SELECT DISTINCT YEAR(created_at) as y FROM branch_operations WHERE status = 'completed' ORDER BY y DESC");
$availableYears = [];
if ($yearsRes) {
    while ($yRow = $yearsRes->fetch_assoc()) {
        $availableYears[] = (int)$yRow['y'];
    }
}
if (!in_array((int)date('Y'), $availableYears, true)) {
    array_unshift($availableYears, (int)date('Y'));
}
sort($availableYears);
$availableYears = array_reverse($availableYears);

// 2. Summary KPI Totals (Filtered by Year and Branch)
$kpiSql = "SELECT 
            COUNT(*) as total_ops,
            SUM(CASE WHEN operation_type = 'deposit' THEN 1 ELSE 0 END) as deposit_count,
            SUM(CASE WHEN operation_type = 'withdrawal' THEN 1 ELSE 0 END) as withdrawal_count,
            COALESCE(SUM(CASE WHEN operation_type = 'deposit' THEN amount ELSE 0 END), 0) as total_deposits_amount,
            COALESCE(SUM(CASE WHEN operation_type = 'withdrawal' THEN amount ELSE 0 END), 0) as total_withdrawals_amount,
            COALESCE(SUM(commission_amount), 0) as total_commissions_amount,
            COALESCE(SUM(amount), 0) as total_volume_processed,
            COALESCE(SUM(points_earned), 0) as total_points_earned,
            COUNT(DISTINCT client_id) as total_clients_transacted
        FROM branch_operations
        WHERE status = 'completed' AND YEAR(created_at) = ? {$branchWhereOp}";

$kpiStmt = $connect->prepare($kpiSql);
$kpiStmt->bind_param('i', $year);
$kpiStmt->execute();
$kpi = $kpiStmt->get_result()->fetch_assoc();
$kpiStmt->close();

$totalDeposits = (float)($kpi['total_deposits_amount'] ?? 0.0);
$totalWithdrawals = (float)($kpi['total_withdrawals_amount'] ?? 0.0);
$totalVolume = (float)($kpi['total_volume_processed'] ?? 0.0);
$totalCommissions = (float)($kpi['total_commissions_amount'] ?? 0.0);
$totalOps = (int)($kpi['total_ops'] ?? 0);
$netCashFlow = round($totalDeposits - $totalWithdrawals, 2);

$totals = [
    'total_ops' => $totalOps,
    'deposit_count' => (int)($kpi['deposit_count'] ?? 0),
    'withdrawal_count' => (int)($kpi['withdrawal_count'] ?? 0),
    'total_deposits_amount' => $totalDeposits,
    'total_withdrawals_amount' => $totalWithdrawals,
    'total_commissions_amount' => $totalCommissions,
    'total_volume_processed' => $totalVolume,
    'net_cash_flow' => $netCashFlow,
    'total_points_earned' => (int)($kpi['total_points_earned'] ?? 0),
    'total_clients_transacted' => (int)($kpi['total_clients_transacted'] ?? 0),
];

// 3. Monthly Deposits and Withdrawals (by Month and Year)
$monthNames = [
    1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
    5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
    9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
];

$monthSql = "SELECT 
                MONTH(created_at) as m,
                COALESCE(SUM(CASE WHEN operation_type = 'deposit' THEN amount ELSE 0 END), 0) as deposit_amount,
                COALESCE(SUM(CASE WHEN operation_type = 'withdrawal' THEN amount ELSE 0 END), 0) as withdrawal_amount,
                COALESCE(SUM(commission_amount), 0) as commission_amount,
                COALESCE(SUM(amount), 0) as total_volume,
                COUNT(*) as total_ops,
                SUM(CASE WHEN operation_type = 'deposit' THEN 1 ELSE 0 END) as deposit_count,
                SUM(CASE WHEN operation_type = 'withdrawal' THEN 1 ELSE 0 END) as withdrawal_count
            FROM branch_operations
            WHERE status = 'completed' AND YEAR(created_at) = ? {$branchWhereOp}
            GROUP BY MONTH(created_at)
            ORDER BY m ASC";

$mStmt = $connect->prepare($monthSql);
$mStmt->bind_param('i', $year);
$mStmt->execute();
$mRes = $mStmt->get_result();
$dbMonthly = [];
while ($mRow = $mRes->fetch_assoc()) {
    $dbMonthly[(int)$mRow['m']] = $mRow;
}
$mStmt->close();

$monthlyData = [];
for ($m = 1; $m <= 12; $m++) {
    $row = $dbMonthly[$m] ?? null;
    $monthlyData[] = [
        'month' => $m,
        'month_name' => $monthNames[$m],
        'deposit_amount' => (float)($row['deposit_amount'] ?? 0.0),
        'withdrawal_amount' => (float)($row['withdrawal_amount'] ?? 0.0),
        'commission_amount' => (float)($row['commission_amount'] ?? 0.0),
        'total_volume' => (float)($row['total_volume'] ?? 0.0),
        'total_ops' => (int)($row['total_ops'] ?? 0),
        'deposit_count' => (int)($row['deposit_count'] ?? 0),
        'withdrawal_count' => (int)($row['withdrawal_count'] ?? 0),
    ];
}

// 4. Total Monetary Value Processed for Each Type of Operation
$operationsByType = [
    [
        'type'      => 'deposit',
        'label'     => 'Depósitos',
        'amount'    => $totalDeposits,
        'count'     => $totals['deposit_count'],
        'percentage' => $totalVolume > 0 ? round(($totalDeposits / $totalVolume) * 100, 1) : 0,
        'color'     => '#10b981', // emerald-500
    ],
    [
        'type'      => 'withdrawal',
        'label'     => 'Retiros',
        'amount'    => $totalWithdrawals,
        'count'     => $totals['withdrawal_count'],
        'percentage' => $totalVolume > 0 ? round(($totalWithdrawals / $totalVolume) * 100, 1) : 0,
        'color'     => '#f43f5e', // rose-500
    ],
];

// 5. Branches with Highest Activity
$bSql = "SELECT 
            b.branch_id,
            b.branch_name,
            b.branch_code,
            COUNT(o.operation_id) as total_ops,
            COALESCE(SUM(CASE WHEN o.operation_type = 'deposit' THEN o.amount ELSE 0 END), 0) as deposits_amount,
            COALESCE(SUM(CASE WHEN o.operation_type = 'withdrawal' THEN o.amount ELSE 0 END), 0) as withdrawals_amount,
            COALESCE(SUM(o.commission_amount), 0) as commissions_amount,
            COALESCE(SUM(o.amount), 0) as total_volume
        FROM branches b
        LEFT JOIN branch_operations o ON b.branch_id = o.branch_id AND o.status = 'completed' AND YEAR(o.created_at) = ?
        WHERE b.record_status = 1
        GROUP BY b.branch_id
        ORDER BY total_volume DESC, total_ops DESC";

$bStmt = $connect->prepare($bSql);
$bStmt->bind_param('i', $year);
$bStmt->execute();
$bRes = $bStmt->get_result();

$branchesActivity = [];
$totalAllBranchesVolume = 0.0;
while ($bRow = $bRes->fetch_assoc()) {
    $vol = (float)$bRow['total_volume'];
    $totalAllBranchesVolume += $vol;
    $branchesActivity[] = [
        'branch_id'             => (int)$bRow['branch_id'],
        'branch_name'           => (string)$bRow['branch_name'],
        'branch_code'           => (string)$bRow['branch_code'],
        'total_ops'             => (int)$bRow['total_ops'],
        'deposits_amount'       => (float)$bRow['deposits_amount'],
        'withdrawals_amount'    => (float)$bRow['withdrawals_amount'],
        'commissions_amount'    => (float)$bRow['commissions_amount'],
        'total_volume'          => $vol,
        'percentage'            => 0.0,
    ];
}
$bStmt->close();

foreach ($branchesActivity as &$ba) {
    $ba['percentage'] = $totalAllBranchesVolume > 0 ? round(($ba['total_volume'] / $totalAllBranchesVolume) * 100, 1) : 0.0;
}
unset($ba);

// 6. Top Clients (Volume, Frequency, and Points)
$cSql = "SELECT 
            c.client_id,
            c.name,
            c.document_type,
            c.document_number,
            c.phone,
            c.points_balance,
            c.lifetime_points,
            COUNT(o.operation_id) as total_ops,
            SUM(CASE WHEN o.operation_type = 'deposit' THEN 1 ELSE 0 END) as deposit_ops,
            SUM(CASE WHEN o.operation_type = 'withdrawal' THEN 1 ELSE 0 END) as withdrawal_ops,
            COALESCE(SUM(o.amount), 0) as total_volume,
            COALESCE(SUM(CASE WHEN o.operation_type = 'deposit' THEN o.amount ELSE 0 END), 0) as total_deposits,
            COALESCE(SUM(CASE WHEN o.operation_type = 'withdrawal' THEN o.amount ELSE 0 END), 0) as total_withdrawals,
            MAX(o.created_at) as last_operation_at
        FROM clients c
        LEFT JOIN branch_operations o ON c.client_id = o.client_id AND o.status = 'completed' AND YEAR(o.created_at) = ? {$branchWhereJoin}
        WHERE c.record_status = 1
        GROUP BY c.client_id";

$cStmt = $connect->prepare($cSql);
$cStmt->bind_param('i', $year);
$cStmt->execute();
$cRes = $cStmt->get_result();

$allClients = [];
while ($cRow = $cRes->fetch_assoc()) {
    $allClients[] = [
        'client_id'             => (int)$cRow['client_id'],
        'name'                  => (string)$cRow['name'],
        'document_type'         => (string)$cRow['document_type'],
        'document_number'       => (string)$cRow['document_number'],
        'phone'                 => (string)($cRow['phone'] ?? ''),
        'points_balance'        => (int)$cRow['points_balance'],
        'lifetime_points'       => (int)$cRow['lifetime_points'],
        'total_ops'             => (int)$cRow['total_ops'],
        'deposit_ops'           => (int)($cRow['deposit_ops'] ?? 0),
        'withdrawal_ops'        => (int)($cRow['withdrawal_ops'] ?? 0),
        'total_volume'          => (float)$cRow['total_volume'],
        'total_deposits'        => (float)$cRow['total_deposits'],
        'total_withdrawals'     => (float)$cRow['total_withdrawals'],
        'last_operation_at'     => $cRow['last_operation_at'],
    ];
}
$cStmt->close();

// Sort Top by Volume
$topByVolume = $allClients;
usort($topByVolume, fn($a, $b) => $b['total_volume'] <=> $a['total_volume'] ?: $b['total_ops'] <=> $a['total_ops']);
$topByVolume = array_slice($topByVolume, 0, 10);

// Sort Top by Frequency (most frequent transactions)
$topByFrequency = $allClients;
usort($topByFrequency, fn($a, $b) => $b['total_ops'] <=> $a['total_ops'] ?: $b['total_volume'] <=> $a['total_volume']);
$topByFrequency = array_slice($topByFrequency, 0, 10);

// Sort Top by Points (highest point totals)
$topByPoints = $allClients;
usort($topByPoints, fn($a, $b) => $b['lifetime_points'] <=> $a['lifetime_points'] ?: $b['points_balance'] <=> $a['points_balance']);
$topByPoints = array_slice($topByPoints, 0, 10);

// Also list branches for the filter dropdown
$branchListRes  = $connect->query("SELECT branch_id, branch_name, branch_code FROM branches WHERE record_status = 1 ORDER BY is_main DESC, branch_id ASC");
$branchesList   = [];
if ($branchListRes) {
    while ($brRow = $branchListRes->fetch_assoc()) {
        $branchesList[] = [
            'branch_id'     => (int)$brRow['branch_id'],
            'branch_name'   => (string)$brRow['branch_name'],
            'branch_code'   => (string)$brRow['branch_code'],
        ];
    }
}

$connect->close();

json_response([
    'success'                   => true,
    'selected_year'             => $year,
    'selected_branch'           => $branchFilter,
    'available_years'           => $availableYears,
    'branches'                  => $branchesList,
    'totals'                    => $totals,
    'monthly'                   => $monthlyData,
    'operations_by_type'        => $operationsByType,
    'branches_activity'         => $branchesActivity,
    'top_clients_by_volume'     => $topByVolume,
    'top_clients_by_frequency'  => $topByFrequency,
    'top_clients_by_points'     => $topByPoints,
]);
