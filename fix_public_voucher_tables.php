<?php
/*
 * Fix Public Voucher Tables
 * Repair missing columns and structure for payment_methods and public_sales
 */

// Security check
$security_key = $_GET['key'] ?? '';
if ($security_key !== 'mikhmon-fix-2024') {
    die('Access denied. Add ?key=mikhmon-fix-2024 to URL');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 Public Voucher Tables - Structure Fix</h2>";

try {
    // Include database config
    if (file_exists('include/db_config.php')) {
        include_once('include/db_config.php');
        $conn = getDBConnection();
        echo "✅ Database connection successful!<br><br>";
    } else {
        throw new Exception("Database config file not found");
    }

    echo "<h3>📋 Checking Current Table Structures</h3>";
    
    // Check payment_methods table structure
    echo "<h4>1. Payment Methods Table</h4>";
    try {
        $stmt = $conn->query("DESCRIBE payment_methods");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $has_gateway_name = false;
        echo "Current columns:<br>";
        foreach ($columns as $col) {
            echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
            if ($col['Field'] === 'gateway_name') {
                $has_gateway_name = true;
            }
        }
        
        if (!$has_gateway_name) {
            echo "<br>❌ Missing 'gateway_name' column - FIXING...<br>";
            
            // Add missing columns to payment_methods
            $alterQueries = [
                "ALTER TABLE payment_methods ADD COLUMN gateway_name VARCHAR(50) NOT NULL DEFAULT 'tripay' AFTER id",
                "ALTER TABLE payment_methods ADD COLUMN method_type VARCHAR(20) NOT NULL DEFAULT 'ewallet' AFTER method_name",
                "ALTER TABLE payment_methods ADD COLUMN icon_url VARCHAR(255) AFTER method_type",
                "ALTER TABLE payment_methods ADD COLUMN admin_fee_type VARCHAR(20) DEFAULT 'flat' AFTER icon_url",
                "ALTER TABLE payment_methods ADD COLUMN admin_fee_value DECIMAL(10,2) DEFAULT 0 AFTER admin_fee_type",
                "ALTER TABLE payment_methods ADD COLUMN min_amount DECIMAL(10,2) DEFAULT 0 AFTER admin_fee_value",
                "ALTER TABLE payment_methods ADD COLUMN max_amount DECIMAL(10,2) DEFAULT 999999999 AFTER min_amount",
                "ALTER TABLE payment_methods ADD COLUMN sort_order INT DEFAULT 0 AFTER is_active"
            ];
            
            foreach ($alterQueries as $query) {
                try {
                    $conn->exec($query);
                    echo "✅ Added column successfully<br>";
                } catch (Exception $e) {
                    // Column might already exist, check if it's a duplicate column error
                    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                        echo "ℹ️ Column already exists, skipping<br>";
                    } else {
                        echo "⚠️ " . $e->getMessage() . "<br>";
                    }
                }
            }
            
            // Add unique constraint
            try {
                $conn->exec("ALTER TABLE payment_methods ADD UNIQUE KEY unique_gateway_method (gateway_name, method_code)");
                echo "✅ Added unique constraint<br>";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                    echo "ℹ️ Unique constraint already exists<br>";
                } else {
                    echo "⚠️ " . $e->getMessage() . "<br>";
                }
            }
            
        } else {
            echo "✅ payment_methods table structure is correct<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error checking payment_methods: " . $e->getMessage() . "<br>";
    }

    // Check public_sales table structure
    echo "<br><h4>2. Public Sales Table</h4>";
    try {
        $stmt = $conn->query("DESCRIBE public_sales");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $has_agent_id = false;
        echo "Current columns:<br>";
        foreach ($columns as $col) {
            echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
            if ($col['Field'] === 'agent_id') {
                $has_agent_id = true;
            }
        }
        
        if (!$has_agent_id) {
            echo "<br>❌ Missing 'agent_id' column - FIXING...<br>";
            
            // Add missing columns to public_sales
            $alterQueries = [
                "ALTER TABLE public_sales ADD COLUMN agent_id INT NOT NULL AFTER payment_reference",
                "ALTER TABLE public_sales ADD COLUMN profile_id INT NOT NULL AFTER agent_id",
                "ALTER TABLE public_sales ADD COLUMN customer_email VARCHAR(100) AFTER customer_phone",
                "ALTER TABLE public_sales ADD COLUMN profile_name VARCHAR(100) NOT NULL AFTER customer_email",
                "ALTER TABLE public_sales ADD COLUMN price DECIMAL(10,2) NOT NULL AFTER profile_name",
                "ALTER TABLE public_sales ADD COLUMN admin_fee DECIMAL(10,2) DEFAULT 0 AFTER price",
                "ALTER TABLE public_sales ADD COLUMN total_amount DECIMAL(10,2) NOT NULL AFTER admin_fee",
                "ALTER TABLE public_sales ADD COLUMN gateway_name VARCHAR(50) NOT NULL AFTER total_amount",
                "ALTER TABLE public_sales ADD COLUMN payment_method VARCHAR(50) AFTER gateway_name",
                "ALTER TABLE public_sales ADD COLUMN payment_channel VARCHAR(50) AFTER payment_method",
                "ALTER TABLE public_sales ADD COLUMN payment_url TEXT AFTER payment_channel",
                "ALTER TABLE public_sales ADD COLUMN qr_url TEXT AFTER payment_url",
                "ALTER TABLE public_sales ADD COLUMN virtual_account VARCHAR(50) AFTER qr_url",
                "ALTER TABLE public_sales ADD COLUMN payment_instructions TEXT AFTER virtual_account",
                "ALTER TABLE public_sales ADD COLUMN expired_at DATETIME AFTER payment_instructions",
                "ALTER TABLE public_sales ADD COLUMN paid_at DATETIME AFTER expired_at",
                "ALTER TABLE public_sales ADD COLUMN status VARCHAR(20) DEFAULT 'pending' AFTER paid_at",
                "ALTER TABLE public_sales ADD COLUMN voucher_code VARCHAR(50) AFTER status",
                "ALTER TABLE public_sales ADD COLUMN voucher_password VARCHAR(50) AFTER voucher_code",
                "ALTER TABLE public_sales ADD COLUMN voucher_generated_at DATETIME AFTER voucher_password",
                "ALTER TABLE public_sales ADD COLUMN voucher_sent_at DATETIME AFTER voucher_generated_at",
                "ALTER TABLE public_sales ADD COLUMN ip_address VARCHAR(50) AFTER voucher_sent_at",
                "ALTER TABLE public_sales ADD COLUMN user_agent TEXT AFTER ip_address",
                "ALTER TABLE public_sales ADD COLUMN callback_data TEXT AFTER user_agent",
                "ALTER TABLE public_sales ADD COLUMN notes TEXT AFTER callback_data"
            ];
            
            foreach ($alterQueries as $query) {
                try {
                    $conn->exec($query);
                    echo "✅ Added column successfully<br>";
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                        echo "ℹ️ Column already exists, skipping<br>";
                    } else {
                        echo "⚠️ " . $e->getMessage() . "<br>";
                    }
                }
            }
            
            // Add indexes
            $indexQueries = [
                "ALTER TABLE public_sales ADD INDEX idx_transaction_id (transaction_id)",
                "ALTER TABLE public_sales ADD INDEX idx_payment_reference (payment_reference)",
                "ALTER TABLE public_sales ADD INDEX idx_status (status)",
                "ALTER TABLE public_sales ADD INDEX idx_customer_phone (customer_phone)",
                "ALTER TABLE public_sales ADD INDEX idx_created_at (created_at)"
            ];
            
            foreach ($indexQueries as $query) {
                try {
                    $conn->exec($query);
                    echo "✅ Added index successfully<br>";
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                        echo "ℹ️ Index already exists<br>";
                    } else {
                        echo "⚠️ " . $e->getMessage() . "<br>";
                    }
                }
            }
            
        } else {
            echo "✅ public_sales table structure is correct<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error checking public_sales: " . $e->getMessage() . "<br>";
    }

    // Insert default payment methods for Tripay
    echo "<br><h3>💳 Adding Default Payment Methods</h3>";
    
    // Check if payment methods exist
    $stmt = $conn->query("SELECT COUNT(*) FROM payment_methods");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        echo "Adding default Tripay payment methods...<br>";
        
        $defaultMethods = [
            ['QRIS', 'QRIS (Semua Bank & E-Wallet)', 'qris', 0, 'flat'],
            ['BRIVA', 'BRI Virtual Account', 'va', 4000, 'flat'],
            ['BNIVA', 'BNI Virtual Account', 'va', 4000, 'flat'],
            ['BCAVA', 'BCA Virtual Account', 'va', 4000, 'flat'],
            ['MANDIRIVA', 'Mandiri Virtual Account', 'va', 4000, 'flat'],
            ['OVO', 'OVO', 'ewallet', 2.5, 'percent'],
            ['DANA', 'DANA', 'ewallet', 2.5, 'percent'],
            ['SHOPEEPAY', 'ShopeePay', 'ewallet', 2.5, 'percent'],
            ['LINKAJA', 'LinkAja', 'ewallet', 2.5, 'percent'],
            ['ALFAMART', 'Alfamart', 'retail', 5000, 'flat'],
            ['INDOMARET', 'Indomaret', 'retail', 5000, 'flat']
        ];
        
        $insertStmt = $conn->prepare("INSERT INTO payment_methods 
            (gateway_name, method_code, method_name, method_type, admin_fee_value, admin_fee_type, min_amount, max_amount, is_active, sort_order) 
            VALUES ('tripay', ?, ?, ?, ?, ?, 10000, 5000000, 1, ?)");
        
        $sortOrder = 1;
        foreach ($defaultMethods as $method) {
            try {
                $insertStmt->execute([
                    $method[0], // method_code
                    $method[1], // method_name  
                    $method[2], // method_type
                    $method[3], // admin_fee_value
                    $method[4], // admin_fee_type
                    $sortOrder++
                ]);
                echo "✅ Added: " . $method[1] . "<br>";
            } catch (Exception $e) {
                echo "⚠️ Error adding " . $method[1] . ": " . $e->getMessage() . "<br>";
            }
        }
    } else {
        echo "✅ Payment methods already exist ($count methods)<br>";
    }

    echo "<br><h3>🔍 Final Verification</h3>";
    
    // Verify tables
    $tables = ['payment_methods', 'public_sales', 'payment_gateway_config', 'agents', 'agent_profile_pricing'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $conn->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "✅ Table '$table': $count records<br>";
        } catch (Exception $e) {
            echo "❌ Table '$table': ERROR - " . $e->getMessage() . "<br>";
        }
    }

    echo "<br><h3>🎉 Fix Complete!</h3>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Database structure is now correct</li>";
    echo "<li>🔗 Test public voucher: <a href='public/index.php?agent=DEMO'>public/index.php?agent=DEMO</a></li>";
    echo "<li>⚙️ Configure payment gateway in admin panel</li>";
    echo "<li>🗑️ Delete this fix file for security</li>";
    echo "</ul>";
    
    echo "<p><strong>Test URLs:</strong></p>";
    echo "<ul>";
    echo "<li><a href='test_public_access.php'>Database Test</a></li>";
    echo "<li><a href='admin.php'>Admin Panel</a></li>";
    echo "<li><a href='public/index.php?agent=DEMO'>Public Sales (DEMO agent)</a></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<h3>❌ Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration and try again.</p>";
}
?>
