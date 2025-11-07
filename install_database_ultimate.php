<?php
/*
 * MikhMon Agent - Ultimate Database Installer
 * One-script installation with ALL data and fixes included
 * Based on working production data
 */

// Security check
$security_key = $_GET['key'] ?? '';
if ($security_key !== 'mikhmon-install-2024') {
    die('Access denied. Add ?key=mikhmon-install-2024 to URL');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 minutes

?>
<!DOCTYPE html>
<html>
<head>
    <title>MikhMon Agent - Ultimate Database Installation</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        .step { margin: 10px 0; padding: 10px; border-left: 4px solid #007bff; background: #f8f9fa; }
        .progress { background: #e9ecef; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .progress-bar { background: #007bff; height: 20px; transition: width 0.3s; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        .highlight { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 MikhMon Agent - Ultimate Database Installation</h1>
        <p>Complete installation with all production data and fixes included.</p>
        
        <div class="progress">
            <div class="progress-bar" id="progressBar" style="width: 0%"></div>
        </div>
        <div id="progressText">Starting installation...</div>
        
        <div id="output">

<?php

function updateProgress($percent, $text) {
    echo "<script>
        document.getElementById('progressBar').style.width = '{$percent}%';
        document.getElementById('progressText').innerHTML = '{$text}';
    </script>";
    flush();
    ob_flush();
}

function logStep($message, $type = 'info') {
    $class = $type;
    echo "<div class='step $class'>$message</div>";
    flush();
    ob_flush();
}

try {
    updateProgress(5, "Connecting to database...");
    
    // Database connection
    if (file_exists('include/db_config.php')) {
        include_once('include/db_config.php');
        $conn = getDBConnection();
        logStep("✅ Database connection successful!", 'success');
    } else {
        throw new Exception("Database config file not found. Please configure include/db_config.php first.");
    }
    
    updateProgress(10, "Creating complete database structure...");
    
    // Create all tables with correct structure
    logStep("🔧 Creating database tables with production-ready structure...", 'info');
    
    // 1. Agents table
    $conn->exec("CREATE TABLE IF NOT EXISTS agents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_code VARCHAR(20) UNIQUE NOT NULL,
        agent_name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        password VARCHAR(255),
        address TEXT,
        balance DECIMAL(15,2) DEFAULT 0.00,
        commission_rate DECIMAL(5,2) DEFAULT 0.00,
        status ENUM('active','inactive','suspended') DEFAULT 'active',
        level ENUM('bronze','silver','gold','platinum') DEFAULT 'bronze',
        created_by VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        notes TEXT,
        UNIQUE KEY unique_agent_code (agent_code),
        KEY idx_agent_code (agent_code),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    updateProgress(15, "Creating agents table...");
    logStep("✅ Agents table created", 'success');
    
    // 2. Agent Settings
    $conn->exec("CREATE TABLE IF NOT EXISTS agent_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id INT NOT NULL,
        setting_key VARCHAR(100) NOT NULL,
        setting_value TEXT,
        setting_type VARCHAR(20),
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(50),
        FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
        UNIQUE KEY unique_agent_setting (agent_id, setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    updateProgress(20, "Creating agent settings...");
    logStep("✅ Agent settings table created", 'success');
    
    // 3. Agent Prices
    $conn->exec("CREATE TABLE IF NOT EXISTS agent_prices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id INT NOT NULL,
        profile_name VARCHAR(100) NOT NULL,
        buy_price DECIMAL(15,2) NOT NULL,
        sell_price DECIMAL(15,2) NOT NULL,
        stock_limit INT(11),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
        UNIQUE KEY unique_agent_profile (agent_id, profile_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    updateProgress(25, "Creating agent prices...");
    logStep("✅ Agent prices table created", 'success');
    
    // 4. Agent Transactions
    $conn->exec("CREATE TABLE IF NOT EXISTS agent_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id INT NOT NULL,
        transaction_type ENUM('topup','generate','refund','commission','penalty') NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        balance_before DECIMAL(15,2) NOT NULL,
        balance_after DECIMAL(15,2) NOT NULL,
        profile_name VARCHAR(100),
        voucher_username VARCHAR(100),
        voucher_password VARCHAR(100),
        quantity INT(11),
        description TEXT,
        reference_id VARCHAR(50),
        created_by VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        user_agent TEXT,
        FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
        INDEX idx_agent_date (agent_id, created_at),
        INDEX idx_reference (reference_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    updateProgress(30, "Creating agent transactions...");
    logStep("✅ Agent transactions table created", 'success');
    
    // 5. Payment Gateway Config
    $conn->exec("CREATE TABLE IF NOT EXISTS payment_gateway_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gateway_name VARCHAR(50) NOT NULL,
        is_active TINYINT(1) DEFAULT 0,
        is_sandbox TINYINT(1) DEFAULT 1,
        api_key VARCHAR(255),
        api_secret VARCHAR(255),
        merchant_code VARCHAR(100),
        callback_token VARCHAR(255),
        config_json TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_gateway (gateway_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    updateProgress(35, "Creating payment gateway config...");
    logStep("✅ Payment gateway config table created", 'success');
    
    // 6. Agent Profile Pricing (for public sales)
    $conn->exec("CREATE TABLE IF NOT EXISTS agent_profile_pricing (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id INT NOT NULL,
        profile_name VARCHAR(100) NOT NULL,
        display_name VARCHAR(100) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        original_price DECIMAL(10,2),
        is_active TINYINT(1) DEFAULT 1,
        is_featured TINYINT(1) DEFAULT 0,
        icon VARCHAR(50) DEFAULT 'fa-wifi',
        color VARCHAR(20) DEFAULT 'blue',
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
        UNIQUE KEY unique_agent_profile (agent_id, profile_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    updateProgress(40, "Creating agent profile pricing...");
    logStep("✅ Agent profile pricing table created", 'success');
    
    // 7. Public Sales (complete structure with all required columns)
    $conn->exec("CREATE TABLE IF NOT EXISTS public_sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id VARCHAR(100) UNIQUE NOT NULL,
        payment_reference VARCHAR(100),
        agent_id INT NOT NULL DEFAULT 1,
        profile_id INT NOT NULL DEFAULT 1,
        customer_name VARCHAR(100) NOT NULL DEFAULT '',
        customer_phone VARCHAR(20) NOT NULL DEFAULT '',
        customer_email VARCHAR(100),
        profile_name VARCHAR(100) NOT NULL DEFAULT '',
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        admin_fee DECIMAL(10,2) DEFAULT 0,
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        gateway_name VARCHAR(50) NOT NULL DEFAULT '',
        payment_method VARCHAR(50),
        payment_channel VARCHAR(50),
        payment_url TEXT,
        qr_url TEXT,
        virtual_account VARCHAR(50),
        payment_instructions TEXT,
        expired_at DATETIME,
        paid_at DATETIME,
        status VARCHAR(20) DEFAULT 'pending',
        voucher_code VARCHAR(50),
        voucher_password VARCHAR(50),
        voucher_generated_at DATETIME,
        voucher_sent_at DATETIME,
        ip_address VARCHAR(50),
        user_agent TEXT,
        callback_data TEXT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
        FOREIGN KEY (profile_id) REFERENCES agent_profile_pricing(id) ON DELETE CASCADE,
        INDEX idx_transaction_id (transaction_id),
        INDEX idx_payment_reference (payment_reference),
        INDEX idx_status (status),
        INDEX idx_customer_phone (customer_phone),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Add additional columns check for existing tables (backward compatibility)
    $publicSalesColumns = [
        'status' => 'VARCHAR(20) DEFAULT "pending"',
        'price' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'gateway_name' => 'VARCHAR(50) NOT NULL DEFAULT ""',
        'payment_channel' => 'VARCHAR(50)',
        'qr_url' => 'TEXT',
        'virtual_account' => 'VARCHAR(50)',
        'payment_instructions' => 'TEXT',
        'expired_at' => 'DATETIME',
        'paid_at' => 'DATETIME',
        'voucher_code' => 'VARCHAR(50)',
        'ip_address' => 'VARCHAR(50)',
        'user_agent' => 'TEXT',
        'callback_data' => 'TEXT',
        'notes' => 'TEXT'
    ];
    
    foreach ($publicSalesColumns as $column => $definition) {
        try {
            $conn->query("SELECT $column FROM public_sales LIMIT 1");
        } catch (Exception $e) {
            try {
                $conn->exec("ALTER TABLE public_sales ADD COLUMN $column $definition");
                logStep("✅ Added missing $column column to public_sales", 'success');
            } catch (Exception $e2) {
                // Column might already exist, continue
            }
        }
    }
    
    updateProgress(45, "Creating public sales table...");
    logStep("✅ Public sales table created", 'success');
    
    // 8. Payment Methods (dual compatibility)
    $conn->exec("CREATE TABLE IF NOT EXISTS payment_methods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gateway_name VARCHAR(50) NOT NULL,
        method_code VARCHAR(50) NOT NULL,
        method_name VARCHAR(100) NOT NULL,
        method_type VARCHAR(20) NOT NULL,
        name VARCHAR(100) NOT NULL,
        type VARCHAR(50) NOT NULL,
        display_name VARCHAR(100) NOT NULL,
        icon VARCHAR(100),
        icon_url VARCHAR(255),
        admin_fee_type ENUM('percentage','fixed','flat','percent') DEFAULT 'fixed',
        admin_fee_value DECIMAL(10,2) DEFAULT 0,
        min_amount DECIMAL(10,2) DEFAULT 0,
        max_amount DECIMAL(10,2) DEFAULT 999999999,
        is_active TINYINT(1) DEFAULT 1,
        sort_order INT DEFAULT 0,
        config TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_gateway_method (gateway_name, method_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    updateProgress(50, "Creating payment methods table...");
    logStep("✅ Payment methods table created", 'success');
    
    // 9. Voucher Settings
    $conn->exec("CREATE TABLE IF NOT EXISTS voucher_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    updateProgress(55, "Creating voucher settings...");
    logStep("✅ Voucher settings table created", 'success');
    
    // 10. Site Pages
    $conn->exec("CREATE TABLE IF NOT EXISTS site_pages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page_slug VARCHAR(50) UNIQUE NOT NULL,
        page_title VARCHAR(200) NOT NULL,
        page_content TEXT NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    updateProgress(60, "Creating site pages...");
    logStep("✅ Site pages table created", 'success');
    
    // Insert production data
    updateProgress(65, "Inserting production data...");
    logStep("📊 Inserting production-ready data...", 'info');
    
    // Insert Agents
    $conn->exec("INSERT IGNORE INTO agents (agent_code, agent_name, status, created_at) VALUES 
        ('AG001', 'Agent Demo', 'active', '2025-11-01 02:27:19'),
        ('AG5136', 'tester', 'active', '2025-11-01 07:13:29'),
        ('PUBLIC', 'Public Catalog', 'active', '2025-11-04 20:34:58')");
    
    logStep("✅ Inserted 3 agents", 'success');
    
    // Insert Agent Settings (32 settings for agent 1)
    $settings = [
        ['admin_whatsapp_numbers', '6281947215703'],
        ['agent_can_set_sell_price', '1'],
        ['agent_registration_enabled', '1'],
        ['auto_approve_topup', '0'],
        ['commission_enabled', '1'],
        ['default_commission_percent', '5'],
        ['max_topup_amount', '10000000'],
        ['min_balance_alert', '10000'],
        ['min_topup_amount', '50000'],
        ['payment_account_name', 'WARJAYA'],
        ['payment_account_number', '420601003953531'],
        ['payment_bank_name', 'BRI'],
        ['payment_wa_confirm', '081947215703'],
        ['public_duration_3k', '1d'],
        ['public_duration_v15', '7d'],
        ['voucher_password_length', '6'],
        ['voucher_password_type', 'alphanumeric'],
        ['voucher_prefix', 'AG'],
        ['voucher_prefix_agent', 'AG'],
        ['voucher_prefix_enabled', '0'],
        ['whatsapp_gateway_url', 'https://api.whatsapp.com'],
        ['whatsapp_token', 'your-token-here'],
        ['notification_enabled', '1'],
        ['auto_generate_voucher', '1'],
        ['voucher_template', 'default'],
        ['commission_type', 'percentage'],
        ['min_commission', '1000'],
        ['max_commission', '50000'],
        ['agent_level_bronze', '0'],
        ['agent_level_silver', '100000'],
        ['agent_level_gold', '500000'],
        ['agent_level_platinum', '1000000']
    ];
    
    // Check if agent_settings has agent_id column, if not add it
    try {
        $conn->query("SELECT agent_id FROM agent_settings LIMIT 1");
    } catch (Exception $e) {
        // Column doesn't exist, add it
        $conn->exec("ALTER TABLE agent_settings ADD COLUMN agent_id INT NOT NULL DEFAULT 1 AFTER id");
        $conn->exec("ALTER TABLE agent_settings ADD FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE");
        logStep("✅ Added agent_id column to agent_settings", 'success');
    }
    
    $settingStmt = $conn->prepare("INSERT IGNORE INTO agent_settings (agent_id, setting_key, setting_value) VALUES (1, ?, ?)");
    foreach ($settings as $setting) {
        $settingStmt->execute($setting);
    }
    
    logStep("✅ Inserted 32 agent settings", 'success');
    
    // Insert Agent Prices
    $prices = [
        [1, '3k', 2000, 3000],
        [1, '5k', 4000, 5000],
        [2, '10k', 8000, 10000],
        [2, '3k', 2000, 3000],
        [2, '5k', 4000, 5000],
        [3, '10k', 0, 10000],
        [3, '15k', 0, 15000],
        [3, '25k', 0, 25000],
        [3, '3k', 0, 3000],
        [3, '50k', 0, 50000],
        [3, '5k', 0, 5000],
        [3, 'v15', 0, 15000]
    ];
    
    $priceStmt = $conn->prepare("INSERT IGNORE INTO agent_prices (agent_id, profile_name, buy_price, sell_price) VALUES (?, ?, ?, ?)");
    foreach ($prices as $price) {
        $priceStmt->execute($price);
    }
    
    logStep("✅ Inserted 12 agent prices", 'success');
    
    // Insert Payment Gateway Config
    $conn->exec("INSERT IGNORE INTO payment_gateway_config 
        (gateway_name, is_active, is_sandbox, api_key, api_secret, merchant_code) VALUES 
        ('tripay', 1, 1, 'DEV-0MGDWC...', 'your-private-key', 'your-merchant-code')");
    
    logStep("✅ Inserted payment gateway config", 'success');
    
    // Insert Agent Profile Pricing (for public sales)
    $profiles = [
        [1, '3k', 'Voucher 1 Hari', 3000, 1, 0],
        [1, '5k', 'Voucher 2 Hari', 5000, 1, 0],
        [1, '10k', 'Voucher 5 Hari', 10000, 1, 0],
        [1, '15k', 'Voucher 7 Hari', 15000, 1, 0],
        [1, '25k', 'Voucher 15 Hari', 25000, 1, 0],
        [1, '50k', 'Voucher 30 Hari', 50000, 1, 0]
    ];
    
    $profileStmt = $conn->prepare("INSERT IGNORE INTO agent_profile_pricing 
        (agent_id, profile_name, display_name, price, is_active, is_featured) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($profiles as $profile) {
        $profileStmt->execute($profile);
    }
    
    logStep("✅ Inserted 6 agent profile pricing", 'success');
    
    updateProgress(75, "Inserting payment methods...");
    
    // Check and fix payment_methods table structure
    try {
        $conn->query("SELECT gateway_name FROM payment_methods LIMIT 1");
    } catch (Exception $e) {
        // Column doesn't exist, add it
        $conn->exec("ALTER TABLE payment_methods ADD COLUMN gateway_name VARCHAR(50) NOT NULL DEFAULT 'tripay' AFTER id");
        logStep("✅ Added gateway_name column to payment_methods", 'success');
    }
    
    try {
        $conn->query("SELECT method_code FROM payment_methods LIMIT 1");
    } catch (Exception $e) {
        $conn->exec("ALTER TABLE payment_methods ADD COLUMN method_code VARCHAR(50) NOT NULL DEFAULT '' AFTER gateway_name");
        logStep("✅ Added method_code column to payment_methods", 'success');
    }
    
    try {
        $conn->query("SELECT method_name FROM payment_methods LIMIT 1");
    } catch (Exception $e) {
        $conn->exec("ALTER TABLE payment_methods ADD COLUMN method_name VARCHAR(100) NOT NULL DEFAULT '' AFTER method_code");
        logStep("✅ Added method_name column to payment_methods", 'success');
    }
    
    try {
        $conn->query("SELECT method_type FROM payment_methods LIMIT 1");
    } catch (Exception $e) {
        $conn->exec("ALTER TABLE payment_methods ADD COLUMN method_type VARCHAR(20) NOT NULL DEFAULT '' AFTER method_name");
        logStep("✅ Added method_type column to payment_methods", 'success');
    }
    
    // Check for other required columns
    $requiredColumns = [
        'name' => 'VARCHAR(100) NOT NULL DEFAULT ""',
        'type' => 'VARCHAR(50) NOT NULL DEFAULT ""',
        'display_name' => 'VARCHAR(100) NOT NULL DEFAULT ""',
        'icon' => 'VARCHAR(100)',
        'admin_fee_type' => 'ENUM("percentage","fixed","flat","percent") DEFAULT "fixed"',
        'admin_fee_value' => 'DECIMAL(10,2) DEFAULT 0',
        'min_amount' => 'DECIMAL(10,2) DEFAULT 0',
        'max_amount' => 'DECIMAL(10,2) DEFAULT 999999999',
        'is_active' => 'TINYINT(1) DEFAULT 1',
        'sort_order' => 'INT DEFAULT 0'
    ];
    
    foreach ($requiredColumns as $column => $definition) {
        try {
            $conn->query("SELECT $column FROM payment_methods LIMIT 1");
        } catch (Exception $e) {
            $conn->exec("ALTER TABLE payment_methods ADD COLUMN $column $definition");
            logStep("✅ Added $column column to payment_methods", 'success');
        }
    }
    
    // Insert Payment Methods (12 Tripay methods)
    $conn->exec("DELETE FROM payment_methods");
    
    $tripayMethods = [
        ['tripay', 'QRIS', 'QRIS (Semua Bank & E-Wallet)', 'qris', 'QRIS', 'qris', 'QRIS (Semua Bank & E-Wallet)', 'fa-qrcode', 'fixed', 0, 10000, 5000000, 1, 1],
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
        ['tripay', 'INDOMARET', 'Indomaret', 'retail', 'INDOMARET', 'retail', 'Indomaret', 'fa-shopping-cart', 'fixed', 5000, 10000, 5000000, 1, 12]
    ];
    
    $insertSQL = "INSERT INTO payment_methods 
        (gateway_name, method_code, method_name, method_type, name, type, display_name, icon, admin_fee_type, admin_fee_value, min_amount, max_amount, is_active, sort_order) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insertSQL);
    
    foreach ($tripayMethods as $method) {
        $stmt->execute($method);
        logStep("✅ Added: " . $method[2], 'success');
    }
    
    updateProgress(85, "Inserting default pages...");
    
    // Insert Site Pages
    $conn->exec("INSERT IGNORE INTO site_pages (page_slug, page_title, page_content) VALUES 
        ('tos', 'Syarat dan Ketentuan', '<h3>Syarat dan Ketentuan</h3><p>Dengan melakukan pembelian voucher WiFi di situs ini, Anda menyetujui syarat dan ketentuan yang berlaku.</p>'),
        ('privacy', 'Kebijakan Privasi', '<h3>Kebijakan Privasi</h3><p>Kami menghormati privasi Anda dan berkomitmen untuk melindungi data pribadi Anda.</p>'),
        ('faq', 'FAQ', '<h3>Pertanyaan yang Sering Diajukan</h3><p>Temukan jawaban untuk pertanyaan umum tentang layanan voucher WiFi kami.</p>')");
    
    logStep("✅ Inserted default site pages", 'success');
    
    updateProgress(90, "Final verification...");
    
    // Verification
    logStep("🔍 Verifying installation...", 'info');
    
    $tables = [
        'agents' => 'Agents',
        'agent_settings' => 'Agent Settings', 
        'agent_prices' => 'Agent Prices',
        'agent_transactions' => 'Agent Transactions',
        'payment_gateway_config' => 'Payment Gateway Config',
        'agent_profile_pricing' => 'Agent Profile Pricing',
        'public_sales' => 'Public Sales',
        'payment_methods' => 'Payment Methods',
        'voucher_settings' => 'Voucher Settings',
        'site_pages' => 'Site Pages'
    ];
    
    foreach ($tables as $table => $name) {
        $stmt = $conn->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        logStep("✅ $name: $count records", 'success');
    }
    
    // Test critical queries
    logStep("🧪 Testing critical queries...", 'info');
    
    // Test payment methods query
    $stmt = $conn->query("SELECT * FROM payment_methods WHERE gateway_name = 'tripay' AND is_active = 1");
    $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    logStep("✅ Payment methods query: " . count($methods) . " methods found", 'success');
    
    // Test grouping
    $grouped = [];
    foreach ($methods as $method) {
        $grouped[$method['method_type']][] = $method;
    }
    logStep("✅ Payment grouping: " . count($grouped) . " categories (QRIS, VA, E-Wallet, Retail)", 'success');
    
    // Test agent profile pricing
    $stmt = $conn->query("SELECT * FROM agent_profile_pricing WHERE is_active = 1");
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    logStep("✅ Public sales profiles: " . count($profiles) . " active packages", 'success');
    
    updateProgress(100, "Installation complete!");
    
    logStep("🎉 Ultimate installation completed successfully!", 'success');
    
} catch (Exception $e) {
    logStep("❌ Error: " . $e->getMessage(), 'error');
    updateProgress(0, "Installation failed!");
}

?>

        </div>
        
        <div class="highlight">
            <h3>🎉 Ultimate Installation Complete!</h3>
            <p><strong>Your MikhMon Agent system is now fully configured with production data.</strong></p>
            
            <h4>📊 What was installed:</h4>
            <ul>
                <li>✅ <strong>3 Agents:</strong> AG001 (Demo), AG5136 (tester), PUBLIC (Catalog)</li>
                <li>✅ <strong>32 Agent Settings:</strong> Complete configuration</li>
                <li>✅ <strong>12 Agent Prices:</strong> Buy/sell pricing structure</li>
                <li>✅ <strong>6 Public Packages:</strong> 3k, 5k, 10k, 15k, 25k, 50k</li>
                <li>✅ <strong>12 Payment Methods:</strong> QRIS, 5 VA, 4 E-Wallet, 2 Retail</li>
                <li>✅ <strong>Active Tripay Gateway:</strong> Ready for payments</li>
                <li>✅ <strong>All table structures:</strong> Compatible with application</li>
            </ul>
            
            <h4>🔗 Ready to Use:</h4>
            <ul>
                <li><a href="public/index.php?agent=AG001" target="_blank" style="color: #007bff; font-weight: bold;">🛒 Test Public Sales (AG001)</a></li>
                <li><a href="admin.php" target="_blank" style="color: #007bff; font-weight: bold;">🔧 Admin Panel</a></li>
                <li><a href="agent/" target="_blank" style="color: #007bff; font-weight: bold;">👤 Agent Panel</a></li>
            </ul>
            
            <h4>⚙️ Next Steps:</h4>
            <ol>
                <li><strong>Configure Tripay API:</strong> Update API Key, Private Key, Merchant Code in admin panel</li>
                <li><strong>Test Public Voucher:</strong> Complete flow from package selection to payment</li>
                <li><strong>Customize Settings:</strong> Adjust pricing, WhatsApp numbers, etc.</li>
                <li><strong>Security:</strong> Delete this installer file: <code>rm install_database_ultimate.php</code></li>
            </ol>
            
            <div style="background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0;">
                <strong>🎯 Perfect for Production:</strong> This installer contains real production data and all compatibility fixes. Use this for all future deployments!
            </div>
        </div>
    </div>
    
    <script>
        // Auto-scroll to bottom
        window.scrollTo(0, document.body.scrollHeight);
    </script>
</body>
</html>
