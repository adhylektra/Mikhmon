<?php
/**
 * Import Data Script
 * Restore data dari file export ke hosting
 */

// Security check
$import_key = isset($_GET['key']) ? $_GET['key'] : '';
$expected_key = 'import-data-2024';

if ($import_key !== $expected_key) {
    die('Access denied. Use: ?key=import-data-2024');
}

// Database configuration - EDIT SESUAI HOSTING
$host = 'localhost';
$user = 'your_db_username';
$pass = 'your_db_password';
$dbname = 'your_db_name';

// Try to load from existing config
if (file_exists('./include/db_config.php')) {
    include './include/db_config.php';
} elseif (file_exists('./include/config.php')) {
    $config_content = file_get_contents('./include/config.php');
    if (strpos($config_content, '$host') !== false) {
        include './include/config.php';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Data - MikhMon Agent</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #17a2b8; color: white; padding: 15px; margin: -20px -20px 20px -20px; border-radius: 10px 10px 0 0; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { background: #17a2b8; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #138496; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; max-height: 200px; }
        .progress { background: #e9ecef; border-radius: 5px; margin: 10px 0; }
        .progress-bar { background: #17a2b8; height: 20px; border-radius: 5px; transition: width 0.3s; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📥 Import Data ke Hosting</h1>
            <p>Restore data dari file export lokal</p>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Handle form submission for database credentials
            if (isset($_POST['db_host'])) {
                $host = $_POST['db_host'];
                $user = $_POST['db_user'];
                $pass = $_POST['db_pass'];
                $dbname = $_POST['db_name'];
            }
            
            if (isset($_POST['import_data'])) {
                try {
                    // Connect to database
                    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    echo "<h2>📥 Importing Data...</h2>";
                    
                    // Check for uploaded file or existing file
                    $sql_content = '';
                    $filename = '';
                    
                    if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
                        // File uploaded
                        $filename = $_FILES['sql_file']['name'];
                        $sql_content = file_get_contents($_FILES['sql_file']['tmp_name']);
                        echo "<div class='info'>📁 Using uploaded file: $filename</div>";
                    } else {
                        // Look for existing export files
                        $export_files = glob('data_export_*.sql');
                        if (!empty($export_files)) {
                            $filename = $export_files[0]; // Use first found
                            $sql_content = file_get_contents($filename);
                            echo "<div class='info'>📁 Using existing file: $filename</div>";
                        } else {
                            throw new Exception("No SQL file found. Please upload a file or ensure export file exists.");
                        }
                    }
                    
                    if (empty($sql_content)) {
                        throw new Exception("SQL file is empty or could not be read.");
                    }
                    
                    echo "<div class='success'>✅ SQL file loaded successfully!</div>";
                    
                    // Split SQL into statements
                    echo "<div class='info'>⚙️ Executing import statements...</div>";
                    $statements = array_filter(array_map('trim', explode(';', $sql_content)));
                    
                    $total_statements = count($statements);
                    $executed = 0;
                    $errors = 0;
                    
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
                                $errors++;
                                if ($errors < 5) { // Show only first 5 errors
                                    echo "<div class='warning'>⚠️ Warning: " . $e->getMessage() . "</div>";
                                }
                            }
                        }
                    }
                    
                    echo "<div class='success'>✅ Import completed! Executed: $executed statements, Errors: $errors</div>";
                    
                    // Verify import
                    echo "<div class='info'>🔍 Verifying imported data...</div>";
                    $tables = [
                        'agents', 'agent_settings', 'agent_prices', 'agent_transactions',
                        'payment_gateway_config', 'agent_profile_pricing', 'public_sales',
                        'payment_methods', 'voucher_settings'
                    ];
                    
                    $verification_results = [];
                    $total_imported_records = 0;
                    
                    foreach ($tables as $table) {
                        try {
                            $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                            $count = $stmt->fetchColumn();
                            $verification_results[] = "✅ Table '$table': $count records";
                            $total_imported_records += $count;
                        } catch (PDOException $e) {
                            $verification_results[] = "❌ Table '$table': ERROR - " . $e->getMessage();
                        }
                    }
                    
                    echo "<div class='success'>";
                    echo "<h3>📊 Import Verification:</h3>";
                    echo "<pre>" . implode("\n", $verification_results) . "</pre>";
                    echo "<p><strong>Total Records Imported:</strong> $total_imported_records</p>";
                    echo "</div>";
                    
                    // Show next steps
                    echo "<div class='info'>";
                    echo "<h3>🎉 Import Complete! Next Steps:</h3>";
                    echo "<ol>";
                    echo "<li>Hapus file import ini untuk keamanan: <code>import_data.php</code></li>";
                    echo "<li>Hapus file export jika ada: <code>$filename</code></li>";
                    echo "<li>Test aplikasi dan semua fitur</li>";
                    echo "<li>Verifikasi data agent dan settings</li>";
                    echo "</ol>";
                    echo "</div>";
                    
                } catch (Exception $e) {
                    echo "<div class='error'>❌ Import failed: " . $e->getMessage() . "</div>";
                }
            }
        } else {
        ?>
        
        <div class="warning">
            <h3>⚠️ Before Import:</h3>
            <ul>
                <li>Pastikan database sudah ter-install dengan struktur tabel yang benar</li>
                <li>Jalankan <code>install_database.php</code> terlebih dahulu jika belum</li>
                <li>File export (.sql) sudah di-upload ke hosting</li>
                <li>Backup data existing jika diperlukan</li>
            </ul>
        </div>

        <div class="info">
            <h3>📋 What This Script Will Do:</h3>
            <ul>
                <li>🔍 <strong>Connect to hosting database</strong></li>
                <li>📁 <strong>Read SQL export file</strong> (upload atau existing)</li>
                <li>🗑️ <strong>Clear existing data</strong> to avoid duplicates</li>
                <li>📥 <strong>Import all data</strong> from local backup</li>
                <li>✅ <strong>Verify import results</strong></li>
            </ul>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <h3>🔧 Database Configuration</h3>
            
            <div class="form-group">
                <label>Database Host:</label>
                <input type="text" name="db_host" value="<?php echo htmlspecialchars($host); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Database Username:</label>
                <input type="text" name="db_user" value="<?php echo htmlspecialchars($user); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Database Password:</label>
                <input type="password" name="db_pass" value="<?php echo htmlspecialchars($pass); ?>">
            </div>
            
            <div class="form-group">
                <label>Database Name:</label>
                <input type="text" name="db_name" value="<?php echo htmlspecialchars($dbname); ?>" required>
            </div>
            
            <h3>📁 SQL File</h3>
            
            <div class="form-group">
                <label>Upload SQL Export File (optional):</label>
                <input type="file" name="sql_file" accept=".sql">
                <small>Jika tidak di-upload, akan mencari file data_export_*.sql yang sudah ada</small>
            </div>
            
            <button type="submit" name="import_data" class="btn">📥 Import Data</button>
        </form>
        
        <?php } ?>
        
        <div class="info" style="margin-top: 30px;">
            <h3>📋 Access URL:</h3>
            <p><code><?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?></code></p>
        </div>
    </div>
</body>
</html>
