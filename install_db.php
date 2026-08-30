<?php
/**
 * Script Instalasi / Migrasi Database tpsonline
 * Membuat seluruh tabel penampungan hasil return/respon API CEISA 4.0 dan memastikan kolom lengkap
 */

require_once __DIR__ . '/includes/db.php';

if (!$pdo_tpsonline) {
    die("Error: Koneksi ke database tpsonline tidak tersedia.");
}

$tables = [
    // 1. Master Log Semua Return / Respon API
    'ceisa_api_logs' => "
        CREATE TABLE IF NOT EXISTS ceisa_api_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            endpoint VARCHAR(100) NOT NULL,
            request_params TEXT,
            http_code INT,
            status VARCHAR(50),
            message TEXT,
            total_rows INT DEFAULT 0,
            raw_response LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_endpoint (endpoint),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 2. PLP Responses
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
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_plp (no_plp, tgl_plp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    
    // 3. Respon Batal PLP
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
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_batal_plp (no_batal_plp, tgl_batal_plp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 4. Data Pendukung PLP
    'ceisa_pendukung_plp' => "
        CREATE TABLE IF NOT EXISTS ceisa_pendukung_plp (
            id INT AUTO_INCREMENT PRIMARY KEY,
            no_bc11 VARCHAR(50),
            tgl_bc11 DATE,
            no_pos_bc11 VARCHAR(50),
            no_cont VARCHAR(50),
            uk_cont VARCHAR(20),
            jns_cont VARCHAR(20),
            no_bl_awb VARCHAR(100),
            tgl_bl_awb DATE,
            consignee VARCHAR(255),
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pendukung_cont (no_cont, no_bc11)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 5. SPJM (Jalur Merah)
    'ceisa_spjm' => "
        CREATE TABLE IF NOT EXISTS ceisa_spjm (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kd_kantor VARCHAR(20),
            car VARCHAR(50),
            no_pib VARCHAR(50),
            tgl_pib DATE,
            nama_imp VARCHAR(150),
            npwp_imp VARCHAR(50),
            no_bc11 VARCHAR(50),
            tgl_bc11 DATE,
            no_pos_bc11 VARCHAR(50),
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_spjm (car, no_pib)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 6. SPPB Impor / BC 2.3 / BC 1.2 / SPPB Permit
    'ceisa_sppb' => "
        CREATE TABLE IF NOT EXISTS ceisa_sppb (
            id INT AUTO_INCREMENT PRIMARY KEY,
            car VARCHAR(50),
            id_header VARCHAR(60),
            no_sppb VARCHAR(60),
            tgl_sppb DATE,
            kd_kantor VARCHAR(20),
            kd_kantor_pengawas VARCHAR(20),
            kd_kpbc VARCHAR(20),
            no_pib VARCHAR(50),
            tgl_pib DATE,
            npwp_imp VARCHAR(50),
            nama_imp VARCHAR(255),
            alamat_imp TEXT,
            npwp_ppjk VARCHAR(50),
            nama_ppjk VARCHAR(255),
            alamat_ppjk TEXT,
            nama_angkut VARCHAR(150),
            no_voy_flight VARCHAR(50),
            bruto DECIMAL(15,2) DEFAULT 0,
            netto DECIMAL(15,2) DEFAULT 0,
            gudang VARCHAR(50),
            status_jalur VARCHAR(20),
            jml_kontainer INT DEFAULT 0,
            no_bc11 VARCHAR(50),
            tgl_bc11 DATE,
            no_pos_bc11 VARCHAR(50),
            no_bl_awb VARCHAR(100),
            tgl_bl_awb DATE,
            no_master_bl_awb VARCHAR(100),
            tgl_master_bl_awb DATE,
            kd_tps VARCHAR(20),
            kd_gudang VARCHAR(20),
            detil_kontainer JSON,
            detil_kemasan JSON,
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sppb (no_sppb, tgl_sppb),
            INDEX idx_sppb_car (car),
            INDEX idx_sppb_pib (no_pib)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 6a. Detil Kontainer SPPB
    'ceisa_sppb_kontainer' => "
        CREATE TABLE IF NOT EXISTS ceisa_sppb_kontainer (
            id INT AUTO_INCREMENT PRIMARY KEY,
            car VARCHAR(50),
            no_sppb VARCHAR(60),
            no_cont VARCHAR(50),
            uk_cont VARCHAR(20),
            jns_cont VARCHAR(20),
            jns_muat VARCHAR(20),
            status_segel VARCHAR(50),
            no_segel VARCHAR(50),
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sppb_cont (no_cont, car)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 6b. Detil Kemasan SPPB
    'ceisa_sppb_kemasan' => "
        CREATE TABLE IF NOT EXISTS ceisa_sppb_kemasan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            car VARCHAR(50),
            no_sppb VARCHAR(60),
            jml_kemasan INT DEFAULT 0,
            jns_kemasan VARCHAR(50),
            kd_jns_kemasan VARCHAR(100),
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sppb_kem (car)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 7. NPE Ekspor
    'ceisa_npe' => "
        CREATE TABLE IF NOT EXISTS ceisa_npe (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kd_kantor VARCHAR(20),
            no_npe VARCHAR(50),
            tgl_npe DATE,
            no_peb VARCHAR(50),
            tgl_peb DATE,
            npwp_eks VARCHAR(50),
            nama_eks VARCHAR(150),
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_npe (no_npe, tgl_npe)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 8. PEB Ekspor
    'ceisa_peb' => "
        CREATE TABLE IF NOT EXISTS ceisa_peb (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kd_kantor VARCHAR(20),
            no_peb VARCHAR(50),
            tgl_peb DATE,
            npwp_eks VARCHAR(50),
            nama_eks VARCHAR(150),
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_peb (no_peb, tgl_peb)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 9. PKBE Ekspor
    'ceisa_pkbe' => "
        CREATE TABLE IF NOT EXISTS ceisa_pkbe (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kd_kantor VARCHAR(20),
            no_pkbe VARCHAR(50),
            tgl_pkbe DATE,
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pkbe (no_pkbe, tgl_pkbe)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 10. Dokumen Pabean (Permit / On Demand / Lengkap dengan Header & Detil)
    'ceisa_dokumen_pabean' => "
        CREATE TABLE IF NOT EXISTS ceisa_dokumen_pabean (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kd_dokumen_inout VARCHAR(20),
            car VARCHAR(50),
            no_dokumen_inout VARCHAR(60),
            tgl_dokumen_inout DATE,
            no_daftar VARCHAR(50),
            tgl_daftar DATE,
            kd_kantor VARCHAR(20),
            kd_kantor_pengawas VARCHAR(20),
            kd_kantor_bongkar VARCHAR(20),
            npwp_imp VARCHAR(50),
            nama_imp VARCHAR(255),
            alamat_imp TEXT,
            npwp_ppjk VARCHAR(50),
            nama_ppjk VARCHAR(255),
            alamat_ppjk TEXT,
            nama_angkut VARCHAR(150),
            no_voy_flight VARCHAR(50),
            bruto DECIMAL(15,2) DEFAULT 0,
            netto DECIMAL(15,2) DEFAULT 0,
            gudang VARCHAR(50),
            status_jalur VARCHAR(50),
            jml_kontainer INT DEFAULT 0,
            no_bc11 VARCHAR(50),
            tgl_bc11 DATE,
            no_pos_bc11 VARCHAR(50),
            no_bl_awb VARCHAR(100),
            tgl_bl_awb DATE,
            no_master_bl_awb VARCHAR(100),
            tgl_master_bl_awb DATE,
            flag_segel VARCHAR(10),
            detil_kontainer JSON,
            detil_kemasan JSON,
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_dok_inout (no_dokumen_inout, tgl_dokumen_inout),
            INDEX idx_car (car),
            INDEX idx_no_daftar (no_daftar)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 10a. Detil Kontainer Dokumen Pabean
    'ceisa_dokumen_pabean_kontainer' => "
        CREATE TABLE IF NOT EXISTS ceisa_dokumen_pabean_kontainer (
            id INT AUTO_INCREMENT PRIMARY KEY,
            car VARCHAR(50),
            no_dokumen_inout VARCHAR(60),
            no_cont VARCHAR(50),
            uk_cont VARCHAR(20),
            jns_cont VARCHAR(20),
            status_segel VARCHAR(50),
            no_segel VARCHAR(50),
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_dp_cont (no_cont, car)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 10b. Detil Kemasan Dokumen Pabean
    'ceisa_dokumen_pabean_kemasan' => "
        CREATE TABLE IF NOT EXISTS ceisa_dokumen_pabean_kemasan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            car VARCHAR(50),
            no_dokumen_inout VARCHAR(60),
            jml_kemasan INT DEFAULT 0,
            jns_kemasan VARCHAR(50),
            merk_kemasan VARCHAR(100),
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_dp_kem (car)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 11. Dokumen Manual
    'ceisa_dokumen_manual' => "
        CREATE TABLE IF NOT EXISTS ceisa_dokumen_manual (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kd_dokumen VARCHAR(20),
            no_dokumen VARCHAR(50),
            tgl_dokumen DATE,
            kd_tps VARCHAR(20),
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_dokumen_manual (no_dokumen, tgl_dokumen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 12. SP3B
    'ceisa_sp3b' => "
        CREATE TABLE IF NOT EXISTS ceisa_sp3b (
            id INT AUTO_INCREMENT PRIMARY KEY,
            no_sp3b VARCHAR(50),
            tgl_sp3b DATE,
            kd_pel_bongkar VARCHAR(20),
            kd_tps VARCHAR(20),
            no_bc11 VARCHAR(50),
            tgl_bc11 DATE,
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sp3b (no_sp3b, tgl_sp3b)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 13. BC 1.1 / Manifes
    'ceisa_bc11' => "
        CREATE TABLE IF NOT EXISTS ceisa_bc11 (
            id INT AUTO_INCREMENT PRIMARY KEY,
            no_bc11 VARCHAR(50),
            tgl_bc11 DATE,
            no_pos VARCHAR(50),
            kd_kantor VARCHAR(20),
            sarana_pengangkut VARCHAR(100),
            voyage VARCHAR(50),
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bc11 (no_bc11, tgl_bc11)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 14. Tracking TPS
    'ceisa_tracking' => "
        CREATE TABLE IF NOT EXISTS ceisa_tracking (
            id INT AUTO_INCREMENT PRIMARY KEY,
            no_cont VARCHAR(50),
            no_bl_awb VARCHAR(100),
            tgl_bl_awb DATE,
            status_tracking VARCHAR(100),
            waktu_status DATETIME,
            keterangan TEXT,
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tracking (no_cont)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 15. Monitoring & Status Counter
    'ceisa_monitoring_log' => "
        CREATE TABLE IF NOT EXISTS ceisa_monitoring_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            jenis_monitoring VARCHAR(50),
            tgl_awal DATE,
            tgl_akhir DATE,
            jumlah_data INT DEFAULT 0,
            status VARCHAR(50),
            keterangan TEXT,
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 16. Respon Penolakan BC 1.2
    'ceisa_penolakan_bc12' => "
        CREATE TABLE IF NOT EXISTS ceisa_penolakan_bc12 (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kd_tps VARCHAR(20),
            no_permohonan VARCHAR(50),
            tgl_permohonan DATE,
            alasan_reject TEXT,
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    // 17. Data OB / Pindah TPS
    'ceisa_ob' => "
        CREATE TABLE IF NOT EXISTS ceisa_ob (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kd_tps VARCHAR(20),
            no_ob VARCHAR(50),
            tgl_ob DATE,
            raw_data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    "
];

echo "<h1>Setup & Migrasi Database tpsonline</h1>";

foreach ($tables as $name => $sql) {
    try {
        $pdo_tpsonline->exec($sql);
        echo "<p style='color:green;'>Tabel <b>$name</b> berhasil dipastikan keberadaannya (terbuat/sudah ada).</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red;'>Gagal membuat tabel <b>$name</b>: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Pastikan kolom-kolom tambahan ada pada tabel-tabel lama
$columnsToCheck = [
    'ceisa_respon_plp' => ['raw_data' => 'JSON'],
    'ceisa_batal_plp' => ['raw_data' => 'JSON'],
    'ceisa_spjm' => [
        'npwp_imp' => 'VARCHAR(50)',
        'no_pos_bc11' => 'VARCHAR(50)',
        'raw_data' => 'JSON'
    ],
    'ceisa_sppb' => [
        'id_header' => 'VARCHAR(60)',
        'kd_kantor_pengawas' => 'VARCHAR(20)',
        'kd_kpbc' => 'VARCHAR(20)',
        'npwp_imp' => 'VARCHAR(50)',
        'nama_imp' => 'VARCHAR(255)',
        'alamat_imp' => 'TEXT',
        'npwp_ppjk' => 'VARCHAR(50)',
        'nama_ppjk' => 'VARCHAR(255)',
        'alamat_ppjk' => 'TEXT',
        'nama_angkut' => 'VARCHAR(150)',
        'no_voy_flight' => 'VARCHAR(50)',
        'bruto' => 'DECIMAL(15,2) DEFAULT 0',
        'netto' => 'DECIMAL(15,2) DEFAULT 0',
        'gudang' => 'VARCHAR(50)',
        'status_jalur' => 'VARCHAR(20)',
        'jml_kontainer' => 'INT DEFAULT 0',
        'no_bc11' => 'VARCHAR(50)',
        'tgl_bc11' => 'DATE',
        'no_pos_bc11' => 'VARCHAR(50)',
        'no_bl_awb' => 'VARCHAR(100)',
        'tgl_bl_awb' => 'DATE',
        'no_master_bl_awb' => 'VARCHAR(100)',
        'tgl_master_bl_awb' => 'DATE',
        'kd_tps' => 'VARCHAR(20)',
        'kd_gudang' => 'VARCHAR(20)',
        'detil_kontainer' => 'JSON',
        'detil_kemasan' => 'JSON',
        'raw_data' => 'JSON'
    ],
    'ceisa_sppb_kontainer' => [
        'jns_muat' => 'VARCHAR(20)'
    ],
    'ceisa_dokumen_pabean' => [
        'kd_dokumen_inout' => 'VARCHAR(20)',
        'car' => 'VARCHAR(50)',
        'no_dokumen_inout' => 'VARCHAR(60)',
        'tgl_dokumen_inout' => 'DATE',
        'no_daftar' => 'VARCHAR(50)',
        'tgl_daftar' => 'DATE',
        'kd_kantor_pengawas' => 'VARCHAR(20)',
        'kd_kantor_bongkar' => 'VARCHAR(20)',
        'npwp_imp' => 'VARCHAR(50)',
        'nama_imp' => 'VARCHAR(255)',
        'alamat_imp' => 'TEXT',
        'npwp_ppjk' => 'VARCHAR(50)',
        'nama_ppjk' => 'VARCHAR(255)',
        'alamat_ppjk' => 'TEXT',
        'nama_angkut' => 'VARCHAR(150)',
        'no_voy_flight' => 'VARCHAR(50)',
        'bruto' => 'DECIMAL(15,2) DEFAULT 0',
        'netto' => 'DECIMAL(15,2) DEFAULT 0',
        'gudang' => 'VARCHAR(50)',
        'status_jalur' => 'VARCHAR(50)',
        'jml_kontainer' => 'INT DEFAULT 0',
        'no_bc11' => 'VARCHAR(50)',
        'tgl_bc11' => 'DATE',
        'no_pos_bc11' => 'VARCHAR(50)',
        'no_bl_awb' => 'VARCHAR(100)',
        'tgl_bl_awb' => 'DATE',
        'no_master_bl_awb' => 'VARCHAR(100)',
        'tgl_master_bl_awb' => 'DATE',
        'flag_segel' => 'VARCHAR(10)',
        'detil_kontainer' => 'JSON',
        'detil_kemasan' => 'JSON',
        'raw_data' => 'JSON'
    ]
];

foreach ($columnsToCheck as $table => $cols) {
    try {
        $existingCols = $pdo_tpsonline->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($cols as $colName => $colType) {
            if (!in_array($colName, $existingCols)) {
                $pdo_tpsonline->exec("ALTER TABLE `$table` ADD COLUMN `$colName` $colType;");
                echo "<p style='color:blue;'>Kolom <b>$colName</b> berhasil ditambahkan ke tabel <b>$table</b>.</p>";
            }
        }
    } catch (PDOException $e) {
        echo "<p style='color:orange;'>Info kolom $table: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<p>Setup Selesai.</p>";
