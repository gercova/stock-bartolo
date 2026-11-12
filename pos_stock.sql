-- Stock Bartolo — Esquema Completo Multi-Sucursal y Seguridad (20 Tablas)
-- Versión: 2.2.0 (SPA Vue 3 + Pinia + Multi-Tenant + Caja General + Caja + Seguridad y Auditoría)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `pos_stock` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `pos_stock`;

-- --------------------------------------------------------
-- 1. Sucursales (Tenants) y Configuración Global
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `branches` (
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `branches` (`branch_id`, `branch_code`, `branch_name`, `address`, `phone`, `email`, `manager_name`, `tax_rate`, `currency_code`, `currency_symbol`, `is_main`, `active`, `record_status`) VALUES
(1, 'SUC-001', 'Sucursal Principal', 'Casa Matriz', '', '', 'Administrador', 13.00, 'USD', '$', 1, 1, 1);

CREATE TABLE IF NOT EXISTS `system_settings` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `system_settings` (`setting_id`, `company_name`, `company_tax_id`, `company_phone`, `company_address`, `receipt_footer`, `default_tax_rate`, `default_currency_code`, `default_currency_symbol`, `enforce_open_till_for_cash`, `auto_fund_till_from_general_cash`, `auto_return_till_to_general_cash`, `allow_branch_switching`) VALUES
(1, 'Stock Bartolo', '', '', '', 'Gracias por su preferencia.', 13.00, 'USD', '$', 0, 1, 1, 1);

-- --------------------------------------------------------
-- 2. Caja General (Capital Operativo Independiente por Sucursal)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `general_cash_accounts` (
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `general_cash_accounts` (`account_id`, `branch_id`, `account_name`, `current_balance`, `initial_capital`, `min_balance_alert`, `status`, `notes`) VALUES
(1, 1, 'Caja General — Sucursal Principal', 0.00, 0.00, 100.00, 1, 'Capital operativo independiente de Sucursal Principal');

CREATE TABLE IF NOT EXISTS `general_cash_movements` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. Módulo Operativo de Caja (Apertura, Arqueo, Cierre Z)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `cash_registers` (
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `cash_registers` (`register_id`, `branch_id`, `register_code`, `register_name`, `location`, `active`, `record_status`) VALUES
(1, 1, 'CAJA-01', 'Caja Principal', 'Mostrador Central', 1, 1);

CREATE TABLE IF NOT EXISTS `cash_sessions` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cash_movements` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cash_reconciliations` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. Catálogo, Inventario y Órdenes (Aislados por branch_id)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `brands` (
  `brand_id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL DEFAULT 1,
  `brand_name` varchar(255) NOT NULL,
  `brand_active` int(11) NOT NULL DEFAULT 0,
  `brand_status` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`brand_id`),
  KEY `idx_brands_branch` (`branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

INSERT IGNORE INTO `brands` (`brand_id`, `branch_id`, `brand_name`, `brand_active`, `brand_status`) VALUES
(1, 1, 'Polo', 1, 1);

CREATE TABLE IF NOT EXISTS `categories` (
  `categories_id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL DEFAULT 1,
  `categories_name` varchar(255) NOT NULL,
  `categories_active` int(11) NOT NULL DEFAULT 0,
  `categories_status` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`categories_id`),
  KEY `idx_categories_branch` (`branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

INSERT IGNORE INTO `categories` (`categories_id`, `branch_id`, `categories_name`, `categories_active`, `categories_status`) VALUES
(1, 1, 'Camisas', 1, 1);

CREATE TABLE IF NOT EXISTS `product` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL DEFAULT 1,
  `product_name` varchar(255) NOT NULL,
  `product_image` text NOT NULL,
  `brand_id` int(11) NOT NULL,
  `categories_id` int(11) NOT NULL,
  `quantity` varchar(255) NOT NULL,
  `rate` varchar(255) NOT NULL,
  `active` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`product_id`),
  KEY `idx_product_branch` (`branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

INSERT IGNORE INTO `product` (`product_id`, `branch_id`, `product_name`, `product_image`, `brand_id`, `categories_id`, `quantity`, `rate`, `active`, `status`) VALUES
(1, 1, 'Camisa formal para hombre', '../assests/images/stock/246865885413162f4c.jpg', 1, 1, '35', '17.99', 1, 1);

CREATE TABLE IF NOT EXISTS `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL DEFAULT 1,
  `order_date` date NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `client_contact` varchar(255) NOT NULL,
  `sub_total` varchar(255) NOT NULL,
  `vat` varchar(255) NOT NULL,
  `total_amount` varchar(255) NOT NULL,
  `discount` varchar(255) NOT NULL,
  `grand_total` varchar(255) NOT NULL,
  `paid` varchar(255) NOT NULL,
  `due` varchar(255) NOT NULL,
  `payment_type` int(11) NOT NULL,
  `payment_status` int(11) NOT NULL,
  `order_status` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`order_id`),
  KEY `idx_orders_branch` (`branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

INSERT IGNORE INTO `orders` (`order_id`, `branch_id`, `order_date`, `client_name`, `client_contact`, `sub_total`, `vat`, `total_amount`, `discount`, `grand_total`, `paid`, `due`, `payment_type`, `payment_status`, `order_status`) VALUES
(1, 1, '2017-01-22', 'Juan Campos', '+(503) 7025-25200', '17.99', '2.34', '20.33', '0', '20.33', '20.33', '0.00', 2, 1, 1);

CREATE TABLE IF NOT EXISTS `order_item` (
  `order_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL DEFAULT 0,
  `product_id` int(11) NOT NULL DEFAULT 0,
  `quantity` varchar(255) NOT NULL,
  `rate` varchar(255) NOT NULL,
  `total` varchar(255) NOT NULL,
  `order_item_status` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`order_item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

INSERT IGNORE INTO `order_item` (`order_item_id`, `order_id`, `product_id`, `quantity`, `rate`, `total`, `order_item_status`) VALUES
(1, 1, 1, '1', '17.99', '17.99', 1);

-- --------------------------------------------------------
-- 5. Préstamos y Seguridad (Aislados por branch_id)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `loans` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `loan_payments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. Seguridad: Usuarios, Roles, Permisos y Auditoría
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL,
  `role_slug` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT '',
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uniq_role_slug` (`role_slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `roles` (`role_id`, `role_name`, `role_slug`, `description`, `is_system`) VALUES
(1, 'Superadministrador', 'superadmin', 'Control absoluto del sistema, todas las sucursales, finanzas y seguridad.', 1),
(2, 'Administrador', 'admin', 'Gestión operativa y administrativa completa de inventario, ventas, caja, préstamos y usuarios.', 1),
(3, 'Supervisor', 'supervisor', 'Supervisión de operaciones, reportes, arqueos y consulta de auditoría.', 0),
(4, 'Cajero', 'cashier', 'Operación de ventas en mostrador, turnos de caja y cobro de cuotas de préstamos.', 0),
(5, 'Vendedor', 'seller', 'Consulta de productos y emisión de órdenes de venta.', 0);

CREATE TABLE IF NOT EXISTS `role_permissions` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `role_permissions` (`role_id`, `module`, `can_access`, `can_create`, `can_update`, `can_delete`) VALUES
(1, 'dashboard', 1, 1, 1, 1), (1, 'brands', 1, 1, 1, 1), (1, 'categories', 1, 1, 1, 1), (1, 'products', 1, 1, 1, 1),
(1, 'orders', 1, 1, 1, 1), (1, 'cash_register', 1, 1, 1, 1), (1, 'loans', 1, 1, 1, 1), (1, 'reports', 1, 1, 1, 1),
(1, 'general_cash', 1, 1, 1, 1), (1, 'branches', 1, 1, 1, 1), (1, 'system_config', 1, 1, 1, 1),
(1, 'users', 1, 1, 1, 1), (1, 'roles', 1, 1, 1, 1), (1, 'audit_logs', 1, 1, 1, 1),
(2, 'dashboard', 1, 0, 0, 0), (2, 'brands', 1, 1, 1, 1), (2, 'categories', 1, 1, 1, 1), (2, 'products', 1, 1, 1, 1),
(2, 'orders', 1, 1, 1, 1), (2, 'cash_register', 1, 1, 1, 1), (2, 'loans', 1, 1, 1, 1), (2, 'reports', 1, 0, 0, 0),
(2, 'general_cash', 1, 1, 1, 1), (2, 'branches', 1, 1, 1, 0), (2, 'system_config', 1, 0, 1, 0),
(2, 'users', 1, 1, 1, 1), (2, 'roles', 1, 1, 1, 0), (2, 'audit_logs', 1, 0, 0, 0),
(3, 'dashboard', 1, 0, 0, 0), (3, 'brands', 1, 1, 1, 0), (3, 'categories', 1, 1, 1, 0), (3, 'products', 1, 1, 1, 0),
(3, 'orders', 1, 1, 1, 1), (3, 'cash_register', 1, 1, 1, 0), (3, 'loans', 1, 1, 1, 0), (3, 'reports', 1, 0, 0, 0),
(3, 'general_cash', 1, 0, 0, 0), (3, 'branches', 1, 0, 0, 0), (3, 'system_config', 0, 0, 0, 0),
(3, 'users', 1, 0, 0, 0), (3, 'roles', 1, 0, 0, 0), (3, 'audit_logs', 1, 0, 0, 0),
(4, 'dashboard', 1, 0, 0, 0), (4, 'brands', 1, 0, 0, 0), (4, 'categories', 1, 0, 0, 0), (4, 'products', 1, 0, 0, 0),
(4, 'orders', 1, 1, 1, 0), (4, 'cash_register', 1, 1, 1, 0), (4, 'loans', 1, 0, 1, 0), (4, 'reports', 0, 0, 0, 0),
(4, 'general_cash', 0, 0, 0, 0), (4, 'branches', 0, 0, 0, 0), (4, 'system_config', 0, 0, 0, 0),
(4, 'users', 0, 0, 0, 0), (4, 'roles', 0, 0, 0, 0), (4, 'audit_logs', 0, 0, 0, 0),
(5, 'dashboard', 1, 0, 0, 0), (5, 'brands', 1, 0, 0, 0), (5, 'categories', 1, 0, 0, 0), (5, 'products', 1, 0, 0, 0),
(5, 'orders', 1, 1, 0, 0), (5, 'cash_register', 0, 0, 0, 0), (5, 'loans', 1, 0, 0, 0), (5, 'reports', 0, 0, 0, 0),
(5, 'general_cash', 0, 0, 0, 0), (5, 'branches', 0, 0, 0, 0), (5, 'system_config', 0, 0, 0, 0),
(5, 'users', 0, 0, 0, 0), (5, 'roles', 0, 0, 0, 0), (5, 'audit_logs', 0, 0, 0, 0);

CREATE TABLE IF NOT EXISTS `audit_logs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `full_name` varchar(150) DEFAULT '',
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(60) DEFAULT '',
  `role_id` int(11) NOT NULL DEFAULT 1,
  `branch_id` int(11) NOT NULL DEFAULT 1,
  `role` varchar(30) NOT NULL DEFAULT 'superadmin',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `idx_users_role_id` (`role_id`),
  KEY `idx_users_branch` (`branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `users` (`user_id`, `username`, `full_name`, `password`, `email`, `phone`, `role_id`, `branch_id`, `role`, `active`) VALUES
(1, 'admin', 'Administrador Principal', '21232f297a57a5a743894a0e4a801fc3', '', '', 1, 1, 'superadmin', 1);

CREATE TABLE IF NOT EXISTS `login_attempts` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
