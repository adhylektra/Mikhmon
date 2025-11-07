<?php
/**
 * Database Installation Script for Hosting
 * MikhMon Agent System - Complete Database Setup
 */

// Security check
$install_key = isset($_GET['key']) ? $_GET['key'] : '';
$expected_key = 'mikhmon-install-2024'; // Change this for security

if ($install_key !== $expected_key) {
    die('Access denied. Please provide correct installation key.');
}

// Database configuration - EDIT THESE VALUES
$db_host = 'localhost';
$db_username = 'your_db_username';
$db_password = 'your_db_password';
$db_name = 'your_db_name';

// Check if config file exists
if (file_exists('./include/config.php')) {
    include './include/config.php';
    // Try to get database config from existing config
    if (isset($host)) $db_host = $host;
    if (isset($user)) $db_username = $user;
    if (isset($pass)) $db_password = $pass;
    if (isset($dbname)) $db_name = $dbname;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MikhMon Agent - Database Installation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #007bff; color: white; padding: 15px; margin: -20px -20px 20px -20px; border-radius: 10px 10px 0 0; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0056b3; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .progress { background: #e9ecef; border-radius: 5px; margin: 10px 0; }
        .progress-bar { background: #28a745; height: 20px; border-radius: 5px; transition: width 0.3s; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 MikhMon Agent System - Database Installation</h1>
            <p>Complete database setup for hosting environment</p>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db_host = $_POST['db_host'];
            $db_username = $_POST['db_username'];
            $db_password = $_POST['db_password'];
            $db_name = $_POST['db_name'];
            
            echo "<h2>🔧 Installation Progress</h2>";
            
            try {
                // Test database connection
                echo "<div class='info'>📡 Testing database connection...</div>";
                $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_username, $db_password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                echo "<div class='success'>✅ Database connection successful!</div>";
                
                // Create database if not exists
                echo "<div class='info'>🗄️ Creating database '$db_name' if not exists...</div>";
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                echo "<div class='success'>✅ Database '$db_name' ready!</div>";
                
                // Connect to specific database
                $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_username, $db_password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Read and execute SQL file
                echo "<div class='info'>📋 Reading installation script...</div>";
                $sql_file = './database/complete_installation.sql';
                
                if (!file_exists($sql_file)) {
                    throw new Exception("SQL file not found: $sql_file");
                }
                
                $sql_content = file_get_contents($sql_file);
                echo "<div class='success'>✅ Installation script loaded!</div>";
                
                // Split SQL into individual statements
                echo "<div class='info'>⚙️ Executing database installation...</div>";
                $statements = array_filter(array_map('trim', explode(';', $sql_content)));
                
                $total_statements = count($statements);
                $executed = 0;
                
                foreach ($statements as $statement) {
                    if (!empty($statement) && !preg_match('/^--/', $statement)) {
                        try {
                            $pdo->exec($statement);
                            $executed++;
                            $progress = ($executed / $total_statements) * 100;
                            echo "<div class='progress'><div class='progress-bar' style='width: {$progress}%'></div></div>";
                            echo "<div style='margin: 5px 0;'>Executed: $executed/$total_statements statements</div>";
                            flush();
                        } catch (PDOException $e) {
                            // Ignore errors for statements that might already exist
                            if (strpos($e->getMessage(), 'already exists') === false && 
                                strpos($e->getMessage(), 'Duplicate') === false) {
                                echo "<div class='warning'>⚠️ Warning: " . $e->getMessage() . "</div>";
                            }
                        }
                    }
                }
                
                // Additional fix for missing tables that commonly fail
                echo "<div class='info'>🔧 Ensuring critical tables exist...</div>";
                
                // Fix agent_settings table structure if needed
                try {
                    // Check if setting_type column exists
                    $stmt = $pdo->query("DESCRIBE agent_settings");
                    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $has_setting_type = false;
                    
                    foreach ($columns as $column) {
                        if ($column['Field'] === 'setting_type') {
                            $has_setting_type = true;
                            break;
                        }
                    }
                    
                    if (!$has_setting_type) {
                        echo "<div class='info'>🔧 Adding missing setting_type column...</div>";
                        $pdo->exec("ALTER TABLE agent_settings ADD COLUMN setting_type VARCHAR(20) DEFAULT 'string' AFTER setting_value");
                        $pdo->exec("ALTER TABLE agent_settings ADD COLUMN updated_by VARCHAR(50) DEFAULT NULL AFTER updated_at");
                        echo "<div class='success'>✅ agent_settings structure fixed!</div>";
                    }
                } catch (PDOException $e) {
                    echo "<div class='warning'>⚠️ agent_settings structure: " . $e->getMessage() . "</div>";
                }
                
                // Create payment_gateway_config table if missing
                try {
                    $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `payment_gateway_config` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `gateway_name` varchar(50) NOT NULL,
                      `is_active` tinyint(1) DEFAULT 0,
                      `is_sandbox` tinyint(1) DEFAULT 1,
                      `api_key` varchar(255) DEFAULT NULL,
                      `api_secret` varchar(255) DEFAULT NULL,
                      `merchant_code` varchar(100) DEFAULT NULL,
                      `callback_token` varchar(255) DEFAULT NULL,
                      `config_json` text,
                      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                      `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `unique_gateway` (`gateway_name`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    ");
                    echo "<div class='success'>✅ payment_gateway_config table ensured!</div>";
                    
                    // Insert default config
                    $pdo->exec("
                    INSERT IGNORE INTO `payment_gateway_config` (`gateway_name`, `is_active`, `is_sandbox`, `api_key`, `merchant_code`) VALUES
                    ('tripay', 0, 1, 'your-tripay-api-key', 'your-merchant-code');
                    ");
                } catch (PDOException $e) {
                    echo "<div class='warning'>⚠️ payment_gateway_config: " . $e->getMessage() . "</div>";
                }
                
                // Create voucher_settings table if missing
                try {
                    $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `voucher_settings` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `profile_name` varchar(100) NOT NULL,
                      `display_name` varchar(100) NOT NULL,
                      `description` text,
                      `price` decimal(10,2) NOT NULL,
                      `validity` varchar(50) DEFAULT NULL,
                      `data_limit` varchar(50) DEFAULT NULL,
                      `speed_limit` varchar(50) DEFAULT NULL,
                      `is_active` tinyint(1) DEFAULT 1,
                      `sort_order` int(11) DEFAULT 0,
                      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                      `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `unique_profile_name` (`profile_name`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    ");
                    echo "<div class='success'>✅ voucher_settings table ensured!</div>";
                    
                    // Insert sample data
                    $pdo->exec("
                    INSERT IGNORE INTO `voucher_settings` (`profile_name`, `display_name`, `description`, `price`, `validity`, `data_limit`, `speed_limit`) VALUES
                    ('1H-1GB', '1 Jam - 1GB', 'Paket internet 1 jam dengan kuota 1GB', 5000.00, '1 Hour', '1GB', '10M/10M'),
                    ('1D-5GB', '1 Hari - 5GB', 'Paket internet 1 hari dengan kuota 5GB', 15000.00, '1 Day', '5GB', '20M/20M'),
                    ('3D-10GB', '3 Hari - 10GB', 'Paket internet 3 hari dengan kuota 10GB', 35000.00, '3 Days', '10GB', '30M/30M'),
                    ('7D-20GB', '1 Minggu - 20GB', 'Paket internet 1 minggu dengan kuota 20GB', 65000.00, '7 Days', '20GB', '50M/50M'),
                    ('30D-100GB', '1 Bulan - 100GB', 'Paket internet 1 bulan dengan kuota 100GB', 150000.00, '30 Days', '100GB', '100M/100M');
                    ");
                } catch (PDOException $e) {
                    echo "<div class='warning'>⚠️ voucher_settings: " . $e->getMessage() . "</div>";
                }
                
                // Ensure all payment methods exist
                try {
                    $pdo->exec("
                    INSERT IGNORE INTO `payment_methods` (`gateway`, `code`, `name`, `type`, `fee_flat`, `fee_percent`, `minimum_amount`, `maximum_amount`, `is_active`) VALUES
                    ('tripay', 'BRIVA', 'BRI Virtual Account', 'virtual_account', 4000, 0, 10000, 50000000, 1),
                    ('tripay', 'BNIVA', 'BNI Virtual Account', 'virtual_account', 4000, 0, 10000, 50000000, 1),
                    ('tripay', 'MANDIRIVA', 'Mandiri Virtual Account', 'virtual_account', 4000, 0, 10000, 50000000, 1),
                    ('tripay', 'PERMATAVA', 'Permata Virtual Account', 'virtual_account', 4000, 0, 10000, 50000000, 1),
                    ('tripay', 'QRIS', 'QRIS (Quick Response Code)', 'qris', 750, 0.7, 1500, 10000000, 1),
                    ('tripay', 'SHOPEEPAY', 'ShopeePay', 'ewallet', 0, 2.5, 10000, 10000000, 1),
                    ('tripay', 'DANA', 'DANA', 'ewallet', 0, 2.5, 10000, 10000000, 1),
                    ('tripay', 'OVO', 'OVO', 'ewallet', 0, 2.5, 10000, 10000000, 1),
                    ('tripay', 'GOPAY', 'GoPay', 'ewallet', 0, 2.5, 10000, 10000000, 1);
                    ");
                    echo "<div class='success'>✅ Payment methods ensured!</div>";
                } catch (PDOException $e) {
                    echo "<div class='warning'>⚠️ payment_methods: " . $e->getMessage() . "</div>";
                }
                
                echo "<div class='success'>✅ Database installation completed successfully!</div>";
                
                // Check for data_insert.php file and include it
                if (file_exists('./data_insert.php')) {
                    echo "<div class='info'>📊 Found data_insert.php - Loading your data...</div>";
                    try {
                        include './data_insert.php';
                        echo "<div class='success'>✅ Your data has been loaded successfully!</div>";
                    } catch (Exception $e) {
                        echo "<div class='warning'>⚠️ Error loading data: " . $e->getMessage() . "</div>";
                    }
                } else {
                    echo "<div class='info'>ℹ️ No data_insert.php found - Database created with default data only</div>";
                    echo "<div class='warning'>";
                    echo "<h4>💡 Want to import your local data?</h4>";
                    echo "<ol>";
                    echo "<li>Run <code>generate_data_insert.php</code> on your local XAMPP</li>";
                    echo "<li>Upload the generated <code>data_insert.php</code> file</li>";
                    echo "<li>Run this installer again to load your data</li>";
                    echo "</ol>";
                    echo "</div>";
                }
                
                // Verify installation
                echo "<div class='info'>🔍 Verifying installation...</div>";
                $tables = [
                    'agents', 'agent_settings', 'agent_prices', 'agent_transactions',
                    'payment_gateway_config', 'agent_profile_pricing', 'public_sales',
                    'payment_methods', 'voucher_settings'
                ];
                
                $verification_results = [];
                foreach ($tables as $table) {
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                        $count = $stmt->fetchColumn();
                        $verification_results[] = "✅ Table '$table': $count records";
                    } catch (PDOException $e) {
                        $verification_results[] = "❌ Table '$table': ERROR - " . $e->getMessage();
                    }
                }
                
                echo "<div class='success'>";
                echo "<h3>📊 Installation Verification:</h3>";
                echo "<pre>" . implode("\n", $verification_results) . "</pre>";
                echo "</div>";
                
                // Show next steps
                echo "<div class='info'>";
                echo "<h3>🎉 Installation Complete! Next Steps:</h3>";
                echo "<ol>";
                echo "<li>Delete this installation file for security: <code>install_database.php</code></li>";
                echo "<li>Configure your payment gateway settings in admin panel</li>";
                echo "<li>Create your first agent account</li>";
                echo "<li>Test the public voucher sales page</li>";
                echo "</ol>";
                echo "</div>";
                
                echo "<div class='success'>";
                echo "<p><strong>🔗 Access Links:</strong></p>";
                echo "<ul>";
                echo "<li>Admin Panel: <a href='./admin.php' target='_blank'>./admin.php</a></li>";
                echo "<li>Agent Panel: <a href='./agent/' target='_blank'>./agent/</a></li>";
                echo "<li>Public Sales: <a href='./public/?agent=AGENT_CODE' target='_blank'>./public/?agent=AGENT_CODE</a></li>";
                echo "</ul>";
                echo "</div>";
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Installation failed: " . $e->getMessage() . "</div>";
                echo "<div class='warning'>Please check your database credentials and try again.</div>";
            }
        } else {
        ?>
        
        <div class="warning">
            <h3>⚠️ Important Notes:</h3>
            <ul>
                <li>This script will create all necessary database tables</li>
                <li>Existing tables will not be dropped (safe installation)</li>
                <li>Make sure your database credentials are correct</li>
                <li>Delete this file after installation for security</li>
            </ul>
        </div>

        <form method="POST">
            <h2>🔧 Database Configuration</h2>
            
            <div class="form-group">
                <label for="db_host">Database Host:</label>
                <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($db_host); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_username">Database Username:</label>
                <input type="text" id="db_username" name="db_username" value="<?php echo htmlspecialchars($db_username); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_password">Database Password:</label>
                <input type="password" id="db_password" name="db_password" value="<?php echo htmlspecialchars($db_password); ?>">
            </div>
            
            <div class="form-group">
                <label for="db_name">Database Name:</label>
                <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($db_name); ?>" required>
            </div>
            
            <button type="submit" class="btn">🚀 Install Database</button>
        </form>
        
        <?php } ?>
        
        <div class="info" style="margin-top: 30px;">
            <h3>📋 Installation URL:</h3>
            <p>Access this installer at: <code><?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?></code></p>
            <p><small>Add <code>?key=mikhmon-install-2024</code> to the URL for security</small></p>
        </div>
    </div>
</body>
</html>
