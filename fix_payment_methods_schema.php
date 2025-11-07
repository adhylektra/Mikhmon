<?php
/*
 * Fix Payment Methods Schema
 * Align existing table structure with application requirements
 */

// Security check
$security_key = $_GET['key'] ?? '';
if ($security_key !== 'mikhmon-fix-2024') {
    die('Access denied. Add ?key=mikhmon-fix-2024 to URL');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 Payment Methods Schema Fix</h2>";

try {
    // Include database config
    if (file_exists('include/db_config.php')) {
        include_once('include/db_config.php');
        $conn = getDBConnection();
        echo "✅ Database connection successful!<br><br>";
    } else {
        throw new Exception("Database config file not found");
    }

    echo "<h3>📋 Current Payment Methods Table Structure</h3>";
    
    // Get current structure
    $stmt = $conn->query("DESCRIBE payment_methods");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $existing_columns = [];
    foreach ($columns as $col) {
        $existing_columns[] = $col['Field'];
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
    }
    
    echo "<br><h3>🔄 Schema Transformation</h3>";
    
    // Check what columns we need to add/modify
    $required_columns = [
        'method_code' => 'VARCHAR(50) NOT NULL',
        'method_name' => 'VARCHAR(100) NOT NULL', 
        'method_type' => 'VARCHAR(20) NOT NULL DEFAULT "ewallet"'
    ];
    
    // Add missing columns
    foreach ($required_columns as $column => $definition) {
        if (!in_array($column, $existing_columns)) {
            try {
                $conn->exec("ALTER TABLE payment_methods ADD COLUMN $column $definition");
                echo "✅ Added column: $column<br>";
            } catch (Exception $e) {
                echo "⚠️ Error adding $column: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "ℹ️ Column $column already exists<br>";
        }
    }
    
    echo "<br><h3>📊 Data Migration</h3>";
    
    // Clear existing data and insert proper payment methods
    echo "Clearing existing payment methods data...<br>";
    $conn->exec("DELETE FROM payment_methods");
    echo "✅ Cleared existing data<br>";
    
    // Insert proper Tripay payment methods
    echo "<br>Inserting Tripay payment methods...<br>";
    
    $tripayMethods = [
        // QRIS
        ['QRIS', 'QRIS (Semua Bank & E-Wallet)', 'qris', '', 'flat', 0, 10000, 5000000, 1, 1],
        
        // Virtual Account
        ['BRIVA', 'BRI Virtual Account', 'va', '', 'flat', 4000, 10000, 5000000, 1, 2],
        ['BNIVA', 'BNI Virtual Account', 'va', '', 'flat', 4000, 10000, 5000000, 1, 3],
        ['BCAVA', 'BCA Virtual Account', 'va', '', 'flat', 4000, 10000, 5000000, 1, 4],
        ['MANDIRIVA', 'Mandiri Virtual Account', 'va', '', 'flat', 4000, 10000, 5000000, 1, 5],
        ['PERMATAVA', 'Permata Virtual Account', 'va', '', 'flat', 4000, 10000, 5000000, 1, 6],
        
        // E-Wallet
        ['OVO', 'OVO', 'ewallet', '', 'percentage', 2.5, 10000, 2000000, 1, 7],
        ['DANA', 'DANA', 'ewallet', '', 'percentage', 2.5, 10000, 2000000, 1, 8],
        ['SHOPEEPAY', 'ShopeePay', 'ewallet', '', 'percentage', 2.5, 10000, 2000000, 1, 9],
        ['LINKAJA', 'LinkAja', 'ewallet', '', 'percentage', 2.5, 10000, 2000000, 1, 10],
        
        // Retail
        ['ALFAMART', 'Alfamart', 'retail', '', 'flat', 5000, 10000, 5000000, 1, 11],
        ['INDOMARET', 'Indomaret', 'retail', '', 'flat', 5000, 10000, 5000000, 1, 12]
    ];
    
    $insertSQL = "INSERT INTO payment_methods 
        (gateway_name, method_code, method_name, method_type, icon_url, admin_fee_type, admin_fee_value, min_amount, max_amount, is_active, sort_order) 
        VALUES ('tripay', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insertSQL);
    
    foreach ($tripayMethods as $method) {
        try {
            $stmt->execute($method);
            echo "✅ Added: " . $method[1] . "<br>";
        } catch (Exception $e) {
            echo "⚠️ Error adding " . $method[1] . ": " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<br><h3>🔍 Verification</h3>";
    
    // Verify the data
    $stmt = $conn->query("SELECT method_code, method_name, method_type, admin_fee_value, admin_fee_type FROM payment_methods ORDER BY sort_order");
    $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Payment methods in database:<br>";
    foreach ($methods as $method) {
        $fee = $method['admin_fee_type'] == 'percentage' ? $method['admin_fee_value'] . '%' : 'Rp ' . number_format($method['admin_fee_value']);
        echo "- " . $method['method_name'] . " (" . $method['method_code'] . ") - Fee: $fee<br>";
    }
    
    echo "<br><h3>🔧 Final Schema Check</h3>";
    
    // Check final structure
    $stmt = $conn->query("DESCRIBE payment_methods");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredCols = ['gateway_name', 'method_code', 'method_name', 'method_type'];
    $missingCols = [];
    
    $existingCols = array_column($finalColumns, 'Field');
    
    foreach ($requiredCols as $col) {
        if (in_array($col, $existingCols)) {
            echo "✅ Required column '$col': EXISTS<br>";
        } else {
            echo "❌ Required column '$col': MISSING<br>";
            $missingCols[] = $col;
        }
    }
    
    if (empty($missingCols)) {
        echo "<br><h3>🎉 Schema Fix Complete!</h3>";
        echo "<p><strong>✅ Payment methods table is now compatible with the application!</strong></p>";
        
        echo "<p><strong>Next Steps:</strong></p>";
        echo "<ul>";
        echo "<li>🔗 Test public voucher: <a href='public/index.php?agent=DEMO' target='_blank'>public/index.php?agent=DEMO</a></li>";
        echo "<li>💳 Test payment selection: Should work now!</li>";
        echo "<li>⚙️ Configure Tripay API keys in admin panel</li>";
        echo "<li>🗑️ Delete this fix file for security</li>";
        echo "</ul>";
        
        echo "<p><strong>Test Flow:</strong></p>";
        echo "<ol>";
        echo "<li>Go to public sales page</li>";
        echo "<li>Select a package</li>";
        echo "<li>Fill customer info</li>";
        echo "<li>Select payment method (should show 12 methods)</li>";
        echo "<li>Complete payment</li>";
        echo "</ol>";
        
    } else {
        echo "<br><h3>⚠️ Still Missing Columns</h3>";
        echo "<p>The following columns are still missing: " . implode(', ', $missingCols) . "</p>";
        echo "<p>Please run this script again or check for errors above.</p>";
    }

} catch (Exception $e) {
    echo "<h3>❌ Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration and try again.</p>";
}
?>
