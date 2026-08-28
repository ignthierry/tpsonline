<?php
/**
 * Helper Sinkronisasi Database
 * Menyimpan data hasil GET dari API CEISA ke dalam tabel database lokal
 */

require_once __DIR__ . '/db.php';

function syncToDatabase(string $endpoint, array $dataArray)
{
    global $pdo_tpsonline;
    if (!$pdo_tpsonline || empty($dataArray)) {
        return;
    }

    try {
        switch ($endpoint) {
            case 'get-respon-plp':
            case 'get-respon-plp-tujuan':
            case 'get-respon-plp-tujuan-v2':
            case 'get-respon-plp-on-demand':
                syncResponPlp($pdo_tpsonline, $dataArray);
                break;
            
            case 'get-respon-batal-plp':
            case 'get-respon-batal-plp-tujuan':
            case 'get-respon-batal-plp-on-demand':
                syncBatalPlp($pdo_tpsonline, $dataArray);
                break;
                
            case 'get-spjm':
            case 'get-spjm-ondemand':
                syncSpjm($pdo_tpsonline, $dataArray);
                break;
                
            case 'get-impor-sppb':
            case 'get-impor-permit':
            case 'get-impor-permit-fasp':
            case 'get-impor-permit-200':
            case 'get-sppb-bc23':
            case 'get-sppb12-tps-asal':
            case 'get-sppb12-tps-tujuan':
            case 'get-dokumen-pabean-permit':
            case 'get-dokumen-pabean-permit-fasp':
            case 'get-dokumen-pabean-ondemand':
                syncSppb($pdo_tpsonline, $dataArray);
                break;
        }
    } catch (Exception $e) {
        error_log("DB_SYNC_ERROR: Gagal menyimpan $endpoint - " . $e->getMessage());
    }
}

function getValue($item, $keys) {
    if (!is_array($keys)) $keys = [$keys];
    foreach ($keys as $k) {
        if (isset($item[$k])) return $item[$k];
    }
    return null;
}

function parseDateDb($dateStr) {
    if (empty($dateStr)) return null;
    // CEISA usually returns dd-MM-yyyy or dd-MM-yyyy HH:mm:ss
    $parts = explode(' ', $dateStr);
    $d = explode('-', $parts[0]);
    if (count($d) === 3) {
        // dd-MM-yyyy to yyyy-MM-dd
        if (strlen($d[2]) === 4) {
            return $d[2] . '-' . $d[1] . '-' . $d[0];
        }
    }
    return $dateStr; // return original if it doesn't match format
}

function syncResponPlp($pdo, $data) {
    $stmtCheck = $pdo->prepare("SELECT id FROM ceisa_respon_plp WHERE no_plp = ? AND tgl_plp = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO ceisa_respon_plp (kd_kantor, kd_tps, ref_number, no_plp, tgl_plp, alasan_reject, no_bc11, tgl_bc11) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($data as $item) {
        $noPlp = getValue($item, ['noPlp', 'NO_PLP', 'nomorPlp', 'NOMOR_PLP']);
        $tglPlp = parseDateDb(getValue($item, ['tglPlp', 'TGL_PLP', 'tanggalPlp', 'TANGGAL_PLP']));
        
        if (!$noPlp) continue;
        
        $stmtCheck->execute([$noPlp, $tglPlp]);
        if ($stmtCheck->fetchColumn()) continue; // Sudah ada, abaikan
        
        $kdKantor = getValue($item, ['kdKantor', 'KD_KANTOR', 'kodeKantor']);
        $kdTps = getValue($item, ['kdTps', 'KD_TPS', 'kodeTps']);
        $refNumber = getValue($item, ['refNumber', 'REF_NUMBER']);
        $alasanReject = getValue($item, ['alasanReject', 'ALASAN_REJECT']);
        $noBc11 = getValue($item, ['noBc11', 'NO_BC11', 'nomorBc11']);
        $tglBc11 = parseDateDb(getValue($item, ['tglBc11', 'TGL_BC11', 'tanggalBc11']));
        
        $stmtInsert->execute([$kdKantor, $kdTps, $refNumber, $noPlp, $tglPlp, $alasanReject, $noBc11, $tglBc11]);
    }
}

