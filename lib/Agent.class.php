<?php
/*
 * Agent Class for Agent/Reseller System
 * MikhMon WhatsApp Integration
 */

class Agent {
    private $db;
    
    public function __construct() {
        // Ensure db_config is included
        if (!function_exists('getDBConnection')) {
            require_once(__DIR__ . '/../include/db_config.php');
        }
        $this->db = getDBConnection();
        
        if (!$this->db) {
            throw new Exception('Database connection failed');
        }
    }
    
    /**
     * Create new agent
     */
    public function createAgent($data) {
        try {
            $sql = "INSERT INTO agents (agent_code, agent_name, phone, email, password, balance, status, level, commission_percent, created_by, notes) 
                    VALUES (:agent_code, :agent_name, :phone, :email, :password, :balance, :status, :level, :commission_percent, :created_by, :notes)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':agent_code' => $data['agent_code'],
                ':agent_name' => $data['agent_name'],
                ':phone' => $data['phone'],
                ':email' => $data['email'] ?? null,
                ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
                ':balance' => $data['balance'] ?? 0,
                ':status' => $data['status'] ?? 'active',
                ':level' => $data['level'] ?? 'bronze',
                ':commission_percent' => $data['commission_percent'] ?? 0,
                ':created_by' => $data['created_by'] ?? 'admin',
                ':notes' => $data['notes'] ?? null
            ]);
            
            return [
                'success' => true,
                'agent_id' => $this->db->lastInsertId(),
                'message' => 'Agent berhasil dibuat'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get agent by ID
     */
    public function getAgentById($id) {
        $sql = "SELECT * FROM agents WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
    
    /**
     * Get agent by phone
     */
    public function getAgentByPhone($phone) {
        $sql = "SELECT * FROM agents WHERE phone = :phone";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':phone' => $phone]);
        return $stmt->fetch();
    }
    
