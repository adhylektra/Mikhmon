<?php
/*
 * WhatsApp Notification Class
 * Send automated notifications via WhatsApp
 */

class WhatsAppNotification {
    private $db;
    private $messageSettings;
    
    public function __construct() {
        if (!function_exists('getDBConnection')) {
            require_once(__DIR__ . '/../include/db_config.php');
        }
        $this->db = getDBConnection();
        $this->loadMessageSettings();
    }
    
    /**
     * Load message settings
     */
    private function loadMessageSettings() {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM agent_settings WHERE setting_key LIKE 'wa_%'");
        
        $this->messageSettings = [
            'header' => '',
            'footer' => '',
            'business_name' => 'WiFi Hotspot',
            'business_phone' => '08123456789',
            'business_address' => 'Jl. Contoh No. 123'
        ];
        
        while ($row = $stmt->fetch()) {
            switch ($row['setting_key']) {
                case 'wa_message_header':
                    $this->messageSettings['header'] = $row['setting_value'];
                    break;
                case 'wa_message_footer':
                    $this->messageSettings['footer'] = $row['setting_value'];
                    break;
                case 'wa_business_name':
                    $this->messageSettings['business_name'] = $row['setting_value'];
                    break;
                case 'wa_business_phone':
                    $this->messageSettings['business_phone'] = $row['setting_value'];
                    break;
                case 'wa_business_address':
                    $this->messageSettings['business_address'] = $row['setting_value'];
                    break;
            }
        }
    }
    
    /**
     * Format message with header and footer
     */
    private function formatMessage($content) {
        $header = $this->messageSettings['header'];
        $footer = $this->messageSettings['footer'];
        
        // Replace variables
        $footer = str_replace('{business_name}', $this->messageSettings['business_name'], $footer);
        $footer = str_replace('{business_phone}', $this->messageSettings['business_phone'], $footer);
        $footer = str_replace('{business_address}', $this->messageSettings['business_address'], $footer);
        
        $message = '';
        if (!empty($header)) {
            $message .= $header . "\n\n";
        }
        $message .= $content;
        if (!empty($footer)) {
            $message .= "\n" . $footer;
        }
        
        return $message;
    }
    
    /**
     * Send WhatsApp message
     */
    private function sendMessage($phone, $message) {
        if (!function_exists('sendWhatsAppMessage')) {
            require_once(__DIR__ . '/../include/whatsapp_config.php');
        }
        return sendWhatsAppMessage($phone, $message);
    }
    
    /**
     * Notify agent about low balance
     */
    public function notifyLowBalance($agentId, $currentBalance, $threshold) {
        $agent = $this->getAgent($agentId);
        if (!$agent) return false;
        
        $content = "⚠️ *PERINGATAN SALDO RENDAH*\n\n";
        $content .= "Halo *{$agent['agent_name']}*,\n\n";
        $content .= "Saldo Anda saat ini:\n";
        $content .= "💰 Rp " . number_format($currentBalance, 0, ',', '.') . "\n\n";
        $content .= "Batas minimum: Rp " . number_format($threshold, 0, ',', '.') . "\n\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $content .= "Silakan lakukan topup agar dapat terus generate voucher.\n\n";
        $content .= "Cara topup:\n";
        $content .= "Ketik: *TOPUP <JUMLAH>*\n";
        $content .= "Contoh: TOPUP 100000";
        
        $message = $this->formatMessage($content);
        return $this->sendMessage($agent['phone'], $message);
    }
    
    /**
     * Notify admin about topup request
     */
    public function notifyTopupRequest($agentId, $amount, $paymentProof = '') {
        $agent = $this->getAgent($agentId);
        if (!$agent) return false;
        
        // Get admin numbers
        $adminNumbers = $this->getAdminNumbers();
        if (empty($adminNumbers)) return false;
        
        $content = "🔔 *TOPUP REQUEST BARU*\n\n";
        $content .= "Agent: *{$agent['agent_name']}*\n";
        $content .= "Kode: {$agent['agent_code']}\n";
        $content .= "Phone: {$agent['phone']}\n\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $content .= "Jumlah Topup:\n";
        $content .= "💰 *Rp " . number_format($amount, 0, ',', '.') . "*\n\n";
        $content .= "Saldo Saat Ini:\n";
        $content .= "Rp " . number_format($agent['balance'], 0, ',', '.') . "\n\n";
        if (!empty($paymentProof)) {
            $content .= "Bukti Transfer: ✅\n\n";
        }
        $content .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $content .= "Silakan proses topup via admin panel.";
        
        $message = $this->formatMessage($content);
        
        // Send to all admin numbers
        $results = [];
        foreach ($adminNumbers as $adminPhone) {
            $results[] = $this->sendMessage($adminPhone, $message);
        }
        
        return in_array(true, $results);
    }
    
    /**
     * Notify customer about expired voucher
     */
    public function notifyVoucherExpired($phone, $username, $profileName) {
        $content = "⏰ *VOUCHER EXPIRED*\n\n";
        $content .= "Voucher Anda telah expired:\n\n";
        $content .= "Username: `$username`\n";
        $content .= "Profile: *$profileName*\n\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $content .= "Ingin beli voucher baru?\n";
        $content .= "Ketik: *HARGA*\n\n";
        $content .= "Atau hubungi kami untuk perpanjangan.";
        
        $message = $this->formatMessage($content);
        return $this->sendMessage($phone, $message);
    }
    
