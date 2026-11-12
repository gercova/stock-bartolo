<?php

function sb_column_exists(array $colsMap, string $table, string $column): bool
{
    return isset($colsMap[strtolower($table)][strtolower($column)]);
}

function sb_ensure_table_column(mysqli $connect, string $table, string $column, string $definition): void
{
    $chk = $connect->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    if ($chk) {
        $chk->bind_param('ss', $table, $column);
        $chk->execute();
        $cnt = (int)($chk->get_result()->fetch_assoc()['c'] ?? 0);
        $chk->close();
        if ($cnt === 0) {
            $connect->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
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
                'orders' => [1,1,1,1], 'cash_register' => [1,1,1,1], 'loans' => [1,1,1,1], 'clients' => [1,1,1,1], 'operations' => [1,1,1,1], 'reports' => [1,1,1,1],
                'general_cash' => [1,1,1,1], 'branches' => [1,1,1,1], 'system_config' => [1,1,1,1],
                'users' => [1,1,1,1], 'roles' => [1,1,1,1], 'audit_logs' => [1,1,1,1]
            ],
            2 => [ // admin
                'dashboard' => [1,0,0,0], 'brands' => [1,1,1,1], 'categories' => [1,1,1,1], 'products' => [1,1,1,1],
                'orders' => [1,1,1,1], 'cash_register' => [1,1,1,1], 'loans' => [1,1,1,1], 'clients' => [1,1,1,1], 'operations' => [1,1,1,1], 'reports' => [1,0,0,0],
                'general_cash' => [1,1,1,1], 'branches' => [1,1,1,0], 'system_config' => [1,0,1,0],
                'users' => [1,1,1,1], 'roles' => [1,1,1,0], 'audit_logs' => [1,0,0,0]
            ],
            3 => [ // supervisor
                'dashboard' => [1,0,0,0], 'brands' => [1,1,1,0], 'categories' => [1,1,1,0], 'products' => [1,1,1,0],
                'orders' => [1,1,1,1], 'cash_register' => [1,1,1,0], 'loans' => [1,1,1,0], 'clients' => [1,1,1,0], 'operations' => [1,1,1,0], 'reports' => [1,0,0,0],
                'general_cash' => [1,0,0,0], 'branches' => [1,0,0,0], 'system_config' => [0,0,0,0],
                'users' => [1,0,0,0], 'roles' => [1,0,0,0], 'audit_logs' => [1,0,0,0]
            ],
            4 => [ // cashier
                'dashboard' => [1,0,0,0], 'brands' => [1,0,0,0], 'categories' => [1,0,0,0], 'products' => [1,0,0,0],
                'orders' => [1,1,1,0], 'cash_register' => [1,1,1,0], 'loans' => [1,0,1,0], 'clients' => [1,1,1,0], 'operations' => [1,1,1,0], 'reports' => [0,0,0,0],
                'general_cash' => [0,0,0,0], 'branches' => [0,0,0,0], 'system_config' => [0,0,0,0],
                'users' => [0,0,0,0], 'roles' => [0,0,0,0], 'audit_logs' => [0,0,0,0]
            ],
            5 => [ // seller
                'dashboard' => [1,0,0,0], 'brands' => [1,0,0,0], 'categories' => [1,0,0,0], 'products' => [1,0,0,0],
                'orders' => [1,1,0,0], 'cash_register' => [0,0,0,0], 'loans' => [1,0,0,0], 'clients' => [1,1,0,0], 'operations' => [1,0,0,0], 'reports' => [0,0,0,0],
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

    // 10. Clients & Suppliers Table with Soft Deletes (record_status = 1 active, 2 deleted)
    $connect->query("CREATE TABLE IF NOT EXISTS `clients` (
      `client_id` int(11) NOT NULL AUTO_INCREMENT,
      `branch_id` int(11) DEFAULT NULL,
      `document_type` enum('DNI','RUC','CE','PASAPORTE','OTRO') NOT NULL DEFAULT 'DNI',
      `document_number` varchar(20) NOT NULL,
      `name` varchar(255) NOT NULL,
      `first_name` varchar(100) DEFAULT NULL,
      `last_name` varchar(100) DEFAULT NULL,
      `email` varchar(150) DEFAULT NULL,
      `phone` varchar(50) DEFAULT NULL,
      `address` varchar(255) DEFAULT NULL,
      `department` varchar(100) DEFAULT NULL,
      `province` varchar(100) DEFAULT NULL,
      `district` varchar(100) DEFAULT NULL,
      `ubigeo` varchar(10) DEFAULT NULL,
      `client_type` enum('client','supplier','both') NOT NULL DEFAULT 'client',
      `status` tinyint(1) NOT NULL DEFAULT 1,
      `record_status` tinyint(1) NOT NULL DEFAULT 1,
      `notes` text DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `deleted_at` datetime DEFAULT NULL,
      PRIMARY KEY (`client_id`),
      KEY `idx_clients_branch` (`branch_id`),
      KEY `idx_clients_document` (`document_type`, `document_number`),
      KEY `idx_clients_record_status` (`record_status`),
      KEY `idx_clients_client_type` (`client_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Sync 'clients' permissions for existing roles if not present
    $clientPermsSync = [
        1 => [1, 1, 1, 1], // superadmin
        2 => [1, 1, 1, 1], // admin
        3 => [1, 1, 1, 0], // supervisor
        4 => [1, 1, 1, 0], // cashier
        5 => [1, 1, 0, 0], // seller
    ];
    foreach ($clientPermsSync as $rId => $p) {
        $chkP = $connect->prepare("SELECT permission_id FROM `role_permissions` WHERE `role_id` = ? AND `module` = 'clients' LIMIT 1");
        if ($chkP) {
            $chkP->bind_param('i', $rId);
            $chkP->execute();
            if (!$chkP->get_result()->fetch_assoc()) {
                $chkP->close();
                $insP = $connect->prepare("INSERT INTO `role_permissions` (`role_id`, `module`, `can_access`, `can_create`, `can_update`, `can_delete`) VALUES (?, 'clients', ?, ?, ?, ?)");
                if ($insP) {
                    $insP->bind_param('iiiii', $rId, $p[0], $p[1], $p[2], $p[3]);
                    $insP->execute();
                    $insP->close();
                }
            } else {
                $chkP->close();
            }
        }
    }

    // 11. Loyalty Points Columns on clients table
    sb_ensure_table_column($connect, 'clients', 'points_balance', 'INT NOT NULL DEFAULT 0 AFTER `status`');
    sb_ensure_table_column($connect, 'clients', 'lifetime_points', 'INT NOT NULL DEFAULT 0 AFTER `points_balance`');

    // 12. Loyalty and Operations Settings in system_settings
    sb_ensure_table_column($connect, 'system_settings', 'points_ratio_soles', 'DECIMAL(10,2) NOT NULL DEFAULT 10.00 AFTER `allow_branch_switching`');
    sb_ensure_table_column($connect, 'system_settings', 'points_ratio_points', 'INT NOT NULL DEFAULT 1 AFTER `points_ratio_soles`');
    sb_ensure_table_column($connect, 'system_settings', 'print_double_ticket', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER `points_ratio_points`');
    sb_ensure_table_column($connect, 'system_settings', 'commission_rate_per_100', 'DECIMAL(10,2) NOT NULL DEFAULT 1.00 AFTER `print_double_ticket`');

    // 13. Branch Operations Table (Deposits & Withdrawals)
    $connect->query("CREATE TABLE IF NOT EXISTS `branch_operations` (
      `operation_id` int(11) NOT NULL AUTO_INCREMENT,
      `branch_id` int(11) NOT NULL,
      `operation_code` varchar(32) NOT NULL,
      `client_id` int(11) NOT NULL,
      `user_id` int(11) NOT NULL,
      `operation_type` enum('deposit','withdrawal') NOT NULL,
      `amount` decimal(12,2) NOT NULL,
      `commission_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
      `commission_included` tinyint(1) NOT NULL DEFAULT 0,
      `net_amount` decimal(12,2) NOT NULL,
      `total_customer_amount` decimal(12,2) NOT NULL,
      `points_earned` int(11) NOT NULL DEFAULT 0,
      `central_balance_before` decimal(12,2) NOT NULL DEFAULT 0.00,
      `central_balance_after` decimal(12,2) NOT NULL DEFAULT 0.00,
      `status` enum('completed','cancelled') NOT NULL DEFAULT 'completed',
      `notes` text DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`operation_id`),
      UNIQUE KEY `uniq_operation_code` (`operation_code`),
      KEY `idx_ops_branch` (`branch_id`, `created_at`),
      KEY `idx_ops_client` (`client_id`),
      KEY `idx_ops_type` (`operation_type`),
      KEY `idx_ops_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 14. Client Points History Table (Audit of all earned, redeemed and reset points)
    $connect->query("CREATE TABLE IF NOT EXISTS `client_points_history` (
      `history_id` int(11) NOT NULL AUTO_INCREMENT,
      `client_id` int(11) NOT NULL,
      `operation_id` int(11) DEFAULT NULL,
      `redemption_id` int(11) DEFAULT NULL,
      `type` enum('earned','redeemed','reset','adjusted') NOT NULL,
      `points` int(11) NOT NULL,
      `lifetime_after` int(11) NOT NULL DEFAULT 0,
      `balance_after` int(11) NOT NULL DEFAULT 0,
      `description` varchar(255) NOT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`history_id`),
      KEY `idx_pts_client` (`client_id`, `created_at`),
      KEY `idx_pts_operation` (`operation_id`),
      KEY `idx_pts_type` (`type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 15. Reward Items Table (Catalog of products/packages with points & price in soles)
    $connect->query("CREATE TABLE IF NOT EXISTS `reward_items` (
      `reward_id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(150) NOT NULL,
      `description` text DEFAULT NULL,
      `category` varchar(60) NOT NULL DEFAULT 'general',
      `points_cost` int(11) NOT NULL,
      `price_soles` decimal(10,2) NOT NULL DEFAULT 0.00,
      `stock` int(11) NOT NULL DEFAULT 0,
      `image` varchar(255) DEFAULT NULL,
      `status` tinyint(1) NOT NULL DEFAULT 1,
      `record_status` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`reward_id`),
      KEY `idx_rewards_status` (`record_status`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    sb_ensure_table_column($connect, 'reward_items', 'category', 'VARCHAR(60) NOT NULL DEFAULT "general"');

    // Seed default reward items if empty
    $chkRewards = $connect->query("SELECT COUNT(*) AS c FROM `reward_items`");
    $rewardCount = $chkRewards ? (int)($chkRewards->fetch_assoc()['c'] ?? 0) : 0;
    if ($rewardCount === 0) {
        $rewardsSeed = [
            ['Set Escolar Bartolo (Cuadernos + Lapiceros)', 'Kit de útiles escolares de primera calidad para clientes frecuentes.', 50, 25.00, 20],
            ['Canasta Familiar Básica', 'Paquete de abarrotes esenciales para el hogar.', 150, 75.00, 15],
            ['Mochila Ejecutiva Antirrobo', 'Mochila resistente al agua con compartimento para laptop de 15.6 pulgadas.', 250, 120.00, 10],
            ['Cupón de Descuento S/ 50.00', 'Vale de descuento aplicable en cualquier compra de productos en tienda.', 100, 50.00, 50],
            ['Licuadora Oster Multivelocidad', 'Electrodoméstico de alta potencia con vaso de vidrio resistente.', 500, 240.00, 5],
        ];
        $insR = $connect->prepare("INSERT INTO `reward_items` (`name`, `description`, `points_cost`, `price_soles`, `stock`, `status`, `record_status`) VALUES (?, ?, ?, ?, ?, 1, 1)");
        if ($insR) {
            foreach ($rewardsSeed as $r) {
                $insR->bind_param('ssidi', $r[0], $r[1], $r[2], $r[3], $r[4]);
                $insR->execute();
            }
            $insR->close();
        }
    }

    // 16. Reward Redemptions Table
    $connect->query("CREATE TABLE IF NOT EXISTS `reward_redemptions` (
      `redemption_id` int(11) NOT NULL AUTO_INCREMENT,
      `redemption_code` varchar(32) NOT NULL,
      `branch_id` int(11) NOT NULL,
      `client_id` int(11) NOT NULL,
      `user_id` int(11) NOT NULL,
      `reward_id` int(11) NOT NULL,
      `points_spent` int(11) NOT NULL,
      `quantity` int(11) NOT NULL DEFAULT 1,
      `points_balance_before` int(11) NOT NULL,
      `points_balance_after` int(11) NOT NULL DEFAULT 0,
      `notes` text DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`redemption_id`),
      UNIQUE KEY `uniq_redemption_code` (`redemption_code`),
      KEY `idx_redemptions_branch` (`branch_id`),
      KEY `idx_redemptions_client` (`client_id`),
      KEY `idx_redemptions_reward` (`reward_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Sync 'operations' permissions for existing roles if not present
    $opsPermsSync = [
        1 => [1, 1, 1, 1], // superadmin
        2 => [1, 1, 1, 1], // admin
        3 => [1, 1, 1, 0], // supervisor
        4 => [1, 1, 1, 0], // cashier
        5 => [1, 0, 0, 0], // seller
    ];
    foreach ($opsPermsSync as $rId => $p) {
        $chkP = $connect->prepare("SELECT permission_id FROM `role_permissions` WHERE `role_id` = ? AND `module` = 'operations' LIMIT 1");
        if ($chkP) {
            $chkP->bind_param('i', $rId);
            $chkP->execute();
            if (!$chkP->get_result()->fetch_assoc()) {
                $chkP->close();
                $insP = $connect->prepare("INSERT INTO `role_permissions` (`role_id`, `module`, `can_access`, `can_create`, `can_update`, `can_delete`) VALUES (?, 'operations', ?, ?, ?, ?)");
                if ($insP) {
                    $insP->bind_param('iiiii', $rId, $p[0], $p[1], $p[2], $p[3]);
                    $insP->execute();
                    $insP->close();
                }
            } else {
                $chkP->close();
            }
        }
    }
}

function ensure_loan_schema(mysqli $connect): void
{
    ensure_app_schema($connect);
}
