<?php
/**
 * Database Connections (PDO)
 * Menyediakan koneksi ke tpsonline, tpp_primamas, dan primamas
 */

require_once __DIR__ . '/../config.php';

$primaryHost = env('DB_HOST', '192.168.0.192');
$primaryUser = env('DB_USER', 'itpsu');
$primaryPass = env('DB_PASS', '123123');

$fallbackHost = '192.168.0.193';
$fallbackUser = 'itpsu';
$fallbackPass = '123';

$dbTpsonline = env('DB_NAME_TPSONLINE', 'tpsonline');
$dbTpp = env('DB_NAME_TPP', 'tpp_primamas');
$dbPrimamas = env('DB_NAME_PRIMAMAS', 'primamas');

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 2,
];

global $pdo_tpsonline, $pdo_tpp, $pdo_primamas;

function createPdoWithFallback($dbname, $options, $primaryHost, $primaryUser, $primaryPass, $fallbackHost, $fallbackUser, $fallbackPass) {
    if (empty($dbname)) return null;
    
    // Coba host utama terlebih dahulu
    try {
        $dsn = "mysql:host=$primaryHost;dbname=$dbname;charset=utf8mb4";
        return new PDO($dsn, $primaryUser, $primaryPass, $options);
    } catch (PDOException $e1) {
        // Fallback ke server sekunder
        try {
            $dsnFallback = "mysql:host=$fallbackHost;dbname=$dbname;charset=utf8mb4";
            return new PDO($dsnFallback, $fallbackUser, $fallbackPass, $options);
        } catch (PDOException $e2) {
            error_log("Koneksi Database Gagal ({$dbname}): " . $e2->getMessage());
            return null;
        }
    }
}

try {
    if (!$pdo_tpsonline) {
        $pdo_tpsonline = createPdoWithFallback($dbTpsonline, $options, $primaryHost, $primaryUser, $primaryPass, $fallbackHost, $fallbackUser, $fallbackPass);
    }
    if (!$pdo_tpp) {
        $pdo_tpp = createPdoWithFallback($dbTpp, $options, $primaryHost, $primaryUser, $primaryPass, $fallbackHost, $fallbackUser, $fallbackPass);
    }
    if (!$pdo_primamas) {
        $pdo_primamas = createPdoWithFallback($dbPrimamas, $options, $primaryHost, $primaryUser, $primaryPass, $fallbackHost, $fallbackUser, $fallbackPass);
    }
} catch (Exception $e) {
    error_log("Database global error: " . $e->getMessage());
}
