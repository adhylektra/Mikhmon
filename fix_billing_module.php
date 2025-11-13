<?php
/**
 * Billing Module Fix Utility
 * -----------------------------------------
 * Jalankan file ini (via browser atau CLI PHP) untuk memastikan
 * tabel billing (profiles, customers, invoices, payments, settings, logs)
 * memiliki struktur terbaru yang dibutuhkan fitur Billing.
 *
 * Catatan: demi keamanan, akses dibatasi dengan parameter ?key=fix-billing-2024
 */

$securityKey = $_GET['key'] ?? ($_SERVER['argv'][1] ?? '');
if ($securityKey !== 'fix-billing-2024') {
    exit("Access denied. Tambahkan ?key=fix-billing-2024 pada URL atau argumen CLI.\n");
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(300);

require_once __DIR__ . '/include/db_config.php';

try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    exit("Gagal konek database: " . $e->getMessage() . "\n");
}

/** @var PDO $pdo */
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$log = [];

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
    $sql = "SHOW TABLES LIKE {$pattern}";
    return (bool) $pdo->query($sql)->fetchColumn();
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $pattern = $pdo->quote($column);
    $tableSafe = str_replace('`', '``', $table);
    $sql = "SHOW COLUMNS FROM `{$tableSafe}` LIKE {$pattern}";
    return (bool) $pdo->query($sql)->fetchColumn();
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $pattern = $pdo->quote($index);
    $tableSafe = str_replace('`', '``', $table);
    $sql = "SHOW INDEX FROM `{$tableSafe}` WHERE Key_name = {$pattern}";
    return (bool) $pdo->query($sql)->fetchColumn();
}

function getColumnInfo(PDO $pdo, string $table, string $column): ?array
{
    $pattern = $pdo->quote($column);
    $tableSafe = str_replace('`', '``', $table);
    $sql = "SHOW FULL COLUMNS FROM `{$tableSafe}` LIKE {$pattern}";
    $stmt = $pdo->query($sql);
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    return $row ?: null;
}

function getTableEngine(PDO $pdo, string $table): ?string
{
    $pattern = $pdo->quote($table);
    $sql = "SHOW TABLE STATUS WHERE Name = {$pattern}";
    $stmt = $pdo->query($sql);
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    return $row['Engine'] ?? null;
}

logMessage('Memulai pengecekan struktur Billing...');

