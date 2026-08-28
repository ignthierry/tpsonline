<?php
/**
 * Database Connections (PDO)
 * Menyediakan koneksi ke tpsonline, tpp_primamas, dan primamas
 */

// Pastikan config sudah diload
$config = require_once __DIR__ . '/../config.php';

$dbConfig = $config['db'] ?? [];
$host = $dbConfig['host'] ?? '127.0.0.1';
$user = $dbConfig['user'] ?? 'root';
$pass = $dbConfig['pass'] ?? '';
$names = $dbConfig['names'] ?? [];

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo_tpsonline = null;
$pdo_tpp       = null;
$pdo_primamas  = null;

try {
    // Koneksi ke tpsonline (untuk tabel-tabel tarikan API)
    if (!empty($names['tpsonline'])) {
        $dsn1 = "mysql:host=$host;dbname={$names['tpsonline']};charset=utf8mb4";
        $pdo_tpsonline = new PDO($dsn1, $user, $pass, $options);
    }
    
    // Koneksi ke tpp_primamas (untuk sumber data API POST)
    if (!empty($names['tpp'])) {
        $dsn2 = "mysql:host=$host;dbname={$names['tpp']};charset=utf8mb4";
        $pdo_tpp = new PDO($dsn2, $user, $pass, $options);
    }

    // Koneksi ke primamas
    if (!empty($names['primamas'])) {
        $dsn3 = "mysql:host=$host;dbname={$names['primamas']};charset=utf8mb4";
        $pdo_primamas = new PDO($dsn3, $user, $pass, $options);
    }

} catch (PDOException $e) {
    // Untuk tahap development, log error. Jangan tampilkan ke user production.
    error_log("Koneksi Database Gagal: " . $e->getMessage());
    die("Kesalahan sistem: Gagal terhubung ke database.");
}
