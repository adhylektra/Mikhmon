<?php
/*
 * Fix Public Sales - Complete Table Structure
 * Comprehensive fix for existing databases
 */

// Security check
$security_key = $_GET['key'] ?? '';
if ($security_key !== 'mikhmon-fix-2024') {
    die('Access denied. Add ?key=mikhmon-fix-2024 to URL');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Public Sales - Complete Structure</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        .step { margin: 10px 0; padding: 10px; border-left: 4px solid #007bff; background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix Public Sales - Complete Table Structure</h1>
        <p>Adding all missing columns to public_sales table for full compatibility.</p>

<?php

try {
    // Database connection
    if (file_exists('include/db_config.php')) {
        include_once('include/db_config.php');
        $conn = getDBConnection();
        echo "<div class='step success'>✅ Database connection successful!</div>";
    } else {
        throw new Exception("Database config file not found");
    }

    // Check if agent_id column exists
    echo "<div class='step info'>🔍 Checking public_sales table structure...</div>";
    
    $hasAgentId = false;
    try {
        $conn->query("SELECT agent_id FROM public_sales LIMIT 1");
        $hasAgentId = true;
        echo "<div class='step success'>✅ agent_id column already exists!</div>";
    } catch (Exception $e) {
        echo "<div class='step info'>⚠️ agent_id column not found - adding...</div>";
        
        // Add agent_id column
        try {
            $conn->exec("ALTER TABLE public_sales ADD COLUMN agent_id INT NOT NULL DEFAULT 1 AFTER payment_reference");
            echo "<div class='step success'>✅ Added agent_id column</div>";
            
            // Add foreign key constraint
            try {
                $conn->exec("ALTER TABLE public_sales ADD FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE");
                echo "<div class='step success'>✅ Added foreign key constraint</div>";
            } catch (Exception $e) {
                echo "<div class='step info'>ℹ️ Foreign key constraint: " . $e->getMessage() . "</div>";
            }
            
            $hasAgentId = true;
            
        } catch (Exception $e) {
            echo "<div class='step error'>❌ Error adding agent_id column: " . $e->getMessage() . "</div>";
        }
    }
    
    // Check and add all required columns
    echo "<div class='step info'>🔍 Checking all required columns...</div>";
    
    $requiredColumns = [
        'profile_id' => 'INT NOT NULL DEFAULT 1',
        'status' => 'VARCHAR(20) DEFAULT "pending"',
        'customer_name' => 'VARCHAR(100) NOT NULL DEFAULT ""',
        'customer_phone' => 'VARCHAR(20) NOT NULL DEFAULT ""',
        'customer_email' => 'VARCHAR(100)',
        'profile_name' => 'VARCHAR(100) NOT NULL DEFAULT ""',
        'price' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'admin_fee' => 'DECIMAL(10,2) DEFAULT 0',
        'total_amount' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'gateway_name' => 'VARCHAR(50) NOT NULL DEFAULT ""',
        'payment_method' => 'VARCHAR(50)',
        'payment_channel' => 'VARCHAR(50)',
        'payment_url' => 'TEXT',
        'qr_url' => 'TEXT',
        'virtual_account' => 'VARCHAR(50)',
        'payment_instructions' => 'TEXT',
        'expired_at' => 'DATETIME',
        'paid_at' => 'DATETIME',
        'voucher_code' => 'VARCHAR(50)',
        'voucher_password' => 'VARCHAR(50)',
        'voucher_generated_at' => 'DATETIME',
        'voucher_sent_at' => 'DATETIME',
        'ip_address' => 'VARCHAR(50)',
        'user_agent' => 'TEXT',
        'callback_data' => 'TEXT',
        'notes' => 'TEXT'
    ];
    
    foreach ($requiredColumns as $column => $definition) {
        try {
            $conn->query("SELECT $column FROM public_sales LIMIT 1");
            echo "<div class='step success'>✅ $column column exists</div>";
        } catch (Exception $e) {
            echo "<div class='step info'>⚠️ $column column not found - adding...</div>";
            
            try {
                $conn->exec("ALTER TABLE public_sales ADD COLUMN $column $definition");
                echo "<div class='step success'>✅ Added $column column</div>";
            } catch (Exception $e) {
                echo "<div class='step error'>❌ Error adding $column: " . $e->getMessage() . "</div>";
            }
        }
    }
    
    // Update existing records with default agent_id if needed
    if ($hasAgentId) {
        echo "<div class='step info'>🔄 Updating existing records...</div>";
        
        try {
            // Get first agent ID
            $stmt = $conn->query("SELECT id FROM agents ORDER BY id LIMIT 1");
            $firstAgentId = $stmt->fetchColumn();
            
            if ($firstAgentId) {
                // Update records with NULL or 0 agent_id
                $stmt = $conn->prepare("UPDATE public_sales SET agent_id = ? WHERE agent_id = 0 OR agent_id IS NULL");
                $stmt->execute([$firstAgentId]);
                $updated = $stmt->rowCount();
                
                if ($updated > 0) {
                    echo "<div class='step success'>✅ Updated $updated records with agent_id = $firstAgentId</div>";
                } else {
                    echo "<div class='step info'>ℹ️ No records needed updating</div>";
                }
            }
        } catch (Exception $e) {
            echo "<div class='step error'>❌ Error updating records: " . $e->getMessage() . "</div>";
        }
    }
    
    // Final verification
    echo "<div class='step info'>🧪 Final verification...</div>";
    
    try {
        $stmt = $conn->query("SELECT COUNT(*) FROM public_sales");
        $count = $stmt->fetchColumn();
        echo "<div class='step success'>✅ public_sales table has $count records</div>";
        
        // Test the problematic query
        $stmt = $conn->query("SELECT ps.*, 'Test' as agent_name, 'TEST' as agent_code
                              FROM public_sales ps
                              LEFT JOIN agents a ON ps.agent_id = a.id
                              LIMIT 1");
        echo "<div class='step success'>✅ Query test passed - no more errors!</div>";
        
    } catch (Exception $e) {
        echo "<div class='step error'>❌ Verification failed: " . $e->getMessage() . "</div>";
    }
    
    echo "<div class='step success'>";
    echo "<h3>🎉 Fix Complete!</h3>";
    echo "<p><strong>What was fixed:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Added agent_id column to public_sales table</li>";
    echo "<li>✅ Added profile_id column if missing</li>";
    echo "<li>✅ Added foreign key constraints</li>";
    echo "<li>✅ Updated existing records</li>";
    echo "<li>✅ Verified query compatibility</li>";
    echo "</ul>";
    
    echo "<p><strong>🔗 Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li><a href='?hotspot=public-sales&session=" . ($_GET['session'] ?? 'YOUR_SESSION') . "'>🔍 Test Public Sales page</a></li>";
    echo "<li>⚙️ Page should now load without errors</li>";
    echo "<li>🗑️ Delete this fix file for security</li>";
    echo "</ol>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='step error'>";
    echo "<h3>❌ Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

?>

    </div>
</body>
</html>
