<?php

function ensure_app_schema(mysqli $connect): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $loansTableSql = "CREATE TABLE IF NOT EXISTS `loans` (
        `loan_id` int(11) NOT NULL AUTO_INCREMENT,
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
        KEY `idx_loans_user` (`user_id`),
        KEY `idx_loans_status` (`loan_status`, `record_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $paymentsTableSql = "CREATE TABLE IF NOT EXISTS `loan_payments` (
        `payment_id` int(11) NOT NULL AUTO_INCREMENT,
        `loan_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `payment_date` date NOT NULL,
        `amount` decimal(12,2) NOT NULL,
        `payment_method` tinyint(1) NOT NULL DEFAULT 2,
        `notes` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`payment_id`),
        KEY `idx_loan_payments_loan` (`loan_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $loginAttemptsTableSql = "CREATE TABLE IF NOT EXISTS `login_attempts` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $connect->query($loansTableSql);
    $connect->query($paymentsTableSql);
    $connect->query($loginAttemptsTableSql);
}

function ensure_loan_schema(mysqli $connect): void
{
    ensure_app_schema($connect);
}
