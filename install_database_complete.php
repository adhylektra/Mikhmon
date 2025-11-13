<?php
/*
 * MikhMon Agent - Complete Database Installer
 * One-script installation for fresh hosting deployment
 * Includes all fixes and optimizations
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
    <title>MikhMon Agent - Complete Database Installation</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        .step { margin: 10px 0; padding: 10px; border-left: 4px solid #007bff; background: #f8f9fa; }
        .progress { background: #e9ecef; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .progress-bar { background: #007bff; height: 20px; transition: width 0.3s; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 MikhMon Agent - Complete Database Installation</h1>
        <p>This installer will set up the complete database with all fixes included.</p>
        
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
    
    updateProgress(10, "Creating database structure...");
    
    // Create all tables with correct structure
    logStep("🔧 Creating database tables...", 'info');
    
    // 1. Agents table
    $conn->exec("CREATE TABLE IF NOT EXISTS agents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_code VARCHAR(20) UNIQUE NOT NULL,
        agent_name VARCHAR(100) NOT NULL,
        contact_person VARCHAR(100),
        phone VARCHAR(20),
        email VARCHAR(100),
        address TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        commission_rate DECIMAL(5,2) DEFAULT 0,
        balance DECIMAL(15,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    updateProgress(15, "Creating agents table...");
    logStep("✅ Agents table created", 'success');
    
    // 2. Agent Settings
    $conn->exec("CREATE TABLE IF NOT EXISTS agent_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id INT NOT NULL,
        setting_key VARCHAR(100) NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
        price DECIMAL(10,2) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
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
        transaction_type ENUM('topup', 'commission', 'withdrawal', 'adjustment') NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        balance_before DECIMAL(15,2) NOT NULL,
        balance_after DECIMAL(15,2) NOT NULL,
        description TEXT,
        reference_id VARCHAR(100),
        status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
    
    // 6. Agent Profile Pricing
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
    
    // 7. Public Sales (with ALL required columns)
    $conn->exec("CREATE TABLE IF NOT EXISTS public_sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id VARCHAR(100) UNIQUE NOT NULL,
        payment_reference VARCHAR(100),
        agent_id INT NOT NULL,
        profile_id INT NOT NULL,
        customer_name VARCHAR(100) NOT NULL,
        customer_phone VARCHAR(20) NOT NULL,
        customer_email VARCHAR(100),
        profile_name VARCHAR(100) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        admin_fee DECIMAL(10,2) DEFAULT 0,
        total_amount DECIMAL(10,2) NOT NULL,
        gateway_name VARCHAR(50) NOT NULL,
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
    
    updateProgress(45, "Creating public sales table...");
    logStep("✅ Public sales table created with all required columns", 'success');
    
    // 8. Payment Methods (with ALL required columns)
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
    logStep("✅ Payment methods table created with dual compatibility", 'success');
    
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
    
    // Insert default payment methods
    updateProgress(65, "Inserting default payment methods...");
    logStep("💳 Inserting Tripay payment methods...", 'info');
    
    // Clear and insert payment methods
    $conn->exec("DELETE FROM payment_methods");
    
    $tripayMethods = [
        // [gateway_name, method_code, method_name, method_type, name, type, display_name, icon, admin_fee_type, admin_fee_value, min_amount, max_amount, is_active, sort_order]
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
    
    updateProgress(70, "Loading existing data...");
    
    // Load data from data_insert.php if exists
    if (file_exists('data_insert.php')) {
        logStep("📊 Loading existing data from data_insert.php...", 'info');
        
        // Include and execute data_insert.php
        ob_start();
        include('data_insert.php');
        $data_output = ob_get_clean();
        
        logStep("✅ Existing data loaded successfully", 'success');
    } else {
        logStep("ℹ️ No data_insert.php found, creating sample data...", 'warning');
        
        // Insert sample agent
        $conn->exec("INSERT IGNORE INTO agents (agent_code, agent_name, contact_person, phone, email, status) 
                    VALUES ('DEMO', 'Demo Agent', 'Demo Person', '08123456789', 'demo@example.com', 'active')");
        
        // Get agent ID
        $stmt = $conn->query("SELECT id FROM agents WHERE agent_code = 'DEMO'");
        $agent_id = $stmt->fetchColumn();
        
        if ($agent_id) {
            // Insert sample pricing
            $samplePricing = [
                ['1 Jam', '1 Hour WiFi Access', 5000, 0, 1, 0, 'fa-clock-o', 'blue', 1],
                ['3 Jam', '3 Hours WiFi Access', 10000, 0, 1, 0, 'fa-clock-o', 'green', 2],
                ['1 Hari', '1 Day WiFi Access', 15000, 20000, 1, 1, 'fa-calendar', 'orange', 3],
                ['3 Hari', '3 Days WiFi Access', 35000, 45000, 1, 0, 'fa-calendar', 'red', 4],
                ['1 Minggu', '1 Week WiFi Access', 50000, 70000, 1, 0, 'fa-calendar-check-o', 'purple', 5]
            ];
            
            $pricingSQL = "INSERT IGNORE INTO agent_profile_pricing 
                (agent_id, profile_name, display_name, price, original_price, is_active, is_featured, icon, color, sort_order) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($pricingSQL);
            
            foreach ($samplePricing as $pricing) {
                $stmt->execute(array_merge([$agent_id], $pricing));
            }
            
            logStep("✅ Sample agent and pricing created", 'success');
        }
    }
    
    updateProgress(80, "Inserting default configurations...");
    
    // Insert default payment gateway config
    $conn->exec("INSERT IGNORE INTO payment_gateway_config 
        (gateway_name, is_active, is_sandbox, api_key, api_secret, merchant_code) 
        VALUES ('tripay', 0, 1, 'your-api-key', 'your-private-key', 'your-merchant-code')");
    
    // Insert default site pages
    $conn->exec("INSERT IGNORE INTO site_pages (page_slug, page_title, page_content) VALUES 
        ('tos', 'Syarat dan Ketentuan', '<h3>Syarat dan Ketentuan</h3><p>Silakan isi dengan syarat dan ketentuan Anda.</p>'),
        ('privacy', 'Kebijakan Privasi', '<h3>Kebijakan Privasi</h3><p>Silakan isi dengan kebijakan privasi Anda.</p>'),
        ('faq', 'FAQ', '<h3>FAQ</h3><p>Silakan isi dengan pertanyaan yang sering diajukan.</p>')");
    
    updateProgress(90, "Verifying installation...");
    
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
    
    updateProgress(95, "Testing application compatibility...");
    
    // Test payment methods query
    $stmt = $conn->query("SELECT * FROM payment_methods WHERE gateway_name = 'tripay' AND is_active = 1");
    $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    logStep("✅ Payment methods test: " . count($methods) . " methods available", 'success');
    
    // Test grouping
    $grouped = [];
    foreach ($methods as $method) {
        $grouped[$method['method_type']][] = $method;
    }
    logStep("✅ Grouping test: " . count($grouped) . " categories found", 'success');
    
    updateProgress(100, "Installation complete!");
    
    logStep("🎉 Installation completed successfully!", 'success');
    
} catch (Exception $e) {
    logStep("❌ Error: " . $e->getMessage(), 'error');
    updateProgress(0, "Installation failed!");
}

?>

        </div>
        
        <div class="step success" style="margin-top: 20px;">
            <h3>🎉 Installation Complete!</h3>
            <p><strong>Your MikhMon Agent system is now ready to use.</strong></p>
            
            <h4>📋 What was installed:</h4>
            <ul>
                <li>✅ Complete database structure (10 tables)</li>
                <li>✅ 12 Tripay payment methods with proper fees</li>
                <li>✅ Sample agent and pricing (if no existing data)</li>
                <li>✅ Default configurations</li>
                <li>✅ All compatibility fixes included</li>
            </ul>
            
            <h4>🔗 Test Your Installation:</h4>
            <ul>
                <li><a href="admin.php" target="_blank">🔧 Admin Panel</a> - Configure your system</li>
                <li><a href="public/index.php?agent=DEMO" target="_blank">🛒 Public Sales</a> - Test voucher sales</li>
                <li><a href="agent/" target="_blank">👤 Agent Panel</a> - Agent dashboard</li>
            </ul>
            
            <h4>⚙️ Next Steps:</h4>
            <ol>
                <li><strong>Configure Tripay API</strong> in admin panel (API Key, Private Key, Merchant Code)</li>
                <li><strong>Create your agents</strong> and set pricing</li>
                <li><strong>Test public voucher flow</strong> end-to-end</li>
                <li><strong>Delete this installer</strong> for security: <code>rm install_database_complete.php</code></li>
            </ol>
            
            <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;">
                <strong>⚠️ Security:</strong> Please delete this installer file after successful installation!
            </div>
        </div>
    </div>
    
    <script>
        // Auto-scroll to bottom
        window.scrollTo(0, document.body.scrollHeight);
    </script>
</body>
</html>