function syncBatalPlp($pdo, $data) {
    $stmtCheck = $pdo->prepare("SELECT id FROM ceisa_batal_plp WHERE no_batal_plp = ? AND tgl_batal_plp = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO ceisa_batal_plp (kd_kantor, kd_tps, ref_number, no_batal_plp, tgl_batal_plp, no_plp, tgl_plp, alasan_reject) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($data as $item) {
        $noBatalPlp = getValue($item, ['noBatalPlp', 'NO_BATAL_PLP', 'nomorBatalPlp']);
        $tglBatalPlp = parseDateDb(getValue($item, ['tglBatalPlp', 'TGL_BATAL_PLP', 'tanggalBatalPlp']));
        
        if (!$noBatalPlp) continue;
        
        $stmtCheck->execute([$noBatalPlp, $tglBatalPlp]);
        if ($stmtCheck->fetchColumn()) continue; // Sudah ada, abaikan
        
        $kdKantor = getValue($item, ['kdKantor', 'KD_KANTOR', 'kodeKantor']);
        $kdTps = getValue($item, ['kdTps', 'KD_TPS', 'kodeTps']);
        $refNumber = getValue($item, ['refNumber', 'REF_NUMBER']);
        $noPlp = getValue($item, ['noPlp', 'NO_PLP', 'nomorPlp']);
        $tglPlp = parseDateDb(getValue($item, ['tglPlp', 'TGL_PLP', 'tanggalPlp']));
        $alasanReject = getValue($item, ['alasanReject', 'ALASAN_REJECT']);
        
        $stmtInsert->execute([$kdKantor, $kdTps, $refNumber, $noBatalPlp, $tglBatalPlp, $noPlp, $tglPlp, $alasanReject]);
    }
}

function syncSpjm($pdo, $data) {
    $stmtCheck = $pdo->prepare("SELECT id FROM ceisa_spjm WHERE car = ? AND no_pib = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO ceisa_spjm (kd_kantor, car, no_pib, tgl_pib, nama_imp, no_bc11, tgl_bc11) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($data as $item) {
        $car = getValue($item, ['car', 'CAR']);
        $noPib = getValue($item, ['noPib', 'NO_PIB', 'nomorPib']);
        
        if (!$car && !$noPib) continue;
        
        $stmtCheck->execute([$car, $noPib]);
        if ($stmtCheck->fetchColumn()) continue; // Sudah ada, abaikan
        
        $kdKantor = getValue($item, ['kdKantor', 'KD_KANTOR', 'kodeKantor']);
        $tglPib = parseDateDb(getValue($item, ['tglPib', 'TGL_PIB', 'tanggalPib']));
        $namaImp = getValue($item, ['namaImp', 'NAMA_IMP', 'namaImportir']);
        $noBc11 = getValue($item, ['noBc11', 'NO_BC11', 'nomorBc11']);
        $tglBc11 = parseDateDb(getValue($item, ['tglBc11', 'TGL_BC11', 'tanggalBc11']));
        
        $stmtInsert->execute([$kdKantor, $car, $noPib, $tglPib, $namaImp, $noBc11, $tglBc11]);
    }
}

function syncSppb($pdo, $data) {
    $stmtCheck = $pdo->prepare("SELECT id FROM ceisa_sppb WHERE no_sppb = ? AND car = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO ceisa_sppb (kd_kantor, car, no_sppb, tgl_sppb, no_pib, tgl_pib, nama_imp, no_bc11, tgl_bc11) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($data as $item) {
        $noSppb = getValue($item, ['noSppb', 'NO_SPPB', 'nomorSppb']);
        $car = getValue($item, ['car', 'CAR']);
        
        if (!$noSppb && !$car) continue;
        
        $stmtCheck->execute([$noSppb, $car]);
        if ($stmtCheck->fetchColumn()) continue; // Sudah ada, abaikan
        
        $kdKantor = getValue($item, ['kdKantor', 'KD_KANTOR', 'kodeKantor']);
        $tglSppb = parseDateDb(getValue($item, ['tglSppb', 'TGL_SPPB', 'tanggalSppb']));
        $noPib = getValue($item, ['noPib', 'NO_PIB', 'nomorPib']);
        $tglPib = parseDateDb(getValue($item, ['tglPib', 'TGL_PIB', 'tanggalPib']));
        $namaImp = getValue($item, ['namaImp', 'NAMA_IMP', 'namaImportir']);
        $noBc11 = getValue($item, ['noBc11', 'NO_BC11', 'nomorBc11']);
        $tglBc11 = parseDateDb(getValue($item, ['tglBc11', 'TGL_BC11', 'tanggalBc11']));
        
        $stmtInsert->execute([$kdKantor, $car, $noSppb, $tglSppb, $noPib, $tglPib, $namaImp, $noBc11, $tglBc11]);
    }
}