    /**
     * Get agent by code
     */
    public function getAgentByCode($code) {
        $sql = "SELECT * FROM agents WHERE agent_code = :code";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':code' => $code]);
        return $stmt->fetch();
    }
    
    /**
     * Get all agents
     */
    public function getAllAgents($status = null) {
        $sql = "SELECT * FROM agents";
        if ($status) {
            $sql .= " WHERE status = :status";
        }
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        if ($status) {
            $stmt->execute([':status' => $status]);
        } else {
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }
    
    /**
     * Update agent
     */
    public function updateAgent($id, $data) {
        try {
            $fields = [];
            $params = [':id' => $id];
            
            foreach ($data as $key => $value) {
                if ($key != 'id' && $key != 'password') {
                    $fields[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }
            
            if (isset($data['password'])) {
                $fields[] = "password = :password";
                $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            $sql = "UPDATE agents SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'message' => 'Agent berhasil diupdate'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Delete agent
     */
    public function deleteAgent($id) {
        try {
            $sql = "DELETE FROM agents WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            return ['success' => true, 'message' => 'Agent berhasil dihapus'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Topup agent balance
     */
    public function topupBalance($agentId, $amount, $description = '', $createdBy = 'admin') {
        try {
            $this->db->beginTransaction();
            
            // Get current balance
            $agent = $this->getAgentById($agentId);
            $balanceBefore = $agent['balance'];
            $balanceAfter = $balanceBefore + $amount;
            
            // Update balance
            $sql = "UPDATE agents SET balance = :balance WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':balance' => $balanceAfter, ':id' => $agentId]);
            
            // Insert transaction
            $sql = "INSERT INTO agent_transactions (agent_id, transaction_type, amount, balance_before, balance_after, description, created_by) 
                    VALUES (:agent_id, 'topup', :amount, :balance_before, :balance_after, :description, :created_by)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':agent_id' => $agentId,
                ':amount' => $amount,
                ':balance_before' => $balanceBefore,
                ':balance_after' => $balanceAfter,
                ':description' => $description,
                ':created_by' => $createdBy
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'message' => 'Topup berhasil'
            ];
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Deduct agent balance
     */
    public function deductBalance($agentId, $amount, $profileName, $username, $description = '') {
        try {
            $this->db->beginTransaction();
            
            // Get current balance
            $agent = $this->getAgentById($agentId);
            $balanceBefore = $agent['balance'];
            
            // Check if balance sufficient
            if ($balanceBefore < $amount) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Saldo tidak mencukupi. Saldo Anda: Rp ' . number_format($balanceBefore, 0, ',', '.')
                ];
            }
            
            $balanceAfter = $balanceBefore - $amount;
            
            // Update balance
            $sql = "UPDATE agents SET balance = :balance WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':balance' => $balanceAfter, ':id' => $agentId]);
            
            // Insert transaction
            $sql = "INSERT INTO agent_transactions (agent_id, transaction_type, amount, balance_before, balance_after, profile_name, voucher_username, description) 
                    VALUES (:agent_id, 'generate', :amount, :balance_before, :balance_after, :profile_name, :username, :description)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':agent_id' => $agentId,
                ':amount' => $amount,
                ':balance_before' => $balanceBefore,
                ':balance_after' => $balanceAfter,
                ':profile_name' => $profileName,
                ':username' => $username,
                ':description' => $description
            ]);
            
            $transactionId = $this->db->lastInsertId();
            
            $this->db->commit();
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'message' => 'Saldo berhasil dipotong'
            ];
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get agent balance
     */
    public function getBalance($agentId) {
        $agent = $this->getAgentById($agentId);
        return $agent ? $agent['balance'] : 0;
    }
    
    /**
     * Get agent transactions
     */
    public function getTransactions($agentId, $limit = 50) {
        $sql = "SELECT * FROM agent_transactions WHERE agent_id = :agent_id ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':agent_id', $agentId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Set agent price for profile
     */
    public function setAgentPrice($agentId, $profileName, $buyPrice, $sellPrice) {
        try {
            $sql = "INSERT INTO agent_prices (agent_id, profile_name, buy_price, sell_price) 
                    VALUES (:agent_id, :profile_name, :buy_price, :sell_price)
                    ON DUPLICATE KEY UPDATE buy_price = VALUES(buy_price), sell_price = VALUES(sell_price)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':agent_id' => $agentId,
                ':profile_name' => $profileName,
                ':buy_price' => $buyPrice,
                ':sell_price' => $sellPrice
            ]);
            
            return ['success' => true, 'message' => 'Harga berhasil diset'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get agent price for profile
     */
    public function getAgentPrice($agentId, $profileName) {
        $sql = "SELECT * FROM agent_prices WHERE agent_id = :agent_id AND profile_name = :profile_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':agent_id' => $agentId, ':profile_name' => $profileName]);
        return $stmt->fetch();
    }
    
    /**
     * Get all agent prices
     */
    public function getAllAgentPrices($agentId) {
        $sql = "SELECT * FROM agent_prices WHERE agent_id = :agent_id ORDER BY profile_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':agent_id' => $agentId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Delete agent price
     */
    public function deleteAgentPrice($priceId) {
        try {
            $sql = "DELETE FROM agent_prices WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $priceId]);
            return ['success' => true, 'message' => 'Harga berhasil dihapus'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Verify agent login
     */
    public function verifyLogin($phone, $password) {
        $agent = $this->getAgentByPhone($phone);
        
        if (!$agent) {
            return ['success' => false, 'message' => 'Agent tidak ditemukan'];
        }
        
        if ($agent['status'] != 'active') {
            return ['success' => false, 'message' => 'Agent tidak aktif'];
        }
        
        if (password_verify($password, $agent['password'])) {
            // Update last login
            $sql = "UPDATE agents SET last_login = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $agent['id']]);
            
            return [
                'success' => true,
                'agent' => $agent,
                'message' => 'Login berhasil'
            ];
        } else {
            return ['success' => false, 'message' => 'Password salah'];
        }
    }
    
    /**
     * Generate unique agent code
     */
    public function generateAgentCode() {
        do {
            $code = 'AG' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $exists = $this->getAgentByCode($code);
        } while ($exists);
        
        return $code;
    }
    
    /**
     * Get agent summary
     */
    public function getAgentSummary($agentId) {
        $sql = "SELECT * FROM agent_summary WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $agentId]);
        return $stmt->fetch();
    }
}
