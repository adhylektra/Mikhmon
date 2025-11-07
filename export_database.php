<?php
/**
 * Export Complete Database Script
 * Backup seluruh database (struktur + data) untuk di-restore ke hosting
 */

// Security check
$export_key = isset($_GET['key']) ? $_GET['key'] : '';
$expected_key = 'export-db-2024';

if ($export_key !== $expected_key) {
    die('Access denied. Use: ?key=export-db-2024');
}

// Database configuration - EDIT SESUAI LOKAL
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'alijayan_mikhmon_agent';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Database - MikhMon Agent</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #28a745; color: white; padding: 15px; margin: -20px -20px 20px -20px; border-radius: 10px 10px 0 0; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #218838; }
        .btn-download { background: #007bff; }
        .btn-download:hover { background: #0056b3; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; max-height: 300px; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📤 Export Complete Database</h1>
            <p>Backup seluruh database (struktur + data) untuk hosting</p>
        </div>

        <?php
        // Handle form submission for database selection
        if (isset($_POST['db_name'])) {
            $dbname = $_POST['db_name'];
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_database'])) {
            try {
                echo "<h2>📦 Exporting Complete Database...</h2>";
                
                // Generate filename
                $timestamp = date('Ymd_His');
                $filename = "mikhmon_agent_complete_{$timestamp}.sql";
                
                // Build mysqldump command
                $command = "mysqldump";
                $command .= " --host=" . escapeshellarg($host);
                $command .= " --user=" . escapeshellarg($user);
                if (!empty($pass)) {
                    $command .= " --password=" . escapeshellarg($pass);
                }
                $command .= " --single-transaction";
                $command .= " --routines";
                $command .= " --triggers";
                $command .= " --add-drop-database";
                $command .= " --databases " . escapeshellarg($dbname);
                $command .= " > " . escapeshellarg($filename);
                
                echo "<div class='info'>🔧 Running mysqldump command...</div>";
                echo "<div class='info'><code>$command</code></div>";
                
                // Execute mysqldump
                $output = [];
                $return_code = 0;
                exec($command . " 2>&1", $output, $return_code);
                
                if ($return_code === 0 && file_exists($filename)) {
                    $file_size = filesize($filename);
                    $file_size_mb = round($file_size / 1024 / 1024, 2);
                    
                    echo "<div class='success'>";
                    echo "<h3>🎉 Database Export Successful!</h3>";
                    echo "<p><strong>File:</strong> $filename</p>";
                    echo "<p><strong>Size:</strong> {$file_size_mb} MB</p>";
                    echo "</div>";
                    
                    // Show download link
                    echo "<div class='info'>";
                    echo "<h3>📥 Download:</h3>";
                    echo "<a href='$filename' download class='btn btn-download'>📥 Download Complete Database</a>";
                    echo "</div>";
                    
                    // Show file preview
                    echo "<div class='info'>";
                    echo "<h4>📋 File Preview (first 30 lines):</h4>";
                    $preview = array_slice(file($filename), 0, 30);
                    echo "<pre>" . htmlspecialchars(implode('', $preview)) . "\n... (file continues)</pre>";
                    echo "</div>";
                    
                    // Instructions
                    echo "<div class='warning'>";
                    echo "<h3>📋 Next Steps:</h3>";
                    echo "<ol>";
                    echo "<li><strong>Download</strong> file database lengkap</li>";
                    echo "<li><strong>Upload</strong> ke hosting bersama aplikasi</li>";
                    echo "<li><strong>Jalankan import_database.php</strong> di hosting</li>";
                    echo "<li><strong>Hapus</strong> file export setelah selesai</li>";
                    echo "</ol>";
                    echo "</div>";
                    
                } else {
                    echo "<div class='error'>";
                    echo "<h3>❌ Export Failed!</h3>";
                    echo "<p><strong>Return Code:</strong> $return_code</p>";
                    if (!empty($output)) {
                        echo "<p><strong>Error Output:</strong></p>";
                        echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
                    }
                    echo "</div>";
                    
                    // Fallback: Try PHP-based export
                    echo "<div class='warning'>";
                    echo "<h3>🔄 Trying PHP-based export...</h3>";
                    echo "</div>";
                    
                    try {
                        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        $sql_dump = "-- ========================================\n";
                        $sql_dump .= "-- COMPLETE DATABASE EXPORT (PHP Method)\n";
                        $sql_dump .= "-- Database: $dbname\n";
                        $sql_dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
                        $sql_dump .= "-- ========================================\n\n";
                        
                        $sql_dump .= "DROP DATABASE IF EXISTS `$dbname`;\n";
                        $sql_dump .= "CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
                        $sql_dump .= "USE `$dbname`;\n\n";
                        
                        // Get all tables
                        $tables = [];
                        $result = $pdo->query("SHOW TABLES");
                        while ($row = $result->fetch(PDO::FETCH_NUM)) {
                            $tables[] = $row[0];
                        }
                        
                        foreach ($tables as $table) {
                            // Get CREATE TABLE statement
                            $result = $pdo->query("SHOW CREATE TABLE `$table`");
                            $create_table = $result->fetch(PDO::FETCH_ASSOC);
                            $sql_dump .= $create_table['Create Table'] . ";\n\n";
                            
                            // Get table data
                            $result = $pdo->query("SELECT * FROM `$table`");
                            $rows = $result->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (!empty($rows)) {
                                $columns = array_keys($rows[0]);
                                $column_list = '`' . implode('`, `', $columns) . '`';
                                
                                $sql_dump .= "INSERT INTO `$table` ($column_list) VALUES\n";
                                
                                $values = [];
                                foreach ($rows as $row) {
                                    $escaped_values = [];
                                    foreach ($row as $value) {
                                        if ($value === null) {
                                            $escaped_values[] = 'NULL';
                                        } else {
                                            $escaped_values[] = "'" . addslashes($value) . "'";
                                        }
                                    }
                                    $values[] = '(' . implode(', ', $escaped_values) . ')';
                                }
                                
                                $sql_dump .= implode(",\n", $values) . ";\n\n";
                            }
                        }
                        
                        file_put_contents($filename, $sql_dump);
                        $file_size = filesize($filename);
                        $file_size_mb = round($file_size / 1024 / 1024, 2);
                        
                        echo "<div class='success'>";
                        echo "<h3>✅ PHP Export Successful!</h3>";
                        echo "<p><strong>File:</strong> $filename</p>";
                        echo "<p><strong>Size:</strong> {$file_size_mb} MB</p>";
                        echo "<a href='$filename' download class='btn btn-download'>📥 Download Database</a>";
                        echo "</div>";
                        
                    } catch (Exception $e) {
                        echo "<div class='error'>❌ PHP Export also failed: " . $e->getMessage() . "</div>";
                    }
                }
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Export failed: " . $e->getMessage() . "</div>";
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
            <h3>📋 Complete Database Export</h3>
            <p>Script ini akan export <strong>seluruh database</strong> termasuk:</p>
            <ul>
                <li>🏗️ <strong>Struktur tabel</strong> (CREATE TABLE statements)</li>
                <li>📊 <strong>Semua data</strong> (INSERT statements)</li>
                <li>🔧 <strong>Indexes & Keys</strong></li>
                <li>⚙️ <strong>Auto increment values</strong></li>
                <li>🎯 <strong>Triggers & Routines</strong> (jika ada)</li>
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
            <h3>⚠️ Requirements:</h3>
            <ul>
                <li><strong>mysqldump</strong> harus tersedia di system PATH</li>
                <li>Jika mysqldump tidak ada, akan menggunakan <strong>PHP fallback method</strong></li>
                <li>Database lokal harus dapat diakses</li>
                <li>Pastikan ada space disk yang cukup</li>
            </ul>
        </div>

        <form method="POST">
            <h3>🔧 Database Selection</h3>
            
            <div class="form-group">
                <label>Select Database to Export:</label>
                <select name="db_name" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required>
                    <option value="">-- Select Database --</option>
                    <?php foreach ($available_databases as $db): ?>
                        <option value="<?= htmlspecialchars($db) ?>" <?= $db === $dbname ? 'selected' : '' ?>>
                            <?= htmlspecialchars($db) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" name="export_database" class="btn">📤 Export Selected Database</button>
        </form>
        
        <div class="success" style="margin-top: 20px;">
            <h3>✅ Keuntungan Method Ini:</h3>
            <ul>
                <li>🎯 <strong>One-click export</strong> - tidak perlu pilih tabel satu-satu</li>
                <li>🏗️ <strong>Struktur + Data</strong> - semua ter-backup</li>
                <li>🔄 <strong>Easy restore</strong> - tinggal import 1 file</li>
                <li>✅ <strong>Complete backup</strong> - tidak ada yang terlewat</li>
            </ul>
        </div>
        
        <?php } ?>
        
        <div class="info" style="margin-top: 30px;">
            <h3>📋 Access URL:</h3>
            <p><code><?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?></code></p>
        </div>
    </div>
</body>
</html>
