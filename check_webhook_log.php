<?php
/*
 * Check Webhook Logs
 */

echo "<h2>📝 Webhook Logs</h2>";
echo "<hr>";

$logFile = './logs/webhook_log.txt';

if (file_exists($logFile)) {
    echo "<h3>Last 50 Webhook Requests:</h3>";
    
    $lines = file($logFile);
    $lines = array_reverse($lines);
    $lines = array_slice($lines, 0, 50);
    
    echo "<pre style='background:#f5f5f5;padding:15px;border-radius:4px;max-height:500px;overflow-y:auto;'>";
    foreach ($lines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
    
    echo "<hr>";
    echo "<form method='POST'>";
    echo "<button type='submit' name='clear_log' style='padding:10px 20px;background:#dc3545;color:white;border:none;border-radius:4px;cursor:pointer;'>Clear Log</button>";
    echo "</form>";
    
    if (isset($_POST['clear_log'])) {
        file_put_contents($logFile, '');
        echo "<p style='color:green;'>✅ Log cleared!</p>";
        echo "<script>setTimeout(function(){ location.reload(); }, 1000);</script>";
    }
} else {
    echo "<p style='color:orange;'>⚠️ No webhook log file found.</p>";
    echo "<p>Log file akan dibuat otomatis saat ada webhook request pertama.</p>";
    echo "<p>Location: <code>$logFile</code></p>";
}

echo "<hr>";
echo "<h3>🧪 Test Webhook Manually:</h3>";
echo "<p>Kirim pesan 'HELP' ke nomor WhatsApp MPWA untuk trigger webhook.</p>";
echo "<p>Atau test manual dengan curl:</p>";
echo "<pre>";
echo 'curl -X POST "http://localhost/mikhmon-agent/api/whatsapp_agent_webhook.php" \\' . "\n";
echo '  -H "Content-Type: application/json" \\' . "\n";
echo '  -d \'{' . "\n";
echo '    "sender": "6281947215703",' . "\n";
echo '    "message": "HELP"' . "\n";
echo '  }\'';
echo "</pre>";

echo "<hr>";
echo "<h3>📋 Webhook URL untuk MPWA:</h3>";
echo "<div style='background:#f8f9fa;padding:10px;border-radius:4px;font-family:monospace;'>";
echo "http://" . $_SERVER['HTTP_HOST'] . "/mikhmon-agent/api/whatsapp_agent_webhook.php";
echo "</div>";
echo "<p><small>Daftarkan URL ini di dashboard MPWA → Settings → Webhook</small></p>";
?>
