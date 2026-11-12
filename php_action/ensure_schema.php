<?php

function sb_column_exists(array $colsMap, string $table, string $column): bool
{
    return isset($colsMap[strtolower($table)][strtolower($column)]);
}

function sb_load_columns_map(mysqli $connect): array
{
    $map = [];
    $res = $connect->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $t = strtolower((string)$row['TABLE_NAME']);
            $c = strtolower((string)$row['COLUMN_NAME']);
            $map[$t][$c] = true;
        }
    }
    return $map;
}

function sb_ensure_index(mysqli $connect, string $table, string $indexName, string $columnsSql): void
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeIdx = preg_replace('/[^a-zA-Z0-9_]/', '', $indexName);
    $res = $connect->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIdx}'");
    if ($res && $res->num_rows === 0) {
        $connect->query("ALTER TABLE `{$safeTable}` ADD KEY `{$safeIdx}` ({$columnsSql})");
    }
}

function ensure_app_schema(mysqli $connect): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    // 1. Multi-branch (multi-tenant) core tables
    $connect->query("CREATE TABLE IF NOT EXISTS `branches` (
        `branch_id` int(11) NOT NULL AUTO_INCREMENT,
        `branch_code` varchar(32) NOT NULL,
        `branch_name` varchar(150) NOT NULL,
        `address` varchar(255) DEFAULT '',
        `phone` varchar(60) DEFAULT '',
        `email` varchar(120) DEFAULT '',
        `manager_name` varchar(120) DEFAULT '',
        `tax_rate` decimal(5,2) NOT NULL DEFAULT 13.00,
        `currency_code` varchar(10) NOT NULL DEFAULT 'USD',
        `currency_symbol` varchar(8) NOT NULL DEFAULT '$',
        `is_main` tinyint(1) NOT NULL DEFAULT 0,
        `active` tinyint(1) NOT NULL DEFAULT 1,
        `record_status` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`branch_id`),
        KEY `idx_branches_code` (`branch_code`),
        KEY `idx_branches_status` (`record_status`, `active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $connect->query("CREATE TABLE IF NOT EXISTS `system_settings` (
        `setting_id` int(11) NOT NULL,
        `company_name` varchar(180) NOT NULL DEFAULT 'Stock Bartolo',
        `company_tax_id` varchar(60) DEFAULT '',
        `company_phone` varchar(60) DEFAULT '',
        `company_address` varchar(255) DEFAULT '',
        `receipt_footer` varchar(255) NOT NULL DEFAULT 'Gracias por su preferencia.',
        `default_tax_rate` decimal(5,2) NOT NULL DEFAULT 13.00,
        `default_currency_code` varchar(10) NOT NULL DEFAULT 'USD',
        `default_currency_symbol` varchar(8) NOT NULL DEFAULT '$',
        `enforce_open_till_for_cash` tinyint(1) NOT NULL DEFAULT 0,
        `auto_fund_till_from_general_cash` tinyint(1) NOT NULL DEFAULT 1,
        `auto_return_till_to_general_cash` tinyint(1) NOT NULL DEFAULT 1,
        `allow_branch_switching` tinyint(1) NOT NULL DEFAULT 1,
        `updated_by` int(11) DEFAULT NULL,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`setting_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 2. General Cash/Till (Caja General - Operational Cash Flow Capital per branch)
    $connect->query("CREATE TABLE IF NOT EXISTS `general_cash_accounts` (
        `account_id` int(11) NOT NULL AUTO_INCREMENT,
        `branch_id` int(11) NOT NULL,
        `account_name` varchar(150) NOT NULL DEFAULT 'Caja General Principal',
        `current_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
        `initial_capital` decimal(12,2) NOT NULL DEFAULT 0.00,
        `min_balance_alert` decimal(12,2) NOT NULL DEFAULT 0.00,
        `status` tinyint(1) NOT NULL DEFAULT 1,
        `notes` varchar(255) DEFAULT '',
        `updated_by` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`account_id`),
        UNIQUE KEY `uniq_general_cash_branch` (`branch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $connect->query("CREATE TABLE IF NOT EXISTS `general_cash_movements` (
        `movement_id` int(11) NOT NULL AUTO_INCREMENT,
        `branch_id` int(11) NOT NULL,
        `account_id` int(11) NOT NULL,
        `cash_session_id` int(11) DEFAULT NULL,
        `user_id` int(11) NOT NULL,
        `movement_type` enum('initial_capital','deposit','withdrawal','till_open_allocation','till_close_return','adjustment_in','adjustment_out') NOT NULL,
        `direction` enum('in','out') NOT NULL,
        `amount` decimal(12,2) NOT NULL,
        `balance_before` decimal(12,2) NOT NULL,
        `balance_after` decimal(12,2) NOT NULL,
        `reference` varchar(100) DEFAULT '',
        `description` varchar(255) NOT NULL,
        `record_status` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`movement_id`),
        KEY `idx_gc_mov_branch` (`branch_id`, `created_at`),
        KEY `idx_gc_mov_account` (`account_id`),
        KEY `idx_gc_mov_session` (`cash_session_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 3. Operational Cash/Till (Caja - Register CRUD, Opening, Movements, Reconciliations, Closing)
    $connect->query("CREATE TABLE IF NOT EXISTS `cash_registers` (
        `register_id` int(11) NOT NULL AUTO_INCREMENT,
        `branch_id` int(11) NOT NULL,
        `register_code` varchar(32) NOT NULL,
        `register_name` varchar(120) NOT NULL,
        `location` varchar(150) DEFAULT '',
        `active` tinyint(1) NOT NULL DEFAULT 1,
        `record_status` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`register_id`),
        KEY `idx_cash_reg_branch` (`branch_id`, `record_status`, `active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $connect->query("CREATE TABLE IF NOT EXISTS `cash_sessions` (
        `session_id` int(11) NOT NULL AUTO_INCREMENT,
        `branch_id` int(11) NOT NULL,
        `register_id` int(11) NOT NULL,
        `session_code` varchar(32) NOT NULL,
        `opened_by` int(11) NOT NULL,
        `closed_by` int(11) DEFAULT NULL,
        `opened_at` datetime NOT NULL,
        `closed_at` datetime DEFAULT NULL,
        `opening_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
        `funded_from_general_cash` tinyint(1) NOT NULL DEFAULT 0,
        `cash_in_total` decimal(12,2) NOT NULL DEFAULT 0.00,
        `cash_out_total` decimal(12,2) NOT NULL DEFAULT 0.00,
        `non_cash_sales_total` decimal(12,2) NOT NULL DEFAULT 0.00,
        `expected_cash` decimal(12,2) NOT NULL DEFAULT 0.00,
        `counted_cash` decimal(12,2) DEFAULT NULL,
        `card_reported` decimal(12,2) NOT NULL DEFAULT 0.00,
        `transfer_reported` decimal(12,2) NOT NULL DEFAULT 0.00,
        `check_reported` decimal(12,2) NOT NULL DEFAULT 0.00,
        `difference_amount` decimal(12,2) DEFAULT NULL,
        `returned_to_general_cash` tinyint(1) NOT NULL DEFAULT 0,
        `amount_returned_general` decimal(12,2) NOT NULL DEFAULT 0.00,
        `retained_float` decimal(12,2) NOT NULL DEFAULT 0.00,
        `status` enum('open','closed') NOT NULL DEFAULT 'open',
        `opening_notes` varchar(255) DEFAULT '',
        `closing_notes` varchar(255) DEFAULT '',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`session_id`),
        KEY `idx_cash_sess_branch_status` (`branch_id`, `status`),
        KEY `idx_cash_sess_reg_status` (`register_id`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $connect->query("CREATE TABLE IF NOT EXISTS `cash_movements` (
        `movement_id` int(11) NOT NULL AUTO_INCREMENT,
        `branch_id` int(11) NOT NULL,
        `session_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `movement_type` enum('sale_cash','order_payment_cash','loan_disbursement','loan_payment_cash','manual_in','manual_out','expense','sweep_to_general') NOT NULL,
        `direction` enum('in','out') NOT NULL,
        `amount` decimal(12,2) NOT NULL,
        `payment_method` tinyint(1) NOT NULL DEFAULT 2,
        `reference_type` varchar(40) DEFAULT '',
        `reference_id` int(11) DEFAULT NULL,
        `description` varchar(255) NOT NULL,
        `record_status` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`movement_id`),
        KEY `idx_cash_mov_session` (`session_id`, `record_status`),
        KEY `idx_cash_mov_branch` (`branch_id`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $connect->query("CREATE TABLE IF NOT EXISTS `cash_reconciliations` (
        `reconciliation_id` int(11) NOT NULL AUTO_INCREMENT,
        `branch_id` int(11) NOT NULL,
        `session_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `reconciliation_type` enum('partial','closing') NOT NULL DEFAULT 'partial',
        `reconciled_at` datetime NOT NULL,
        `expected_cash` decimal(12,2) NOT NULL,
        `counted_cash` decimal(12,2) NOT NULL,
        `difference_amount` decimal(12,2) NOT NULL,
        `card_reported` decimal(12,2) NOT NULL DEFAULT 0.00,
        `transfer_reported` decimal(12,2) NOT NULL DEFAULT 0.00,
        `check_reported` decimal(12,2) NOT NULL DEFAULT 0.00,
        `status_label` enum('exact','surplus','shortage') NOT NULL,
        `denominations_json` text DEFAULT NULL,
        `notes` varchar(255) DEFAULT '',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`reconciliation_id`),
        KEY `idx_cash_rec_session` (`session_id`, `reconciled_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 4. Existing Loans & Login Attempts tables
    $connect->query("CREATE TABLE IF NOT EXISTS `loans` (
        `loan_id` int(11) NOT NULL AUTO_INCREMENT,
        `branch_id` int(11) NOT NULL DEFAULT 1,
        `loan_code` varchar(32) NOT NULL,
        `user_id` int(11) NOT NULL,
        `client_name` varchar(255) NOT NULL,
        `client_contact` varchar(100) NOT NULL,
        `client_document` varchar(100) DEFAULT NULL,
        `order_id` int(11) DEFAULT NULL,
        `loan_date` date NOT NULL,
        `due_date` date DEFAULT NULL,
        `principal_amount` decimal(12,2) NOT NULL,
        `has_interest` tinyint(1) NOT NULL DEFAULT 0,
        `interest_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
        `interest_mode` enum('flat','monthly') NOT NULL DEFAULT 'flat',
        `term_months` int(11) NOT NULL DEFAULT 1,
        `interest_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
        `total_amount` decimal(12,2) NOT NULL,
        `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
        `balance_due` decimal(12,2) NOT NULL,
        `loan_status` tinyint(1) NOT NULL DEFAULT 1,
        `record_status` tinyint(1) NOT NULL DEFAULT 1,
        `notes` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`loan_id`),
        KEY `idx_loans_branch` (`branch_id`),
        KEY `idx_loans_user` (`user_id`),
        KEY `idx_loans_status` (`loan_status`, `record_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $connect->query("CREATE TABLE IF NOT EXISTS `loan_payments` (
        `payment_id` int(11) NOT NULL AUTO_INCREMENT,
        `branch_id` int(11) NOT NULL DEFAULT 1,
        `loan_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `payment_date` date NOT NULL,
        `amount` decimal(12,2) NOT NULL,
        `payment_method` tinyint(1) NOT NULL DEFAULT 2,
        `notes` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`payment_id`),
        KEY `idx_loan_payments_branch` (`branch_id`),
        KEY `idx_loan_payments_loan` (`loan_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $connect->query("CREATE TABLE IF NOT EXISTS `login_attempts` (
        `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
        `ip_address` varchar(45) NOT NULL,
        `username` varchar(150) NOT NULL,
        `attempts` int(11) NOT NULL DEFAULT 0,
        `first_attempt_at` datetime NOT NULL,
        `locked_until` datetime DEFAULT NULL,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`attempt_id`),
        UNIQUE KEY `uniq_login_attempt_ip_user` (`ip_address`, `username`),
        KEY `idx_login_attempts_ip_updated` (`ip_address`, `updated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 5. Security Management: Roles, Role Permissions, and Audit Logs
    $connect->query("CREATE TABLE IF NOT EXISTS `roles` (
        `role_id` int(11) NOT NULL AUTO_INCREMENT,
        `role_name` varchar(100) NOT NULL,
        `role_slug` varchar(50) NOT NULL,
        `description` varchar(255) DEFAULT '',
        `is_system` tinyint(1) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`role_id`),
        UNIQUE KEY `uniq_role_slug` (`role_slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $connect->query("CREATE TABLE IF NOT EXISTS `role_permissions` (
        `permission_id` int(11) NOT NULL AUTO_INCREMENT,
        `role_id` int(11) NOT NULL,
        `module` varchar(50) NOT NULL,
        `can_access` tinyint(1) NOT NULL DEFAULT 0,
        `can_create` tinyint(1) NOT NULL DEFAULT 0,
        `can_update` tinyint(1) NOT NULL DEFAULT 0,
        `can_delete` tinyint(1) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`permission_id`),
        UNIQUE KEY `uniq_role_module` (`role_id`, `module`),
        KEY `idx_role_perm_role` (`role_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $connect->query("CREATE TABLE IF NOT EXISTS `audit_logs` (
        `log_id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) DEFAULT NULL,
        `username` varchar(100) NOT NULL DEFAULT 'sistema',
        `branch_id` int(11) DEFAULT NULL,
        `module` varchar(50) NOT NULL,
        `action` varchar(50) NOT NULL,
        `description` varchar(255) NOT NULL,
        `ip_address` varchar(45) NOT NULL DEFAULT '',
        `user_agent` varchar(255) DEFAULT '',
        `details` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`log_id`),
        KEY `idx_audit_created` (`created_at`),
        KEY `idx_audit_user` (`user_id`, `created_at`),
        KEY `idx_audit_module_action` (`module`, `action`),
        KEY `idx_audit_branch` (`branch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 6. Idempotent column upgrades for legacy tables
    $colsMap = sb_load_columns_map($connect);

    $branchScopedTables = [
        'brands' => 'brand_id',
        'categories' => 'categories_id',
        'product' => 'product_id',
        'orders' => 'order_id',
        'loans' => 'loan_id',
        'loan_payments' => 'payment_id',
    ];

    foreach ($branchScopedTables as $tbl => $afterCol) {
        if (isset($colsMap[$tbl]) && !sb_column_exists($colsMap, $tbl, 'branch_id')) {
            $connect->query("ALTER TABLE `{$tbl}` ADD COLUMN `branch_id` int(11) NOT NULL DEFAULT 1 AFTER `{$afterCol}`");
            sb_ensure_index($connect, $tbl, "idx_{$tbl}_branch", '`branch_id`');
        }
    }

    if (isset($colsMap['users'])) {
        if (!sb_column_exists($colsMap, 'users', 'branch_id')) {
            $connect->query("ALTER TABLE `users` ADD COLUMN `branch_id` int(11) NOT NULL DEFAULT 1");
        }
        if (!sb_column_exists($colsMap, 'users', 'role')) {
            $connect->query("ALTER TABLE `users` ADD COLUMN `role` varchar(30) NOT NULL DEFAULT 'superadmin'");
        }
        if (!sb_column_exists($colsMap, 'users', 'role_id')) {
            $connect->query("ALTER TABLE `users` ADD COLUMN `role_id` int(11) DEFAULT 1 AFTER `email`");
            sb_ensure_index($connect, 'users', 'idx_users_role_id', '`role_id`');
        }
        if (!sb_column_exists($colsMap, 'users', 'full_name')) {
            $connect->query("ALTER TABLE `users` ADD COLUMN `full_name` varchar(150) DEFAULT '' AFTER `username`");
        }
        if (!sb_column_exists($colsMap, 'users', 'phone')) {
            $connect->query("ALTER TABLE `users` ADD COLUMN `phone` varchar(60) DEFAULT '' AFTER `email`");
        }
        if (!sb_column_exists($colsMap, 'users', 'last_login')) {
            $connect->query("ALTER TABLE `users` ADD COLUMN `last_login` datetime DEFAULT NULL");
        }
        if (!sb_column_exists($colsMap, 'users', 'created_at')) {
            $connect->query("ALTER TABLE `users` ADD COLUMN `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
        if (!sb_column_exists($colsMap, 'users', 'updated_at')) {
            $connect->query("ALTER TABLE `users` ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
        if (!sb_column_exists($colsMap, 'users', 'active')) {
            $connect->query("ALTER TABLE `users` ADD COLUMN `active` tinyint(1) NOT NULL DEFAULT 1");
        }
    }

    // 7. Seed baseline tenant #1 & global config atomically if absent
    $connect->query("INSERT IGNORE INTO `branches`
        (`branch_id`, `branch_code`, `branch_name`, `address`, `phone`, `email`, `manager_name`, `tax_rate`, `currency_code`, `currency_symbol`, `is_main`, `active`, `record_status`)
        VALUES
        (1, 'SUC-001', 'Sucursal Principal', 'Casa Matriz', '', '', 'Administrador', 13.00, 'USD', '$', 1, 1, 1)");

    $connect->query("INSERT IGNORE INTO `system_settings`
        (`setting_id`, `company_name`, `company_tax_id`, `company_phone`, `company_address`, `receipt_footer`, `default_tax_rate`, `default_currency_code`, `default_currency_symbol`, `enforce_open_till_for_cash`, `auto_fund_till_from_general_cash`, `auto_return_till_to_general_cash`, `allow_branch_switching`)
        VALUES
        (1, 'Stock Bartolo', '', '', '', 'Gracias por su preferencia.', 13.00, 'USD', '$', 0, 1, 1, 1)");

    $connect->query("INSERT IGNORE INTO `general_cash_accounts`
        (`branch_id`, `account_name`, `current_balance`, `initial_capital`, `min_balance_alert`, `status`, `notes`)
        VALUES
        (1, 'Caja General — Sucursal Principal', 0.00, 0.00, 100.00, 1, 'Capital operativo independiente de Sucursal Principal')");

    $regCountRes = $connect->query("SELECT COUNT(*) AS cnt FROM `cash_registers` WHERE `branch_id` = 1");
    if ($regCountRes && (int)($regCountRes->fetch_assoc()['cnt'] ?? 0) === 0) {
        $connect->query("INSERT INTO `cash_registers`
            (`branch_id`, `register_code`, `register_name`, `location`, `active`, `record_status`)
            VALUES
            (1, 'CAJA-01', 'Caja Principal', 'Mostrador Central', 1, 1)");
    }

    // 8. Seed default roles and permissions if empty
    $rolesCountRes = $connect->query("SELECT COUNT(*) AS cnt FROM `roles`");
    if ($rolesCountRes && (int)($rolesCountRes->fetch_assoc()['cnt'] ?? 0) === 0) {
        $rolesToSeed = [
            [
                'role_id' => 1,
                'name' => 'Superadministrador',
                'slug' => 'superadmin',
                'desc' => 'Control absoluto del sistema, todas las sucursales, finanzas y seguridad.',
                'system' => 1,
            ],
            [
                'role_id' => 2,
                'name' => 'Administrador',
                'slug' => 'admin',
                'desc' => 'Gestión operativa y administrativa completa de inventario, ventas, caja, préstamos y usuarios.',
                'system' => 1,
            ],
            [
                'role_id' => 3,
                'name' => 'Supervisor',
                'slug' => 'supervisor',
                'desc' => 'Supervisión de operaciones, reportes, arqueos y consulta de auditoría.',
                'system' => 0,
            ],
            [
                'role_id' => 4,
                'name' => 'Cajero',
                'slug' => 'cashier',
                'desc' => 'Operación de ventas en mostrador, turnos de caja y cobro de cuotas de préstamos.',
                'system' => 0,
            ],
            [
                'role_id' => 5,
                'name' => 'Vendedor',
                'slug' => 'seller',
                'desc' => 'Consulta de productos y emisión de órdenes de venta.',
                'system' => 0,
            ],
        ];

        foreach ($rolesToSeed as $r) {
            $stmt = $connect->prepare("INSERT INTO `roles` (`role_id`, `role_name`, `role_slug`, `description`, `is_system`) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('isssi', $r['role_id'], $r['name'], $r['slug'], $r['desc'], $r['system']);
            $stmt->execute();
            $stmt->close();
        }

        $allModules = [
            'dashboard', 'brands', 'categories', 'products', 'orders',
            'cash_register', 'loans', 'reports', 'general_cash', 'branches',
            'system_config', 'users', 'roles', 'audit_logs'
        ];

        // Permissions definitions for each role: [access, create, update, delete]
        $rolePermPresets = [
            1 => [ // superadmin: all 1
                'dashboard' => [1,1,1,1], 'brands' => [1,1,1,1], 'categories' => [1,1,1,1], 'products' => [1,1,1,1],
                'orders' => [1,1,1,1], 'cash_register' => [1,1,1,1], 'loans' => [1,1,1,1], 'reports' => [1,1,1,1],
                'general_cash' => [1,1,1,1], 'branches' => [1,1,1,1], 'system_config' => [1,1,1,1],
                'users' => [1,1,1,1], 'roles' => [1,1,1,1], 'audit_logs' => [1,1,1,1]
            ],
            2 => [ // admin
                'dashboard' => [1,0,0,0], 'brands' => [1,1,1,1], 'categories' => [1,1,1,1], 'products' => [1,1,1,1],
                'orders' => [1,1,1,1], 'cash_register' => [1,1,1,1], 'loans' => [1,1,1,1], 'reports' => [1,0,0,0],
                'general_cash' => [1,1,1,1], 'branches' => [1,1,1,0], 'system_config' => [1,0,1,0],
                'users' => [1,1,1,1], 'roles' => [1,1,1,0], 'audit_logs' => [1,0,0,0]
            ],
            3 => [ // supervisor
                'dashboard' => [1,0,0,0], 'brands' => [1,1,1,0], 'categories' => [1,1,1,0], 'products' => [1,1,1,0],
                'orders' => [1,1,1,1], 'cash_register' => [1,1,1,0], 'loans' => [1,1,1,0], 'reports' => [1,0,0,0],
                'general_cash' => [1,0,0,0], 'branches' => [1,0,0,0], 'system_config' => [0,0,0,0],
                'users' => [1,0,0,0], 'roles' => [1,0,0,0], 'audit_logs' => [1,0,0,0]
            ],
            4 => [ // cashier
                'dashboard' => [1,0,0,0], 'brands' => [1,0,0,0], 'categories' => [1,0,0,0], 'products' => [1,0,0,0],
                'orders' => [1,1,1,0], 'cash_register' => [1,1,1,0], 'loans' => [1,0,1,0], 'reports' => [0,0,0,0],
                'general_cash' => [0,0,0,0], 'branches' => [0,0,0,0], 'system_config' => [0,0,0,0],
                'users' => [0,0,0,0], 'roles' => [0,0,0,0], 'audit_logs' => [0,0,0,0]
            ],
            5 => [ // seller
                'dashboard' => [1,0,0,0], 'brands' => [1,0,0,0], 'categories' => [1,0,0,0], 'products' => [1,0,0,0],
                'orders' => [1,1,0,0], 'cash_register' => [0,0,0,0], 'loans' => [1,0,0,0], 'reports' => [0,0,0,0],
                'general_cash' => [0,0,0,0], 'branches' => [0,0,0,0], 'system_config' => [0,0,0,0],
                'users' => [0,0,0,0], 'roles' => [0,0,0,0], 'audit_logs' => [0,0,0,0]
            ],
        ];

        foreach ($rolePermPresets as $rId => $pMap) {
            foreach ($allModules as $mod) {
                $a = $pMap[$mod][0] ?? 0;
                $c = $pMap[$mod][1] ?? 0;
                $u = $pMap[$mod][2] ?? 0;
                $d = $pMap[$mod][3] ?? 0;
                $insP = $connect->prepare("INSERT INTO `role_permissions` (`role_id`, `module`, `can_access`, `can_create`, `can_update`, `can_delete`) VALUES (?, ?, ?, ?, ?, ?)");
                $insP->bind_param('isiiii', $rId, $mod, $a, $c, $u, $d);
                $insP->execute();
                $insP->close();
            }
        }
    }

    // 9. Attach admin user to role #1 and set full_name
    $connect->query("UPDATE `users` SET `role_id` = 1, `full_name` = 'Administrador Principal' WHERE `user_id` = 1 AND (`role_id` IS NULL OR `role_id` = 0 OR `full_name` = '' OR `full_name` IS NULL)");
}

function ensure_loan_schema(mysqli $connect): void
{
    ensure_app_schema($connect);
}
