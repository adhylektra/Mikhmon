<?php
/*
 * WhatsApp Gateway Installer
 * Web-based installer untuk WhatsApp Gateway
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$error = '';
$success = '';

// Check if Node.js installed
function checkNodeJS() {
    $output = [];
    $return = 0;
    
    // Try to execute node --version
    exec('node --version 2>&1', $output, $return);
    
    if ($return === 0 && !empty($output)) {
        return [
            'installed' => true,
            'version' => trim($output[0]),
            'message' => 'Node.js installed: ' . trim($output[0])
        ];
    }
    
    return [
        'installed' => false,
        'version' => null,
        'message' => 'Node.js not installed'
    ];
}

// Check if npm installed
function checkNPM() {
    $output = [];
    $return = 0;
    
    exec('npm --version 2>&1', $output, $return);
    
    if ($return === 0 && !empty($output)) {
        return [
            'installed' => true,
            'version' => trim($output[0]),
            'message' => 'npm installed: ' . trim($output[0])
        ];
    }
    
    return [
        'installed' => false,
        'version' => null,
        'message' => 'npm not installed'
    ];
}

// Check if dependencies installed
function checkDependencies() {
    $nodeModulesPath = __DIR__ . '/whatsapp-gateway/node_modules';
    return file_exists($nodeModulesPath);
}

// Check if .env exists
function checkEnvFile() {
    $envPath = __DIR__ . '/whatsapp-gateway/.env';
    return file_exists($envPath);
}

// Generate random API key
function generateApiKey() {
    return bin2hex(random_bytes(32));
}

// Install dependencies
function installDependencies() {
    $gatewayPath = __DIR__ . '/whatsapp-gateway';
    $output = [];
    $return = 0;
    
    // Change to gateway directory and run npm install
    $command = "cd " . escapeshellarg($gatewayPath) . " && npm install 2>&1";
    exec($command, $output, $return);
    
    return [
        'success' => ($return === 0),
        'output' => implode("\n", $output),
        'return' => $return
    ];
}

// Create .env file
function createEnvFile($apiKey) {
    $envExample = __DIR__ . '/whatsapp-gateway/.env.example';
    $envFile = __DIR__ . '/whatsapp-gateway/.env';
    
    if (!file_exists($envExample)) {
        return ['success' => false, 'message' => '.env.example not found'];
    }
    
    $content = file_get_contents($envExample);
    
    // Replace API_KEY
    $content = str_replace('your-secret-api-key-change-this', $apiKey, $content);
    
    // Write to .env
    if (file_put_contents($envFile, $content)) {
        return ['success' => true, 'message' => '.env file created'];
    }
    
    return ['success' => false, 'message' => 'Failed to create .env file'];
}

// Update whatsapp_config.php
function updateWhatsAppConfig($apiKey) {
    $configFile = __DIR__ . '/include/whatsapp_config.php';
    
    if (!file_exists($configFile)) {
        return ['success' => false, 'message' => 'whatsapp_config.php not found'];
    }
    
    $content = file_get_contents($configFile);
    
    // Update API key
    $content = preg_replace(
        "/define\('SELFHOSTED_API_KEY',\s*'[^']*'\);/",
        "define('SELFHOSTED_API_KEY', '$apiKey');",
        $content
    );
    
    // Update gateway type
    $content = preg_replace(
        "/define\('WHATSAPP_GATEWAY',\s*'[^']*'\);/",
        "define('WHATSAPP_GATEWAY', 'selfhosted');",
        $content
    );
    
    if (file_put_contents($configFile, $content)) {
        return ['success' => true, 'message' => 'whatsapp_config.php updated'];
    }
    
    return ['success' => false, 'message' => 'Failed to update whatsapp_config.php'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'install_dependencies':
                $result = installDependencies();
                if ($result['success']) {
                    $success = 'Dependencies installed successfully!';
                    $step = 3;
                } else {
                    $error = 'Failed to install dependencies: ' . $result['output'];
                }
                break;
                
            case 'create_env':
                $apiKey = generateApiKey();
                
                // Create .env
                $envResult = createEnvFile($apiKey);
                if (!$envResult['success']) {
                    $error = $envResult['message'];
                    break;
                }
                
                // Update whatsapp_config.php
                $configResult = updateWhatsAppConfig($apiKey);
                if (!$configResult['success']) {
                    $error = $configResult['message'];
                    break;
                }
                
                $_SESSION['api_key'] = $apiKey;
                $success = 'Configuration created successfully!';
                $step = 4;
                break;
        }
    }
}

// Get system info
$nodeInfo = checkNodeJS();
$npmInfo = checkNPM();
$depsInstalled = checkDependencies();
$envExists = checkEnvFile();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Gateway Installer - MikhMon</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .step {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        .step-number {
            width: 40px;
            height: 40px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .step-number.active {
            background: #10b981;
        }
        .step-number.inactive {
            background: #ccc;
        }
        .step-content h2 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #333;
        }
        .step-content p {
            color: #666;
            line-height: 1.6;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
        .check-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .check-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-weight: bold;
        }
        .check-icon.success {
            background: #d1fae5;
            color: #065f46;
        }
        .check-icon.error {
            background: #fee2e2;
            color: #991b1b;
        }
        .btn {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-secondary {
            background: #6b7280;
        }
        .code-block {
            background: #1f2937;
            color: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
            margin: 15px 0;
        }
        .info-box {
            background: #f0f9ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .info-box strong {
            color: #1e40af;
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 WhatsApp Gateway Installer</h1>
            <p>Self-Hosted WhatsApp Gateway untuk MikhMon</p>
        </div>
        
        <div class="content">
            <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>❌ Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>✅ Success:</strong> <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>
            
            <!-- Step 1: Check Requirements -->
            <div class="step">
                <div class="step-number <?= $step >= 1 ? 'active' : 'inactive' ?>">1</div>
                <div class="step-content">
                    <h2>Check Requirements</h2>
                    <p>Memeriksa kebutuhan sistem untuk WhatsApp Gateway</p>
                </div>
            </div>
            
            <?php if ($step == 1): ?>
            <div class="check-item">
                <div class="check-icon <?= $nodeInfo['installed'] ? 'success' : 'error' ?>">
                    <?= $nodeInfo['installed'] ? '✓' : '✗' ?>
                </div>
                <div>
                    <strong>Node.js</strong><br>
                    <?= $nodeInfo['message'] ?>
                </div>
            </div>
            
            <div class="check-item">
                <div class="check-icon <?= $npmInfo['installed'] ? 'success' : 'error' ?>">
                    <?= $npmInfo['installed'] ? '✓' : '✗' ?>
                </div>
                <div>
                    <strong>npm (Node Package Manager)</strong><br>
                    <?= $npmInfo['message'] ?>
                </div>
            </div>
            
            <?php if (!$nodeInfo['installed']): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Node.js Not Installed!</strong><br>
                WhatsApp Gateway membutuhkan Node.js untuk berjalan.<br><br>
                <strong>Cara Install:</strong><br>
                <strong>Windows:</strong> Download dari <a href="https://nodejs.org/" target="_blank">https://nodejs.org/</a> (pilih LTS)<br>
                <strong>Linux:</strong>
                <div class="code-block">curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -<br>sudo apt-get install -y nodejs</div>
                Setelah install, refresh halaman ini.
            </div>
            <?php else: ?>
            <div class="button-group">
                <a href="?step=2" class="btn">Next: Install Dependencies →</a>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- Step 2: Install Dependencies -->
            <div class="step">
                <div class="step-number <?= $step >= 2 ? 'active' : 'inactive' ?>">2</div>
                <div class="step-content">
                    <h2>Install Dependencies</h2>
                    <p>Install Node.js packages yang dibutuhkan</p>
                </div>
            </div>
            
            <?php if ($step == 2): ?>
            <?php if ($depsInstalled): ?>
            <div class="alert alert-success">
                ✅ Dependencies sudah terinstall!
            </div>
            <div class="button-group">
                <a href="?step=3" class="btn">Next: Configure →</a>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <strong>ℹ️ Install Dependencies</strong><br>
                Proses ini akan install packages: Baileys, Express, QRCode, dll.<br>
                Membutuhkan waktu 2-5 menit tergantung koneksi internet.<br>
                <strong>Pastikan koneksi internet stabil!</strong>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="install_dependencies">
                <div class="button-group">
                    <button type="submit" class="btn">Install Dependencies</button>
                    <a href="?step=1" class="btn btn-secondary">← Back</a>
                </div>
            </form>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- Step 3: Configure -->
            <div class="step">
                <div class="step-number <?= $step >= 3 ? 'active' : 'inactive' ?>">3</div>
                <div class="step-content">
                    <h2>Configure</h2>
                    <p>Setup konfigurasi API key dan environment</p>
                </div>
            </div>
            
            <?php if ($step == 3): ?>
            <?php if ($envExists): ?>
            <div class="alert alert-success">
                ✅ Configuration sudah ada!
            </div>
            <div class="button-group">
                <a href="?step=4" class="btn">Next: Complete →</a>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <strong>ℹ️ Create Configuration</strong><br>
                Sistem akan generate API key random dan membuat file .env<br>
                API key yang sama akan diset di MikhMon config.
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_env">
                <div class="button-group">
                    <button type="submit" class="btn">Create Configuration</button>
                    <a href="?step=2" class="btn btn-secondary">← Back</a>
                </div>
            </form>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- Step 4: Complete -->
            <div class="step">
                <div class="step-number <?= $step >= 4 ? 'active' : 'inactive' ?>">4</div>
                <div class="step-content">
                    <h2>Complete!</h2>
                    <p>Instalasi selesai, siap digunakan</p>
                </div>
            </div>
            
            <?php if ($step == 4): ?>
            <div class="alert alert-success">
                <strong>🎉 Installation Complete!</strong><br>
                WhatsApp Gateway sudah siap digunakan!
            </div>
            
            <?php if (isset($_SESSION['api_key'])): ?>
            <div class="info-box">
                <strong>API Key:</strong><br>
                <div class="code-block"><?= $_SESSION['api_key'] ?></div>
                <small>API key ini sudah diset di .env dan whatsapp_config.php</small>
            </div>
            <?php endif; ?>
            
            <div class="alert alert-info">
                <strong>📝 Next Steps:</strong><br>
                1. Start Gateway: <code>cd whatsapp-gateway && npm start</code><br>
                2. Atau dengan PM2: <code>npm run pm2</code><br>
                3. Scan QR Code di Admin Panel<br>
                4. Test kirim pesan!
            </div>
            
            <div class="button-group">
                <a href="../settings/whatsapp_gateway_admin.php" class="btn">Open Admin Panel →</a>
                <a href="?step=1" class="btn btn-secondary">← Start Over</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
