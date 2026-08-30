<?php
/**
 * Database Connections (PDO)
 * Menyediakan koneksi ke tpsonline, tpp_primamas, dan primamas
 */

require_once __DIR__ . '/../config.php';

$host = env('DB_HOST', '100.90.187.128');
$user = env('DB_USER', 'luna');
$pass = env('DB_PASS', 'N2145tb@');
$dbTpsonline = env('DB_NAME_TPSONLINE', 'tpsonline');
$dbTpp = env('DB_NAME_TPP', 'tpp_primamas');
$dbPrimamas = env('DB_NAME_PRIMAMAS', 'primamas');

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

global $pdo_tpsonline, $pdo_tpp, $pdo_primamas;

try {
    // Koneksi ke tpsonline (untuk tabel-tabel tarikan API)
    if (!$pdo_tpsonline && !empty($dbTpsonline)) {
        $dsn1 = "mysql:host=$host;dbname=$dbTpsonline;charset=utf8mb4";
        $pdo_tpsonline = new PDO($dsn1, $user, $pass, $options);
    }
    
    // Koneksi ke tpp_primamas (untuk sumber data API POST)
    if (!$pdo_tpp && !empty($dbTpp)) {
        $dsn2 = "mysql:host=$host;dbname=$dbTpp;charset=utf8mb4";
        $pdo_tpp = new PDO($dsn2, $user, $pass, $options);
    }

    // Koneksi ke primamas
    if (!$pdo_primamas && !empty($dbPrimamas)) {
        $dsn3 = "mysql:host=$host;dbname=$dbPrimamas;charset=utf8mb4";
        $pdo_primamas = new PDO($dsn3, $user, $pass, $options);
    }

} catch (PDOException $e) {
    error_log("Koneksi Database Gagal: " . $e->getMessage());
    die("Kesalahan sistem: Gagal terhubung ke database. " . $e->getMessage());
}
