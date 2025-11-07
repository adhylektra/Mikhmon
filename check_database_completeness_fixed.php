<?php
/*
 * Database Completeness Checker - Fixed Version
 * Compatible with actual table structures
 */

// Security check
$security_key = $_GET['key'] ?? '';
if ($security_key !== 'mikhmon-check-2024') {
    die('Access denied. Add ?key=mikhmon-check-2024 to URL');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Completeness Check - Fixed</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        .section { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .missing { background-color: #ffebee; }
        .present { background-color: #e8f5e8; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Database Completeness Check - Fixed Version</h1>
        <p>Checking all data with correct column names and structures.</p>

<?php

try {
    // Database connection
    if (file_exists('include/db_config.php')) {
        include_once('include/db_config.php');
        $conn = getDBConnection();
        echo "<div class='section success'>✅ Database connection successful!</div>";
    } else {
        throw new Exception("Database config file not found");
    }

    echo "<h2>📊 Complete Database Analysis</h2>";

    // Function to get table structure
    function getTableStructure($conn, $table) {
        try {
            $stmt = $conn->query("DESCRIBE $table");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }

    // 1. Agents Table
    echo "<div class='section'>";
    echo "<h3>1. Agents Table</h3>";
    
    $stmt = $conn->query("SELECT COUNT(*) FROM agents");
    $agentCount = $stmt->fetchColumn();
    echo "<p><strong>Total agents:</strong> $agentCount</p>";
    
    if ($agentCount > 0) {
        $stmt = $conn->query("SELECT agent_code, agent_name, status, created_at FROM agents ORDER BY id");
        $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>Agent Code</th><th>Agent Name</th><th>Status</th><th>Created At</th></tr>";
        foreach ($agents as $agent) {
            echo "<tr class='present'>";
            echo "<td>" . htmlspecialchars($agent['agent_code']) . "</td>";
            echo "<td>" . htmlspecialchars($agent['agent_name']) . "</td>";
            echo "<td>" . htmlspecialchars($agent['status']) . "</td>";
            echo "<td>" . htmlspecialchars($agent['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";

    // 2. Agent Settings
    echo "<div class='section'>";
    echo "<h3>2. Agent Settings</h3>";
    
    $stmt = $conn->query("SELECT COUNT(*) FROM agent_settings");
    $settingsCount = $stmt->fetchColumn();
    echo "<p><strong>Total settings:</strong> $settingsCount</p>";
    
    if ($settingsCount > 0) {
        $stmt = $conn->query("SELECT agent_id, setting_key, LEFT(setting_value, 50) as setting_value_preview FROM agent_settings ORDER BY agent_id, setting_key LIMIT 20");
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>Agent ID</th><th>Setting Key</th><th>Value Preview</th></tr>";
        foreach ($settings as $setting) {
            echo "<tr class='present'>";
            echo "<td>" . htmlspecialchars($setting['agent_id']) . "</td>";
            echo "<td>" . htmlspecialchars($setting['setting_key']) . "</td>";
            echo "<td>" . htmlspecialchars($setting['setting_value_preview']) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
        if ($settingsCount > 20) {
            echo "<p><em>Showing first 20 of $settingsCount settings</em></p>";
        }
    }
    echo "</div>";

    // 3. Agent Prices - Fixed to use correct column names
    echo "<div class='section'>";
    echo "<h3>3. Agent Prices</h3>";
    
    // First check table structure
    $structure = getTableStructure($conn, 'agent_prices');
    if ($structure) {
        echo "<p><strong>Table structure:</strong> ";
        $columns = array_column($structure, 'Field');
        echo implode(', ', $columns) . "</p>";
    }
    
    $stmt = $conn->query("SELECT COUNT(*) FROM agent_prices");
    $pricesCount = $stmt->fetchColumn();
    echo "<p><strong>Total prices:</strong> $pricesCount</p>";
    
    if ($pricesCount > 0) {
        // Use actual column names from the table
        $stmt = $conn->query("SELECT agent_id, profile_name, buy_price, sell_price FROM agent_prices ORDER BY agent_id, profile_name");
        $prices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>Agent ID</th><th>Profile Name</th><th>Buy Price</th><th>Sell Price</th></tr>";
        foreach ($prices as $price) {
            echo "<tr class='present'>";
            echo "<td>" . htmlspecialchars($price['agent_id']) . "</td>";
            echo "<td>" . htmlspecialchars($price['profile_name']) . "</td>";
            echo "<td>Rp " . number_format($price['buy_price']) . "</td>";
            echo "<td>Rp " . number_format($price['sell_price']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";

    // 4. Agent Transactions
    echo "<div class='section'>";
    echo "<h3>4. Agent Transactions</h3>";
    
    $stmt = $conn->query("SELECT COUNT(*) FROM agent_transactions");
    $transCount = $stmt->fetchColumn();
    echo "<p><strong>Total transactions:</strong> $transCount</p>";
    
    if ($transCount > 0) {
        $stmt = $conn->query("SELECT agent_id, transaction_type, amount, balance_after, created_at FROM agent_transactions ORDER BY created_at DESC LIMIT 10");
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>Agent ID</th><th>Type</th><th>Amount</th><th>Balance After</th><th>Date</th></tr>";
        foreach ($transactions as $trans) {
            echo "<tr class='present'>";
            echo "<td>" . htmlspecialchars($trans['agent_id']) . "</td>";
            echo "<td>" . htmlspecialchars($trans['transaction_type']) . "</td>";
            echo "<td>Rp " . number_format($trans['amount']) . "</td>";
            echo "<td>Rp " . number_format($trans['balance_after']) . "</td>";
            echo "<td>" . htmlspecialchars($trans['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        if ($transCount > 10) {
            echo "<p><em>Showing latest 10 of $transCount transactions</em></p>";
        }
    }
    echo "</div>";

    // 5. Payment Gateway Config
    echo "<div class='section'>";
    echo "<h3>5. Payment Gateway Config</h3>";
    
    $stmt = $conn->query("SELECT COUNT(*) FROM payment_gateway_config");
    $gatewayCount = $stmt->fetchColumn();
    echo "<p><strong>Total gateways:</strong> $gatewayCount</p>";
    
    if ($gatewayCount > 0) {
        $stmt = $conn->query("SELECT gateway_name, is_active, is_sandbox, LEFT(api_key, 10) as api_key_preview FROM payment_gateway_config");
        $gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>Gateway</th><th>Active</th><th>Sandbox</th><th>API Key Preview</th></tr>";
        foreach ($gateways as $gateway) {
            echo "<tr class='present'>";
            echo "<td>" . htmlspecialchars($gateway['gateway_name']) . "</td>";
            echo "<td>" . ($gateway['is_active'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . ($gateway['is_sandbox'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . htmlspecialchars($gateway['api_key_preview']) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>⚠️ No payment gateway config found</p>";
    }
    echo "</div>";

    // 6. Agent Profile Pricing (for public sales)
    echo "<div class='section'>";
    echo "<h3>6. Agent Profile Pricing (Public Sales)</h3>";
    
    $stmt = $conn->query("SELECT COUNT(*) FROM agent_profile_pricing");
    $profileCount = $stmt->fetchColumn();
    echo "<p><strong>Total profile pricing:</strong> $profileCount</p>";
    
    if ($profileCount > 0) {
        $stmt = $conn->query("SELECT agent_id, profile_name, display_name, price, is_active, is_featured FROM agent_profile_pricing ORDER BY agent_id, sort_order");
        $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>Agent ID</th><th>Profile</th><th>Display Name</th><th>Price</th><th>Active</th><th>Featured</th></tr>";
        foreach ($profiles as $profile) {
            echo "<tr class='present'>";
            echo "<td>" . htmlspecialchars($profile['agent_id']) . "</td>";
            echo "<td>" . htmlspecialchars($profile['profile_name']) . "</td>";
            echo "<td>" . htmlspecialchars($profile['display_name']) . "</td>";
            echo "<td>Rp " . number_format($profile['price']) . "</td>";
            echo "<td>" . ($profile['is_active'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . ($profile['is_featured'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ No agent profile pricing found - public sales won't work!</p>";
    }
    echo "</div>";

    // 7. Payment Methods
    echo "<div class='section'>";
    echo "<h3>7. Payment Methods</h3>";
    
    $stmt = $conn->query("SELECT COUNT(*) FROM payment_methods");
    $methodsCount = $stmt->fetchColumn();
    echo "<p><strong>Total payment methods:</strong> $methodsCount</p>";
    
    if ($methodsCount > 0) {
        $stmt = $conn->query("SELECT gateway_name, method_code, method_name, method_type, admin_fee_type, admin_fee_value, is_active FROM payment_methods ORDER BY sort_order");
        $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>Gateway</th><th>Code</th><th>Name</th><th>Type</th><th>Fee Type</th><th>Fee Value</th><th>Active</th></tr>";
        foreach ($methods as $method) {
            echo "<tr class='present'>";
            echo "<td>" . htmlspecialchars($method['gateway_name']) . "</td>";
            echo "<td>" . htmlspecialchars($method['method_code']) . "</td>";
            echo "<td>" . htmlspecialchars($method['method_name']) . "</td>";
            echo "<td>" . htmlspecialchars($method['method_type']) . "</td>";
            echo "<td>" . htmlspecialchars($method['admin_fee_type']) . "</td>";
            echo "<td>" . ($method['admin_fee_type'] == 'percentage' ? $method['admin_fee_value'] . '%' : 'Rp ' . number_format($method['admin_fee_value'])) . "</td>";
            echo "<td>" . ($method['is_active'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ No payment methods found - this will break public voucher sales!</p>";
    }
    echo "</div>";

    // 8. Public Sales
    echo "<div class='section'>";
    echo "<h3>8. Public Sales Transactions</h3>";
    
    $stmt = $conn->query("SELECT COUNT(*) FROM public_sales");
    $publicSalesCount = $stmt->fetchColumn();
    echo "<p><strong>Total public sales:</strong> $publicSalesCount</p>";
    
    if ($publicSalesCount > 0) {
        $stmt = $conn->query("SELECT transaction_id, customer_name, profile_name, total_amount, status, created_at FROM public_sales ORDER BY created_at DESC LIMIT 10");
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>Transaction ID</th><th>Customer</th><th>Package</th><th>Amount</th><th>Status</th><th>Date</th></tr>";
        foreach ($sales as $sale) {
            echo "<tr class='present'>";
            echo "<td>" . htmlspecialchars($sale['transaction_id']) . "</td>";
            echo "<td>" . htmlspecialchars($sale['customer_name']) . "</td>";
            echo "<td>" . htmlspecialchars($sale['profile_name']) . "</td>";
            echo "<td>Rp " . number_format($sale['total_amount']) . "</td>";
            echo "<td>" . htmlspecialchars($sale['status']) . "</td>";
            echo "<td>" . htmlspecialchars($sale['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>ℹ️ No public sales found (normal for fresh installation)</p>";
    }
    echo "</div>";

    // Summary
    echo "<h2>📋 Complete Summary</h2>";
    
    echo "<div class='section'>";
    echo "<h3>📊 Data Summary</h3>";
    echo "<table>";
    echo "<tr><th>Table</th><th>Records</th><th>Status</th><th>Critical</th></tr>";
    echo "<tr><td>Agents</td><td>$agentCount</td><td>" . ($agentCount > 0 ? "<span class='success'>✅ OK</span>" : "<span class='error'>❌ Empty</span>") . "</td><td>" . ($agentCount > 0 ? "No" : "Yes") . "</td></tr>";
    echo "<tr><td>Agent Settings</td><td>$settingsCount</td><td>" . ($settingsCount > 0 ? "<span class='success'>✅ OK</span>" : "<span class='warning'>⚠️ Empty</span>") . "</td><td>No</td></tr>";
    echo "<tr><td>Agent Prices</td><td>$pricesCount</td><td>" . ($pricesCount > 0 ? "<span class='success'>✅ OK</span>" : "<span class='warning'>⚠️ Empty</span>") . "</td><td>No</td></tr>";
    echo "<tr><td>Agent Transactions</td><td>$transCount</td><td><span class='info'>ℹ️ Optional</span></td><td>No</td></tr>";
    echo "<tr><td>Payment Gateway Config</td><td>$gatewayCount</td><td>" . ($gatewayCount > 0 ? "<span class='success'>✅ OK</span>" : "<span class='error'>❌ Missing</span>") . "</td><td>" . ($gatewayCount > 0 ? "No" : "Yes") . "</td></tr>";
    echo "<tr><td>Agent Profile Pricing</td><td>$profileCount</td><td>" . ($profileCount > 0 ? "<span class='success'>✅ OK</span>" : "<span class='error'>❌ Missing</span>") . "</td><td>" . ($profileCount > 0 ? "No" : "Yes") . "</td></tr>";
    echo "<tr><td>Public Sales</td><td>$publicSalesCount</td><td><span class='info'>ℹ️ Optional</span></td><td>No</td></tr>";
    echo "<tr><td>Payment Methods</td><td>$methodsCount</td><td>" . ($methodsCount >= 10 ? "<span class='success'>✅ OK</span>" : "<span class='error'>❌ Insufficient</span>") . "</td><td>" . ($methodsCount >= 10 ? "No" : "Yes") . "</td></tr>";
    echo "</table>";
    echo "</div>";

    // Critical Issues Check
    $criticalIssues = [];
    if ($agentCount == 0) $criticalIssues[] = "No agents found";
    if ($gatewayCount == 0) $criticalIssues[] = "No payment gateway configured";
    if ($profileCount == 0) $criticalIssues[] = "No agent profile pricing (public sales won't work)";
    if ($methodsCount < 10) $criticalIssues[] = "Insufficient payment methods ($methodsCount found, need at least 10)";

    if (!empty($criticalIssues)) {
        echo "<div class='section error'>";
        echo "<h3>❌ Critical Issues Found</h3>";
        echo "<ul>";
        foreach ($criticalIssues as $issue) {
            echo "<li>$issue</li>";
        }
        echo "</ul>";
        echo "<p><strong>Recommendation:</strong> Run the complete installer to fix these issues:</p>";
        echo "<pre>https://yourdomain.com/install_database_complete.php?key=mikhmon-install-2024</pre>";
        echo "</div>";
    } else {
        echo "<div class='section success'>";
        echo "<h3>✅ Database Structure Looks Good!</h3>";
        echo "<p>All critical data structures are present. Check individual table data above for completeness.</p>";
        echo "</div>";
    }

    // Test Public Voucher Compatibility
    echo "<div class='section'>";
    echo "<h3>🧪 Public Voucher Compatibility Test</h3>";
    
    if ($agentCount > 0 && $profileCount > 0 && $methodsCount >= 10) {
        // Get first agent for testing
        $stmt = $conn->query("SELECT agent_code FROM agents WHERE status = 'active' ORDER BY id LIMIT 1");
        $testAgent = $stmt->fetchColumn();
        
        echo "<p class='success'>✅ Public voucher system should work!</p>";
        echo "<p><strong>Test URL:</strong> <a href='public/index.php?agent=$testAgent' target='_blank'>public/index.php?agent=$testAgent</a></p>";
        
        // Check if payment gateway is configured
        if ($gatewayCount > 0) {
            $stmt = $conn->query("SELECT is_active FROM payment_gateway_config LIMIT 1");
            $isActive = $stmt->fetchColumn();
            if ($isActive) {
                echo "<p class='success'>✅ Payment gateway is active - payments should work</p>";
            } else {
                echo "<p class='warning'>⚠️ Payment gateway exists but not active - configure in admin panel</p>";
            }
        }
        
    } else {
        echo "<p class='error'>❌ Public voucher system will not work due to missing data</p>";
        echo "<ul>";
        if ($agentCount == 0) echo "<li>No agents found</li>";
        if ($profileCount == 0) echo "<li>No agent profile pricing</li>";
        if ($methodsCount < 10) echo "<li>Insufficient payment methods</li>";
        echo "</ul>";
    }
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='section error'>";
    echo "<h3>❌ Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

?>

        <div class="section">
            <h3>🔧 Next Steps</h3>
            <p><strong>If you found missing critical data:</strong></p>
            <ol>
                <li>Run the complete installer: <code>install_database_complete.php?key=mikhmon-install-2024</code></li>
                <li>Configure payment gateway settings in admin panel</li>
                <li>Test public voucher functionality</li>
            </ol>
            
            <p><strong>If everything looks good:</strong></p>
            <ol>
                <li>Configure Tripay API credentials in admin panel</li>
                <li>Test public voucher flow end-to-end</li>
                <li>Delete checker files for security</li>
            </ol>
        </div>
    </div>
</body>
</html>
