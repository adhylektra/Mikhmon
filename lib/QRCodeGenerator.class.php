<?php
/*
 * QR Code Generator Class
 * Generate QR codes for vouchers
 */

class QRCodeGenerator {
    
    /**
     * Generate QR code for voucher
     * Using Google Charts API (simple, no dependencies)
     */
    public function generateVoucherQR($username, $password, $loginUrl = '') {
        // Build login URL if not provided
        if (empty($loginUrl)) {
            $loginUrl = "http://10.5.50.1/login?username=$username&password=$password";
        }
        
        // Google Charts QR Code API
        $qrUrl = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . urlencode($loginUrl) . "&choe=UTF-8";
        
        return $qrUrl;
    }
    
    /**
     * Generate QR code data URL (base64)
     */
    public function generateQRDataURL($username, $password, $loginUrl = '') {
        $qrUrl = $this->generateVoucherQR($username, $password, $loginUrl);
        
        // Get image data
        $imageData = @file_get_contents($qrUrl);
        
        if ($imageData === false) {
            return null;
        }
        
        // Convert to base64
        $base64 = base64_encode($imageData);
        return "data:image/png;base64,$base64";
    }
    
    /**
     * Save QR code to file
     */
    public function saveQRCode($username, $password, $loginUrl = '', $filename = '') {
        $qrUrl = $this->generateVoucherQR($username, $password, $loginUrl);
        
        // Get image data
        $imageData = @file_get_contents($qrUrl);
        
        if ($imageData === false) {
            return false;
        }
        
        // Generate filename if not provided
        if (empty($filename)) {
            $filename = __DIR__ . '/../temp/qr_' . $username . '.png';
        }
        
        // Ensure directory exists
        $dir = dirname($filename);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Save file
        return file_put_contents($filename, $imageData) !== false;
    }
    
    /**
     * Generate QR code HTML img tag
     */
    public function generateQRImageTag($username, $password, $loginUrl = '', $size = 200) {
        $qrUrl = $this->generateVoucherQR($username, $password, $loginUrl);
        
        // Adjust size in URL
        $qrUrl = str_replace('300x300', "{$size}x{$size}", $qrUrl);
        
        return "<img src='$qrUrl' alt='QR Code' width='$size' height='$size' />";
    }
    
    /**
     * Generate multiple QR codes for vouchers
     */
    public function generateMultipleQR($vouchers, $loginUrlTemplate = '') {
        $qrCodes = [];
        
        foreach ($vouchers as $voucher) {
            $username = $voucher['username'];
            $password = $voucher['password'];
            
            // Replace placeholders in URL template
            $loginUrl = str_replace(['{username}', '{password}'], [$username, $password], $loginUrlTemplate);
            
            $qrCodes[] = [
                'username' => $username,
                'password' => $password,
                'qr_url' => $this->generateVoucherQR($username, $password, $loginUrl),
                'qr_data_url' => $this->generateQRDataURL($username, $password, $loginUrl)
            ];
        }
        
        return $qrCodes;
    }
}
