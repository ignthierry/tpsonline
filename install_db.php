<?php
/**
 * Script Instalasi / Migrasi Database tpsonline
 * Membuat tabel-tabel penampungan hasil tarikan API CEISA 4.0
 */

require_once __DIR__ . '/includes/db.php';

if (!$pdo_tpsonline) {
    die("Error: Koneksi ke database tpsonline tidak tersedia.");
}

$tables = [
    'ceisa_respon_plp' => "
        CREATE TABLE IF NOT EXISTS ceisa_respon_plp (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kd_kantor VARCHAR(20),
            kd_tps VARCHAR(20),
            ref_number VARCHAR(50),
            no_plp VARCHAR(50),
            tgl_plp DATE,
            alasan_reject TEXT,
            no_bc11 VARCHAR(50),
            tgl_bc11 DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    
    'ceisa_batal_plp' => "
        CREATE TABLE IF NOT EXISTS ceisa_batal_plp (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kd_kantor VARCHAR(20),
            kd_tps VARCHAR(20),
            ref_number VARCHAR(50),
            no_batal_plp VARCHAR(50),
            tgl_batal_plp DATE,
            no_plp VARCHAR(50),
            tgl_plp DATE,
            alasan_reject TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    'ceisa_spjm' => "
        CREATE TABLE IF NOT EXISTS ceisa_spjm (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kd_kantor VARCHAR(20),
            car VARCHAR(50),
            no_pib VARCHAR(50),
            tgl_pib DATE,
            nama_imp VARCHAR(100),
            no_bc11 VARCHAR(50),
            tgl_bc11 DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    'ceisa_sppb' => "
        CREATE TABLE IF NOT EXISTS ceisa_sppb (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kd_kantor VARCHAR(20),
            car VARCHAR(50),
            no_sppb VARCHAR(50),
            tgl_sppb DATE,
            no_pib VARCHAR(50),
            tgl_pib DATE,
            nama_imp VARCHAR(100),
            no_bc11 VARCHAR(50),
            tgl_bc11 DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    "
];

echo "<h1>Setup Database tpsonline</h1>";

foreach ($tables as $name => $sql) {
    try {
        $pdo_tpsonline->exec($sql);
        echo "<p style='color:green;'>Tabel <b>$name</b> berhasil dipastikan keberadaannya (terbuat/sudah ada).</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red;'>Gagal membuat tabel <b>$name</b>: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<p>Setup Selesai.</p>";
