<?php
/**
 * CCTV Snapshot Proxy
 * Berfungsi mengambil frame gambar (snapshot) dari IP Camera (Hikvision)
 * dan meneruskannya ke browser. Ini mem-bypass masalah CORS dan batasan akses lokal.
 */

require_once __DIR__ . '/../includes/session.php';
requireAuth();

// Ambil parameter kamera
$ip = $_GET['ip'] ?? '192.168.1.101';
$channel = $_GET['channel'] ?? '101';
$user = $_GET['user'] ?? 'admin';
$pass = $_GET['pass'] ?? 'Password123';

// Batasi akses hanya ke IP internal (keamanan dasar)
if (!preg_match('/^192\.168\./', $ip) && !preg_match('/^10\./', $ip)) {
    http_response_code(403);
    die("Akses IP Camera ditolak.");
}

// URL ISAPI Hikvision untuk snapshot
$url = "http://{$ip}/ISAPI/Streaming/channels/{$channel}/picture";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Timeout cepat agar tidak membebani server
// Gunakan metode otentikasi ANY (biasanya Digest untuk kamera modern)
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Header wajib untuk ISAPI kadang-kadang
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: image/jpeg'
]);

$imageData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode === 200 && $imageData) {
    // Berhasil mendapat gambar, kirim sebagai JPEG
    header("Content-Type: image/jpeg");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    echo $imageData;
} else {
    // Gagal, kirim gambar kosong transparan atau error text
    http_response_code($httpCode ?: 500);
    header("Content-Type: text/plain");
    echo "Gagal mengambil frame CCTV. Status: $httpCode. Error: $error";
}
