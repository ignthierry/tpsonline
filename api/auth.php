<?php
/**
 * API Auth Handler
 * Menangani login pengguna aplikasi (tabel `users`), logout, dan refresh token CEISA 4.0
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/CeisaClient.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Metode HTTP tidak didukung'], 405);
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input['action'] ?? ($_GET['action'] ?? 'login');

// 1. ACTION: REFRESH TOKEN CEISA 4.0
if (!empty($input['refresh']) || $action === 'refresh') {
    if (!isLoggedIn()) {
        jsonResponse(['success' => false, 'message' => 'Silakan login terlebih dahulu'], 401);
    }
    try {
        $client = new CeisaClient();
        $token = $client->getValidAccessToken(true);
        jsonResponse([
            'success' => true,
            'message' => 'Token CEISA 4.0 berhasil diperbarui!',
            'token'   => substr($token, 0, 15) . '...',
        ]);
    } catch (Exception $e) {
        jsonResponse([
            'success' => false,
            'message' => 'Gagal memperbarui token CEISA: ' . $e->getMessage(),
        ], 500);
    }
}

// 2. ACTION: LOGOUT
if ($action === 'logout') {
    clearSession();
    jsonResponse([
        'success'  => true,
        'message'  => 'Logout berhasil.',
        'redirect' => 'login.php'
    ]);
}

// 3. ACTION: LOGIN PENGGUNA APLIKASI
$username   = trim((string)($input['username'] ?? ''));
$password   = trim((string)($input['password'] ?? ''));
$rememberMe = !empty($input['remember_me']);

if (empty($username) || empty($password)) {
    jsonResponse([
        'success' => false,
        'message' => 'Username dan kata sandi wajib diisi.'
    ], 400);
}

global $pdo_tpsonline;
if (!$pdo_tpsonline) {
    jsonResponse([
        'success' => false,
        'message' => 'Koneksi ke database otentikasi tidak tersedia. Silakan hubungi IT PSU.'
    ], 500);
}

try {
    $stmt = $pdo_tpsonline->prepare("SELECT id, username, password, nama_lengkap, email, role, status FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        jsonResponse([
            'success' => false,
            'message' => 'Username atau kata sandi salah.'
        ], 401);
    }

    if ($user['status'] !== 'active') {
        jsonResponse([
            'success' => false,
            'message' => 'Akun Anda dinonaktifkan. Silakan hubungi administrator.'
        ], 403);
    }

    // Verifikasi password hash
    if (!password_verify($password, $user['password'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Username atau kata sandi salah.'
        ], 401);
    }

    // Login Berhasil: set session
    $_SESSION['logged_in']    = true;
    $_SESSION['user_id']      = (int)$user['id'];
    $_SESSION['username']     = $user['username'];
    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
    $_SESSION['role']         = $user['role'];
    $_SESSION['email']        = $user['email'] ?? '';
    $_SESSION['login_time']   = time();

    // Fitur Remember Me (30 Hari Penuh)
    if ($rememberMe) {
        try {
            $token = bin2hex(random_bytes(32));
            $updToken = $pdo_tpsonline->prepare("UPDATE users SET remember_token = :tok, terakhir_login = NOW() WHERE id = :id");
            $updToken->execute([':tok' => $token, ':id' => $user['id']]);

            $cookieExpire = time() + (30 * 86400); // 30 hari (2.592.000 detik)
            
            // Set cookie auth token yang aman
            setcookie('ceisa_remember_token', $token, [
                'expires'  => $cookieExpire,
                'path'     => '/',
                'secure'   => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            // Set cookie username untuk kenyamanan auto-fill saat kembali
            setcookie('ceisa_remember_user', $user['username'], [
                'expires'  => $cookieExpire,
                'path'     => '/',
                'secure'   => false,
                'httponly' => false,
                'samesite' => 'Lax'
            ]);
        } catch (Exception $e) {
            error_log("Gagal set remember_token: " . $e->getMessage());
        }
    } else {
        try {
            $updLogin = $pdo_tpsonline->prepare("UPDATE users SET remember_token = NULL, terakhir_login = NOW() WHERE id = :id");
            $updLogin->execute([':id' => $user['id']]);

            // Hapus cookie remember jika user login tanpa mencentang Ingat Saya
            if (isset($_COOKIE['ceisa_remember_token'])) {
                setcookie('ceisa_remember_token', '', [
                    'expires'  => time() - 3600,
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                unset($_COOKIE['ceisa_remember_token']);
            }
        } catch (Exception $e) {}
    }

    // Tentukan URL tujuan setelah login
    $redirectUrl = $_SESSION['redirect_url'] ?? 'dashboard.php';
    unset($_SESSION['redirect_url']);

    // Persiapkan koneksi token CEISA di latar belakang
    try {
        $client = new CeisaClient();
        $client->getValidAccessToken();
    } catch (Exception $e) {
        error_log("CEISA token init warning on login: " . $e->getMessage());
    }

    jsonResponse([
        'success'  => true,
        'message'  => 'Login berhasil! Selamat datang, ' . $user['nama_lengkap'],
        'redirect' => $redirectUrl,
        'user'     => [
            'id'           => (int)$user['id'],
            'username'     => $user['username'],
            'nama_lengkap' => $user['nama_lengkap'],
            'role'         => $user['role']
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database login error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem saat otentikasi.'
    ], 500);
}
