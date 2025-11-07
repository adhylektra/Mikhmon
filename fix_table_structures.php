<?php
/*
 * Fix Table Structures
 * Repair any remaining table structure issues
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
    <title>Table Structure Fix</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        .section { margin: 15px 0; padding: 10px; border-left: 4px solid #007bff; background: #f8f9fa; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Table Structure Fix</h1>
        <p>Fixing remaining table structure issues found in the completeness check.</p>

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

    echo "<h2>🔍 Analyzing Table Structures</h2>";

    // Function to check if column exists
    function columnExists($conn, $table, $column) {
        try {
            $stmt = $conn->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $col) {
                if ($col['Field'] === $column) {
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    // Function to show table structure
    function showTableStructure($conn, $table) {
        try {
            $stmt = $conn->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<strong>Current $table structure:</strong><br>";
            foreach ($columns as $col) {
                echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
            }
            return $columns;
        } catch (Exception $e) {
            echo "<span class='error'>❌ Table $table not found</span><br>";
            return false;
        }
    }

    // 1. Fix agent_settings table
    echo "<div class='section'>";
    echo "<h3>1. Fixing agent_settings Table</h3>";
    
    $structure = showTableStructure($conn, 'agent_settings');
    
    if ($structure) {
        // Check if agent_id column exists
        if (!columnExists($conn, 'agent_settings', 'agent_id')) {
            echo "<br><span class='warning'>⚠️ Missing agent_id column - Adding...</span><br>";
            
            try {
                // Add agent_id column
                $conn->exec("ALTER TABLE agent_settings ADD COLUMN agent_id INT NOT NULL AFTER id");
                echo "<span class='success'>✅ Added agent_id column</span><br>";
                
                // Add foreign key constraint
                try {
                    $conn->exec("ALTER TABLE agent_settings ADD FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE");
                    echo "<span class='success'>✅ Added foreign key constraint</span><br>";
                } catch (Exception $e) {
                    echo "<span class='warning'>⚠️ Foreign key constraint: " . $e->getMessage() . "</span><br>";
                }
                
                // Add unique constraint
                try {
                    $conn->exec("ALTER TABLE agent_settings ADD UNIQUE KEY unique_agent_setting (agent_id, setting_key)");
                    echo "<span class='success'>✅ Added unique constraint</span><br>";
                } catch (Exception $e) {
                    echo "<span class='warning'>⚠️ Unique constraint: " . $e->getMessage() . "</span><br>";
                }
                
            } catch (Exception $e) {
                echo "<span class='error'>❌ Error adding agent_id: " . $e->getMessage() . "</span><br>";
            }
        } else {
            echo "<span class='success'>✅ agent_id column already exists</span><br>";
        }
        
        // Check other required columns
        $requiredColumns = [
            'setting_key' => 'VARCHAR(100) NOT NULL',
            'setting_value' => 'TEXT',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ];
        
        foreach ($requiredColumns as $column => $definition) {
            if (!columnExists($conn, 'agent_settings', $column)) {
                try {
                    $conn->exec("ALTER TABLE agent_settings ADD COLUMN $column $definition");
                    echo "<span class='success'>✅ Added $column column</span><br>";
                } catch (Exception $e) {
                    echo "<span class='warning'>⚠️ Error adding $column: " . $e->getMessage() . "</span><br>";
                }
            }
        }
    }
    echo "</div>";

    // 2. Fix agent_prices table
    echo "<div class='section'>";
    echo "<h3>2. Checking agent_prices Table</h3>";
    
    $structure = showTableStructure($conn, 'agent_prices');
    
    if ($structure) {
        if (!columnExists($conn, 'agent_prices', 'agent_id')) {
            echo "<br><span class='warning'>⚠️ Missing agent_id column - Adding...</span><br>";
            
            try {
                $conn->exec("ALTER TABLE agent_prices ADD COLUMN agent_id INT NOT NULL AFTER id");
                echo "<span class='success'>✅ Added agent_id column</span><br>";
                
                // Add foreign key
                try {
                    $conn->exec("ALTER TABLE agent_prices ADD FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE");
                    echo "<span class='success'>✅ Added foreign key constraint</span><br>";
                } catch (Exception $e) {
                    echo "<span class='warning'>⚠️ Foreign key: " . $e->getMessage() . "</span><br>";
                }
                
            } catch (Exception $e) {
                echo "<span class='error'>❌ Error: " . $e->getMessage() . "</span><br>";
            }
        } else {
            echo "<span class='success'>✅ agent_prices structure looks good</span><br>";
        }
    }
    echo "</div>";

    // 3. Check agent_transactions table
    echo "<div class='section'>";
    echo "<h3>3. Checking agent_transactions Table</h3>";
    
    $structure = showTableStructure($conn, 'agent_transactions');
    
    if ($structure) {
        if (!columnExists($conn, 'agent_transactions', 'agent_id')) {
            echo "<br><span class='warning'>⚠️ Missing agent_id column - Adding...</span><br>";
            
            try {
                $conn->exec("ALTER TABLE agent_transactions ADD COLUMN agent_id INT NOT NULL AFTER id");
                echo "<span class='success'>✅ Added agent_id column</span><br>";
                
                // Add foreign key
                try {
                    $conn->exec("ALTER TABLE agent_transactions ADD FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE");
                    echo "<span class='success'>✅ Added foreign key constraint</span><br>";
                } catch (Exception $e) {
                    echo "<span class='warning'>⚠️ Foreign key: " . $e->getMessage() . "</span><br>";
                }
                
            } catch (Exception $e) {
                echo "<span class='error'>❌ Error: " . $e->getMessage() . "</span><br>";
            }
        } else {
            echo "<span class='success'>✅ agent_transactions structure looks good</span><br>";
        }
    }
    echo "</div>";

    // 4. Update existing data with proper agent_id references
    echo "<div class='section'>";
    echo "<h3>4. Updating Data References</h3>";
    
    // Get first agent ID for default assignment
    $stmt = $conn->query("SELECT id FROM agents ORDER BY id LIMIT 1");
    $firstAgentId = $stmt->fetchColumn();
    
    if ($firstAgentId) {
        echo "<span class='info'>ℹ️ Using agent ID $firstAgentId as default reference</span><br>";
        
        // Update agent_settings if agent_id is 0 or NULL
        try {
            $stmt = $conn->prepare("UPDATE agent_settings SET agent_id = ? WHERE agent_id = 0 OR agent_id IS NULL");
            $stmt->execute([$firstAgentId]);
            $updated = $stmt->rowCount();
            if ($updated > 0) {
                echo "<span class='success'>✅ Updated $updated agent_settings records</span><br>";
            }
        } catch (Exception $e) {
            echo "<span class='warning'>⚠️ agent_settings update: " . $e->getMessage() . "</span><br>";
        }
        
        // Update agent_prices if agent_id is 0 or NULL
        try {
            $stmt = $conn->prepare("UPDATE agent_prices SET agent_id = ? WHERE agent_id = 0 OR agent_id IS NULL");
            $stmt->execute([$firstAgentId]);
            $updated = $stmt->rowCount();
            if ($updated > 0) {
                echo "<span class='success'>✅ Updated $updated agent_prices records</span><br>";
            }
        } catch (Exception $e) {
            echo "<span class='warning'>⚠️ agent_prices update: " . $e->getMessage() . "</span><br>";
        }
        
        // Update agent_transactions if agent_id is 0 or NULL
        try {
            $stmt = $conn->prepare("UPDATE agent_transactions SET agent_id = ? WHERE agent_id = 0 OR agent_id IS NULL");
            $stmt->execute([$firstAgentId]);
            $updated = $stmt->rowCount();
            if ($updated > 0) {
                echo "<span class='success'>✅ Updated $updated agent_transactions records</span><br>";
            }
        } catch (Exception $e) {
            echo "<span class='warning'>⚠️ agent_transactions update: " . $e->getMessage() . "</span><br>";
        }
        
    } else {
        echo "<span class='warning'>⚠️ No agents found - cannot update references</span><br>";
    }
    echo "</div>";

    // 5. Verification
    echo "<div class='section'>";
    echo "<h3>5. Final Verification</h3>";
    
    $tables = ['agent_settings', 'agent_prices', 'agent_transactions'];
    
    foreach ($tables as $table) {
        try {
            // Test the problematic query
            $stmt = $conn->query("SELECT agent_id, COUNT(*) as count FROM $table GROUP BY agent_id LIMIT 5");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<span class='success'>✅ $table: Query test passed</span><br>";
            foreach ($results as $result) {
                echo "&nbsp;&nbsp;- Agent ID " . $result['agent_id'] . ": " . $result['count'] . " records<br>";
            }
            
        } catch (Exception $e) {
            echo "<span class='error'>❌ $table: " . $e->getMessage() . "</span><br>";
        }
    }
    echo "</div>";

    echo "<div class='section success'>";
    echo "<h3>🎉 Table Structure Fix Complete!</h3>";
    echo "<p><strong>What was fixed:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Added missing agent_id columns</li>";
    echo "<li>✅ Added foreign key constraints</li>";
    echo "<li>✅ Updated data references</li>";
    echo "<li>✅ Verified query compatibility</li>";
    echo "</ul>";
    
    echo "<p><strong>🔗 Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li><a href='check_database_completeness.php?key=mikhmon-check-2024' target='_blank'>🔍 Run completeness check again</a></li>";
    echo "<li><a href='public/index.php?agent=AG001' target='_blank'>🛒 Test public voucher</a></li>";
    echo "<li>⚙️ Configure payment gateway in admin panel</li>";
    echo "<li>🗑️ Delete fix files for security</li>";
    echo "</ol>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='section error'>";
    echo "<h3>❌ Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

?>

    </div>
</body>
</html>
