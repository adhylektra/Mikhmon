<?php
/*
 * Public Payment Gateway Class
 * Support: Tripay, Xendit, Midtrans
 * For public voucher sales
 */

class PublicPayment {
    private $db;
    private $gateway_name;
    private $config;
    
    public function __construct($gateway_name = null) {
        if (!function_exists('getDBConnection')) {
            require_once(__DIR__ . '/../include/db_config.php');
        }
        $this->db = getDBConnection();
        
        error_log("PublicPayment - Constructor called with gateway: " . ($gateway_name ?? 'null'));
        
        if ($gateway_name) {
            $this->gateway_name = $gateway_name;
            $this->loadConfig($gateway_name);
        }
    }
    
    /**
     * Load gateway configuration from database
     */
    private function loadConfig($gateway_name) {
        $stmt = $this->db->prepare("SELECT * FROM payment_gateway_config WHERE gateway_name = :gateway AND is_active = 1");
        $stmt->execute([':gateway' => $gateway_name]);
        $this->config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$this->config) {
            throw new Exception("Payment gateway $gateway_name not configured or inactive");
        }
    }
    
    /**
     * Get active payment gateway
     */
    public function getActiveGateway() {
        $stmt = $this->db->query("SELECT * FROM payment_gateway_config WHERE is_active = 1 LIMIT 1");
        $gateway = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($gateway) {
            $this->gateway_name = $gateway['gateway_name'];
            $this->config = $gateway;
            return $gateway;
        }
        
        return null;
    }
    
    /**
     * Get available payment methods for gateway
     */
    public function getPaymentMethods($amount = null) {
        if (!$this->gateway_name) {
            error_log("PublicPayment - No gateway name set");
            return [];
        }
        
        error_log("PublicPayment - Getting methods for gateway: " . $this->gateway_name . " with amount: " . ($amount ?? 'null'));
        
        $sql = "SELECT * FROM payment_methods 
                WHERE gateway_name = :gateway AND is_active = 1";
        
        $params = [':gateway' => $this->gateway_name];
        
        // Filter by amount if provided
        if ($amount !== null) {
            $sql .= " AND min_amount <= :min_amount AND max_amount >= :max_amount";
            $params[':min_amount'] = $amount;
            $params[':max_amount'] = $amount;
        }
        
        $sql .= " ORDER BY sort_order, id";
        
        try {
            error_log("PublicPayment - SQL: " . $sql);
            error_log("PublicPayment - Params: " . json_encode($params));
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("PublicPayment - Found " . count($methods) . " payment methods for amount " . ($amount ?? 'any'));
            
            return $methods;
        } catch (Exception $e) {
            error_log("PublicPayment - SQL Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create payment transaction
     */
    public function createPayment($data) {
        error_log("PublicPayment - createPayment called for gateway: " . $this->gateway_name);
        error_log("PublicPayment - Payment data: " . json_encode($data));
        
        switch ($this->gateway_name) {
            case 'tripay':
                return $this->createTripayPayment($data);
            case 'xendit':
                return $this->createXenditPayment($data);
            case 'midtrans':
                return $this->createMidtransPayment($data);
            default:
                error_log("PublicPayment - Unsupported gateway: " . $this->gateway_name);
                throw new Exception("Gateway not supported");
        }
    }
    
    /**
     * TRIPAY - Create payment
     */
    private function createTripayPayment($data) {
        $apiKey = $this->config['api_key'];
        $privateKey = $this->config['api_secret'];
        $merchantCode = $this->config['merchant_code'];
        $isSandbox = $this->config['is_sandbox'];
        
        $baseUrl = $isSandbox 
            ? 'https://tripay.co.id/api-sandbox' 
            : 'https://tripay.co.id/api';
        
        // Generate merchant reference
        $merchantRef = 'INV-' . time() . '-' . rand(1000, 9999);
        
        // Calculate signature - amount must be integer for Tripay
        $amount = (int)$data['amount'];
        $signature = hash_hmac('sha256', $merchantCode . $merchantRef . $amount, $privateKey);
        
        error_log("Tripay Payment - Signature data: " . $merchantCode . $merchantRef . $amount);
        error_log("Tripay Payment - Signature: " . $signature);
        
        // Prepare request data
        $requestData = [
            'method' => $data['payment_method'], // QRIS, BRIVA, OVO, etc
            'merchant_ref' => $merchantRef,
            'amount' => $amount,
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'] ?? 'noreply@example.com',
            'customer_phone' => $data['customer_phone'],
            'order_items' => [
                [
                    'name' => $data['product_name'],
                    'price' => $amount,
                    'quantity' => 1
                ]
            ],
            'callback_url' => $data['callback_url'],
            'return_url' => $data['return_url'],
            'expired_time' => (time() + (24 * 60 * 60)), // 24 hours
            'signature' => $signature
        ];
        
        // Make API request
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $baseUrl . '/transaction/create',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $result = json_decode($response, true);
        
        if ($httpCode == 200 && $result['success']) {
            return [
                'success' => true,
                'transaction_id' => $merchantRef,
                'payment_reference' => $result['data']['reference'],
                'payment_url' => $result['data']['checkout_url'],
                'qr_url' => $result['data']['qr_url'] ?? null,
                'virtual_account' => $result['data']['pay_code'] ?? null,
                'amount' => $result['data']['amount'],
                'fee' => $result['data']['total_fee'],
                'expired_at' => date('Y-m-d H:i:s', $result['data']['expired_time']),
                'instructions' => $result['data']['instructions'] ?? [],
                'raw_response' => $result
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['message'] ?? 'Payment creation failed',
            'raw_response' => $result
        ];
    }
    
    /**
     * XENDIT - Create payment
     */
    private function createXenditPayment($data) {
        $apiKey = $this->config['api_key'];
        $isSandbox = $this->config['is_sandbox'];
        
        $baseUrl = 'https://api.xendit.co';
        
        // Generate external ID
        $externalId = 'INV-' . time() . '-' . rand(1000, 9999);
        
        $paymentMethod = $data['payment_method'];
        $amount = $data['amount'];
        
        // Different endpoints for different payment methods
        if (strpos($paymentMethod, 'VA_') === 0) {
            // Virtual Account
            $bankCode = str_replace('VA_', '', $paymentMethod);
            
            $requestData = [
                'external_id' => $externalId,
                'bank_code' => $bankCode,
                'name' => $data['customer_name'],
                'expected_amount' => $amount,
                'is_closed' => true,
                'expiration_date' => date('c', strtotime('+24 hours'))
            ];
            
            $endpoint = '/callback_virtual_accounts';
            
        } elseif ($paymentMethod == 'QRIS') {
            // QRIS
            $requestData = [
                'external_id' => $externalId,
                'type' => 'DYNAMIC',
                'callback_url' => $data['callback_url'],
                'amount' => $amount
            ];
            
            $endpoint = '/qr_codes';
            
        } elseif (in_array($paymentMethod, ['OVO', 'DANA', 'LINKAJA', 'SHOPEEPAY'])) {
            // E-Wallet
            $requestData = [
                'external_id' => $externalId,
                'amount' => $amount,
                'phone' => $data['customer_phone'],
                'ewallet_type' => $paymentMethod,
                'callback_url' => $data['callback_url'],
                'redirect_url' => $data['return_url']
            ];
            
            $endpoint = '/ewallets/charges';
        } else {
            return [
                'success' => false,
                'message' => 'Payment method not supported'
            ];
        }
        
        // Make API request
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $baseUrl . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($apiKey . ':'),
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'transaction_id' => $externalId,
                'payment_reference' => $result['id'] ?? $externalId,
                'payment_url' => $result['invoice_url'] ?? $result['actions']['desktop_web_checkout_url'] ?? null,
                'qr_url' => $result['qr_string'] ?? null,
                'virtual_account' => $result['account_number'] ?? null,
                'amount' => $amount,
                'expired_at' => $result['expiration_date'] ?? date('Y-m-d H:i:s', strtotime('+24 hours')),
                'raw_response' => $result
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['message'] ?? 'Payment creation failed',
            'raw_response' => $result
        ];
    }
    
    /**
     * MIDTRANS - Create payment
     */
    private function createMidtransPayment($data) {
        $serverKey = $this->config['api_key'];
        $isSandbox = $this->config['is_sandbox'];
        
        $baseUrl = $isSandbox 
            ? 'https://app.sandbox.midtrans.com/snap/v1' 
            : 'https://app.midtrans.com/snap/v1';
        
        // Generate order ID
        $orderId = 'INV-' . time() . '-' . rand(1000, 9999);
        
        // Prepare request data
        $requestData = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $data['amount']
            ],
            'customer_details' => [
                'first_name' => $data['customer_name'],
                'email' => $data['customer_email'] ?? 'noreply@example.com',
                'phone' => $data['customer_phone']
            ],
            'item_details' => [
                [
                    'id' => 'VOUCHER',
                    'price' => $data['amount'],
                    'quantity' => 1,
                    'name' => $data['product_name']
                ]
            ],
            'callbacks' => [
                'finish' => $data['return_url']
            ]
        ];
        
        // If specific payment method selected
        if (isset($data['payment_method']) && $data['payment_method'] != 'all') {
            $requestData['enabled_payments'] = [$data['payment_method']];
        }
        
        // Make API request
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $baseUrl . '/payment-links',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($serverKey . ':'),
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $result = json_decode($response, true);
        
        if ($httpCode == 201 || $httpCode == 200) {
            return [
                'success' => true,
                'transaction_id' => $orderId,
                'payment_reference' => $result['token'] ?? $orderId,
                'payment_url' => $result['redirect_url'] ?? null,
                'snap_token' => $result['token'] ?? null,
                'amount' => $data['amount'],
                'expired_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
                'raw_response' => $result
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['error_messages'][0] ?? 'Payment creation failed',
            'raw_response' => $result
        ];
    }
    
    /**
     * Verify callback signature
     */
    public function verifyCallback($data, $signature) {
        switch ($this->gateway_name) {
            case 'tripay':
                return $this->verifyTripayCallback($data, $signature);
            case 'xendit':
                return $this->verifyXenditCallback($data, $signature);
            case 'midtrans':
                return $this->verifyMidtransCallback($data, $signature);
            default:
                return false;
        }
    }
    
    /**
     * Verify Tripay callback
     */
    private function verifyTripayCallback($data, $signature) {
        $privateKey = $this->config['api_secret'];
        $callbackToken = $this->config['callback_token'];
        
        $json = json_encode($data);
        $calculatedSignature = hash_hmac('sha256', $json, $privateKey);
        
        return hash_equals($calculatedSignature, $signature);
    }
    
    /**
     * Verify Xendit callback
     */
    private function verifyXenditCallback($data, $signature) {
        $callbackToken = $this->config['callback_token'];
        
        if (!$callbackToken) {
            return true; // Skip verification if no token set
        }
        
        // Xendit uses X-CALLBACK-TOKEN header
        return hash_equals($callbackToken, $signature);
    }
    
    /**
     * Verify Midtrans callback
     */
    private function verifyMidtransCallback($data, $signature) {
        $serverKey = $this->config['api_key'];
        
        $orderId = $data['order_id'];
        $statusCode = $data['status_code'];
        $grossAmount = $data['gross_amount'];
        
        $calculatedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        
        return hash_equals($calculatedSignature, $signature);
    }
    
    /**
     * Get payment status from callback data
     */
    public function getPaymentStatus($data) {
        switch ($this->gateway_name) {
            case 'tripay':
                return $this->getTripayStatus($data);
            case 'xendit':
                return $this->getXenditStatus($data);
            case 'midtrans':
                return $this->getMidtransStatus($data);
            default:
                return 'unknown';
        }
    }
    
    private function getTripayStatus($data) {
        $status = $data['status'] ?? '';
        
        switch ($status) {
            case 'PAID':
                return 'paid';
            case 'EXPIRED':
                return 'expired';
            case 'FAILED':
                return 'failed';
            case 'REFUND':
                return 'refunded';
            default:
                return 'pending';
        }
    }
    
    private function getXenditStatus($data) {
        $status = $data['status'] ?? '';
        
        switch (strtoupper($status)) {
            case 'PAID':
            case 'COMPLETED':
            case 'SUCCEEDED':
                return 'paid';
            case 'EXPIRED':
                return 'expired';
            case 'FAILED':
                return 'failed';
            default:
                return 'pending';
        }
    }
    
    private function getMidtransStatus($data) {
        $transactionStatus = $data['transaction_status'] ?? '';
        $fraudStatus = $data['fraud_status'] ?? 'accept';
        
        if ($transactionStatus == 'capture') {
            if ($fraudStatus == 'accept') {
                return 'paid';
            }
        } elseif ($transactionStatus == 'settlement') {
            return 'paid';
        } elseif ($transactionStatus == 'pending') {
            return 'pending';
        } elseif (in_array($transactionStatus, ['deny', 'expire', 'cancel'])) {
            return 'failed';
        }
        
        return 'pending';
    }
}
