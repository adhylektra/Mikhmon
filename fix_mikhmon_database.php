<?php
/**
 * Comprehensive DB upgrader based on database/mikhmon_fix.sql
 * -----------------------------------------------------------
 * Jalankan file ini (sekali) untuk memastikan instalasi lama mendapatkan
 * semua tabel, kolom, index, default data, trigger, dan view terbaru.
 *
 * Keamanan: akses dengan ?key=fix-mikhmon-2025 atau argumen CLI.
 */

$securityKey = $_GET['key'] ?? ($_SERVER['argv'][1] ?? '');
if ($securityKey !== 'fix-mikhmon-2025') {
    exit("Access denied. Gunakan ?key=fix-mikhmon-2025 atau argumen CLI.\n");
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(600);

require_once __DIR__ . '/include/db_config.php';
require_once __DIR__ . '/lib/routeros_api.class.php'; // ensure available for decrypt helper

try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    exit("Gagal konek database: " . $e->getMessage() . "\n");
}

date_default_timezone_set('Asia/Jakarta');

/** @var PDO $pdo */
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function logMessage(string $message, string $status = 'info'): void
{
    $prefix = [
        'info' => '[*] ',
        'ok' => '[OK] ',
        'warn' => '[!!] ',
        'error' => '[XX] ',
    ][$status] ?? '[*] ';

    echo $prefix . $message . (PHP_SAPI === 'cli' ? "\n" : '<br>');
    flush();
}

function tableExists(PDO $pdo, string $table): bool
{
    $pattern = $pdo->quote($table);
    return (bool)$pdo->query("SHOW TABLES LIKE {$pattern}")->fetchColumn();
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $pattern = $pdo->quote($column);
    $safe = str_replace('`', '``', $table);
    return (bool)$pdo->query("SHOW COLUMNS FROM `{$safe}` LIKE {$pattern}")->fetchColumn();
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $pattern = $pdo->quote($index);
    $safe = str_replace('`', '``', $table);
    return (bool)$pdo->query("SHOW INDEX FROM `{$safe}` WHERE Key_name = {$pattern}")->fetchColumn();
}

function triggerExists(PDO $pdo, string $trigger): bool
{
    $pattern = $pdo->quote($trigger);
    return (bool)$pdo->query("SHOW TRIGGERS WHERE `Trigger` = {$pattern}")->fetchColumn();
}

function viewExists(PDO $pdo, string $view): bool
{
    $pattern = $pdo->quote($view);
    return (bool)$pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_" . $pdo->query('SELECT DATABASE()')->fetchColumn() . " LIKE {$pattern}")->fetchColumn();
}

function ensureTable(PDO $pdo, string $name, string $ddl): void
{
    if (!tableExists($pdo, $name)) {
        logMessage("Membuat tabel {$name} ...", 'info');
        $pdo->exec($ddl);
        logMessage("Tabel {$name} dibuat", 'ok');
    } else {
        logMessage("Tabel {$name} sudah tersedia", 'info');
    }
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition, ?string $after = null): void
{
    if (!columnExists($pdo, $table, $column)) {
        $tableSafe = str_replace('`', '``', $table);
        $afterSql = $after ? " AFTER `" . str_replace('`', '``', $after) . "`" : '';
        $sql = "ALTER TABLE `{$tableSafe}` ADD COLUMN `{$column}` {$definition}{$afterSql}";
        logMessage("Menambahkan kolom {$table}.{$column} ...", 'warn');
        $pdo->exec($sql);
        logMessage("Kolom {$table}.{$column} ditambahkan", 'ok');
    }
}

function ensureIndex(PDO $pdo, string $table, string $index, string $definition): void
{
    if (!indexExists($pdo, $table, $index)) {
        $safe = str_replace('`', '``', $table);
        logMessage("Menambahkan index {$index} pada {$table} ...", 'warn');
        $pdo->exec("ALTER TABLE `{$safe}` ADD {$definition}");
        logMessage("Index {$index} ditambahkan", 'ok');
    }
}

function ensureTrigger(PDO $pdo, string $name, string $ddl): void
{
    if (!triggerExists($pdo, $name)) {
        logMessage("Membuat trigger {$name} ...", 'info');
        $pdo->exec($ddl);
        logMessage("Trigger {$name} dibuat", 'ok');
    }
}

