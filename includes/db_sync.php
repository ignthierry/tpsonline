<?php
/**
 * Helper Sinkronisasi Database Lengkap untuk API CEISA 4.0
 * Menyimpan seluruh return / respon GET dari API CEISA ke tabel log dan tabel spesifik
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (!function_exists('isSequentialArray')) {
    function isSequentialArray(array $arr): bool
    {
        if (empty($arr)) return true;
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}

/**
 * Fungsi Utama: Menyimpan SEMUA return/respon GET ke database
 */
function saveGetApiResponse(string $endpoint, array $params, array $result): void
{
    global $pdo_tpsonline;
    if (!$pdo_tpsonline) {
        return;
    }

    try {
        $httpCode = $result['code'] ?? ($result['success'] ? 200 : 500);
        $status = $result['success'] ? 'SUCCESS' : 'FAILED';
        $message = $result['message'] ?? '';
        $rawResponseJson = json_encode($result['raw'] ?? $result, JSON_UNESCAPED_UNICODE);
        $requestParamsJson = json_encode($params, JSON_UNESCAPED_UNICODE);

        // Ekstrak baris-baris data dari respon
        $rows = extractDataRows($result);
        $totalRows = count($rows);

        // 1. Simpan ke Master Audit Log (ceisa_api_logs)
        $stmtLog = $pdo_tpsonline->prepare("
            INSERT INTO ceisa_api_logs (endpoint, request_params, http_code, status, message, total_rows, raw_response, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmtLog->execute([
            $endpoint,
            $requestParamsJson,
            (int)$httpCode,
            $status,
            $message,
            $totalRows,
            $rawResponseJson
        ]);

        // 2. Jika ada data baris, sinkronkan ke tabel terstruktur spesifik
        if ($totalRows > 0) {
            syncStructuredData($endpoint, $rows);
        }
    } catch (Exception $e) {
        error_log("DB_SAVE_RESPONSE_ERROR: Gagal menyimpan respon $endpoint - " . $e->getMessage());
    }
}

/**
 * Kompatibilitas untuk pemanggilan lama
 */
function syncToDatabase(string $endpoint, array $dataArray)
{
    syncStructuredData($endpoint, $dataArray);
}

/**
 * Ekstraktor data otomatis dari berbagai format payload respon CEISA 4.0
 */
function extractDataRows(array $result): array
{
    if (empty($result['data']) && empty($result['raw']['data']) && empty($result['raw'])) {
        return [];
    }

    $data = $result['data'] ?? $result['raw']['data'] ?? $result['raw'] ?? null;

    if (empty($data)) {
        return [];
    }

    if (is_array($data)) {
        // Cek jika dibungkus oleh key entity seperti 'sppb', 'dokumenPabean', 'data', 'list', 'rows', 'responPlp', 'responBatalPlp', 'spjm', dll.
        foreach (['sppb', 'dokumenPabean', 'data', 'list', 'rows', 'responPlp', 'responBatalPlp', 'spjm', 'npe', 'peb', 'pkbe', 'sp3b', 'manifes', 'tracking'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return isSequentialArray($data[$key]) ? $data[$key] : [$data[$key]];
            }
        }

        // Cek apakah sequential array (banyak baris)
        if (isSequentialArray($data)) {
            return $data;
        }

        // Jika associative array (1 baris objek), bungkus dalam array
        return [$data];
    }

    return [];
}

/**
 * Sinkronisasi data ke tabel spesifik berdasarkan nama endpoint
 */
function syncStructuredData(string $endpoint, array $rows): void
{
    global $pdo_tpsonline;
    if (!$pdo_tpsonline || empty($rows)) {
        return;
    }

    try {
        switch ($endpoint) {
            // SPPB Impor & BC 2.3 & BC 1.2 & SPPB Permit
            case 'get-impor-sppb':
            case 'get-impor-permit':
            case 'get-impor-permit-fasp':
            case 'get-impor-permit-200':
            case 'get-sppb-bc23':
            case 'get-bc23-permit':
            case 'get-bc23-permit-fasp':
            case 'get-sppb12-tps-asal':
            case 'get-sppb12-tps-tujuan':
                syncSppb($pdo_tpsonline, $rows);
                break;

            // Dokumen Pabean (Permit / On Demand / Lengkap)
            case 'get-dokumen-pabean-permit':
            case 'get-dokumen-pabean-permit-fasp':
            case 'get-dokumen-pabean-ondemand':
            case 'get-batal-pabean-permit':
            case 'get-batal-pabean-on-demand':
                syncDokumenPabean($pdo_tpsonline, $rows);
                break;

            // PLP Responses
            case 'get-respon-plp':
            case 'get-respon-plp-tujuan':
            case 'get-respon-plp-tujuan-v2':
            case 'get-respon-plp-on-demand':
                syncResponPlp($pdo_tpsonline, $rows);
                break;
            
            // Batal PLP
            case 'get-respon-batal-plp':
            case 'get-respon-batal-plp-tujuan':
            case 'get-respon-batal-plp-on-demand':
                syncBatalPlp($pdo_tpsonline, $rows);
                break;

            // Pendukung PLP
            case 'get-pendukung-plp':
            case 'get-pendukung-plp-bl':
                syncPendukungPlp($pdo_tpsonline, $rows);
                break;

            // SPJM
            case 'get-spjm':
            case 'get-spjm-ondemand':
                syncSpjm($pdo_tpsonline, $rows);
                break;

            // Dokumen Manual
            case 'get-dokumen-manual':
            case 'get-dokumen-manual-ondemand':
                syncDokumenManual($pdo_tpsonline, $rows);
                break;

            // Ekspor NPE
            case 'get-ekspor-npe':
            case 'get-ekspor-permit-fnpe':
            case 'get-npe':
            case 'cek-npe':
                syncNpe($pdo_tpsonline, $rows);
                break;

            // Ekspor PEB
            case 'get-ekspor-peb':
                syncPeb($pdo_tpsonline, $rows);
                break;

            // Ekspor PKBE
            case 'get-ekspor-pkbe':
                syncPkbe($pdo_tpsonline, $rows);
                break;

            // SP3B
            case 'get-sp3b-pel-bongkar-akhir':
            case 'get-sp3b-ondemand':
            case 'get-sp3b-tps-bongkar':
                syncSp3b($pdo_tpsonline, $rows);
                break;

            // BC 1.1 / Manifes
            case 'get-impor-bc11':
            case 'get-info-nomor-bc11':
            case 'get-manifes':
                syncBc11($pdo_tpsonline, $rows);
                break;

            // Tracking TPS
            case 'tps-tracking':
                syncTracking($pdo_tpsonline, $rows);
                break;

            // Monitoring Counters
            case 'cek-data-terkirim':
            case 'cek-data-sppb':
            case 'cek-data-sppb-tpb':
            case 'cek-data-gagal-kirim':
            case 'get-reject-data':
                syncMonitoring($pdo_tpsonline, $endpoint, $rows);
                break;

            // Penolakan BC 1.2
            case 'get-respon-penolakan-bc12':
                syncPenolakanBc12($pdo_tpsonline, $rows);
                break;

            // Pindah TPS / OB
            case 'get-data-ob':
                syncOb($pdo_tpsonline, $rows);
                break;
        }
    } catch (Exception $e) {
        error_log("DB_SYNC_STRUCTURED_ERROR: Gagal sync $endpoint - " . $e->getMessage());
    }
}

function getValue($item, $keys) {
    if (!is_array($item)) return null;
    if (!is_array($keys)) $keys = [$keys];
    foreach ($keys as $k) {
        if (isset($item[$k]) && $item[$k] !== '') return $item[$k];
        $lk = strtolower($k);
        $uk = strtoupper($k);
        if (isset($item[$lk]) && $item[$lk] !== '') return $item[$lk];
        if (isset($item[$uk]) && $item[$uk] !== '') return $item[$uk];
    }
    return null;
}

function parseDateDb($dateStr) {
    if (empty($dateStr)) return null;
    $dateStr = trim($dateStr);
    $parts = explode(' ', $dateStr);
    $d = explode('-', $parts[0]);
    if (count($d) === 3) {
        if (strlen($d[2]) === 4) {
            return $d[2] . '-' . $d[1] . '-' . $d[0];
        }
        if (strlen($d[0]) === 4) {
            return $parts[0];
        }
    }
    return $dateStr;
}

// 0. SPPB Impor & BC 2.3 & BC 1.2 & SPPB Permit (Header + Detil Kontainer + Detil Kemasan)
function syncSppb($pdo, $data) {
    $stmtCheck = $pdo->prepare("SELECT id FROM ceisa_sppb WHERE (no_sppb = ? AND (tgl_sppb = ? OR tgl_sppb IS NULL)) OR (car = ? AND no_pib = ?)");
    $stmtInsert = $pdo->prepare("
        INSERT INTO ceisa_sppb (
            car, id_header, no_sppb, tgl_sppb, kd_kantor, kd_kantor_pengawas, kd_kpbc,
            no_pib, tgl_pib, npwp_imp, nama_imp, alamat_imp, npwp_ppjk, nama_ppjk, alamat_ppjk,
            nama_angkut, no_voy_flight, bruto, netto, gudang, status_jalur, jml_kontainer,
            no_bc11, tgl_bc11, no_pos_bc11, no_bl_awb, tgl_bl_awb, no_master_bl_awb, tgl_master_bl_awb,
            kd_tps, kd_gudang, detil_kontainer, detil_kemasan, raw_data, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, NOW()
        )
    ");

    $stmtInsertCont = $pdo->prepare("
        INSERT INTO ceisa_sppb_kontainer (car, no_sppb, no_cont, uk_cont, jns_cont, jns_muat, status_segel, no_segel, raw_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmtInsertKem = $pdo->prepare("
        INSERT INTO ceisa_sppb_kemasan (car, no_sppb, jml_kemasan, jns_kemasan, kd_jns_kemasan, raw_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($data as $item) {
        $header = $item['header'] ?? $item;
        $detil = $item['detil'] ?? [];

        $noSppb = getValue($header, ['noSppb', 'NO_SPPB', 'nomorSppb', 'nomorDokumen', 'noDokumen']);
        $tglSppb = parseDateDb(getValue($header, ['tglSppb', 'TGL_SPPB', 'tanggalSppb', 'tanggalDokumen', 'tglDokumen']));
        $car = getValue($header, ['car', 'CAR', 'nomorAju', 'noAju']);
        $noPib = getValue($header, ['noPib', 'NO_PIB', 'nomorPib', 'nomorDaftar']);
        $tglPib = parseDateDb(getValue($header, ['tglPib', 'TGL_PIB', 'tanggalPib', 'tanggalDaftar']));

        if (!$noSppb && !$car && !$noPib) continue;

        $stmtCheck->execute([$noSppb, $tglSppb, $car, $noPib]);
        if ($stmtCheck->fetchColumn()) continue;

        $idHeader = getValue($header, ['idHeader', 'ID_HEADER']);
        $kdKantor = getValue($header, ['kdKantor', 'KD_KANTOR', 'kodeKantor', 'kodeKantorPengawas', 'kodeKpbc']);
        $kdKantorPengawas = getValue($header, ['kodeKantorPengawas', 'kdKantorPengawas']);
        $kdKpbc = getValue($header, ['kodeKpbc', 'kdKpbc']);
        $npwpImp = getValue($header, ['npwpImp', 'NPWP_IMP', 'npwpImportir', 'npwp']);
        $namaImp = getValue($header, ['namaImp', 'NAMA_IMP', 'namaImportir']);
        $alamatImp = getValue($header, ['alamatImp', 'ALAMAT_IMP']);
        $npwpPpjk = getValue($header, ['npwpPpjk', 'NPWP_PPJK']);
        $namaPpjk = getValue($header, ['namaPpjk', 'NAMA_PPJK']);
        $alamatPpjk = getValue($header, ['alamatPpjk', 'ALAMAT_PPJK']);
        $namaAngkut = getValue($header, ['namaAngkut', 'NAMA_ANGKUT', 'saranaPengangkut']);
        $noVoyFlight = getValue($header, ['noVoyFlight', 'nomorVoyFlight', 'voyage', 'noVoyage']);
        $bruto = (float)getValue($header, ['bruto', 'BRUTO']);
        $netto = (float)getValue($header, ['netto', 'NETTO']);
        $gudang = getValue($header, ['gudang', 'GUDANG', 'kodeGudang', 'kdGudang']);
        $statusJalur = getValue($header, ['statusJalur', 'STATUS_JALUR', 'jalur']);
        $jmlKontainer = (int)getValue($header, ['jumlahKontainer', 'jmlKontainer', 'jml_kontainer']);
        $noBc11 = getValue($header, ['noBc11', 'NO_BC11', 'nomorBc11']);
        $tglBc11 = parseDateDb(getValue($header, ['tglBc11', 'TGL_BC11', 'tanggalBc11']));
        $noPos = getValue($header, ['noPosBc11', 'NO_POS_BC11', 'nomorPos']);
        $noBlAwb = getValue($header, ['noBlAwb', 'NO_BL_AWB', 'nomorBlAwb']);
        $tglBlAwb = parseDateDb(getValue($header, ['tglBlAwb', 'TGL_BL_AWB', 'tanggalBlAwb']));
        $noMasterBlAwb = getValue($header, ['noMasterBlAwb', 'nomorMasterBlAwb']);
        $tglMasterBlAwb = parseDateDb(getValue($header, ['tglMasterBlAwb', 'tanggalMasterBlAwb']));
        $kdTps = getValue($header, ['kdTps', 'KD_TPS', 'kodeTps', 'kodeTpsAsal', 'kodeTpsTujuan']);
        $kdGudang = getValue($header, ['kdGudang', 'KD_GUDANG', 'kodeGudang', 'gudang']);

        $kontainerList = $detil['kontainer'] ?? $item['kontainer'] ?? [];
        $kemasanList = $detil['kemasan'] ?? $item['kemasan'] ?? [];

        $detilKontainerJson = !empty($kontainerList) ? json_encode($kontainerList, JSON_UNESCAPED_UNICODE) : null;
        $detilKemasanJson = !empty($kemasanList) ? json_encode($kemasanList, JSON_UNESCAPED_UNICODE) : null;
        $rawDataJson = json_encode($item, JSON_UNESCAPED_UNICODE);

        $stmtInsert->execute([
            $car, $idHeader, $noSppb, $tglSppb, $kdKantor, $kdKantorPengawas, $kdKpbc,
            $noPib, $tglPib, $npwpImp, $namaImp, $alamatImp, $npwpPpjk, $namaPpjk, $alamatPpjk,
            $namaAngkut, $noVoyFlight, $bruto, $netto, $gudang, $statusJalur, $jmlKontainer,
            $noBc11, $tglBc11, $noPos, $noBlAwb, $tglBlAwb, $noMasterBlAwb, $tglMasterBlAwb,
            $kdTps, $kdGudang, $detilKontainerJson, $detilKemasanJson, $rawDataJson
        ]);

        // Simpan Detil Kontainer jika ada
        if (is_array($kontainerList)) {
            foreach ($kontainerList as $cont) {
                $noCont = getValue($cont, ['nomorKontainer', 'noCont', 'no_cont']);
                if (!$noCont) continue;
                $ukCont = getValue($cont, ['ukuranKontainer', 'ukCont', 'uk_cont']);
                $jnsCont = getValue($cont, ['jenisKontainer', 'jnsCont', 'jns_cont']);
                $jnsMuat = getValue($cont, ['jenisMuat', 'jnsMuat', 'jns_muat', 'jenis']);
                $statusSegel = getValue($cont, ['statusSegel', 'status_segel']);
                $noSegel = getValue($cont, ['nomorSegel', 'noSegel', 'no_segel']);
                $rawCont = json_encode($cont, JSON_UNESCAPED_UNICODE);
                $stmtInsertCont->execute([$car, $noSppb, $noCont, $ukCont, $jnsCont, $jnsMuat, $statusSegel, $noSegel, $rawCont]);
            }
        }

        // Simpan Detil Kemasan jika ada
        if (is_array($kemasanList)) {
            foreach ($kemasanList as $kem) {
                $jmlKem = (int)getValue($kem, ['jumlahKemasan', 'jmlKemasan', 'jml_kemasan']);
                $jnsKem = getValue($kem, ['jenisKemasan', 'jnsKemasan', 'jns_kemasan']);
                $kdJnsKem = getValue($kem, ['kodeJenisKemasan', 'kdJnsKemasan', 'merkKemasan', 'merk']);
                $rawKem = json_encode($kem, JSON_UNESCAPED_UNICODE);
                $stmtInsertKem->execute([$car, $noSppb, $jmlKem, $jnsKem, $kdJnsKem, $rawKem]);
            }
        }
    }
}

// 1. Dokumen Pabean (Header + Detil Kontainer + Detil Kemasan)
function syncDokumenPabean($pdo, $data) {
    $stmtCheck = $pdo->prepare("SELECT id FROM ceisa_dokumen_pabean WHERE (no_dokumen_inout = ? AND (tgl_dokumen_inout = ? OR tgl_dokumen_inout IS NULL)) OR (car = ? AND no_daftar = ?)");
    $stmtInsert = $pdo->prepare("
        INSERT INTO ceisa_dokumen_pabean (
            kd_dokumen_inout, car, no_dokumen_inout, tgl_dokumen_inout, no_daftar, tgl_daftar,
            kd_kantor, kd_kantor_pengawas, kd_kantor_bongkar, npwp_imp, nama_imp, alamat_imp,
            npwp_ppjk, nama_ppjk, alamat_ppjk, nama_angkut, no_voy_flight, bruto, netto,
            gudang, status_jalur, jml_kontainer, no_bc11, tgl_bc11, no_pos_bc11,
            no_bl_awb, tgl_bl_awb, no_master_bl_awb, tgl_master_bl_awb, flag_segel,
            detil_kontainer, detil_kemasan, raw_data, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, NOW()
        )
    ");

    $stmtInsertCont = $pdo->prepare("
        INSERT INTO ceisa_dokumen_pabean_kontainer (car, no_dokumen_inout, no_cont, uk_cont, jns_cont, status_segel, no_segel, raw_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmtInsertKem = $pdo->prepare("
        INSERT INTO ceisa_dokumen_pabean_kemasan (car, no_dokumen_inout, jml_kemasan, jns_kemasan, merk_kemasan, raw_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($data as $item) {
        $header = $item['header'] ?? $item;
        $detil = $item['detil'] ?? [];

        $noDokInOut = getValue($header, ['nomorDokumenInOut', 'noDokumenInOut', 'no_dokumen_inout', 'nomorDokumen', 'noDokumen', 'noSppb']);
        $tglDokInOut = parseDateDb(getValue($header, ['tanggalDokumenInOut', 'tglDokumenInOut', 'tgl_dokumen_inout', 'tanggalDokumen', 'tglDokumen', 'tglSppb']));
        $car = getValue($header, ['car', 'CAR', 'nomorAju', 'noAju']);
        $noDaftar = getValue($header, ['nomorDaftar', 'noDaftar', 'no_daftar', 'noPib', 'nomorPib']);
        $tglDaftar = parseDateDb(getValue($header, ['tanggalDaftar', 'tglDaftar', 'tgl_daftar', 'tglPib', 'tanggalPib']));

        if (!$noDokInOut && !$car && !$noDaftar) continue;

        // Cek duplikasi
        $stmtCheck->execute([$noDokInOut, $tglDokInOut, $car, $noDaftar]);
        if ($stmtCheck->fetchColumn()) continue;

        $kdDokInOut = getValue($header, ['kodeDokumenInOut', 'kdDokumenInOut', 'kd_dokumen_inout', 'kodeDokumen', 'kdDokumen']);
        $kdKantor = getValue($header, ['kodeKantor', 'kdKantor', 'kd_kantor']);
        $kdKantorPengawas = getValue($header, ['kodeKantorPengawas', 'kdKantorPengawas']);
        $kdKantorBongkar = getValue($header, ['kodeKantorBongkar', 'kdKantorBongkar', 'kd_kantor_bongkar']);
        $npwpImp = getValue($header, ['npwpImp', 'NPWP_IMP', 'npwpImportir', 'npwp']);
        $namaImp = getValue($header, ['namaImp', 'NAMA_IMP', 'namaImportir']);
        $alamatImp = getValue($header, ['alamatImp', 'ALAMAT_IMP', 'alamatImportir']);
        $npwpPpjk = getValue($header, ['npwpPpjk', 'NPWP_PPJK']);
        $namaPpjk = getValue($header, ['namaPpjk', 'NAMA_PPJK']);
        $alamatPpjk = getValue($header, ['alamatPpjk', 'ALAMAT_PPJK']);
        $namaAngkut = getValue($header, ['namaAngkut', 'NAMA_ANGKUT', 'saranaPengangkut']);
        $noVoyFlight = getValue($header, ['nomorVoyFlight', 'noVoyFlight', 'voyage', 'noVoyage']);
        $bruto = (float)getValue($header, ['bruto', 'BRUTO']);
        $netto = (float)getValue($header, ['netto', 'NETTO']);
        $gudang = getValue($header, ['gudang', 'GUDANG', 'kodeGudang', 'kdGudang']);
        $statusJalur = getValue($header, ['statusJalur', 'STATUS_JALUR', 'jalur']);
        $jmlKontainer = (int)getValue($header, ['jumlahKontainer', 'jmlKontainer', 'jml_kontainer']);
        $noBc11 = getValue($header, ['nomorBc11', 'noBc11', 'NO_BC11']);
        $tglBc11 = parseDateDb(getValue($header, ['tanggalBc11', 'tglBc11', 'TGL_BC11']));
        $noPosBc11 = getValue($header, ['nomorPosBc11', 'noPosBc11', 'noPos']);
        $noBlAwb = getValue($header, ['nomorBlAwb', 'noBlAwb', 'NO_BL_AWB']);
        $tglBlAwb = parseDateDb(getValue($header, ['tanggalBlAWb', 'tanggalBlAwb', 'tglBlAwb']));
        $noMasterBlAwb = getValue($header, ['nomorMasterBlAwb', 'noMasterBlAwb']);
        $tglMasterBlAwb = parseDateDb(getValue($header, ['tanggalMasterBlAwb', 'tglMasterBlAwb']));
        $flagSegel = getValue($header, ['flagSegel', 'FLAG_SEGEL']);

        $kontainerList = $detil['kontainer'] ?? $item['kontainer'] ?? [];
        $kemasanList = $detil['kemasan'] ?? $item['kemasan'] ?? [];

        $detilKontainerJson = !empty($kontainerList) ? json_encode($kontainerList, JSON_UNESCAPED_UNICODE) : null;
        $detilKemasanJson = !empty($kemasanList) ? json_encode($kemasanList, JSON_UNESCAPED_UNICODE) : null;
        $rawDataJson = json_encode($item, JSON_UNESCAPED_UNICODE);

        $stmtInsert->execute([
            $kdDokInOut, $car, $noDokInOut, $tglDokInOut, $noDaftar, $tglDaftar,
            $kdKantor, $kdKantorPengawas, $kdKantorBongkar, $npwpImp, $namaImp, $alamatImp,
            $npwpPpjk, $namaPpjk, $alamatPpjk, $namaAngkut, $noVoyFlight, $bruto, $netto,
            $gudang, $statusJalur, $jmlKontainer, $noBc11, $tglBc11, $noPosBc11,
            $noBlAwb, $tglBlAwb, $noMasterBlAwb, $tglMasterBlAwb, $flagSegel,
            $detilKontainerJson, $detilKemasanJson, $rawDataJson
        ]);

        // Simpan Detil Kontainer jika ada
        if (is_array($kontainerList)) {
            foreach ($kontainerList as $cont) {
                $noCont = getValue($cont, ['nomorKontainer', 'noCont', 'no_cont']);
                if (!$noCont) continue;
                $ukCont = getValue($cont, ['ukuranKontainer', 'ukCont', 'uk_cont', 'ukuran']);
                $jnsCont = getValue($cont, ['jenisKontainer', 'jnsCont', 'jns_cont', 'jenis']);
                $statusSegel = getValue($cont, ['statusSegel', 'status_segel']);
                $noSegel = getValue($cont, ['nomorSegel', 'noSegel', 'no_segel']);
                $rawCont = json_encode($cont, JSON_UNESCAPED_UNICODE);
                $stmtInsertCont->execute([$car, $noDokInOut, $noCont, $ukCont, $jnsCont, $statusSegel, $noSegel, $rawCont]);
            }
        }

        // Simpan Detil Kemasan jika ada
        if (is_array($kemasanList)) {
            foreach ($kemasanList as $kem) {
                $jmlKem = (int)getValue($kem, ['jumlahKemasan', 'jmlKemasan', 'jml_kemasan', 'jumlah']);
                $jnsKem = getValue($kem, ['jenisKemasan', 'jnsKemasan', 'jns_kemasan', 'jenis']);
                $merkKem = getValue($kem, ['merkKemasan', 'merk_kemasan', 'merk']);
                $rawKem = json_encode($kem, JSON_UNESCAPED_UNICODE);
                $stmtInsertKem->execute([$car, $noDokInOut, $jmlKem, $jnsKem, $merkKem, $rawKem]);
            }
        }
    }
}

// 2. PLP Respon
function syncResponPlp($pdo, $data) {
    $stmtCheck = $pdo->prepare("SELECT id FROM ceisa_respon_plp WHERE no_plp = ? AND (tgl_plp = ? OR tgl_plp IS NULL)");
    $stmtInsert = $pdo->prepare("
        INSERT INTO ceisa_respon_plp (kd_kantor, kd_tps, ref_number, no_plp, tgl_plp, alasan_reject, no_bc11, tgl_bc11, raw_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    foreach ($data as $item) {
        $noPlp = getValue($item, ['noPlp', 'NO_PLP', 'nomorPlp', 'NOMOR_PLP', 'no_plp']);
        $tglPlp = parseDateDb(getValue($item, ['tglPlp', 'TGL_PLP', 'tanggalPlp', 'TANGGAL_PLP', 'tgl_plp']));
        
        if (!$noPlp) continue;
        
        $stmtCheck->execute([$noPlp, $tglPlp]);
        if ($stmtCheck->fetchColumn()) continue;
        
        $kdKantor = getValue($item, ['kdKantor', 'KD_KANTOR', 'kodeKantor', 'kd_kantor']);
        $kdTps = getValue($item, ['kdTps', 'KD_TPS', 'kodeTps', 'kd_tps']);
        $refNumber = getValue($item, ['refNumber', 'REF_NUMBER', 'nomorReference', 'ref_number']);
        $alasanReject = getValue($item, ['alasanReject', 'ALASAN_REJECT', 'alasan_reject', 'keterangan']);
        $noBc11 = getValue($item, ['noBc11', 'NO_BC11', 'nomorBc11', 'no_bc11']);
        $tglBc11 = parseDateDb(getValue($item, ['tglBc11', 'TGL_BC11', 'tanggalBc11', 'tgl_bc11']));
        $rawData = json_encode($item, JSON_UNESCAPED_UNICODE);
        
        $stmtInsert->execute([$kdKantor, $kdTps, $refNumber, $noPlp, $tglPlp, $alasanReject, $noBc11, $tglBc11, $rawData]);
    }
}

// 3. Batal PLP
function syncBatalPlp($pdo, $data) {
    $stmtCheck = $pdo->prepare("SELECT id FROM ceisa_batal_plp WHERE no_batal_plp = ? AND (tgl_batal_plp = ? OR tgl_batal_plp IS NULL)");
    $stmtInsert = $pdo->prepare("
        INSERT INTO ceisa_batal_plp (kd_kantor, kd_tps, ref_number, no_batal_plp, tgl_batal_plp, no_plp, tgl_plp, alasan_reject, raw_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    foreach ($data as $item) {
        $noBatalPlp = getValue($item, ['noBatalPlp', 'NO_BATAL_PLP', 'nomorBatalPlp', 'no_batal_plp']);
        $tglBatalPlp = parseDateDb(getValue($item, ['tglBatalPlp', 'TGL_BATAL_PLP', 'tanggalBatalPlp', 'tgl_batal_plp']));
        
        if (!$noBatalPlp) continue;
        
        $stmtCheck->execute([$noBatalPlp, $tglBatalPlp]);
        if ($stmtCheck->fetchColumn()) continue;
        
        $kdKantor = getValue($item, ['kdKantor', 'KD_KANTOR', 'kodeKantor', 'kd_kantor']);
        $kdTps = getValue($item, ['kdTps', 'KD_TPS', 'kodeTps', 'kd_tps']);
        $refNumber = getValue($item, ['refNumber', 'REF_NUMBER', 'ref_number']);
        $noPlp = getValue($item, ['noPlp', 'NO_PLP', 'nomorPlp', 'no_plp']);
        $tglPlp = parseDateDb(getValue($item, ['tglPlp', 'TGL_PLP', 'tanggalPlp', 'tgl_plp']));
        $alasanReject = getValue($item, ['alasanReject', 'ALASAN_REJECT', 'alasan_reject']);
        $rawData = json_encode($item, JSON_UNESCAPED_UNICODE);
        
        $stmtInsert->execute([$kdKantor, $kdTps, $refNumber, $noBatalPlp, $tglBatalPlp, $noPlp, $tglPlp, $alasanReject, $rawData]);
    }
}

// 4. Pendukung PLP
function syncPendukungPlp($pdo, $data) {
    $stmtCheck = $pdo->prepare("SELECT id FROM ceisa_pendukung_plp WHERE no_cont = ? AND no_bc11 = ?");
    $stmtInsert = $pdo->prepare("
        INSERT INTO ceisa_pendukung_plp (no_bc11, tgl_bc11, no_pos_bc11, no_cont, uk_cont, jns_cont, no_bl_awb, tgl_bl_awb, consignee, raw_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($data as $item) {
        $noCont = getValue($item, ['noCont', 'NO_CONT', 'nomorKontainer', 'no_cont']);
        $noBc11 = getValue($item, ['noBc11', 'NO_BC11', 'nomorBc11', 'no_bc11']);
        
        if (!$noCont && !$noBc11) continue;

        $stmtCheck->execute([$noCont, $noBc11]);
        if ($stmtCheck->fetchColumn()) continue;

        $tglBc11 = parseDateDb(getValue($item, ['tglBc11', 'TGL_BC11', 'tanggalBc11', 'tgl_bc11']));
        $noPos = getValue($item, ['noPosBc11', 'NO_POS_BC11', 'nomorPos', 'no_pos']);
        $ukCont = getValue($item, ['ukCont', 'UK_CONT', 'ukuranKontainer', 'uk_cont']);
        $jnsCont = getValue($item, ['jnsCont', 'JNS_CONT', 'jenisKontainer', 'jns_cont']);
        $noBlAwb = getValue($item, ['noBlAwb', 'NO_BL_AWB', 'nomorBlAwb', 'no_bl_awb']);
        $tglBlAwb = parseDateDb(getValue($item, ['tglBlAwb', 'TGL_BL_AWB', 'tanggalBlAwb', 'tgl_bl_awb']));
        $consignee = getValue($item, ['consignee', 'CONSIGNEE', 'namaConsignee', 'importir']);
        $rawData = json_encode($item, JSON_UNESCAPED_UNICODE);

        $stmtInsert->execute([$noBc11, $tglBc11, $noPos, $noCont, $ukCont, $jnsCont, $noBlAwb, $tglBlAwb, $consignee, $rawData]);
    }
}

// 5. SPJM
function syncSpjm($pdo, $data) {
    $stmtCheck = $pdo->prepare("SELECT id FROM ceisa_spjm WHERE car = ? AND no_pib = ?");
    $stmtInsert = $pdo->prepare("
        INSERT INTO ceisa_spjm (kd_kantor, car, no_pib, tgl_pib, nama_imp, npwp_imp, no_bc11, tgl_bc11, no_pos_bc11, raw_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    foreach ($data as $item) {
        $car = getValue($item, ['car', 'CAR', 'nomorAju', 'noAju']);
        $noPib = getValue($item, ['noPib', 'NO_PIB', 'nomorPib', 'nomorDaftar', 'noDaftar']);
        
        if (!$car && !$noPib) continue;
        
        $stmtCheck->execute([$car, $noPib]);
        if ($stmtCheck->fetchColumn()) continue;
        
        $kdKantor = getValue($item, ['kdKantor', 'KD_KANTOR', 'kodeKantor']);
        $tglPib = parseDateDb(getValue($item, ['tglPib', 'TGL_PIB', 'tanggalPib', 'tanggalDaftar', 'tglDaftar']));
        $namaImp = getValue($item, ['namaImp', 'NAMA_IMP', 'namaImportir']);
        $npwpImp = getValue($item, ['npwpImp', 'NPWP_IMP', 'npwpImportir', 'npwp']);
        $noBc11 = getValue($item, ['noBc11', 'NO_BC11', 'nomorBc11']);
        $tglBc11 = parseDateDb(getValue($item, ['tglBc11', 'TGL_BC11', 'tanggalBc11']));
        $noPos = getValue($item, ['noPosBc11', 'NO_POS_BC11', 'nomorPos']);
        $rawData = json_encode($item, JSON_UNESCAPED_UNICODE);
        
        $stmtInsert->execute([$kdKantor, $car, $noPib, $tglPib, $namaImp, $npwpImp, $noBc11, $tglBc11, $noPos, $rawData]);
    }
}

// 6. NPE Ekspor
function syncNpe($pdo, $data) {
    $stmtCheck = $pdo->prepare("SELECT id FROM ceisa_npe WHERE no_npe = ? AND (tgl_npe = ? OR tgl_npe IS NULL)");
    $stmtInsert = $pdo->prepare("
        INSERT INTO ceisa_npe (kd_kantor, no_npe, tgl_npe, no_peb, tgl_peb, npwp_eks, nama_eks, raw_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($data as $item) {
        $noNpe = getValue($item, ['noNpe', 'NO_NPE', 'nomorNpe']);
        $tglNpe = parseDateDb(getValue($item, ['tglNpe', 'TGL_NPE', 'tanggalNpe']));

        if (!$noNpe) continue;

        $stmtCheck->execute([$noNpe, $tglNpe]);
        if ($stmtCheck->fetchColumn()) continue;

        $kdKantor = getValue($item, ['kdKantor', 'KD_KANTOR', 'kodeKantor']);
        $noPeb = getValue($item, ['noPeb', 'NO_PEB', 'nomorPeb']);
        $tglPeb = parseDateDb(getValue($item, ['tglPeb', 'TGL_PEB', 'tanggalPeb']));
        $npwpEks = getValue($item, ['npwp', 'NPWP', 'npwpEks', 'npwpEksportir']);
        $namaEks = getValue($item, ['namaEks', 'NAMA_EKS', 'namaEksportir']);
        $rawData = json_encode($item, JSON_UNESCAPED_UNICODE);

        $stmtInsert->execute([$kdKantor, $noNpe, $tglNpe, $noPeb, $tglPeb, $npwpEks, $namaEks, $rawData]);
    }
}

// 7. PEB Ekspor
function syncPeb($pdo, $data) {
    $stmtCheck = $pdo->prepare("SELECT id FROM ceisa_peb WHERE no_peb = ? AND (tgl_peb = ? OR tgl_peb IS NULL)");
    $stmtInsert = $pdo->prepare("
        INSERT INTO ceisa_peb (kd_kantor, no_peb, tgl_peb, npwp_eks, nama_eks, raw_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($data as $item) {
        $noPeb = getValue($item, ['noPeb', 'NO_PEB', 'nomorPeb']);
        $tglPeb = parseDateDb(getValue($item, ['tglPeb', 'TGL_PEB', 'tanggalPeb']));

        if (!$noPeb) continue;

        $stmtCheck->execute([$noPeb, $tglPeb]);
        if ($stmtCheck->fetchColumn()) continue;

        $kdKantor = getValue($item, ['kdKantor', 'KD_KANTOR', 'kodeKantor']);
        $npwpEks = getValue($item, ['npwp', 'NPWP', 'npwpEks', 'npwpEksportir']);
        $namaEks = getValue($item, ['namaEks', 'NAMA_EKS', 'namaEksportir']);
        $rawData = json_encode($item, JSON_UNESCAPED_UNICODE);

        $stmtInsert->execute([$kdKantor, $noPeb, $tglPeb, $npwpEks, $namaEks, $rawData]);
    }
}

// 8. PKBE Ekspor
function syncPkbe($pdo, $data) {
    $stmtCheck = $pdo->prepare("SELECT id FROM ceisa_pkbe WHERE no_pkbe = ? AND (tgl_pkbe = ? OR tgl_pkbe IS NULL)");
    $stmtInsert = $pdo->prepare("
        INSERT INTO ceisa_pkbe (kd_kantor, no_pkbe, tgl_pkbe, raw_data, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    foreach ($data as $item) {
        $noPkbe = getValue($item, ['noPkbe', 'NO_PKBE', 'nomorPkbe']);
        $tglPkbe = parseDateDb(getValue($item, ['tglPkbe', 'TGL_PKBE', 'tanggalPkbe']));

        if (!$noPkbe) continue;

        $stmtCheck->execute([$noPkbe, $tglPkbe]);
        if ($stmtCheck->fetchColumn()) continue;

        $kdKantor = getValue($item, ['kdKantor', 'KD_KANTOR', 'kodeKantor']);
        $rawData = json_encode($item, JSON_UNESCAPED_UNICODE);

        $stmtInsert->execute([$kdKantor, $noPkbe, $tglPkbe, $rawData]);
    }
}

// 9. Dokumen Manual
function syncDokumenManual($pdo, $data) {
    $stmtCheck = $pdo->prepare("SELECT id FROM ceisa_dokumen_manual WHERE no_dokumen = ? AND (tgl_dokumen = ? OR tgl_dokumen IS NULL)");
    $stmtInsert = $pdo->prepare("
        INSERT INTO ceisa_dokumen_manual (kd_dokumen, no_dokumen, tgl_dokumen, kd_tps, raw_data, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");

    foreach ($data as $item) {
        $noDokumen = getValue($item, ['noDokumen', 'NO_DOKUMEN', 'nomorDokumen']);
        $tglDokumen = parseDateDb(getValue($item, ['tglDokumen', 'TGL_DOKUMEN', 'tanggalDokumen']));

        if (!$noDokumen) continue;

        $stmtCheck->execute([$noDokumen, $tglDokumen]);
        if ($stmtCheck->fetchColumn()) continue;

        $kdDokumen = getValue($item, ['kdDokumen', 'KD_DOKUMEN', 'kodeDokumen']);
        $kdTps = getValue($item, ['kdTps', 'KD_TPS', 'kodeTps']);
        $rawData = json_encode($item, JSON_UNESCAPED_UNICODE);

        $stmtInsert->execute([$kdDokumen, $noDokumen, $tglDokumen, $kdTps, $rawData]);
    }
}

// 10. SP3B
function syncSp3b($pdo, $data) {
    $stmtCheck = $pdo->prepare("SELECT id FROM ceisa_sp3b WHERE no_sp3b = ? AND (tgl_sp3b = ? OR tgl_sp3b IS NULL)");
    $stmtInsert = $pdo->prepare("
        INSERT INTO ceisa_sp3b (no_sp3b, tgl_sp3b, kd_pel_bongkar, kd_tps, no_bc11, tgl_bc11, raw_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($data as $item) {
        $noSp3b = getValue($item, ['noSp3b', 'NO_SP3B', 'nomorSp3b', 'nomorSP3B']);
        $tglSp3b = parseDateDb(getValue($item, ['tglSp3b', 'TGL_SP3B', 'tanggalSp3b', 'tanggalSP3B']));

        if (!$noSp3b) continue;

        $stmtCheck->execute([$noSp3b, $tglSp3b]);
        if ($stmtCheck->fetchColumn()) continue;

        $kdPel = getValue($item, ['kodePelabuhanAkhir', 'kdPelabuhanAkhir', 'kd_pel_bongkar']);
        $kdTps = getValue($item, ['kdTps', 'KD_TPS', 'kodeTps']);
        $noBc11 = getValue($item, ['noBc11', 'NO_BC11', 'nomorBc11']);
        $tglBc11 = parseDateDb(getValue($item, ['tglBc11', 'TGL_BC11', 'tanggalBc11']));
        $rawData = json_encode($item, JSON_UNESCAPED_UNICODE);

        $stmtInsert->execute([$noSp3b, $tglSp3b, $kdPel, $kdTps, $noBc11, $tglBc11, $rawData]);
    }
}

// 11. BC 1.1 / Manifes
function syncBc11($pdo, $data) {
    $stmtCheck = $pdo->prepare("SELECT id FROM ceisa_bc11 WHERE no_bc11 = ? AND (tgl_bc11 = ? OR tgl_bc11 IS NULL)");
    $stmtInsert = $pdo->prepare("
        INSERT INTO ceisa_bc11 (no_bc11, tgl_bc11, no_pos, kd_kantor, sarana_pengangkut, voyage, raw_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($data as $item) {
        $noBc11 = getValue($item, ['noBc11', 'NO_BC11', 'nomorBc11']);
        $tglBc11 = parseDateDb(getValue($item, ['tglBc11', 'TGL_BC11', 'tanggalBc11']));

        if (!$noBc11) continue;

        $stmtCheck->execute([$noBc11, $tglBc11]);
        if ($stmtCheck->fetchColumn()) continue;

        $noPos = getValue($item, ['noPos', 'NO_POS', 'nomorPos']);
        $kdKantor = getValue($item, ['kdKantor', 'KD_KANTOR', 'kodeKantor']);
        $sarana = getValue($item, ['saranaPengangkut', 'namaSaranaPengangkut', 'sarana_pengangkut']);
        $voyage = getValue($item, ['voyage', 'VOYAGE', 'noVoyage']);
        $rawData = json_encode($item, JSON_UNESCAPED_UNICODE);

        $stmtInsert->execute([$noBc11, $tglBc11, $noPos, $kdKantor, $sarana, $voyage, $rawData]);
    }
}

// 12. Tracking TPS
function syncTracking($pdo, $data) {
    $stmtInsert = $pdo->prepare("
        INSERT INTO ceisa_tracking (no_cont, no_bl_awb, tgl_bl_awb, status_tracking, waktu_status, keterangan, raw_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($data as $item) {
        $noCont = getValue($item, ['noCont', 'NO_CONT', 'nomorKontainer']);
        $noBlAwb = getValue($item, ['noBlAwb', 'NO_BL_AWB', 'nomorBlAwb']);
        $tglBlAwb = parseDateDb(getValue($item, ['tglBlAwb', 'TGL_BL_AWB', 'tanggalBlAwb']));
        $statusTracking = getValue($item, ['status', 'STATUS', 'statusTracking', 'status_tracking']);
        $waktuStatus = getValue($item, ['waktuStatus', 'WAKTU_STATUS', 'waktu_status', 'waktu']);
        $keterangan = getValue($item, ['keterangan', 'KETERANGAN', 'uraian']);
        $rawData = json_encode($item, JSON_UNESCAPED_UNICODE);

        $stmtInsert->execute([$noCont, $noBlAwb, $tglBlAwb, $statusTracking, $waktuStatus, $keterangan, $rawData]);
    }
}

// 13. Monitoring Counters
function syncMonitoring($pdo, $endpoint, $data) {
    $stmtInsert = $pdo->prepare("
        INSERT INTO ceisa_monitoring_log (jenis_monitoring, tgl_awal, tgl_akhir, jumlah_data, status, keterangan, raw_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($data as $item) {
        $tglAwal = parseDateDb(getValue($item, ['tanggalAwal', 'tglAwal', 'tgl_awal', 'tanggalSPPB']));
        $tglAkhir = parseDateDb(getValue($item, ['tanggalAkhir', 'tglAkhir', 'tgl_akhir', 'tanggalSPPB']));
        $jumlahData = (int)getValue($item, ['jumlahData', 'jumlah', 'total', 'count', 'jmlData']);
        $status = getValue($item, ['status', 'STATUS', 'keterangan']);
        $keterangan = getValue($item, ['keterangan', 'detail', 'pesan']);
        $rawData = json_encode($item, JSON_UNESCAPED_UNICODE);

        $stmtInsert->execute([$endpoint, $tglAwal, $tglAkhir, $jumlahData, $status, $keterangan, $rawData]);
    }
}

// 14. Penolakan BC 1.2
function syncPenolakanBc12($pdo, $data) {
    $stmtInsert = $pdo->prepare("
        INSERT INTO ceisa_penolakan_bc12 (kd_tps, no_permohonan, tgl_permohonan, alasan_reject, raw_data, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");

    foreach ($data as $item) {
        $kdTps = getValue($item, ['kdTps', 'KD_TPS', 'kodeTps']);
        $noPermohonan = getValue($item, ['noPermohonan', 'NO_PERMOHONAN', 'nomorPermohonan']);
        $tglPermohonan = parseDateDb(getValue($item, ['tglPermohonan', 'TGL_PERMOHONAN', 'tanggalPermohonan']));
        $alasanReject = getValue($item, ['alasanReject', 'ALASAN_REJECT', 'alasan_reject']);
        $rawData = json_encode($item, JSON_UNESCAPED_UNICODE);

        $stmtInsert->execute([$kdTps, $noPermohonan, $tglPermohonan, $alasanReject, $rawData]);
    }
}

// 15. OB / Pindah TPS
function syncOb($pdo, $data) {
    $stmtInsert = $pdo->prepare("
        INSERT INTO ceisa_ob (kd_tps, no_ob, tgl_ob, raw_data, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    foreach ($data as $item) {
        $kdTps = getValue($item, ['kdTps', 'KD_TPS', 'kodeTps']);
        $noOb = getValue($item, ['noOb', 'NO_OB', 'nomorOb']);
        $tglOb = parseDateDb(getValue($item, ['tglOb', 'TGL_OB', 'tanggalOb']));
        $rawData = json_encode($item, JSON_UNESCAPED_UNICODE);

        $stmtInsert->execute([$kdTps, $noOb, $tglOb, $rawData]);
    }
}
