<?php
/**
 * Session Management
 * Mengelola session PHP untuk dashboard dengan dukungan Auto-Auth via ENV
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/CeisaClient.php';

// Set timezone
$config = require __DIR__ . '/../config.php';
date_default_timezone_set($config['timezone'] ?? 'Asia/Jakarta');

// Configure session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', $config['session_lifetime'] ?? 28800);
session_set_cookie_params($config['session_lifetime'] ?? 28800);
session_name($config['session_name'] ?? 'ceisa4_dashboard');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cek apakah user sudah login atau punya token valid
 */
function isLoggedIn(): bool
{
    global $config;
    if (isset($_SESSION['access_token']) && !empty($_SESSION['access_token'])) {
        return true;
    }

    // Jika mode auto_auth aktif, coba dapatkan token dari ENV
    if (!empty($config['auto_auth'])) {
        try {
            $client = new CeisaClient();
            $token = $client->getValidAccessToken();
            return !empty($token);
        } catch (Exception $e) {
            return false;
        }
    }

    return false;
}

/**
 * Pastikan user sudah siap menggunakan dashboard (auto-login jika ENV tersedia)
 */
function requireAuth(): void
{
    global $config;
    if (!isLoggedIn()) {
        if (!empty($config['auto_auth'])) {
            try {
                $client = new CeisaClient();
                $client->getValidAccessToken();
                return;
            } catch (Exception $e) {
                // Biarkan lanjut atau redirect
            }
        }
        header('Location: index.php');
        exit;
    }
}

/**
 * Cek apakah token sudah expired
 */
function isTokenExpired(): bool
{
    if (!isset($_SESSION['token_expiry'])) {
        return true;
    }
    return time() >= ($_SESSION['token_expiry'] - 60);
}

/**
 * Simpan token ke session
 */
function saveTokenToSession(array $tokenData): void
{
    $_SESSION['access_token'] = $tokenData['access_token'];
    $_SESSION['refresh_token'] = $tokenData['refresh_token'] ?? '';
    $_SESSION['token_expiry'] = time() + ($tokenData['expires_in'] ?? 28800);
    $_SESSION['username'] = $tokenData['username'] ?? 'User';
    $_SESSION['name'] = $tokenData['name'] ?? 'User';
    $_SESSION['login_time'] = time();
}

/**
 * Hapus semua data session (logout/reset token)
 */
function clearSession(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // Hapus file token cache juga jika di-reset
    $cacheFile = __DIR__ . '/../data/token_cache.json';
    if (file_exists($cacheFile)) {
        @unlink($cacheFile);
    }
    
    session_destroy();
}
