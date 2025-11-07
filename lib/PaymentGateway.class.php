<?php
/*
 * Payment Gateway Integration Class
 * Support for Midtrans and Xendit
 */

class PaymentGateway {
    private $db;
    private $gateway;
    private $config;
    
    public function __construct($gateway = 'midtrans') {
        if (!function_exists('getDBConnection')) {
            require_once(__DIR__ . '/../include/db_config.php');
        }
        $this->db = getDBConnection();
        $this->gateway = $gateway;
        $this->loadConfig();
    }
    
    /**
     * Load payment gateway configuration
     */
    private function loadConfig() {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM agent_settings WHERE setting_key LIKE 'payment_%'");
        
        $this->config = [];
        while ($row = $stmt->fetch()) {
            $this->config[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    /**
     * Create payment for topup
     */
    public function createTopupPayment($agentId, $amount) {
        switch ($this->gateway) {
            case 'midtrans':
                return $this->createMidtransPayment($agentId, $amount);
            case 'xendit':
                return $this->createXenditPayment($agentId, $amount);
            default:
                return ['success' => false, 'message' => 'Gateway not supported'];
        }
    }
    
    /**
     * Create Midtrans payment
     */
    private function createMidtransPayment($agentId, $amount) {
        $serverKey = $this->config['payment_midtrans_server_key'] ?? '';
        $isProduction = ($this->config['payment_midtrans_environment'] ?? 'sandbox') == 'production';
        
        if (empty($serverKey)) {
            return ['success' => false, 'message' => 'Midtrans not configured'];
        }
        
        // Get agent data
        $stmt = $this->db->prepare("SELECT * FROM agents WHERE id = ?");
        $stmt->execute([$agentId]);
        $agent = $stmt->fetch();
        
        if (!$agent) {
            return ['success' => false, 'message' => 'Agent not found'];
        }
        
        // Generate order ID
        $orderId = 'TOPUP-' . $agentId . '-' . time();
        
        // Midtrans API endpoint
        $url = $isProduction 
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
        
        // Prepare transaction data
        $transactionData = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $amount
            ],
            'customer_details' => [
                'first_name' => $agent['agent_name'],
                'email' => $agent['email'] ?? 'agent@example.com',
                'phone' => $agent['phone']
            ],
            'item_details' => [[
                'id' => 'TOPUP',
                'price' => $amount,
                'quantity' => 1,
                'name' => 'Topup Saldo Agent'
            ]]
        ];
        
        // Make API request
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transactionData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($serverKey . ':')
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 201) {
            $result = json_decode($response, true);
            
            // Save payment record
            $this->savePaymentRecord($orderId, $agentId, $amount, 'midtrans', 'pending');
            
            return [
                'success' => true,
                'order_id' => $orderId,
                'snap_token' => $result['token'],
                'redirect_url' => $result['redirect_url']
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to create payment'];
    }
    
    /**
     * Create Xendit payment
     */
    private function createXenditPayment($agentId, $amount) {
        $apiKey = $this->config['payment_xendit_api_key'] ?? '';
        
        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Xendit not configured'];
        }
        
        // Get agent data
        $stmt = $this->db->prepare("SELECT * FROM agents WHERE id = ?");
        $stmt->execute([$agentId]);
        $agent = $stmt->fetch();
        
        if (!$agent) {
            return ['success' => false, 'message' => 'Agent not found'];
        }
        
        // Generate external ID
        $externalId = 'TOPUP-' . $agentId . '-' . time();
        
        // Xendit Invoice API
        $url = 'https://api.xendit.co/v2/invoices';
        
        // Prepare invoice data
        $invoiceData = [
            'external_id' => $externalId,
            'amount' => $amount,
            'payer_email' => $agent['email'] ?? 'agent@example.com',
            'description' => 'Topup Saldo Agent - ' . $agent['agent_name'],
            'customer' => [
                'given_names' => $agent['agent_name'],
                'mobile_number' => $agent['phone']
            ]
        ];
        
        // Make API request
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($invoiceData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($apiKey . ':')
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 || $httpCode == 201) {
            $result = json_decode($response, true);
            
            // Save payment record
            $this->savePaymentRecord($externalId, $agentId, $amount, 'xendit', 'pending');
            
            return [
                'success' => true,
                'order_id' => $externalId,
                'invoice_url' => $result['invoice_url']
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to create invoice'];
    }
    
    /**
     * Handle payment callback/webhook
     */
    public function handleCallback($data) {
        switch ($this->gateway) {
            case 'midtrans':
                return $this->handleMidtransCallback($data);
            case 'xendit':
                return $this->handleXenditCallback($data);
            default:
                return false;
        }
    }
    
    /**
     * Handle Midtrans callback
     */
    private function handleMidtransCallback($data) {
        $orderId = $data['order_id'] ?? '';
        $status = $data['transaction_status'] ?? '';
        
        if ($status == 'settlement' || $status == 'capture') {
            return $this->processSuccessfulPayment($orderId);
        }
        
        return false;
    }
    
    /**
     * Handle Xendit callback
     */
    private function handleXenditCallback($data) {
        $externalId = $data['external_id'] ?? '';
        $status = $data['status'] ?? '';
        
        if ($status == 'PAID') {
            return $this->processSuccessfulPayment($externalId);
        }
        
        return false;
    }
    
    /**
     * Process successful payment
     */
    private function processSuccessfulPayment($orderId) {
        // Get payment record
        $stmt = $this->db->prepare("SELECT * FROM payment_records WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $payment = $stmt->fetch();
        
        if (!$payment || $payment['status'] != 'pending') {
            return false;
        }
        
        // Update payment status
        $stmt = $this->db->prepare("UPDATE payment_records SET status = 'paid', paid_at = NOW() WHERE order_id = ?");
        $stmt->execute([$orderId]);
        
        // Topup agent balance
        include_once(__DIR__ . '/Agent.class.php');
        $agent = new Agent();
        $result = $agent->topupBalance(
            $payment['agent_id'],
            $payment['amount'],
            'Auto topup via ' . $payment['gateway'],
            'system'
        );
        
        // Send notification
        if ($result['success']) {
            include_once(__DIR__ . '/WhatsAppNotification.class.php');
            $notification = new WhatsAppNotification();
            $notification->notifyTopupApproved(
                $payment['agent_id'],
                $payment['amount'],
                $result['balance_after']
            );
        }
        
        return $result['success'];
    }
    
    /**
     * Save payment record
     */
    private function savePaymentRecord($orderId, $agentId, $amount, $gateway, $status) {
        $stmt = $this->db->prepare("
            INSERT INTO payment_records (order_id, agent_id, amount, gateway, status, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$orderId, $agentId, $amount, $gateway, $status]);
    }
}
