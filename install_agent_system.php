<?php
/*
 * Agent System Installer
 * Auto-setup database untuk sistem agent/reseller
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Handle installation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test_connection'])) {
        $host = $_POST['db_host'];
        $user = $_POST['db_user'];
        $pass = $_POST['db_pass'];
        
        try {
            $conn = new PDO("mysql:host=$host", $user, $pass);
            $success = "✅ Koneksi database berhasil!";
            $step = 2;
        } catch (PDOException $e) {
            $error = "❌ Koneksi gagal: " . $e->getMessage();
        }
    } elseif (isset($_POST['install_database'])) {
        $host = $_POST['db_host'];
        $user = $_POST['db_user'];
        $pass = $_POST['db_pass'];
        $dbname = $_POST['db_name'];
        
        try {
            // Connect to MySQL
            $conn = new PDO("mysql:host=$host", $user, $pass);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database
            $conn->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $conn->exec("USE `$dbname`");
            
            // Read SQL file
            $sqlFile = __DIR__ . '/database/agent_system.sql';
            if (!file_exists($sqlFile)) {
                throw new Exception("File agent_system.sql tidak ditemukan!");
            }
            
            $sql = file_get_contents($sqlFile);
            
            // Remove comments and split by semicolon
            $sql = preg_replace('/--.*$/m', '', $sql);
            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
            
            // Execute SQL statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            $executed = 0;
            $failed = 0;
            
            foreach ($statements as $statement) {
                if (empty($statement)) continue;
                
                try {
                    $conn->exec($statement);
                    $executed++;
                } catch (PDOException $e) {
                    // Skip if table already exists
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $failed++;
                    }
                }
            }
            
            // Update db_config.php
            $configFile = __DIR__ . '/include/db_config.php';
            $configContent = file_get_contents($configFile);
            
            $configContent = preg_replace(
                "/define\('DB_HOST', '.*?'\);/",
                "define('DB_HOST', '$host');",
                $configContent
            );
            $configContent = preg_replace(
                "/define\('DB_USER', '.*?'\);/",
                "define('DB_USER', '$user');",
                $configContent
            );
            $configContent = preg_replace(
                "/define\('DB_PASS', '.*?'\);/",
                "define('DB_PASS', '$pass');",
                $configContent
            );
            $configContent = preg_replace(
                "/define\('DB_NAME', '.*?'\);/",
                "define('DB_NAME', '$dbname');",
                $configContent
            );
            
            file_put_contents($configFile, $configContent);
            
            $success = "✅ Database berhasil dibuat! <br>Database: <strong>$dbname</strong><br>Tables created: <strong>$executed</strong>";
            $step = 3;
            
        } catch (Exception $e) {
            $error = "❌ Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent System Installer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .header p { opacity: 0.9; }
        .content { padding: 40px; }
        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }
        .steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e0e0e0;
            z-index: 0;
        }
        .step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #999;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
        }
        .step.active .step-circle {
            background: #667eea;
            color: white;
        }
        .step.completed .step-circle {
            background: #10b981;
            color: white;
        }
        .step-label {
            font-size: 14px;
            color: #666;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-danger {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        .alert-info {
            background: #e7f3ff;
            color: #0066cc;
            border: 1px solid #b3d9ff;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .info-box h3 {
            margin-bottom: 10px;
            color: #667eea;
        }
        .info-box ul {
            margin-left: 20px;
            line-height: 1.8;
        }
        .success-icon {
            font-size: 80px;
            color: #10b981;
            text-align: center;
            margin: 20px 0;
        }
        .link-box {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }
        .link-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
        }
        .link-card:hover {
            background: #667eea;
            color: white;
            transform: translateY(-5px);
        }
        .link-card i {
            font-size: 32px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Agent System Installer</h1>
            <p>Setup database untuk sistem agent/reseller MikhMon</p>
        </div>

        <div class="content">
            <!-- Steps -->
            <div class="steps">
                <div class="step <?= $step >= 1 ? 'active' : ''; ?> <?= $step > 1 ? 'completed' : ''; ?>">
                    <div class="step-circle">1</div>
                    <div class="step-label">Koneksi DB</div>
                </div>
                <div class="step <?= $step >= 2 ? 'active' : ''; ?> <?= $step > 2 ? 'completed' : ''; ?>">
                    <div class="step-circle">2</div>
                    <div class="step-label">Install DB</div>
                </div>
                <div class="step <?= $step >= 3 ? 'active' : ''; ?>">
                    <div class="step-circle">3</div>
                    <div class="step-label">Selesai</div>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa fa-exclamation-circle"></i> <?= $error; ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa fa-check-circle"></i> <?= $success; ?>
            </div>
            <?php endif; ?>

            <!-- Step 1: Test Connection -->
            <?php if ($step == 1): ?>
            <div class="info-box">
                <h3><i class="fa fa-info-circle"></i> Informasi</h3>
                <ul>
                    <li>Pastikan MySQL/MariaDB sudah terinstall</li>
                    <li>Siapkan username dan password database</li>
                    <li>Database akan dibuat otomatis</li>
                </ul>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" name="db_host" class="form-control" value="localhost" required>
                </div>

                <div class="form-group">
                    <label>Database Username</label>
                    <input type="text" name="db_user" class="form-control" value="root" required>
                </div>

                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" name="db_pass" class="form-control" placeholder="Kosongkan jika tidak ada password">
                </div>

                <button type="submit" name="test_connection" class="btn btn-primary">
                    <i class="fa fa-plug"></i> Test Koneksi
                </button>
            </form>
            <?php endif; ?>

            <!-- Step 2: Install Database -->
            <?php if ($step == 2): ?>
            <div class="info-box">
                <h3><i class="fa fa-database"></i> Siap Install Database</h3>
                <p>Installer akan membuat:</p>
                <ul>
                    <li>Database baru</li>
                    <li>10 tables untuk sistem agent</li>
                    <li>Views untuk reporting</li>
                    <li>Stored procedures</li>
                    <li>Sample data (opsional)</li>
                </ul>
            </div>

            <form method="POST">
                <input type="hidden" name="db_host" value="<?= $_POST['db_host'] ?? 'localhost'; ?>">
                <input type="hidden" name="db_user" value="<?= $_POST['db_user'] ?? 'root'; ?>">
                <input type="hidden" name="db_pass" value="<?= $_POST['db_pass'] ?? ''; ?>">

                <div class="form-group">
                    <label>Nama Database</label>
                    <input type="text" name="db_name" class="form-control" value="mikhmon_agents" required>
                </div>

                <button type="submit" name="install_database" class="btn btn-primary">
                    <i class="fa fa-download"></i> Install Database
                </button>
            </form>
            <?php endif; ?>

            <!-- Step 3: Success -->
            <?php if ($step == 3): ?>
            <div class="success-icon">
                <i class="fa fa-check-circle"></i>
            </div>

            <h2 style="text-align: center; margin-bottom: 20px;">Instalasi Berhasil! 🎉</h2>

            <div class="alert alert-info">
                <strong>Database berhasil dibuat!</strong><br>
                Sistem agent/reseller siap digunakan.
            </div>

            <div class="info-box">
                <h3><i class="fa fa-key"></i> Login Demo</h3>
                <p><strong>Agent Panel:</strong></p>
                <ul>
                    <li>Phone: 081234567890</li>
                    <li>Password: agent123</li>
                </ul>
                <p style="margin-top: 10px;"><strong>Admin Panel:</strong></p>
                <ul>
                    <li>Login via MikhMon → Menu Agent/Reseller</li>
                </ul>
            </div>

            <div class="link-box">
                <a href="admin.php?id=login" class="link-card">
                    <i class="fa fa-user-secret"></i>
                    <h3>Admin Panel</h3>
                    <p>Kelola Agent</p>
                </a>
                <a href="agent/index.php" class="link-card">
                    <i class="fa fa-users"></i>
                    <h3>Agent Panel</h3>
                    <p>Login Agent</p>
                </a>
            </div>

            <div style="margin-top: 20px; text-align: center;">
                <a href="whatsapp_start.html" class="btn btn-success">
                    <i class="fa fa-whatsapp"></i> Setup WhatsApp Integration
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