function ensureView(PDO $pdo, string $name, string $ddl): void
{
    if (viewExists($pdo, $name)) {
        logMessage("View {$name} di-drop ulang", 'warn');
        $pdo->exec("DROP VIEW IF EXISTS `{$name}`");
    }

    logMessage("Membuat view {$name} ...", 'info');
    $pdo->exec($ddl);
    logMessage("View {$name} dibuat", 'ok');
}

function upsertSetting(PDO $pdo, string $table, string $key, $value): void
{
    $stmt = $pdo->prepare("INSERT INTO {$table} (setting_key, setting_value) VALUES (:key, :value)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([':key' => $key, ':value' => $value]);
}

logMessage('=== Memulai proses sinkronisasi struktur database Mikhmon Billing ===');

$tables = [
    'agents' => <<<SQL
CREATE TABLE `agents` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `agent_code` VARCHAR(20) NOT NULL,
  `agent_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `password` VARCHAR(255) DEFAULT NULL,
  `balance` DECIMAL(15,2) DEFAULT 0.00,
  `status` ENUM('active','inactive','suspended') DEFAULT 'active',
  `level` ENUM('bronze','silver','gold','platinum') DEFAULT 'bronze',
  `commission_percent` DECIMAL(5,2) DEFAULT 0.00,
  `created_by` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  UNIQUE KEY `unique_agent_code` (`agent_code`),
  UNIQUE KEY `unique_phone` (`phone`),
  KEY `idx_agent_code` (`agent_code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'agent_commissions' => <<<SQL
CREATE TABLE `agent_commissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT UNSIGNED NOT NULL,
  `voucher_id` INT UNSIGNED DEFAULT NULL,
  `commission_amount` DECIMAL(15,2) NOT NULL,
  `commission_percent` DECIMAL(5,2) NOT NULL,
  `voucher_price` DECIMAL(15,2) NOT NULL,
  `status` ENUM('pending','paid','cancelled') DEFAULT 'pending',
  `earned_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` TIMESTAMP NULL DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  KEY `idx_agent_id` (`agent_id`),
  KEY `idx_status` (`status`),
  KEY `idx_voucher_id` (`voucher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'agent_prices' => <<<SQL
CREATE TABLE `agent_prices` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT UNSIGNED NOT NULL,
  `profile_name` VARCHAR(100) NOT NULL,
  `buy_price` DECIMAL(15,2) NOT NULL,
  `sell_price` DECIMAL(15,2) NOT NULL,
  `stock_limit` INT DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_agent_profile` (`agent_id`,`profile_name`),
  KEY `idx_agent_id` (`agent_id`),
  KEY `idx_profile` (`profile_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'agent_profile_pricing' => <<<SQL
CREATE TABLE `agent_profile_pricing` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT UNSIGNED NOT NULL,
  `profile_name` VARCHAR(100) NOT NULL,
  `display_name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `original_price` DECIMAL(10,2) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `is_featured` TINYINT(1) DEFAULT 0,
  `icon` VARCHAR(50) DEFAULT 'fa-wifi',
  `color` VARCHAR(20) DEFAULT 'blue',
  `sort_order` INT DEFAULT 0,
  `user_type` ENUM('voucher','member') DEFAULT 'voucher',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_agent_profile` (`agent_id`,`profile_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'agent_settings' => <<<SQL
CREATE TABLE `agent_settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `setting_type` VARCHAR(20) DEFAULT 'string',
  `description` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` VARCHAR(50) DEFAULT NULL,
  UNIQUE KEY `unique_setting_key` (`setting_key`),
  KEY `idx_agent_id` (`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'agent_topup_requests' => <<<SQL
CREATE TABLE `agent_topup_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `payment_proof` VARCHAR(255) DEFAULT NULL,
  `bank_name` VARCHAR(50) DEFAULT NULL,
  `account_number` VARCHAR(50) DEFAULT NULL,
  `account_name` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `requested_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` TIMESTAMP NULL DEFAULT NULL,
  `processed_by` VARCHAR(50) DEFAULT NULL,
  `admin_notes` TEXT DEFAULT NULL,
  `agent_notes` TEXT DEFAULT NULL,
  KEY `idx_agent_id` (`agent_id`),
  KEY `idx_status` (`status`),
  KEY `idx_requested_at` (`requested_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'agent_transactions' => <<<SQL
CREATE TABLE `agent_transactions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT UNSIGNED NOT NULL,
  `transaction_type` ENUM('topup','generate','refund','commission','penalty','digiflazz') NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `balance_before` DECIMAL(15,2) NOT NULL,
  `balance_after` DECIMAL(15,2) NOT NULL,
  `profile_name` VARCHAR(100) DEFAULT NULL,
  `voucher_username` VARCHAR(100) DEFAULT NULL,
  `voucher_password` VARCHAR(100) DEFAULT NULL,
  `quantity` INT DEFAULT 1,
  `description` TEXT DEFAULT NULL,
  `reference_id` VARCHAR(50) DEFAULT NULL,
  `created_by` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  KEY `idx_agent_id` (`agent_id`),
  KEY `idx_type` (`transaction_type`),
  KEY `idx_date` (`created_at`),
  KEY `idx_reference` (`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'agent_vouchers' => <<<SQL
CREATE TABLE `agent_vouchers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT UNSIGNED NOT NULL,
  `transaction_id` INT UNSIGNED DEFAULT NULL,
  `username` VARCHAR(100) NOT NULL,
  `password` VARCHAR(100) NOT NULL,
  `profile_name` VARCHAR(100) NOT NULL,
  `buy_price` DECIMAL(15,2) NOT NULL,
  `sell_price` DECIMAL(15,2) DEFAULT NULL,
  `status` ENUM('active','used','expired','deleted') DEFAULT 'active',
  `customer_phone` VARCHAR(20) DEFAULT NULL,
  `customer_name` VARCHAR(100) DEFAULT NULL,
  `sent_via` ENUM('web','whatsapp','manual') DEFAULT 'web',
  `sent_at` TIMESTAMP NULL DEFAULT NULL,
  `used_at` TIMESTAMP NULL DEFAULT NULL,
  `expired_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT DEFAULT NULL,
  KEY `idx_agent_id` (`agent_id`),
  KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_username` (`username`),
  KEY `idx_status` (`status`),
  KEY `idx_customer_phone` (`customer_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'billing_profiles' => <<<SQL
CREATE TABLE `billing_profiles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `profile_name` VARCHAR(100) NOT NULL,
  `price_monthly` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `speed_label` VARCHAR(100) DEFAULT NULL,
  `mikrotik_profile_normal` VARCHAR(100) NOT NULL,
  `mikrotik_profile_isolation` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_profile_name` (`profile_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'billing_customers' => <<<SQL
CREATE TABLE `billing_customers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `profile_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(32) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `service_number` VARCHAR(100) DEFAULT NULL,
  `billing_day` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `is_isolated` TINYINT(1) NOT NULL DEFAULT 0,
  `next_isolation_date` DATE DEFAULT NULL,
  `genieacs_match_mode` ENUM('device_id','phone_tag','pppoe_username') NOT NULL DEFAULT 'device_id',
  `genieacs_pppoe_username` VARCHAR(191) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_profile_id` (`profile_id`),
  KEY `idx_billing_day` (`billing_day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'billing_invoices' => <<<SQL
CREATE TABLE `billing_invoices` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT UNSIGNED NOT NULL,
  `profile_snapshot` JSON DEFAULT NULL,
  `period` CHAR(7) NOT NULL,
  `due_date` DATE NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('draft','unpaid','paid','overdue','cancelled') NOT NULL DEFAULT 'unpaid',
  `paid_at` DATETIME DEFAULT NULL,
  `payment_channel` VARCHAR(100) DEFAULT NULL,
  `reference_number` VARCHAR(100) DEFAULT NULL,
  `paid_via` VARCHAR(50) DEFAULT NULL,
  `paid_via_agent_id` INT UNSIGNED DEFAULT NULL,
  `whatsapp_sent_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_customer_period` (`customer_id`,`period`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'billing_payments' => <<<SQL
CREATE TABLE `billing_payments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `method` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_invoice_id` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'billing_logs' => <<<SQL
CREATE TABLE `billing_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` BIGINT UNSIGNED DEFAULT NULL,
  `customer_id` INT UNSIGNED DEFAULT NULL,
  `event` VARCHAR(100) NOT NULL,
  `metadata` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `idx_customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'billing_portal_otps' => <<<SQL
CREATE TABLE `billing_portal_otps` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT UNSIGNED NOT NULL,
  `identifier` VARCHAR(191) NOT NULL,
  `otp_code` VARCHAR(191) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `sent_via` ENUM('whatsapp','sms','email') DEFAULT 'whatsapp',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_customer_identifier` (`customer_id`,`identifier`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'billing_settings' => <<<SQL
CREATE TABLE `billing_settings` (
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'digiflazz_products' => <<<SQL
CREATE TABLE `digiflazz_products` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `buyer_sku_code` VARCHAR(50) NOT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `brand` VARCHAR(100) DEFAULT NULL,
  `category` VARCHAR(50) DEFAULT NULL,
  `type` ENUM('prepaid','postpaid') DEFAULT 'prepaid',
  `price` INT DEFAULT 0,
  `buyer_price` INT DEFAULT NULL,
  `seller_price` INT DEFAULT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `desc_header` VARCHAR(150) DEFAULT NULL,
  `desc_footer` TEXT DEFAULT NULL,
  `icon_url` VARCHAR(255) DEFAULT NULL,
  `allow_markup` TINYINT(1) DEFAULT 1,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_sku` (`buyer_sku_code`),
  KEY `idx_category` (`category`),
  KEY `idx_brand` (`brand`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'digiflazz_transactions' => <<<SQL
CREATE TABLE `digiflazz_transactions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT UNSIGNED DEFAULT NULL,
  `ref_id` VARCHAR(60) NOT NULL,
  `buyer_sku_code` VARCHAR(50) NOT NULL,
  `customer_no` VARCHAR(50) NOT NULL,
  `customer_name` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending','success','failed','refund') DEFAULT 'pending',
  `message` VARCHAR(255) DEFAULT NULL,
  `price` INT DEFAULT 0,
  `sell_price` INT DEFAULT 0,
  `serial_number` VARCHAR(100) DEFAULT NULL,
  `response` TEXT DEFAULT NULL,
  `whatsapp_notified` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_ref` (`ref_id`),
  KEY `idx_agent` (`agent_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'payment_gateway_config' => <<<SQL
CREATE TABLE `payment_gateway_config` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `gateway_name` VARCHAR(50) NOT NULL,
  `is_active` TINYINT(1) DEFAULT 0,
  `is_sandbox` TINYINT(1) DEFAULT 1,
  `api_key` VARCHAR(255) DEFAULT NULL,
  `api_secret` VARCHAR(255) DEFAULT NULL,
  `merchant_code` VARCHAR(100) DEFAULT NULL,
  `callback_token` VARCHAR(255) DEFAULT NULL,
  `config_json` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_gateway` (`gateway_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'payment_methods' => <<<SQL
CREATE TABLE `payment_methods` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `gateway_name` VARCHAR(50) NOT NULL,
  `method_code` VARCHAR(50) NOT NULL,
  `method_name` VARCHAR(100) NOT NULL,
  `method_type` VARCHAR(20) NOT NULL,
  `name` VARCHAR(100) NOT NULL DEFAULT '',
  `type` VARCHAR(50) NOT NULL DEFAULT '',
  `display_name` VARCHAR(100) NOT NULL DEFAULT '',
  `icon` VARCHAR(100) DEFAULT NULL,
  `icon_url` VARCHAR(255) DEFAULT NULL,
  `admin_fee_type` ENUM('percentage','fixed','flat','percent') DEFAULT 'fixed',
  `admin_fee_value` DECIMAL(10,2) DEFAULT 0,
  `min_amount` DECIMAL(10,2) DEFAULT 0,
  `max_amount` DECIMAL(12,2) DEFAULT 999999999.99,
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `config` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_gateway_method` (`gateway_name`,`method_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'public_sales' => <<<SQL
CREATE TABLE `public_sales` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `transaction_id` VARCHAR(100) NOT NULL,
  `payment_reference` VARCHAR(100) DEFAULT NULL,
  `agent_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `profile_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `customer_name` VARCHAR(100) NOT NULL DEFAULT '',
  `customer_phone` VARCHAR(20) NOT NULL DEFAULT '',
  `customer_email` VARCHAR(100) DEFAULT NULL,
  `profile_name` VARCHAR(100) NOT NULL DEFAULT '',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `admin_fee` DECIMAL(10,2) DEFAULT 0,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `gateway_name` VARCHAR(50) NOT NULL DEFAULT '',
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `payment_channel` VARCHAR(50) DEFAULT NULL,
  `payment_url` TEXT DEFAULT NULL,
  `qr_url` TEXT DEFAULT NULL,
  `virtual_account` VARCHAR(50) DEFAULT NULL,
  `payment_instructions` TEXT DEFAULT NULL,
  `expired_at` DATETIME DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `status` VARCHAR(20) DEFAULT 'pending',
  `voucher_code` VARCHAR(50) DEFAULT NULL,
  `voucher_password` VARCHAR(50) DEFAULT NULL,
  `voucher_generated_at` DATETIME DEFAULT NULL,
  `voucher_sent_at` DATETIME DEFAULT NULL,
  `ip_address` VARCHAR(50) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `callback_data` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_transaction` (`transaction_id`),
  KEY `idx_payment_reference` (`payment_reference`),
  KEY `idx_status` (`status`),
  KEY `idx_customer_phone` (`customer_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'site_pages' => <<<SQL
CREATE TABLE `site_pages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `page_slug` VARCHAR(50) NOT NULL,
  `page_title` VARCHAR(200) NOT NULL,
  `page_content` TEXT NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_page_slug` (`page_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'voucher_settings' => <<<SQL
CREATE TABLE `voucher_settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
];

foreach ($tables as $table => $ddl) {
    ensureTable($pdo, $table, $ddl);
}

logMessage('--- Memastikan relasi foreign key ---');

$foreignKeys = [
    'agent_commissions' => [
        "ADD CONSTRAINT `fk_agent_commissions_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE",
        "ADD CONSTRAINT `fk_agent_commissions_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `agent_vouchers`(`id`) ON DELETE SET NULL",
    ],
    'agent_prices' => [
        "ADD CONSTRAINT `fk_agent_prices_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE",
    ],
    'agent_profile_pricing' => [
        "ADD CONSTRAINT `fk_agent_profile_pricing_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE",
    ],
    'agent_settings' => [
        "ADD CONSTRAINT `fk_agent_settings_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE",
    ],
    'agent_topup_requests' => [
        "ADD CONSTRAINT `fk_agent_topup_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE",
    ],
    'agent_transactions' => [
        "ADD CONSTRAINT `fk_agent_transactions_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE",
    ],
    'agent_vouchers' => [
        "ADD CONSTRAINT `fk_agent_vouchers_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE",
        "ADD CONSTRAINT `fk_agent_vouchers_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `agent_transactions`(`id`) ON DELETE SET NULL",
    ],
    'billing_customers' => [
        "ADD CONSTRAINT `fk_billing_customers_profile` FOREIGN KEY (`profile_id`) REFERENCES `billing_profiles`(`id`) ON UPDATE CASCADE",
    ],
    'billing_invoices' => [
        "ADD CONSTRAINT `fk_billing_invoices_customer` FOREIGN KEY (`customer_id`) REFERENCES `billing_customers`(`id`) ON DELETE CASCADE ON UPDATE CASCADE",
    ],
    'billing_logs' => [
        "ADD CONSTRAINT `fk_billing_logs_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `billing_invoices`(`id`) ON DELETE SET NULL ON UPDATE CASCADE",
        "ADD CONSTRAINT `fk_billing_logs_customer` FOREIGN KEY (`customer_id`) REFERENCES `billing_customers`(`id`) ON DELETE SET NULL ON UPDATE CASCADE",
    ],
    'billing_payments' => [
        "ADD CONSTRAINT `fk_billing_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `billing_invoices`(`id`) ON DELETE CASCADE ON UPDATE CASCADE",
    ],
    'digiflazz_transactions' => [
        "ADD CONSTRAINT `fk_digiflazz_transactions_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE SET NULL",
    ],
    'public_sales' => [
        "ADD CONSTRAINT `fk_public_sales_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE",
        "ADD CONSTRAINT `fk_public_sales_profile` FOREIGN KEY (`profile_id`) REFERENCES `agent_profile_pricing`(`id`) ON DELETE CASCADE",
    ],
];

foreach ($foreignKeys as $table => $constraints) {
    foreach ($constraints as $constraint) {
        try {
            $pdo->exec("ALTER TABLE `{$table}` {$constraint}");
            logMessage("Menambahkan FK pada {$table}: {$constraint}", 'ok');
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) {
                logMessage("FK {$table} gagal: " . $e->getMessage(), 'error');
            }
        }
    }
}

logMessage('--- Memastikan trigger dan view ---');

$triggerSql = <<<SQL
CREATE TRIGGER `after_agent_voucher_insert` AFTER INSERT ON `agent_vouchers`
FOR EACH ROW
BEGIN
    DECLARE v_commission_enabled TINYINT(1);
    DECLARE v_commission_percent DECIMAL(5,2);
    DECLARE v_commission_amount DECIMAL(15,2);

    SELECT CAST(setting_value AS UNSIGNED)
      INTO v_commission_enabled
      FROM agent_settings
     WHERE setting_key = 'commission_enabled'
     LIMIT 1;

    IF v_commission_enabled = 1 THEN
        SELECT commission_percent
          INTO v_commission_percent
          FROM agents
         WHERE id = NEW.agent_id
         LIMIT 1;

        IF v_commission_percent > 0 AND NEW.sell_price IS NOT NULL THEN
            SET v_commission_amount = NEW.sell_price * v_commission_percent / 100;

            INSERT INTO agent_commissions (
                agent_id, voucher_id, commission_amount,
                commission_percent, voucher_price
            ) VALUES (
                NEW.agent_id, NEW.id, v_commission_amount,
                v_commission_percent, NEW.sell_price
            );
        END IF;
    END IF;
END
SQL;
ensureTrigger($pdo, 'after_agent_voucher_insert', $triggerSql);

$agentSummaryView = <<<SQL
CREATE VIEW `agent_summary` AS
SELECT
    a.id,
    a.agent_code,
    a.agent_name,
    a.phone,
    a.balance,
    a.status,
    a.level,
    COUNT(DISTINCT av.id) AS total_vouchers,
    COUNT(DISTINCT CASE WHEN av.status = 'used' THEN av.id END) AS used_vouchers,
    SUM(CASE WHEN at.transaction_type = 'topup' THEN at.amount ELSE 0 END) AS total_topup,
    SUM(CASE WHEN at.transaction_type = 'generate' THEN at.amount ELSE 0 END) AS total_spent,
    COALESCE(SUM(ac.commission_amount), 0) AS total_commission,
    a.created_at,
    a.last_login
FROM agents a
LEFT JOIN agent_vouchers av ON a.id = av.agent_id
LEFT JOIN agent_transactions at ON a.id = at.agent_id
LEFT JOIN agent_commissions ac ON a.id = ac.agent_id AND ac.status = 'paid'
GROUP BY a.id;
SQL;
ensureView($pdo, 'agent_summary', $agentSummaryView);

$dailySalesView = <<<SQL
CREATE VIEW `daily_agent_sales` AS
SELECT
    DATE(av.created_at) AS sale_date,
    a.agent_code,
    a.agent_name,
    av.profile_name,
    COUNT(*) AS voucher_count,
    SUM(av.buy_price) AS total_buy_price,
    SUM(av.sell_price) AS total_sell_price,
    SUM(av.sell_price - av.buy_price) AS total_profit
FROM agent_vouchers av
JOIN agents a ON av.agent_id = a.id
WHERE av.status <> 'deleted'
GROUP BY DATE(av.created_at), a.id, av.profile_name;
SQL;
ensureView($pdo, 'daily_agent_sales', $dailySalesView);

logMessage('--- Menyisipkan data default / baseline ---');

$agentsSeed = [
    ['AG001', 'Alijaya Net', '081947215703', 'agent@demo.com'],
    ['AG5136', 'tester', 'seed-ag5136', null],
    ['PUBLIC', 'Public Catalog', 'seed-public', null],
];

$insertAgent = $pdo->prepare("INSERT INTO agents (agent_code, agent_name, phone, email, password, balance, status)
    VALUES (:code, :name, :phone, :email, '', 0, 'active')
    ON DUPLICATE KEY UPDATE agent_name = VALUES(agent_name), phone = VALUES(phone), email = VALUES(email)");
foreach ($agentsSeed as $row) {
    [$code, $name, $phone, $email] = $row;
    $insertAgent->execute([
        ':code' => $code,
        ':name' => $name,
        ':phone' => $phone,
        ':email' => $email,
    ]);
}
logMessage('Data agent default disinkronisasi', 'ok');

$agentIdLookup = $pdo->query("SELECT agent_code, id FROM agents")->fetchAll(PDO::FETCH_KEY_PAIR);
$demoId = $agentIdLookup['AG001'] ?? null;
$testerId = $agentIdLookup['AG5136'] ?? null;
$publicId = $agentIdLookup['PUBLIC'] ?? null;

if ($demoId) {
    $defaultSettings = [
        ['admin_whatsapp_numbers', '6281234567890'],
        ['agent_can_set_sell_price', '1'],
        ['agent_registration_enabled', '1'],
        ['min_topup_amount', '50000'],
        ['max_topup_amount', '10000000'],
        ['commission_enabled', '1'],
        ['default_commission_percent', '5'],
        ['whatsapp_notification_enabled', '1'],
        ['voucher_prefix_agent', 'AG'],
        ['whatsapp_gateway_url', 'https://api.whatsapp.com'],
        ['whatsapp_token', ''],
        ['digiflazz_enabled', '0'],
    ];

    $stmt = $pdo->prepare("INSERT INTO agent_settings (agent_id, setting_key, setting_value) VALUES (:agent, :key, :value)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($defaultSettings as [$key, $value]) {
        $stmt->execute([
            ':agent' => $demoId,
            ':key' => $key,
            ':value' => $value,
        ]);
    }
    logMessage('Agent settings baseline diperbarui', 'ok');
}

if ($demoId || $testerId || $publicId) {
    $priceStmt = $pdo->prepare("INSERT INTO agent_prices (agent_id, profile_name, buy_price, sell_price)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE buy_price = VALUES(buy_price), sell_price = VALUES(sell_price)");
    $priceSeeds = array_filter([
        $demoId ? [$demoId, '3k', 2000, 3000] : null,
        $demoId ? [$demoId, '5k', 4000, 5000] : null,
        $demoId ? [$demoId, '10k', 7000, 10000] : null,
        $testerId ? [$testerId, '3k', 2000, 3000] : null,
        $testerId ? [$testerId, '5k', 4000, 5000] : null,
        $publicId ? [$publicId, '3k', 0, 3000] : null,
        $publicId ? [$publicId, '5k', 0, 5000] : null,
        $publicId ? [$publicId, '10k', 0, 10000] : null,
    ]);
    foreach ($priceSeeds as $seed) {
        $priceStmt->execute($seed);
    }
    logMessage('Agent prices baseline disinkronisasi', 'ok');
}

if ($demoId) {
    $profileStmt = $pdo->prepare("INSERT INTO agent_profile_pricing
        (agent_id, profile_name, display_name, description, price, is_featured, icon, color, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), description = VALUES(description),
            price = VALUES(price), is_featured = VALUES(is_featured), icon = VALUES(icon),
            color = VALUES(color), sort_order = VALUES(sort_order)");
    $profileSeeds = [
        [$demoId, '3k', 'Voucher 1 Hari', 'Voucher hotspot 1 hari', 3000, 0, 'fa-wifi', 'blue', 1],
        [$demoId, '5k', 'Voucher 3 Hari', 'Voucher hotspot 3 hari', 5000, 0, 'fa-wifi', 'indigo', 2],
        [$demoId, '10k', 'Voucher 7 Hari', 'Voucher hotspot 7 hari', 10000, 0, 'fa-wifi', 'purple', 3],
    ];
    foreach ($profileSeeds as $seed) {
        $profileStmt->execute($seed);
    }
    logMessage('Agent profile pricing baseline disinkronisasi', 'ok');
}

if ($demoId) {
    $billingProfileStmt = $pdo->prepare("INSERT INTO billing_profiles
        (profile_name, price_monthly, speed_label, mikrotik_profile_normal, mikrotik_profile_isolation, description)
        VALUES (:name, :price, :speed, :normal, :isolation, :desc)
        ON DUPLICATE KEY UPDATE price_monthly = VALUES(price_monthly), speed_label = VALUES(speed_label),
            mikrotik_profile_normal = VALUES(mikrotik_profile_normal),
            mikrotik_profile_isolation = VALUES(mikrotik_profile_isolation),
            description = VALUES(description)");
    $billingProfileStmt->execute([
        ':name' => 'BRONZE',
        ':price' => 110000,
        ':speed' => 'Upto 5Mbps',
        ':normal' => 'BRONZE',
        ':isolation' => 'ISOLIR',
        ':desc' => '',
    ]);
    logMessage('Billing profile default tersedia', 'ok');
}

$billingDefaults = [
    'billing_portal_contact_heading' => 'Butuh bantuan? Hubungi Admin ISP',
    'billing_portal_contact_whatsapp' => '081234567890',
    'billing_portal_contact_email' => 'support@ispanda.com',
    'billing_portal_contact_body' => 'Jam operasional: 08.00 - 22.00',
    'billing_portal_base_url' => '',
    'billing_portal_otp_enabled' => '0',
    'billing_portal_otp_digits' => '6',
    'billing_portal_otp_expiry_minutes' => '5',
    'billing_portal_otp_max_attempts' => '5',
    'billing_isolation_delay' => '1',
    'billing_reminder_days_before' => '3,1',
];
foreach ($billingDefaults as $key => $value) {
    upsertSetting($pdo, 'billing_settings', $key, $value);
}
logMessage('Billing settings dasar dipastikan', 'ok');

$tripayStmt = $pdo->prepare("INSERT INTO payment_methods
    (gateway_name, method_code, method_name, method_type, name, type, display_name, icon, admin_fee_type, admin_fee_value, min_amount, max_amount, is_active, sort_order)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE method_name = VALUES(method_name), method_type = VALUES(method_type),
        name = VALUES(name), type = VALUES(type), display_name = VALUES(display_name), icon = VALUES(icon),
        admin_fee_type = VALUES(admin_fee_type), admin_fee_value = VALUES(admin_fee_value),
        min_amount = VALUES(min_amount), max_amount = VALUES(max_amount), is_active = VALUES(is_active),
        sort_order = VALUES(sort_order)");
$tripaySeeds = [
    ['tripay', 'QRIS', 'QRIS (Semua Bank & E-Wallet)', 'qris', 'QRIS', 'qris', 'QRIS (Semua Bank & E-Wallet)', 'fa-qrcode', 'percentage', 0, 10000, 5000000, 1, 1],
    ['tripay', 'BRIVA', 'BRI Virtual Account', 'va', 'BRIVA', 'va', 'BRI Virtual Account', 'fa-bank', 'fixed', 4000, 10000, 5000000, 1, 2],
    ['tripay', 'BNIVA', 'BNI Virtual Account', 'va', 'BNIVA', 'va', 'BNI Virtual Account', 'fa-bank', 'fixed', 4000, 10000, 5000000, 1, 3],
    ['tripay', 'BCAVA', 'BCA Virtual Account', 'va', 'BCAVA', 'va', 'BCA Virtual Account', 'fa-bank', 'fixed', 4000, 10000, 5000000, 1, 4],
    ['tripay', 'MANDIRIVA', 'Mandiri Virtual Account', 'va', 'MANDIRIVA', 'va', 'Mandiri Virtual Account', 'fa-bank', 'fixed', 4000, 10000, 5000000, 1, 5],
    ['tripay', 'PERMATAVA', 'Permata Virtual Account', 'va', 'PERMATAVA', 'va', 'Permata Virtual Account', 'fa-bank', 'fixed', 4000, 10000, 5000000, 1, 6],
    ['tripay', 'OVO', 'OVO', 'ewallet', 'OVO', 'ewallet', 'OVO', 'fa-mobile', 'percentage', 2.5, 10000, 2000000, 1, 7],
    ['tripay', 'DANA', 'DANA', 'ewallet', 'DANA', 'ewallet', 'DANA', 'fa-mobile', 'percentage', 2.5, 10000, 2000000, 1, 8],
    ['tripay', 'SHOPEEPAY', 'ShopeePay', 'ewallet', 'SHOPEEPAY', 'ewallet', 'ShopeePay', 'fa-mobile', 'percentage', 2.5, 10000, 2000000, 1, 9],
    ['tripay', 'LINKAJA', 'LinkAja', 'ewallet', 'LINKAJA', 'ewallet', 'LinkAja', 'fa-mobile', 'percentage', 2.5, 10000, 2000000, 1, 10],
    ['tripay', 'ALFAMART', 'Alfamart', 'retail', 'ALFAMART', 'retail', 'Alfamart', 'fa-shopping-cart', 'fixed', 5000, 10000, 5000000, 1, 11],
    ['tripay', 'INDOMARET', 'Indomaret', 'retail', 'INDOMARET', 'retail', 'Indomaret', 'fa-shopping-cart', 'fixed', 5000, 10000, 5000000, 1, 12],
];
foreach ($tripaySeeds as $seed) {
    $tripayStmt->execute($seed);
}
$pdo->exec("INSERT INTO payment_gateway_config (gateway_name, is_active, is_sandbox)
    VALUES ('tripay', 1, 1)
    ON DUPLICATE KEY UPDATE is_active = VALUES(is_active), is_sandbox = VALUES(is_sandbox)");
logMessage('Konfigurasi Tripay dipastikan', 'ok');

$pdo->exec("INSERT IGNORE INTO site_pages (page_slug, page_title, page_content)
    VALUES ('tos', 'Syarat dan Ketentuan', '<h3>Syarat dan Ketentuan</h3><p>Sesuaikan konten ini.</p>'),
           ('privacy', 'Kebijakan Privasi', '<h3>Kebijakan Privasi</h3><p>Sesuaikan konten ini.</p>'),
           ('faq', 'FAQ', '<h3>FAQ</h3><p>Sesuaikan konten ini.</p>')");
logMessage('Site pages default tersedia', 'ok');

logMessage('=== Sinkronisasi selesai ===', 'ok');

echo PHP_EOL . 'Silakan hapus file ini jika sudah tidak diperlukan.' . PHP_EOL;