    /**
     * Notify topup approved
     */
    public function notifyTopupApproved($agentId, $amount, $newBalance) {
        $agent = $this->getAgent($agentId);
        if (!$agent) return false;
        
        $content = "✅ *TOPUP BERHASIL*\n\n";
        $content .= "Halo *{$agent['agent_name']}*,\n\n";
        $content .= "Topup Anda telah diproses!\n\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $content .= "Jumlah Topup:\n";
        $content .= "💰 Rp " . number_format($amount, 0, ',', '.') . "\n\n";
        $content .= "Saldo Baru:\n";
        $content .= "💵 *Rp " . number_format($newBalance, 0, ',', '.')  . "*\n\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $content .= "Sekarang Anda dapat generate voucher.\n";
        $content .= "Ketik: *GEN <PROFILE> <QTY>*";
        
        $message = $this->formatMessage($content);
        return $this->sendMessage($agent['phone'], $message);
    }
    
    /**
     * Send sales report
     */
    public function sendSalesReport($agentId, $period = 'today') {
        $agent = $this->getAgent($agentId);
        if (!$agent) return false;
        
        $stats = $this->getSalesStats($agentId, $period);
        
        $periodText = $period == 'today' ? 'Hari Ini' : ($period == 'week' ? 'Minggu Ini' : 'Bulan Ini');
        
        $content = "📊 *LAPORAN PENJUALAN*\n";
        $content .= "*$periodText*\n\n";
        $content .= "Agent: {$agent['agent_name']}\n";
        $content .= "Kode: {$agent['agent_code']}\n\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $content .= "📈 *Statistik:*\n\n";
        $content .= "• Voucher Terjual: {$stats['total_vouchers']}\n";
        $content .= "• Total Penjualan: Rp " . number_format($stats['total_sales'], 0, ',', '.') . "\n";
        $content .= "• Total Profit: Rp " . number_format($stats['total_profit'], 0, ',', '.') . "\n\n";
        
        if (!empty($stats['by_profile'])) {
            $content .= "📋 *Per Profile:*\n\n";
            foreach ($stats['by_profile'] as $profile) {
                $content .= "• {$profile['profile']}: {$profile['count']} voucher\n";
            }
        }
        
        $content .= "\n━━━━━━━━━━━━━━━━━━━━\n\n";
        $content .= "💰 Saldo Saat Ini:\n";
        $content .= "Rp " . number_format($agent['balance'], 0, ',', '.');
        
        $message = $this->formatMessage($content);
        return $this->sendMessage($agent['phone'], $message);
    }
    
    /**
     * Broadcast message to customers
     */
    public function broadcastToCustomers($agentId, $messageContent) {
        // Get customers who bought from this agent
        $stmt = $this->db->prepare("
            SELECT DISTINCT customer_phone, customer_name 
            FROM agent_vouchers 
            WHERE agent_id = ? AND customer_phone IS NOT NULL AND customer_phone != ''
        ");
        $stmt->execute([$agentId]);
        $customers = $stmt->fetchAll();
        
        if (empty($customers)) return ['success' => false, 'message' => 'Tidak ada customer'];
        
        $content = "📢 *BROADCAST MESSAGE*\n\n";
        $content .= $messageContent;
        
        $message = $this->formatMessage($content);
        
        $successCount = 0;
        foreach ($customers as $customer) {
            if ($this->sendMessage($customer['customer_phone'], $message)) {
                $successCount++;
            }
            usleep(500000); // Delay 0.5 detik antar pesan
        }
        
        return [
            'success' => true,
            'total' => count($customers),
            'sent' => $successCount
        ];
    }
    
    /**
     * Get agent data
     */
    private function getAgent($agentId) {
        $stmt = $this->db->prepare("SELECT * FROM agents WHERE id = ?");
        $stmt->execute([$agentId]);
        return $stmt->fetch();
    }
    
    /**
     * Get admin numbers
     */
    private function getAdminNumbers() {
        $stmt = $this->db->query("SELECT setting_value FROM agent_settings WHERE setting_key = 'admin_whatsapp_numbers'");
        $result = $stmt->fetch();
        
        if ($result && !empty($result['setting_value'])) {
            return explode(',', $result['setting_value']);
        }
        
        return [];
    }
    
    /**
     * Get sales statistics
     */
    private function getSalesStats($agentId, $period) {
        $dateCondition = '';
        switch ($period) {
            case 'today':
                $dateCondition = "AND DATE(created_at) = CURDATE()";
                break;
            case 'week':
                $dateCondition = "AND YEARWEEK(created_at) = YEARWEEK(NOW())";
                break;
            case 'month':
                $dateCondition = "AND YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())";
                break;
        }
        
        // Total stats
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_vouchers,
                SUM(sell_price) as total_sales,
                SUM(sell_price - buy_price) as total_profit
            FROM agent_vouchers 
            WHERE agent_id = ? $dateCondition
        ");
        $stmt->execute([$agentId]);
        $stats = $stmt->fetch();
        
        // By profile
        $stmt = $this->db->prepare("
            SELECT 
                profile_name as profile,
                COUNT(*) as count
            FROM agent_vouchers 
            WHERE agent_id = ? $dateCondition
            GROUP BY profile_name
            ORDER BY count DESC
        ");
        $stmt->execute([$agentId]);
        $stats['by_profile'] = $stmt->fetchAll();
        
        return $stats;
    }
}
