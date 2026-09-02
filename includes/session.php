<?php
/**
 * Session & Authentication Management
 * Mengelola sesi autentikasi pengguna aplikasi TPS Online & integrasi CEISA 4.0
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/CeisaClient.php';

// Set timezone
$config = require __DIR__ . '/../config.php';
date_default_timezone_set($config['timezone'] ?? 'Asia/Jakarta');

// Configure session cookie
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', $config['session_lifetime'] ?? 28800);
session_set_cookie_params([
    'lifetime' => $config['session_lifetime'] ?? 28800,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_name($config['session_name'] ?? 'ceisa4_dashboard');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cek apakah user telah terautentikasi (login)
 */
function isLoggedIn(): bool
{
    // 1. Cek sesi aktif
    if (!empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])) {
        return true;
    }

    // 2. Cek fitur "Remember Me" via cookie
    if (!empty($_COOKIE['ceisa_remember_token'])) {
        global $pdo_tpsonline;
        if ($pdo_tpsonline) {
            try {
                $stmt = $pdo_tpsonline->prepare("SELECT id, username, nama_lengkap, email, role, status FROM users WHERE remember_token = ? AND status = 'active' LIMIT 1");
                $stmt->execute([$_COOKIE['ceisa_remember_token']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $_SESSION['logged_in']    = true;
                    $_SESSION['user_id']      = (int)$user['id'];
                    $_SESSION['username']     = $user['username'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['role']         = $user['role'];
                    $_SESSION['email']        = $user['email'] ?? '';
                    $_SESSION['login_time']   = time();
                    return true;
                }
            } catch (Exception $e) {
                error_log("Remember token error: " . $e->getMessage());
            }
        }
    }

    return false;
}

/**
 * Proteksi halaman: pastikan pengguna sudah login sebelum mengakses
 */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        // Simpan halaman tujuan untuk redirect kembali setelah login sukses
        $targetUri = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
        // Jangan simpan login.php atau logout.php sebagai redirect
        if (!str_contains($targetUri, 'login.php') && !str_contains($targetUri, 'logout.php')) {
            $_SESSION['redirect_url'] = $targetUri;
        }
        header('Location: login.php');
        exit;
    }
}

/**
 * Ambil data user yang sedang login
 */
function getAuthUser(): array
{
    return [
        'id'           => $_SESSION['user_id'] ?? 0,
        'username'     => $_SESSION['username'] ?? 'guest',
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? 'Pengguna',
        'role'         => $_SESSION['role'] ?? 'operator',
        'email'        => $_SESSION['email'] ?? ''
    ];
}

/**
 * Cek apakah token API CEISA sudah expired
 */
function isTokenExpired(): bool
{
    if (!isset($_SESSION['token_expiry'])) {
        return true;
    }
    return time() >= ($_SESSION['token_expiry'] - 60);
}

/**
 * Simpan token API CEISA ke session
 */
function saveTokenToSession(array $tokenData): void
{
    $_SESSION['access_token']  = $tokenData['access_token'];
    $_SESSION['refresh_token'] = $tokenData['refresh_token'] ?? '';
    $_SESSION['token_expiry']  = time() + ($tokenData['expires_in'] ?? 28800);
}

/**
 * Hapus semua data sesi & cookie saat logout
 */
function clearSession(): void
{
    // Hapus remember_token di database jika ada
    if (!empty($_SESSION['user_id'])) {
        global $pdo_tpsonline;
        if ($pdo_tpsonline) {
            try {
                $stmt = $pdo_tpsonline->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
            } catch (Exception $e) {}
        }
    }

    $_SESSION = [];

    // Hapus cookie sesi
    if (!headers_sent() && ini_get('session.use_cookies')) {
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

    // Hapus cookie remember_token & remember_user
    if (!headers_sent()) {
        if (isset($_COOKIE['ceisa_remember_token'])) {
            setcookie('ceisa_remember_token', '', time() - 3600, '/');
            unset($_COOKIE['ceisa_remember_token']);
        }
        if (isset($_COOKIE['ceisa_remember_user'])) {
            setcookie('ceisa_remember_user', '', time() - 3600, '/');
            unset($_COOKIE['ceisa_remember_user']);
        }
    }

    // Hapus file token cache jika ada
    $cacheFile = __DIR__ . '/../data/token_cache.json';
    if (file_exists($cacheFile)) {
        @unlink($cacheFile);
    }

    session_destroy();
}
