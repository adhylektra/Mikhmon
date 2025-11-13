<?php
session_start();
header('Content-Type: application/json');

default_timezone_check();

if (!isset($_POST['agent_token']) || !isset($_SESSION['agent_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized request']);
    exit();
}

$agentId = (int)$_SESSION['agent_id'];
$agentToken = $_POST['agent_token'];

include_once('../include/db_config.php');
include_once('../lib/Agent.class.php');
include_once('../lib/DigiflazzClient.class.php');

try {
    $agent = new Agent();
    $agentData = $agent->getAgentById($agentId);
    if (!$agentData) {
        throw new Exception('Agent tidak ditemukan.');
    }

    if ($agentData['status'] !== 'active') {
        throw new Exception('Akun agent tidak aktif.');
    }

    if (md5($agentId . $agentData['phone']) !== $agentToken) {
        throw new Exception('Token agent tidak valid. Mohon login ulang.');
    }

    $productId = (int)($_POST['product_id'] ?? 0);
    $customerNo = trim($_POST['customer_no'] ?? '');
    $customerName = trim($_POST['customer_name'] ?? '');

    if ($productId <= 0 || $customerNo === '') {
        throw new Exception('Produk atau nomor tujuan belum lengkap.');
    }

    $pdo = getDBConnection();

    $stmt = $pdo->prepare('SELECT * FROM digiflazz_products WHERE id = :id AND status = "active"');
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new Exception('Produk tidak ditemukan atau tidak aktif.');
    }

    $digiflazzClient = new DigiflazzClient();
    if (!$digiflazzClient->isEnabled()) {
        throw new Exception('Integrasi Digiflazz belum dikonfigurasi. Hubungi administrator.');
    }
    $digiflazzSettings = $digiflazzClient->getSettings();
    $defaultMarkupNominal = isset($digiflazzSettings['default_markup_nominal']) ? (int)$digiflazzSettings['default_markup_nominal'] : 0;

    $productType = strtolower($product['type'] ?? 'prepaid');
    $refId = $digiflazzClient->generateRefId('DF' . ($agentData['agent_code'] ?? $agentId));
    $billData = null;
    $costPrice = (int)$product['price'];

    if ($costPrice <= 0 && isset($product['buyer_price'])) {
        $costPrice = (int)$product['buyer_price'];
    }

    $payload = [
        'buyer_sku_code' => $product['buyer_sku_code'],
        'customer_no' => $customerNo,
        'ref_id' => $refId
    ];

    if ($customerName !== '') {
        $payload['customer_name'] = $customerName;
    }

    $sellPrice = 0;

    if ($productType === 'postpaid') {
        $billResult = $digiflazzClient->checkBill($product['buyer_sku_code'], $customerNo);
        $billData = $billResult['data'];
        $refId = $billResult['ref_id'];
        $costPrice = isset($billData['total']) ? (int)$billData['total'] : 0;

        if ($costPrice <= 0) {
            throw new Exception('Total tagihan tidak valid.');
        }

        $sellPrice = $costPrice;
        if (!empty($product['seller_price']) && (int)$product['seller_price'] > 0) {
            $sellPrice = (int)$product['seller_price'];
        } elseif ($defaultMarkupNominal > 0) {
            $sellPrice = $costPrice + $defaultMarkupNominal;
        }

        if ($sellPrice < $costPrice) {
            $sellPrice = $costPrice;
        }

        if ($agentData['balance'] < $sellPrice) {
            throw new Exception('Saldo tidak mencukupi. Saldo: Rp ' . number_format($agentData['balance'], 0, ',', '.') . ' | Total bayar: Rp ' . number_format($sellPrice, 0, ',', '.'));
        }

        $payload = [
            'type' => 'postpaid',
            'ref_id' => $refId,
            'buyer_sku_code' => $product['buyer_sku_code'],
            'customer_no' => $customerNo,
            'amount' => $costPrice
        ];
    } else {
        if ($costPrice <= 0) {
            throw new Exception('Harga produk tidak valid.');
        }

        $sellPrice = $costPrice;
        if (!empty($product['seller_price']) && (int)$product['seller_price'] > 0) {
            $sellPrice = (int)$product['seller_price'];
        } elseif ($defaultMarkupNominal > 0) {
            $sellPrice = $costPrice + $defaultMarkupNominal;
        }

        if ($sellPrice < $costPrice) {
            $sellPrice = $costPrice;
        }

        if ($agentData['balance'] < $sellPrice) {
            throw new Exception('Saldo tidak mencukupi. Saldo: Rp ' . number_format($agentData['balance'], 0, ',', '.') . ' | Total bayar: Rp ' . number_format($sellPrice, 0, ',', '.'));
        }
    }

    $digiflazzResponse = $digiflazzClient->createTransactionWithRetry($payload);
    $digiflazzResponseData = $digiflazzResponse;
    if (isset($digiflazzResponse['data']) && is_array($digiflazzResponse['data'])) {
        $digiflazzResponseData = $digiflazzResponse['data'];
    }

    $finalRefId = $digiflazzResponseData['ref_id'] ?? $refId;

    $statusRaw = strtolower($digiflazzResponseData['status'] ?? '');
    $failureStatuses = ['failed', 'fail', 'gagal', 'refund', 'refunded', 'cancel', 'cancelled', 'canceled', 'expired', 'error'];
    $isFailure = $statusRaw && in_array($statusRaw, $failureStatuses, true);

    $deductResult = null;
    $transactionId = null;
    $balanceBefore = $agentData['balance'];
    $balanceAfter = $agentData['balance'];

    if (!$isFailure) {
        $deductResult = $agent->deductBalance(
            $agentId,
            $sellPrice,
            $product['product_name'],
            $finalRefId,
            'Digiflazz order: ' . $product['product_name'],
            'digiflazz'
        );

        if (!$deductResult['success']) {
            throw new Exception($deductResult['message']);
        }

        $transactionId = $deductResult['transaction_id'];
        $balanceBefore = $deductResult['balance_before'];
        $balanceAfter = $deductResult['balance_after'];
    }
    try {
        $transactionStmt = $pdo->prepare('INSERT INTO digiflazz_transactions (
            agent_id, ref_id, buyer_sku_code, customer_no, customer_name, status, message, price, sell_price, serial_number, response
        ) VALUES (
            :agent_id, :ref_id, :sku, :customer_no, :customer_name, :status, :message, :price, :sell_price, :serial, :response
        )');

        $transactionStmt->execute([
            ':agent_id' => $agentId,
            ':ref_id' => $finalRefId,
            ':sku' => $product['buyer_sku_code'],
            ':customer_no' => $customerNo,
            ':customer_name' => $customerName,
            ':status' => strtolower($digiflazzResponseData['status'] ?? 'pending'),
            ':message' => $digiflazzResponseData['message'] ?? ($digiflazzResponse['message'] ?? ''),
            ':price' => $costPrice,
            ':sell_price' => $sellPrice,
            ':serial' => $digiflazzResponseData['sn'] ?? ($digiflazzResponseData['serial_number'] ?? ''),
            ':response' => json_encode([
                'digiflazz' => $digiflazzResponse,
                'bill' => $billData
            ])
        ]);

        $serialNumber = $digiflazzResponseData['sn'] ?? ($digiflazzResponseData['serial_number'] ?? '');

        if (!empty($serialNumber) && $transactionId) {
            $voucherStmt = $pdo->prepare('INSERT INTO agent_vouchers (
                agent_id, transaction_id, username, password, profile_name, buy_price, sell_price, status, customer_phone, customer_name, sent_via
            ) VALUES (
                :agent_id, :transaction_id, :username, :password, :profile_name, :buy_price, :sell_price, :status, :customer_phone, :customer_name, :sent_via
            )');

            $voucherStmt->execute([
                ':agent_id' => $agentId,
                ':transaction_id' => $transactionId,
                ':username' => $serialNumber,
                ':password' => $serialNumber,
                ':profile_name' => $product['product_name'],
                ':buy_price' => $costPrice,
                ':sell_price' => $sellPrice,
                ':status' => 'active',
                ':customer_phone' => $customerNo,
                ':customer_name' => $customerName,
                ':sent_via' => 'digiflazz'
            ]);
        }
    } catch (Exception $dbException) {
        // Refund balance if recording transaction fails
        if ($transactionId) {
            $agent->topupBalance(
                $agentId,
                $sellPrice,
                'Refund Digiflazz order failure: ' . $product['product_name'],
                'system'
            );
        }
        throw new Exception('Gagal menyimpan data transaksi. ' . $dbException->getMessage());
    }

    $responseMessage = $digiflazzResponseData['message'] ?? ($digiflazzResponse['message'] ?? 'Transaksi berhasil diproses.');
    $updatedAgent = $agent->getAgentById($agentId);

    $responsePayload = [
        'success' => !$isFailure,
        'message' => $responseMessage,
        'status' => strtolower($digiflazzResponseData['status'] ?? ($digiflazzResponse['status'] ?? 'pending')),
        'serial_number' => $digiflazzResponseData['sn'] ?? ($digiflazzResponseData['serial_number'] ?? null),
        'balance' => $updatedAgent['balance'],
        'bill_details' => $billData,
        'sell_price' => $sellPrice,
        'base_price' => $costPrice
    ];

    if ($isFailure) {
        http_response_code(400);
    }

    echo json_encode($responsePayload);
} catch (Exception $e) {
    $fallbackData = [
        'product_id' => $productId ?? 0,
        'customer_no' => $customerNo ?? '',
        'customer_name' => $customerName ?? '',
        'agent_token' => $agentToken
    ];

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'transaction_data' => $fallbackData
    ]);
}

function default_timezone_check() {
    if (!ini_get('date.timezone')) {
        date_default_timezone_set('Asia/Jakarta');
    }
}