$createStatements = [
    'billing_portal_otps' => <<<SQL
CREATE TABLE `billing_portal_otps` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `identifier` VARCHAR(191) NOT NULL,
  `otp_code` VARCHAR(191) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `sent_via` ENUM('whatsapp','sms','email') DEFAULT 'whatsapp',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customer_identifier` (`customer_id`, `identifier`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'digiflazz_transactions' => <<<SQL
CREATE TABLE `digiflazz_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT,
  `ref_id` VARCHAR(60) NOT NULL,
  `buyer_sku_code` VARCHAR(50) NOT NULL,
  `customer_no` VARCHAR(50) NOT NULL,
  `customer_name` VARCHAR(100),
  `status` ENUM('pending', 'success', 'failed', 'refund') DEFAULT 'pending',
  `message` VARCHAR(255),
  `price` INT DEFAULT 0,
  `sell_price` INT DEFAULT 0,
  `serial_number` VARCHAR(100),
  `response` TEXT,
  `whatsapp_notified` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE SET NULL,
  INDEX `idx_ref` (`ref_id`),
  INDEX `idx_agent` (`agent_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'billing_profiles' => <<<SQL
CREATE TABLE `billing_profiles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `profile_name` VARCHAR(100) NOT NULL,
  `price_monthly` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `speed_label` VARCHAR(100) DEFAULT NULL,
  `mikrotik_profile_normal` VARCHAR(100) NOT NULL,
  `mikrotik_profile_isolation` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_profile_name` (`profile_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'billing_customers' => <<<SQL
CREATE TABLE `billing_customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
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
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_profile_id` (`profile_id`),
  KEY `idx_billing_day` (`billing_day`),
  CONSTRAINT `fk_billing_customers_profile` FOREIGN KEY (`profile_id`) REFERENCES `billing_profiles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'billing_invoices' => <<<SQL
CREATE TABLE `billing_invoices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `profile_snapshot` JSON DEFAULT NULL,
  `period` CHAR(7) NOT NULL COMMENT 'Format YYYY-MM',
  `due_date` DATE NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('draft','unpaid','paid','overdue','cancelled') NOT NULL DEFAULT 'unpaid',
  `paid_at` DATETIME DEFAULT NULL,
  `payment_channel` VARCHAR(100) DEFAULT NULL,
  `reference_number` VARCHAR(100) DEFAULT NULL,
  `whatsapp_sent_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_customer_period` (`customer_id`,`period`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_billing_invoices_customer` FOREIGN KEY (`customer_id`) REFERENCES `billing_customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
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
    'billing_logs' => <<<SQL
CREATE TABLE `billing_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` BIGINT UNSIGNED DEFAULT NULL,
  `customer_id` INT UNSIGNED DEFAULT NULL,
  `event` VARCHAR(100) NOT NULL,
  `metadata` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `idx_customer_id` (`customer_id`),
  CONSTRAINT `fk_billing_logs_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `billing_invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_billing_logs_customer` FOREIGN KEY (`customer_id`) REFERENCES `billing_customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
];

foreach ($createStatements as $table => $sql) {
    if (! tableExists($pdo, $table)) {
        logMessage("Membuat tabel {$table}...", 'info');
        $pdo->exec($sql);
        logMessage("Tabel {$table} berhasil dibuat", 'ok');
    } else {
        logMessage("Tabel {$table} sudah ada", 'info');
    }
}

// Pastikan kolom whatsapp_notified tersedia pada digiflazz_transactions
if (tableExists($pdo, 'digiflazz_transactions') && ! columnExists($pdo, 'digiflazz_transactions', 'whatsapp_notified')) {
    logMessage('Menambahkan kolom digiflazz_transactions.whatsapp_notified ...', 'warn');
    $pdo->exec("ALTER TABLE `digiflazz_transactions` ADD COLUMN `whatsapp_notified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `response`");
    logMessage('Kolom whatsapp_notified ditambahkan ke digiflazz_transactions', 'ok');
}

// Pastikan kolom otp_code cukup panjang untuk menyimpan hash
if (tableExists($pdo, 'billing_portal_otps')) {
    $otpColumnInfo = getColumnInfo($pdo, 'billing_portal_otps', 'otp_code');
    if ($otpColumnInfo && stripos((string)$otpColumnInfo['Type'], 'varchar(191)') === false) {
        logMessage('Menyesuaikan ukuran kolom billing_portal_otps.otp_code ...', 'warn');
        $pdo->exec("ALTER TABLE `billing_portal_otps` MODIFY `otp_code` VARCHAR(191) NOT NULL");
        logMessage('Kolom otp_code disesuaikan menjadi VARCHAR(191)', 'ok');
    }
}

// Pastikan kolom dan indeks penting tersedia
// 1. billing_customers.profile_id
if (! columnExists($pdo, 'billing_customers', 'profile_id')) {
    logMessage('Menambahkan kolom billing_customers.profile_id ...', 'warn');
    $pdo->exec("ALTER TABLE `billing_customers` ADD COLUMN `profile_id` INT UNSIGNED NOT NULL AFTER `id`");
    $pdo->exec("ALTER TABLE `billing_customers` ADD KEY `idx_profile_id` (`profile_id`)");
    logMessage('Kolom profile_id ditambahkan ke billing_customers', 'ok');
}

// Pastikan kolom name tersedia (dipakai di dashboard)
if (! columnExists($pdo, 'billing_customers', 'name')) {
    logMessage('Menambahkan kolom billing_customers.name ...', 'warn');
    $pdo->exec("ALTER TABLE `billing_customers` ADD COLUMN `name` VARCHAR(150) NOT NULL DEFAULT '' AFTER `profile_id`");
    logMessage('Kolom name ditambahkan ke billing_customers', 'ok');
}

// Pastikan kolom status tersedia
if (! columnExists($pdo, 'billing_customers', 'status')) {
    logMessage('Menambahkan kolom billing_customers.status ...', 'warn');
    $pdo->exec("ALTER TABLE `billing_customers` ADD COLUMN `status` ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER `billing_day`");
    logMessage('Kolom status ditambahkan ke billing_customers', 'ok');
}

// Pastikan kolom service_number tersedia (dipakai untuk integrasi perangkat)
if (! columnExists($pdo, 'billing_customers', 'service_number')) {
    logMessage('Menambahkan kolom billing_customers.service_number ...', 'warn');
    $pdo->exec("ALTER TABLE `billing_customers` ADD COLUMN `service_number` VARCHAR(100) DEFAULT NULL AFTER `address`");
    logMessage('Kolom service_number ditambahkan ke billing_customers', 'ok');
}

// Kolom konfigurasi mapping GenieACS (mode & PPPoE username)
if (! columnExists($pdo, 'billing_customers', 'genieacs_match_mode')) {
    logMessage('Menambahkan kolom billing_customers.genieacs_match_mode ...', 'warn');
    $pdo->exec("ALTER TABLE `billing_customers` ADD COLUMN `genieacs_match_mode` ENUM('device_id','phone_tag','pppoe_username') NOT NULL DEFAULT 'device_id' AFTER `service_number`");
    logMessage('Kolom genieacs_match_mode ditambahkan ke billing_customers', 'ok');
}

if (! columnExists($pdo, 'billing_customers', 'genieacs_pppoe_username')) {
    logMessage('Menambahkan kolom billing_customers.genieacs_pppoe_username ...', 'warn');
    $pdo->exec("ALTER TABLE `billing_customers` ADD COLUMN `genieacs_pppoe_username` VARCHAR(191) DEFAULT NULL AFTER `genieacs_match_mode`");
    logMessage('Kolom genieacs_pppoe_username ditambahkan ke billing_customers', 'ok');
}

// Pastikan kolom is_isolated ada (dibutuhkan oleh UI)
if (! columnExists($pdo, 'billing_customers', 'is_isolated')) {
    logMessage('Menambahkan kolom billing_customers.is_isolated ...', 'warn');
    if (columnExists($pdo, 'billing_customers', 'status')) {
        $pdo->exec("ALTER TABLE `billing_customers` ADD COLUMN `is_isolated` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
    } else {
        $pdo->exec("ALTER TABLE `billing_customers` ADD COLUMN `is_isolated` TINYINT(1) NOT NULL DEFAULT 0");
    }
    logMessage('Kolom is_isolated ditambahkan ke billing_customers', 'ok');
}

// 2. billing_customers.next_isolation_date (opsional, untuk future automation)
if (! columnExists($pdo, 'billing_customers', 'next_isolation_date')) {
    logMessage('Menambahkan kolom billing_customers.next_isolation_date ...', 'warn');
    if (columnExists($pdo, 'billing_customers', 'is_isolated')) {
        $pdo->exec("ALTER TABLE `billing_customers` ADD COLUMN `next_isolation_date` DATE DEFAULT NULL AFTER `is_isolated`");
    } else {
        $pdo->exec("ALTER TABLE `billing_customers` ADD COLUMN `next_isolation_date` DATE DEFAULT NULL");
    }
    logMessage('Kolom next_isolation_date ditambahkan ke billing_customers', 'ok');
}

// 3a. billing_invoices.profile_snapshot (untuk menyimpan snapshot profil)
if (! columnExists($pdo, 'billing_invoices', 'profile_snapshot')) {
    logMessage('Menambahkan kolom billing_invoices.profile_snapshot ...', 'warn');
    $pdo->exec("ALTER TABLE `billing_invoices` ADD COLUMN `profile_snapshot` JSON DEFAULT NULL");
    logMessage('Kolom profile_snapshot ditambahkan ke billing_invoices', 'ok');
}

// 3b. billing_invoices.period
if (! columnExists($pdo, 'billing_invoices', 'period')) {
    logMessage('Menambahkan kolom billing_invoices.period ...', 'warn');
    if (columnExists($pdo, 'billing_invoices', 'profile_snapshot')) {
        $pdo->exec("ALTER TABLE `billing_invoices` ADD COLUMN `period` CHAR(7) NOT NULL COMMENT 'Format YYYY-MM' AFTER `profile_snapshot`");
    } else {
        $pdo->exec("ALTER TABLE `billing_invoices` ADD COLUMN `period` CHAR(7) NOT NULL COMMENT 'Format YYYY-MM'");
    }
    logMessage('Kolom period ditambahkan ke billing_invoices', 'ok');
}

if (! indexExists($pdo, 'billing_invoices', 'uniq_customer_period')) {
    logMessage('Menambahkan index uniq_customer_period pada billing_invoices ...', 'warn');
    $pdo->exec("ALTER TABLE `billing_invoices` ADD UNIQUE KEY `uniq_customer_period` (`customer_id`,`period`)");
    logMessage('Index uniq_customer_period ditambahkan ke billing_invoices', 'ok');
}

// 4. Pastikan kolom payment_channel tersedia (beberapa instalasi lama belum punya)
if (! columnExists($pdo, 'billing_invoices', 'payment_channel')) {
    logMessage('Menambahkan kolom billing_invoices.payment_channel ...', 'warn');
    $pdo->exec("ALTER TABLE `billing_invoices` ADD COLUMN `payment_channel` VARCHAR(100) DEFAULT NULL AFTER `paid_at`");
    logMessage('Kolom payment_channel ditambahkan ke billing_invoices', 'ok');
}

// 5. billing_invoices.reference_number (jaga-jaga jika kolom hilang)
if (! columnExists($pdo, 'billing_invoices', 'reference_number')) {
    logMessage('Menambahkan kolom billing_invoices.reference_number ...', 'warn');
    if (columnExists($pdo, 'billing_invoices', 'payment_channel')) {
        $pdo->exec("ALTER TABLE `billing_invoices` ADD COLUMN `reference_number` VARCHAR(100) DEFAULT NULL AFTER `payment_channel`");
    } else {
        $pdo->exec("ALTER TABLE `billing_invoices` ADD COLUMN `reference_number` VARCHAR(100) DEFAULT NULL");
    }
    logMessage('Kolom reference_number ditambahkan ke billing_invoices', 'ok');
}

// 6. billing_invoices.whatsapp_sent_at
if (! columnExists($pdo, 'billing_invoices', 'whatsapp_sent_at')) {
    logMessage('Menambahkan kolom billing_invoices.whatsapp_sent_at ...', 'warn');
    if (columnExists($pdo, 'billing_invoices', 'reference_number')) {
        $pdo->exec("ALTER TABLE `billing_invoices` ADD COLUMN `whatsapp_sent_at` DATETIME DEFAULT NULL AFTER `reference_number`");
    } else {
        $pdo->exec("ALTER TABLE `billing_invoices` ADD COLUMN `whatsapp_sent_at` DATETIME DEFAULT NULL");
    }
    logMessage('Kolom whatsapp_sent_at ditambahkan ke billing_invoices', 'ok');
}

// 6. billing_payments table (dibuat terpisah agar bisa menyesuaikan struktur parent)
if (! tableExists($pdo, 'billing_payments')) {
    logMessage('Membuat tabel billing_payments ...', 'info');

    $invoiceIdInfo = getColumnInfo($pdo, 'billing_invoices', 'id');
    $invoiceIdType = $invoiceIdInfo['Type'] ?? 'bigint(20) unsigned';
    $invoiceIdTypeSql = strtoupper(str_replace('unsigned', 'UNSIGNED', $invoiceIdType));

    $invoiceEngine = strtolower((string) getTableEngine($pdo, 'billing_invoices'));
    $supportForeignKey = $invoiceEngine === 'innodb';

    $createPaymentsSql = <<<SQL
CREATE TABLE `billing_payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` {$invoiceIdTypeSql} NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `method` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_id` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    $pdo->exec($createPaymentsSql);
    logMessage('Tabel billing_payments berhasil dibuat', 'ok');

    if ($supportForeignKey) {
        try {
            $pdo->exec("ALTER TABLE `billing_payments` ADD CONSTRAINT `fk_billing_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `billing_invoices`(`id`) ON DELETE CASCADE ON UPDATE CASCADE");
            logMessage('Relasi foreign key billing_payments -> billing_invoices ditambahkan', 'ok');
        } catch (PDOException $e) {
            logMessage('Tidak dapat menambahkan foreign key billing_payments: ' . $e->getMessage(), 'warn');
        }
    } else {
        logMessage('Lewati penambahan foreign key billing_payments karena tabel billing_invoices bukan InnoDB.', 'warn');
    }
} else {
    logMessage('Tabel billing_payments sudah ada', 'info');
}

// 7. Pastikan kolom penting pada payment_gateway_config tersedia
if (tableExists($pdo, 'payment_gateway_config')) {
    if (! columnExists($pdo, 'payment_gateway_config', 'name')) {
        logMessage('Menambahkan kolom payment_gateway_config.name ...', 'warn');
        $pdo->exec("ALTER TABLE `payment_gateway_config` ADD COLUMN `name` VARCHAR(100) DEFAULT NULL AFTER `gateway_name`");
        logMessage('Kolom name ditambahkan ke payment_gateway_config', 'ok');
    }

    if (! columnExists($pdo, 'payment_gateway_config', 'provider')) {
        logMessage('Menambahkan kolom payment_gateway_config.provider ...', 'warn');
        $pdo->exec("ALTER TABLE `payment_gateway_config` ADD COLUMN `provider` VARCHAR(50) DEFAULT NULL AFTER `name`");
        logMessage('Kolom provider ditambahkan ke payment_gateway_config', 'ok');
    }

    if (! columnExists($pdo, 'payment_gateway_config', 'callback_url')) {
        logMessage('Menambahkan kolom payment_gateway_config.callback_url ...', 'warn');
        $pdo->exec("ALTER TABLE `payment_gateway_config` ADD COLUMN `callback_url` VARCHAR(255) DEFAULT NULL AFTER `config_json`");
        logMessage('Kolom callback_url ditambahkan ke payment_gateway_config', 'ok');
    }
} else {
    logMessage('Tabel payment_gateway_config belum ada. Jalankan installer agent/payment untuk membuatnya.', 'warn');
}

logMessage('Semua pengecekan selesai.', 'ok');
logMessage('Silakan refresh menu Billing untuk memastikan error telah hilang.', 'info');

if (PHP_SAPI !== 'cli') {
    echo '<hr><strong>Selesai.</strong>';
}
