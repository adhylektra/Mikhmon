<?php
/**
 * Generate Data Insert Script
 * Membuat file PHP berisi data INSERT untuk di-include ke install_database.php
 */

// Security check
$export_key = isset($_GET['key']) ? $_GET['key'] : '';
$expected_key = 'generate-data-2024';

if ($export_key !== $expected_key) {
    die('Access denied. Use: ?key=generate-data-2024');
}

// Database configuration - SESUAIKAN DENGAN LOKAL ANDA
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'mikhmon'; // GANTI DENGAN NAMA DATABASE ANDA

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Data Insert - MikhMon Agent</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #6f42c1; color: white; padding: 15px; margin: -20px -20px 20px -20px; border-radius: 10px 10px 0 0; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { background: #6f42c1; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #5a32a3; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; max-height: 400px; }
        textarea { width: 100%; height: 300px; font-family: monospace; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Generate Data Insert Script</h1>
            <p>Membuat file data_insert.php dari database lokal</p>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Handle form submission
            if (isset($_POST['db_name'])) {
                $dbname = $_POST['db_name'];
            }
            
            if (isset($_POST['generate_data'])) {
                try {
                    // Connect to database
                    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    echo "<h2>🔧 Generating Data Insert Script...</h2>";
                    
                    // Tables to export data from
                    $tables_to_export = [
                        'agents',
                        'agent_settings', 
                        'agent_prices',
                        'agent_transactions',
                        'payment_gateway_config',
                        'agent_profile_pricing',
                        'public_sales',
                        'payment_methods',
                        'voucher_settings'
                    ];
                    
                    $php_content = "<?php\n";
                    $php_content .= "/**\n";
                    $php_content .= " * Data Insert Script\n";
                    $php_content .= " * Generated from local database: $dbname\n";
                    $php_content .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
                    $php_content .= " */\n\n";
                    $php_content .= "// This file will be included in install_database.php\n";
                    $php_content .= "// \$pdo variable is available from parent script\n\n";
                    
                    $total_records = 0;
                    
                    foreach ($tables_to_export as $table) {
                        try {
                            // Check if table exists and has data
                            $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                            $count = $stmt->fetchColumn();
                            
                            if ($count > 0) {
                                echo "<div class='info'>📋 Processing table '$table' ($count records)...</div>";
                                
                                // Get table data
                                $stmt = $pdo->query("SELECT * FROM `$table`");
                                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (!empty($rows)) {
                                    $php_content .= "// ========================================\n";
                                    $php_content .= "// INSERT DATA FOR TABLE: $table\n";
                                    $php_content .= "// ========================================\n";
                                    $php_content .= "try {\n";
                                    $php_content .= "    echo \"<div class='info'>📊 Inserting data into $table...</div>\";\n";
                                    $php_content .= "    \$pdo->exec(\"DELETE FROM `$table`\"); // Clear existing data\n\n";
                                    
                                    // Get column names
                                    $columns = array_keys($rows[0]);
                                    $column_list = '`' . implode('`, `', $columns) . '`';
                                    $placeholders = ':' . implode(', :', $columns);
                                    
                                    $php_content .= "    \$stmt = \$pdo->prepare(\"INSERT INTO `$table` ($column_list) VALUES ($placeholders)\");\n\n";
                                    
                                    // Add each row
                                    foreach ($rows as $row) {
                                        $php_content .= "    \$stmt->execute([\n";
                                        foreach ($row as $column => $value) {
                                            if ($value === null) {
                                                $php_content .= "        ':$column' => null,\n";
                                            } else {
                                                $escaped_value = addslashes($value);
                                                $php_content .= "        ':$column' => '$escaped_value',\n";
                                            }
                                        }
                                        $php_content .= "    ]);\n";
                                    }
                                    
                                    $php_content .= "\n    echo \"<div class='success'>✅ Inserted " . count($rows) . " records into $table</div>\";\n";
                                    $php_content .= "} catch (PDOException \$e) {\n";
                                    $php_content .= "    echo \"<div class='warning'>⚠️ Error inserting data into $table: \" . \$e->getMessage() . \"</div>\";\n";
                                    $php_content .= "}\n\n";
                                    
                                    $total_records += count($rows);
                                }
                                
                                echo "<div class='success'>✅ Table '$table' processed ($count records)</div>";
                            } else {
                                echo "<div class='warning'>⚠️ Table '$table' is empty, skipping...</div>";
                            }
                            
                        } catch (PDOException $e) {
                            echo "<div class='error'>❌ Error processing table '$table': " . $e->getMessage() . "</div>";
                        }
                    }
                    
                    $php_content .= "echo \"<div class='success'>🎉 All data inserted successfully! Total records: $total_records</div>\";\n";
                    $php_content .= "?>";
                    
                    // Save to file
                    $filename = 'data_insert.php';
                    file_put_contents($filename, $php_content);
                    
                    echo "<div class='success'>";
                    echo "<h3>🎉 Data Insert Script Generated!</h3>";
                    echo "<p><strong>File Created:</strong> $filename</p>";
                    echo "<p><strong>Total Records:</strong> $total_records</p>";
                    echo "<p><strong>File Size:</strong> " . round(filesize($filename) / 1024, 2) . " KB</p>";
                    echo "</div>";
                    
                    // Show preview
                    echo "<div class='info'>";
                    echo "<h4>📋 File Preview (first 50 lines):</h4>";
                    $preview_lines = array_slice(explode("\n", $php_content), 0, 50);
                    echo "<textarea readonly>" . htmlspecialchars(implode("\n", $preview_lines)) . "\n\n... (file continues)</textarea>";
                    echo "</div>";
                    
                    // Instructions
                    echo "<div class='warning'>";
                    echo "<h3>📋 Next Steps:</h3>";
                    echo "<ol>";
                    echo "<li><strong>Upload file data_insert.php</strong> ke hosting bersama aplikasi</li>";
                    echo "<li><strong>Modifikasi install_database.php</strong> untuk include file ini</li>";
                    echo "<li><strong>Jalankan install_database.php</strong> di hosting</li>";
                    echo "<li><strong>Hapus file ini</strong> setelah selesai untuk keamanan</li>";
                    echo "</ol>";
                    echo "</div>";
                    
                } catch (Exception $e) {
                    echo "<div class='error'>❌ Generation failed: " . $e->getMessage() . "</div>";
                }
            }
        } else {
            // Get available databases
            $available_databases = [];
            try {
                $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $stmt = $pdo->query("SHOW DATABASES");
                $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Filter out system databases
                $system_dbs = ['information_schema', 'performance_schema', 'mysql', 'sys'];
                foreach ($databases as $db) {
                    if (!in_array($db, $system_dbs)) {
                        $available_databases[] = $db;
                    }
                }
            } catch (Exception $e) {
                echo "<div class='error'>❌ Could not connect to MySQL: " . $e->getMessage() . "</div>";
            }
        ?>
        
        <div class="info">
            <h3>📋 Generate Data Insert Script</h3>
            <p>Script ini akan membuat file <strong>data_insert.php</strong> yang berisi:</p>
            <ul>
                <li>📊 <strong>Semua data</strong> dari database lokal dalam format PHP</li>
                <li>🔧 <strong>INSERT statements</strong> yang siap dijalankan</li>
                <li>✅ <strong>Error handling</strong> untuk setiap tabel</li>
                <li>🎯 <strong>Compatible</strong> dengan install_database.php</li>
            </ul>
        </div>

        <?php if (!empty($available_databases)): ?>
        <div class="success">
            <h3>📊 Available Databases:</h3>
            <ul>
                <?php foreach ($available_databases as $db): ?>
                    <li>🗄️ <strong><?= htmlspecialchars($db) ?></strong></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="warning">
            <h3>⚠️ Important Notes:</h3>
            <ul>
                <li>Pilih database yang berisi data MikhMon Agent Anda</li>
                <li>File yang dihasilkan akan di-include ke install_database.php</li>
                <li>Data akan di-insert setelah struktur tabel dibuat</li>
                <li>Existing data akan di-clear untuk avoid duplicate</li>
            </ul>
        </div>

        <form method="POST">
            <h3>🔧 Database Selection</h3>
            
            <div class="form-group">
                <label>Select Database:</label>
                <select name="db_name" required>
                    <option value="">-- Select Database --</option>
                    <?php foreach ($available_databases as $db): ?>
                        <option value="<?= htmlspecialchars($db) ?>" <?= $db === $dbname ? 'selected' : '' ?>>
                            <?= htmlspecialchars($db) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" name="generate_data" class="btn">🔧 Generate Data Insert Script</button>
        </form>
        
        <?php } ?>
        
        <div class="info" style="margin-top: 30px;">
            <h3>📋 Access URL:</h3>
            <p><code><?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?></code></p>
        </div>
    </div>
</body>
</html>
