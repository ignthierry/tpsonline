<?php
/**
 * API Auth Handler
 * Endpoint AJAX untuk login atau refresh token ke API CEISA
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/CeisaClient.php';

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Ambil input JSON
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$isRefresh = !empty($input['refresh']);
$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

$client = new CeisaClient();

if ($isRefresh || (empty($username) && empty($password))) {
    // Mode refresh menggunakan kredensial yang ada di ENV / config
    try {
        $token = $client->getValidAccessToken(true);
        jsonResponse([
            'success' => true,
            'message' => 'Token berhasil diperbarui!',
            'token' => substr($token, 0, 15) . '...',
        ]);
    } catch (Exception $e) {
        jsonResponse([
            'success' => false,
            'message' => $e->getMessage(),
        ], 401);
    }
}

// Mode manual login dengan kredensial custom
$result = $client->login($username, $password);

if ($result['success']) {
    jsonResponse([
        'success' => true,
        'message' => 'Login berhasil!',
        'expires_in' => $result['expires_in'],
    ]);
} else {
    jsonResponse([
        'success' => false,
        'message' => $result['message'],
    ], 401);
}
