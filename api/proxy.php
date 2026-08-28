<?php
/**
 * API Proxy Handler
 * Proxy universal untuk semua GET endpoint CEISA dengan token auto-auth dan auto-refresh
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/CeisaClient.php';

// Validasi endpoint
$endpoint = input('endpoint');
if (empty($endpoint)) {
    jsonResponse(['success' => false, 'message' => 'Parameter endpoint diperlukan'], 400);
}

// Validasi endpoint — hanya izinkan endpoint yang terdaftar
$validEndpoints = [];
foreach (getEndpointDefinitions() as $category) {
    foreach ($category['endpoints'] as $key => $def) {
        $validEndpoints[] = $key;
    }
}

if (!in_array($endpoint, $validEndpoints)) {
    jsonResponse(['success' => false, 'message' => 'Endpoint tidak valid: ' . $endpoint], 400);
}

// Kumpulkan query parameters (kecuali 'endpoint')
$params = $_GET;
unset($params['endpoint']);

// Panggil API CEISA via Client (otomatis handle token dari session/ENV/cache & auto-refresh)
$client = new CeisaClient();
$result = $client->get($endpoint, $params);

// Sinkronisasi data ke database jika sukses
if ($result['success'] && isset($result['data']['data']) && is_array($result['data']['data'])) {
    require_once __DIR__ . '/../includes/db_sync.php';
    syncToDatabase($endpoint, $result['data']['data']);
}

// Return response
$statusCode = $result['success'] ? 200 : ($result['code'] ?: 500);
jsonResponse($result, $statusCode > 0 ? $statusCode : 500);
