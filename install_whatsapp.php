<?php
/*
 * WhatsApp Integration Installer for MikhMon
 * Run this file once to setup WhatsApp integration
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$errors = [];
$warnings = [];
$success = [];

echo "<!DOCTYPE html>
<html>
<head>
    <title>WhatsApp Integration Installer</title>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; color: #856404; padding: 10px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #ffc107; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #17a2b8; }
        .btn { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #5568d3; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        ul { line-height: 1.8; }
        .step { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #667eea; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📱 WhatsApp Integration Installer</h1>
        <p>Installer ini akan memeriksa dan mengkonfigurasi WhatsApp Integration untuk MikhMon.</p>
";

// Check PHP version
echo "<h2>🔍 Checking Requirements...</h2>";

if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
    $success[] = "✅ PHP Version: " . PHP_VERSION . " (OK)";
} else {
    $errors[] = "❌ PHP Version: " . PHP_VERSION . " (Minimum required: 5.4.0)";
}

// Check cURL
if (function_exists('curl_init')) {
    $success[] = "✅ cURL Extension: Installed";
} else {
    $errors[] = "❌ cURL Extension: Not installed (Required for WhatsApp API)";
}

// Check JSON
if (function_exists('json_encode')) {
    $success[] = "✅ JSON Extension: Installed";
} else {
    $errors[] = "❌ JSON Extension: Not installed";
}

// Check file permissions
$dirs_to_check = [
    'logs' => './logs',
    'include' => './include',
    'api' => './api',
    'settings' => './settings',
];

foreach ($dirs_to_check as $name => $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            $success[] = "✅ Directory '$name': Writable";
        } else {
            $warnings[] = "⚠️ Directory '$name': Not writable (chmod 755 recommended)";
        }
    } else {
        $warnings[] = "⚠️ Directory '$name': Not found";
    }
}

// Check required files
$required_files = [
    'include/whatsapp_config.php' => 'WhatsApp Configuration',
    'api/whatsapp_webhook.php' => 'Webhook Handler',
    'hotspot/send_voucher_wa.php' => 'Send Voucher Function',
    'settings/whatsapp_settings.php' => 'Settings Page',
];

foreach ($required_files as $file => $desc) {
    if (file_exists($file)) {
        $success[] = "✅ File '$desc': Found";
    } else {
        $errors[] = "❌ File '$desc': Not found ($file)";
    }
}

// Display results
foreach ($success as $msg) {
    echo "<div class='success'>$msg</div>";
}

foreach ($warnings as $msg) {
    echo "<div class='warning'>$msg</div>";
}

foreach ($errors as $msg) {
    echo "<div class='error'>$msg</div>";
}

// Create logs directory if not exists
if (!is_dir('./logs')) {
    if (mkdir('./logs', 0755, true)) {
        echo "<div class='success'>✅ Created logs directory</div>";
    } else {
        echo "<div class='error'>❌ Failed to create logs directory</div>";
    }
}

// Create .htaccess for logs if not exists
if (!file_exists('./logs/.htaccess')) {
    $htaccess_content = "Options -Indexes\nDeny from all";
    if (file_put_contents('./logs/.htaccess', $htaccess_content)) {
        echo "<div class='success'>✅ Created .htaccess for logs security</div>";
    }
}

// Create index.php for logs if not exists
if (!file_exists('./logs/index.php')) {
    $index_content = "<?php\nheader('Location: ../admin.php?id=login');\nexit;\n";
    if (file_put_contents('./logs/index.php', $index_content)) {
        echo "<div class='success'>✅ Created index.php for logs security</div>";
    }
}

// Summary
echo "<h2>📊 Installation Summary</h2>";

if (empty($errors)) {
    echo "<div class='success'>";
    echo "<h3>✅ Installation Successful!</h3>";
    echo "<p>WhatsApp Integration siap digunakan. Silakan lanjutkan ke konfigurasi.</p>";
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>🚀 Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Pilih WhatsApp Gateway (Fonnte, Wablas, atau WooWA)</li>";
    echo "<li>Daftar dan dapatkan API Token dari gateway</li>";
    echo "<li>Buka halaman Settings untuk konfigurasi</li>";
    echo "<li>Test kirim pesan WhatsApp</li>";
    echo "<li>Daftarkan Webhook URL di gateway</li>";
    echo "<li>Mulai gunakan fitur WhatsApp!</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<h3>📚 Dokumentasi:</h3>";
    echo "<ul>";
    echo "<li><strong>Quick Start:</strong> WHATSAPP_QUICKSTART.md</li>";
    echo "<li><strong>Full Documentation:</strong> WHATSAPP_INTEGRATION.md</li>";
    echo "<li><strong>Config Example:</strong> config.example.whatsapp.php</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='text-align: center; margin-top: 30px;'>";
    echo "<a href='./settings/whatsapp_settings.php' class='btn'>⚙️ Go to Settings</a>";
    echo "<a href='./whatsapp_menu.html' class='btn btn-secondary'>📱 WhatsApp Menu</a>";
    echo "<a href='./admin.php?id=sessions' class='btn btn-secondary'>🏠 Dashboard</a>";
    echo "</div>";
    
} else {
    echo "<div class='error'>";
    echo "<h3>❌ Installation Failed</h3>";
    echo "<p>Terdapat " . count($errors) . " error yang harus diperbaiki.</p>";
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>🔧 Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Pastikan semua file WhatsApp Integration sudah diupload</li>";
    echo "<li>Periksa permission folder (chmod 755)</li>";
    echo "<li>Pastikan PHP version minimal 5.4</li>";
    echo "<li>Install cURL extension jika belum ada</li>";
    echo "<li>Hubungi administrator jika masalah berlanjut</li>";
    echo "</ul>";
    echo "</div>";
}

// System info
echo "<h2>ℹ️ System Information</h2>";
echo "<div class='info'>";
echo "<ul>";
echo "<li><strong>PHP Version:</strong> " . PHP_VERSION . "</li>";
echo "<li><strong>Server:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</li>";
echo "<li><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</li>";
echo "<li><strong>Current Path:</strong> " . __DIR__ . "</li>";
echo "<li><strong>cURL:</strong> " . (function_exists('curl_init') ? 'Installed' : 'Not Installed') . "</li>";
echo "<li><strong>JSON:</strong> " . (function_exists('json_encode') ? 'Installed' : 'Not Installed') . "</li>";
echo "</ul>";
echo "</div>";

// Webhook URL info
echo "<h2>🔗 Webhook URL</h2>";
echo "<div class='info'>";
echo "<p>Gunakan URL ini sebagai webhook di gateway WhatsApp Anda:</p>";
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$webhook_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/api/whatsapp_webhook.php';
echo "<p style='background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; word-break: break-all;'>";
echo $webhook_url;
echo "</p>";
echo "</div>";

echo "<hr style='margin: 30px 0;'>";
echo "<p style='text-align: center; color: #666;'>";
echo "WhatsApp Integration for MikhMon v1.0.0<br>";
echo "© 2024 - Licensed under GPL v2";
echo "</p>";

echo "</div></body></html>";
