<?php
/**
 * Import Complete Database Script
 * Restore seluruh database dari file backup ke hosting
 */

// Security check
$import_key = isset($_GET['key']) ? $_GET['key'] : '';
$expected_key = 'import-db-2024';

if ($import_key !== $expected_key) {
    die('Access denied. Use: ?key=import-db-2024');
}

// Database configuration - EDIT SESUAI HOSTING
$host = 'localhost';
$user = 'your_db_username';
$pass = 'your_db_password';
$dbname = 'your_db_name';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Database - MikhMon Agent</title>
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
            <h1>📥 Import Complete Database</h1>
            <p>Restore seluruh database dari backup lokal</p>
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
            
            if (isset($_POST['import_database'])) {
                try {
                    echo "<h2>📥 Importing Complete Database...</h2>";
                    
                    // Check for uploaded file or existing file
                    $sql_file_path = '';
                    $filename = '';
                    
                    if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
                        // File uploaded
                        $filename = $_FILES['sql_file']['name'];
                        $sql_file_path = $_FILES['sql_file']['tmp_name'];
                        echo "<div class='info'>📁 Using uploaded file: $filename</div>";
                    } else {
                        // Look for existing export files
                        $export_files = glob('mikhmon_agent_complete_*.sql');
                        if (!empty($export_files)) {
                            $filename = $export_files[0]; // Use first found
                            $sql_file_path = $filename;
                            echo "<div class='info'>📁 Using existing file: $filename</div>";
                        } else {
                            throw new Exception("No SQL file found. Please upload a file or ensure export file exists.");
                        }
                    }
                    
                    if (!file_exists($sql_file_path)) {
                        throw new Exception("SQL file not found or could not be read.");
                    }
                    
                    $file_size = filesize($sql_file_path);
                    $file_size_mb = round($file_size / 1024 / 1024, 2);
                    echo "<div class='success'>✅ SQL file loaded successfully! Size: {$file_size_mb} MB</div>";
                    
                    // Try mysql command first (faster for large files)
                    echo "<div class='info'>🔧 Attempting mysql command import...</div>";
                    
                    $command = "mysql";
                    $command .= " --host=" . escapeshellarg($host);
                    $command .= " --user=" . escapeshellarg($user);
                    if (!empty($pass)) {
                        $command .= " --password=" . escapeshellarg($pass);
                    }
                    $command .= " < " . escapeshellarg($sql_file_path);
                    
                    $output = [];
                    $return_code = 0;
                    exec($command . " 2>&1", $output, $return_code);
                    
                    if ($return_code === 0) {
                        echo "<div class='success'>✅ MySQL command import successful!</div>";
                    } else {
                        echo "<div class='warning'>⚠️ MySQL command failed, trying PHP method...</div>";
                        if (!empty($output)) {
                            echo "<div class='warning'>Command output: " . implode("\n", $output) . "</div>";
                        }
                        
                        // Fallback: PHP-based import
                        echo "<div class='info'>🔄 Using PHP-based import...</div>";
                        
                        // Connect to MySQL server (not specific database yet)
                        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        // Read and execute SQL file
                        $sql_content = file_get_contents($sql_file_path);
                        
                        // Split into statements
                        $statements = array_filter(array_map('trim', explode(';', $sql_content)));
                        
                        $total_statements = count($statements);
                        $executed = 0;
                        $errors = 0;
                        
                        echo "<div class='info'>⚙️ Executing $total_statements SQL statements...</div>";
                        
                        foreach ($statements as $statement) {
                            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                                try {
                                    $pdo->exec($statement);
                                    $executed++;
                                    
                                    if ($executed % 50 == 0) { // Update progress every 50 statements
                                        $progress = ($executed / $total_statements) * 100;
                                        echo "<div class='progress'><div class='progress-bar' style='width: {$progress}%'></div></div>";
                                        echo "<div style='margin: 5px 0;'>Executed: $executed/$total_statements statements</div>";
                                        flush();
                                    }
                                } catch (PDOException $e) {
                                    $errors++;
                                    if ($errors < 5) { // Show only first 5 errors
                                        echo "<div class='warning'>⚠️ Warning: " . $e->getMessage() . "</div>";
                                    }
                                }
                            }
                        }
                        
                        echo "<div class='success'>✅ PHP import completed! Executed: $executed statements, Errors: $errors</div>";
                    }
                    
                    // Verify import by connecting to the imported database
                    echo "<div class='info'>🔍 Verifying imported database...</div>";
                    
                    try {
                        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        // Get table list
                        $stmt = $pdo->query("SHOW TABLES");
                        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        echo "<div class='success'>";
                        echo "<h3>📊 Import Verification:</h3>";
                        echo "<p><strong>Database:</strong> $dbname</p>";
                        echo "<p><strong>Tables Found:</strong> " . count($tables) . "</p>";
                        
                        if (!empty($tables)) {
                            echo "<p><strong>Table List:</strong></p>";
                            echo "<ul>";
                            $total_records = 0;
                            foreach ($tables as $table) {
                                try {
                                    $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                                    $count = $stmt->fetchColumn();
                                    echo "<li>✅ $table ($count records)</li>";
                                    $total_records += $count;
                                } catch (Exception $e) {
                                    echo "<li>❌ $table (error: " . $e->getMessage() . ")</li>";
                                }
                            }
                            echo "</ul>";
                            echo "<p><strong>Total Records:</strong> $total_records</p>";
                        }
                        echo "</div>";
                        
                    } catch (Exception $e) {
                        echo "<div class='error'>❌ Could not verify database: " . $e->getMessage() . "</div>";
                    }
                    
                    // Show next steps
                    echo "<div class='info'>";
                    echo "<h3>🎉 Import Complete! Next Steps:</h3>";
                    echo "<ol>";
                    echo "<li>Hapus file import ini untuk keamanan: <code>import_database.php</code></li>";
                    echo "<li>Hapus file backup jika ada: <code>$filename</code></li>";
                    echo "<li>Test aplikasi dan login</li>";
                    echo "<li>Verifikasi semua data tersedia</li>";
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
                <li><strong>Database sudah dibuat</strong> di hosting panel</li>
                <li><strong>File backup (.sql)</strong> sudah di-upload ke hosting</li>
                <li><strong>Backup data existing</strong> jika diperlukan (akan di-overwrite)</li>
                <li><strong>Pastikan space disk</strong> cukup untuk database</li>
            </ul>
        </div>

        <div class="info">
            <h3>📋 What This Script Will Do:</h3>
            <ul>
                <li>🗑️ <strong>Drop existing database</strong> (jika ada)</li>
                <li>🏗️ <strong>Create database</strong> dengan struktur lengkap</li>
                <li>📊 <strong>Import all data</strong> dari backup lokal</li>
                <li>🔧 <strong>Restore indexes & keys</strong></li>
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
                <small>Database akan dibuat otomatis jika belum ada</small>
            </div>
            
            <h3>📁 Database Backup File</h3>
            
            <div class="form-group">
                <label>Upload Database Backup (.sql):</label>
                <input type="file" name="sql_file" accept=".sql">
                <small>Jika tidak di-upload, akan mencari file mikhmon_agent_complete_*.sql yang sudah ada</small>
            </div>
            
            <button type="submit" name="import_database" class="btn">📥 Import Complete Database</button>
        </form>
        
        <?php } ?>
        
        <div class="info" style="margin-top: 30px;">
            <h3>📋 Access URL:</h3>
            <p><code><?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?></code></p>
        </div>
    </div>
</body>
</html>
