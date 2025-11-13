<?php
header('Content-Type: application/json');

require_once(__DIR__ . '/../include/db_config.php');
require_once(__DIR__ . '/../lib/BillingService.class.php');

$response = ['success' => false];

function sendJson($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function getInputData(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

try {
    $service = new BillingService();

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $profiles = $service->getProfiles();
            sendJson(['success' => true, 'data' => $profiles]);

        case 'POST':
            $data = getInputData();
            $action = $data['action'] ?? 'create';

            if ($action === 'create') {
                $required = ['profile_name', 'price_monthly', 'mikrotik_profile_normal', 'mikrotik_profile_isolation'];
                foreach ($required as $field) {
                    if (empty($data[$field])) {
                        sendJson(['success' => false, 'message' => "Field {$field} wajib diisi"], 400);
                    }
                }

                $id = $service->createProfile([
                    'profile_name' => trim($data['profile_name']),
                    'price_monthly' => (float)$data['price_monthly'],
                    'speed_label' => $data['speed_label'] ?? null,
                    'mikrotik_profile_normal' => trim($data['mikrotik_profile_normal']),
                    'mikrotik_profile_isolation' => trim($data['mikrotik_profile_isolation']),
                    'description' => $data['description'] ?? null,
                ]);

                sendJson(['success' => true, 'profile_id' => $id]);
            }

            if ($action === 'update') {
                $id = isset($data['id']) ? (int)$data['id'] : 0;
                if ($id <= 0) {
                    sendJson(['success' => false, 'message' => 'ID profil tidak valid'], 400);
                }

                $service->updateProfile($id, [
                    'profile_name' => trim($data['profile_name'] ?? ''),
                    'price_monthly' => (float)($data['price_monthly'] ?? 0),
                    'speed_label' => $data['speed_label'] ?? null,
                    'mikrotik_profile_normal' => trim($data['mikrotik_profile_normal'] ?? ''),
                    'mikrotik_profile_isolation' => trim($data['mikrotik_profile_isolation'] ?? ''),
                    'description' => $data['description'] ?? null,
                ]);

                sendJson(['success' => true]);
            }

            if ($action === 'delete') {
                $id = isset($data['id']) ? (int)$data['id'] : 0;
                if ($id <= 0) {
                    sendJson(['success' => false, 'message' => 'ID profil tidak valid'], 400);
                }

                $service->deleteProfile($id);
                sendJson(['success' => true]);
            }

            sendJson(['success' => false, 'message' => 'Aksi tidak dikenal'], 400);

        default:
            sendJson(['success' => false, 'message' => 'Metode tidak diizinkan'], 405);
    }
} catch (Throwable $e) {
    sendJson(['success' => false, 'message' => $e->getMessage()], 500);
}
