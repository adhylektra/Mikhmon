<?php
/**
 * Export Data Script
 * Backup semua data dari database lokal untuk di-import ke hosting
 */

// Security check
$export_key = isset($_GET['key']) ? $_GET['key'] : '';
$expected_key = 'export-data-2024';

if ($export_key !== $expected_key) {
    die('Access denied. Use: ?key=export-data-2024');
}

// Database configuration - EDIT SESUAI LOKAL
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'alijayan_mikhmon_agent';

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
    <title>Export Data - MikhMon Agent</title>
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
        textarea { width: 100%; height: 200px; font-family: monospace; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📤 Export Data dari Lokal</h1>
            <p>Backup data untuk di-upload ke hosting</p>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Connect to database
                $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                echo "<h2>📦 Exporting Data...</h2>";
                
                // Tables to export (only data, not structure)
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
                
                $export_sql = "-- ========================================\n";
                $export_sql .= "-- DATA EXPORT FROM LOCAL XAMPP\n";
                $export_sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
                $export_sql .= "-- ========================================\n\n";
                
                $total_records = 0;
                
                foreach ($tables_to_export as $table) {
                    try {
                        // Check if table exists
                        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                        $count = $stmt->fetchColumn();
                        
                        if ($count > 0) {
                            echo "<div class='info'>📋 Exporting table '$table' ($count records)...</div>";
                            
                            // Get table data
                            $stmt = $pdo->query("SELECT * FROM `$table`");
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (!empty($rows)) {
                                // Clear existing data first
                                $export_sql .= "-- Clear existing data from $table\n";
                                $export_sql .= "DELETE FROM `$table`;\n\n";
                                
                                // Get column names
                                $columns = array_keys($rows[0]);
                                $column_list = '`' . implode('`, `', $columns) . '`';
                                
                                $export_sql .= "-- Insert data into $table\n";
                                $export_sql .= "INSERT INTO `$table` ($column_list) VALUES\n";
                                
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
                                
                                $export_sql .= implode(",\n", $values) . ";\n\n";
                                $total_records += count($rows);
                            }
                            
                            echo "<div class='success'>✅ Table '$table' exported ($count records)</div>";
                        } else {
                            echo "<div class='warning'>⚠️ Table '$table' is empty, skipping...</div>";
                        }
                        
                    } catch (PDOException $e) {
                        echo "<div class='error'>❌ Error exporting table '$table': " . $e->getMessage() . "</div>";
                    }
                }
                
                // Add reset auto increment
                $export_sql .= "-- Reset auto increment values\n";
                foreach ($tables_to_export as $table) {
                    $export_sql .= "ALTER TABLE `$table` AUTO_INCREMENT = 1;\n";
                }
                
                $export_sql .= "\n-- Export completed: $total_records total records\n";
                
                // Save to file
                $filename = 'data_export_' . date('Ymd_His') . '.sql';
                file_put_contents($filename, $export_sql);
                
                echo "<div class='success'>";
                echo "<h3>🎉 Export Completed Successfully!</h3>";
                echo "<p><strong>Total Records Exported:</strong> $total_records</p>";
                echo "<p><strong>File Created:</strong> $filename</p>";
                echo "</div>";
                
                // Show download link and preview
                echo "<div class='info'>";
                echo "<h3>📥 Download & Preview:</h3>";
                echo "<a href='$filename' download class='btn btn-download'>📥 Download SQL File</a>";
                echo "<button onclick='showPreview()' class='btn'>👁️ Preview SQL</button>";
                echo "</div>";
                
                echo "<div id='preview' style='display: none;'>";
                echo "<h4>SQL Preview (first 50 lines):</h4>";
                $preview_lines = array_slice(explode("\n", $export_sql), 0, 50);
                echo "<textarea readonly>" . htmlspecialchars(implode("\n", $preview_lines)) . "\n\n... (truncated, download full file)</textarea>";
                echo "</div>";
                
                echo "<script>";
                echo "function showPreview() {";
                echo "  document.getElementById('preview').style.display = 'block';";
                echo "}";
                echo "</script>";
                
                // Instructions
                echo "<div class='warning'>";
                echo "<h3>📋 Next Steps:</h3>";
                echo "<ol>";
                echo "<li><strong>Download</strong> file SQL yang sudah dibuat</li>";
                echo "<li><strong>Upload</strong> file ini ke hosting bersama dengan aplikasi</li>";
                echo "<li><strong>Jalankan import_data.php</strong> di hosting untuk restore data</li>";
                echo "<li><strong>Hapus</strong> file export dan import setelah selesai</li>";
                echo "</ol>";
                echo "</div>";
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Export failed: " . $e->getMessage() . "</div>";
            }
        } else {
        ?>
        
        <div class="info">
            <h3>📋 What This Script Will Do:</h3>
            <ul>
                <li>🔍 <strong>Scan all tables</strong> in your local database</li>
                <li>📤 <strong>Export all data</strong> (agents, settings, prices, transactions, etc.)</li>
                <li>💾 <strong>Create SQL file</strong> ready for import to hosting</li>
                <li>🔄 <strong>Include DELETE statements</strong> to avoid duplicates</li>
                <li>📥 <strong>Provide download link</strong> for the export file</li>
            </ul>
        </div>

        <div class="warning">
            <h3>⚠️ Important Notes:</h3>
            <ul>
                <li>Pastikan database lokal sudah berisi data yang ingin di-backup</li>
                <li>File export akan menghapus data existing di hosting (untuk avoid duplicate)</li>
                <li>Simpan file export dengan aman</li>
                <li>Hapus file export setelah selesai untuk keamanan</li>
            </ul>
        </div>

        <form method="POST">
            <button type="submit" class="btn">📤 Start Export Data</button>
        </form>
        
        <?php } ?>
        
        <div class="info" style="margin-top: 30px;">
            <h3>📋 Access URL:</h3>
            <p><code><?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?></code></p>
        </div>
    </div>
</body>
</html>
