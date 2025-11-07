<?php
/*
 * Installer for Voucher Settings Feature
 * Run this file once to add voucher settings to database
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Install Voucher Settings</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .step { background: #f8f9fa; padding: 15px; border-left: 4px solid #667eea; margin: 10px 0; }
        h1 { color: #333; }
        h2 { color: #667eea; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        .btn { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #5568d3; }
    </style>
</head>
<body>";

echo "<h1>🎫 Voucher Settings Installer</h1>";

// Include database config
if (!file_exists('./include/db_config.php')) {
    echo "<div class='error'>❌ File <code>include/db_config.php</code> tidak ditemukan!</div>";
    echo "<p>Pastikan Anda menjalankan script ini dari root directory MikhMon.</p>";
    exit;
}

include_once('./include/db_config.php');

echo "<div class='info'>📋 Memulai instalasi Voucher Settings...</div>";

try {
    $db = getDBConnection();
    
    if (!$db) {
        throw new Exception("Gagal koneksi ke database");
    }
    
    echo "<div class='success'>✅ Koneksi database berhasil</div>";
    
    // Check if agent_settings table exists
    echo "<div class='step'><strong>Step 1:</strong> Memeriksa tabel agent_settings...</div>";
    
    $stmt = $db->query("SHOW TABLES LIKE 'agent_settings'");
    if ($stmt->rowCount() == 0) {
        echo "<div class='error'>❌ Tabel <code>agent_settings</code> tidak ditemukan!</div>";
        echo "<p>Silakan install Agent System terlebih dahulu dengan menjalankan <code>install_agent_system.php</code></p>";
        exit;
    }
    
    echo "<div class='success'>✅ Tabel agent_settings ditemukan</div>";
    
    // Insert voucher settings
    echo "<div class='step'><strong>Step 2:</strong> Menambahkan voucher settings...</div>";
    
    $settings = [
        ['voucher_username_password_same', '0', 'boolean', 'Username dan password sama atau berbeda'],
        ['voucher_username_type', 'alphanumeric', 'string', 'Tipe karakter username: numeric, alpha, alphanumeric'],
        ['voucher_username_length', '8', 'number', 'Panjang karakter username'],
        ['voucher_password_type', 'alphanumeric', 'string', 'Tipe karakter password: numeric, alpha, alphanumeric'],
        ['voucher_password_length', '6', 'number', 'Panjang karakter password'],
        ['voucher_prefix_enabled', '1', 'boolean', 'Gunakan prefix untuk username'],
        ['voucher_prefix', 'AG', 'string', 'Prefix untuk username'],
        ['voucher_uppercase', '1', 'boolean', 'Gunakan huruf kapital']
    ];
    
    $insertedCount = 0;
    $updatedCount = 0;
    
    foreach ($settings as $setting) {
        // Check if exists
        $checkStmt = $db->prepare("SELECT setting_key FROM agent_settings WHERE setting_key = ?");
        $checkStmt->execute([$setting[0]]);
        $exists = $checkStmt->rowCount() > 0;
        
        // Insert or update - prepare statement inside loop to avoid parameter reuse
        $stmt = $db->prepare("
            INSERT INTO agent_settings (setting_key, setting_value, setting_type, description, updated_by) 
            VALUES (?, ?, ?, ?, 'system')
            ON DUPLICATE KEY UPDATE 
                setting_value = ?,
                setting_type = ?,
                description = ?
        ");
        
        $stmt->execute([
            $setting[0], // setting_key
            $setting[1], // setting_value
            $setting[2], // setting_type
            $setting[3], // description
            $setting[1], // setting_value for UPDATE
            $setting[2], // setting_type for UPDATE
            $setting[3]  // description for UPDATE
        ]);
        
        if ($exists) {
            $updatedCount++;
            echo "<div class='info'>🔄 Updated: <code>{$setting[0]}</code> = {$setting[1]}</div>";
        } else {
            $insertedCount++;
            echo "<div class='success'>➕ Inserted: <code>{$setting[0]}</code> = {$setting[1]}</div>";
        }
    }
    
    echo "<div class='step'><strong>Step 3:</strong> Verifikasi instalasi...</div>";
    
    // Verify installation
    $stmt = $db->query("SELECT COUNT(*) as count FROM agent_settings WHERE setting_key LIKE 'voucher_%'");
    $result = $stmt->fetch();
    
    if ($result['count'] >= 8) {
        echo "<div class='success'>✅ Semua voucher settings berhasil ditambahkan!</div>";
        echo "<div class='success'>";
        echo "<strong>📊 Summary:</strong><br>";
        echo "- Settings baru ditambahkan: {$insertedCount}<br>";
        echo "- Settings di-update: {$updatedCount}<br>";
        echo "- Total voucher settings: {$result['count']}";
        echo "</div>";
    } else {
        echo "<div class='error'>⚠️ Hanya {$result['count']} settings yang ditemukan. Seharusnya 8.</div>";
    }
    
    // Check if VoucherGenerator class exists
    echo "<div class='step'><strong>Step 4:</strong> Memeriksa file VoucherGenerator...</div>";
    
    if (file_exists('./lib/VoucherGenerator.class.php')) {
        echo "<div class='success'>✅ File <code>lib/VoucherGenerator.class.php</code> ditemukan</div>";
    } else {
        echo "<div class='error'>❌ File <code>lib/VoucherGenerator.class.php</code> tidak ditemukan!</div>";
    }
    
    // Check if voucher_settings.php exists
    if (file_exists('./agent-admin/voucher_settings.php')) {
        echo "<div class='success'>✅ File <code>agent-admin/voucher_settings.php</code> ditemukan</div>";
    } else {
        echo "<div class='error'>❌ File <code>agent-admin/voucher_settings.php</code> tidak ditemukan!</div>";
    }
    
    echo "<div class='step'><strong>✨ Instalasi Selesai!</strong></div>";
    
    echo "<div class='success'>";
    echo "<h2>🎉 Voucher Settings berhasil diinstall!</h2>";
    echo "<p>Anda sekarang dapat mengakses halaman pengaturan format voucher.</p>";
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<h3>📍 Langkah Selanjutnya:</h3>";
    echo "<ol>";
    echo "<li>Login ke admin panel MikhMon</li>";
    echo "<li>Buka menu <strong>Agent/Reseller</strong> → <strong>Format Voucher</strong></li>";
    echo "<li>Atur format voucher sesuai kebutuhan</li>";
    echo "<li>Klik <strong>Simpan Pengaturan</strong></li>";
    echo "<li>Test generate voucher dari agent dashboard</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<div style='text-align: center; margin-top: 30px;'>";
    echo "<a href='./admin.php' class='btn'>🏠 Ke Admin Panel</a>";
    echo "<a href='./agent/index.php' class='btn'>👤 Ke Agent Panel</a>";
    echo "</div>";
    
    echo "<div class='info' style='margin-top: 30px;'>";
    echo "<h3>📚 Dokumentasi:</h3>";
    echo "<p>Baca panduan lengkap di <code>VOUCHER_SETTINGS_GUIDE.md</code></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<h3>🔧 Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Pastikan database credentials di <code>include/db_config.php</code> sudah benar</li>";
    echo "<li>Pastikan Agent System sudah terinstall</li>";
    echo "<li>Cek error log PHP untuk detail lebih lanjut</li>";
    echo "</ul>";
    echo "</div>";
}

echo "</body></html>";
?>
