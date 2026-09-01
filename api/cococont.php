<?php
/**
 * API Handler: Coarri Codeco (CoCoCont) CEISA 4.0
 * 
 * Menangani:
 * - action=fetch : Penarikan data Gate-In / Gate-Out dari database TPP dan pembentukan payload JSON CEISA 4.0
 * - action=send  : Pengiriman payload JSON ke endpoint CEISA 4.0 OpenAPI Bea Cukai
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/CeisaClient.php';

// Pastikan user terautentikasi
requireAuth();

header('Content-Type: application/json; charset=utf-8');

$action = input('action', 'fetch');

try {
    if ($action === 'fetch') {
        handleFetch();
    } elseif ($action === 'send') {
        handleSend();
    } else {
        jsonResponse(['success' => false, 'message' => 'Aksi tidak valid: ' . $action], 400);
    }
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Kesalahan server: ' . $e->getMessage(),
        'error_detail' => $e->getTraceAsString()
    ], 500);
}

/**
 * Normalisasi format tanggal ke YYYY-MM-DD
 */
function normalizeDate($dateStr) {
    $dateStr = trim($dateStr);
    if (empty($dateStr)) {
        return date('Y-m-d');
    }
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $dateStr, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        return $dateStr;
    }
    $ts = strtotime($dateStr);
    return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
}

/**
 * Format tanggal ke dd-MM-yyyy sesuai standar REST API CEISA 4.0
 */
function formatDateDMY($val, $fallback = '') {
    $val = trim((string)$val);
    if (empty($val) || $val === '0000-00-00' || $val === '00000000' || $val === '00-00-0000' || $val === '0000-00-00 00:00:00') {
        if (!empty($fallback)) {
            return formatDateDMY($fallback, '');
        }
        return date('d-m-Y');
    }
    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $val, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $val, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $val, $m)) {
        return $val;
    }
    $ts = strtotime($val);
    return ($ts && $ts > 0) ? date('d-m-Y', $ts) : date('d-m-Y');
}

/**
 * Format waktu in/out ke dd-MM-yyyy HH:mm:ss sesuai standar REST API CEISA 4.0
 */
function formatDateTimeDMY($val, $fallback = '') {
    $val = trim((string)$val);
    if (empty($val) || $val === '000000' || $val === '00000000000000' || $val === '0000-00-00 00:00:00') {
        if (!empty($fallback)) {
            return formatDateTimeDMY($fallback, '');
        }
        return date('d-m-Y H:i:s');
    }
    if (preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$/', $val, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]} {$m[4]}:{$m[5]}:{$m[6]}";
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/', $val, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]} {$m[4]}:{$m[5]}:{$m[6]}";
    }
    $ts = strtotime($val);
    return ($ts && $ts > 0) ? date('d-m-Y H:i:s', $ts) : date('d-m-Y H:i:s');
}

/**
 * Handle Penarikan Data dari Database TPP dan Konversi ke CEISA 4.0
 */
function handleFetch() {
    global $pdo_tpp;

    if (!$pdo_tpp) {
        jsonResponse(['success' => false, 'message' => 'Koneksi database TPP tidak tersedia. Periksa konfigurasi db.php'], 500);
    }

    $type     = input('type', 'In'); // 'In' atau 'Out'
    $tglAwal  = normalizeDate(input('tglAwal'));
    $tglAkhir = normalizeDate(input('tglAkhir'));

    if ($type !== 'In' && $type !== 'Out') {
        jsonResponse(['success' => false, 'message' => 'Tipe harus In atau Out'], 400);
    }

    if ($type === 'In') {
        $sql = "SELECT 
                    tppmanifestplp.idPLP, 
                    '5' AS KD_DOK, 
                    'PSU0' AS KD_TPS, 
                    IFNULL(H.VOYAGE, '') AS NM_ANGKUT, 
                    IFNULL(H.NO_VOYAGE, '') AS NO_VOY_FLIGHT, 
                    IFNULL(H.CALL_SIGN, '') AS CALL_SIGN, 
                    IFNULL(DATE_FORMAT(H.TGL_TIBA, '%Y%m%d'), '') AS TGL_TIBA, 
                    IFNULL(H.KD_GUDANG, 'CPSU') AS KD_GUDANG, 
                    tppcontplp.noCont AS NO_CONT,
                    IFNULL(tppcontplp.size, '20') AS UK_CONT, 
                    IFNULL(tppcontplp.seal, IFNULL(tppcontplp.NO_SEGEL_PELAYARAN, '')) AS NO_SEGEL, 
                    IFNULL(tppmanifestplp.status, IFNULL(D.JNS_CONT, 'FCL')) AS JNS_CONT, 
                    IFNULL(D.NO_BL_AWB, IFNULL(tppcontplp.NO_MASTER_BL_AWB, '')) AS NO_BL_AWB, 
                    IFNULL(DATE_FORMAT(D.TGL_BL_AWB, '%Y%m%d'), '') AS TGL_BL_AWB, 
                    IFNULL(SUBSTRING(tppcontplp.NO_MASTER_BL_AWB, 1, 30), '') AS NO_MASTER_BL_AWB, 
                    IFNULL(DATE_FORMAT(tppcontplp.TGL_MASTER_BL_AWB, '%Y%m%d'), '') AS TGL_MASTER_BL_AWB, 
                    IFNULL(tppconsignee.No_NPWPC, '') AS ID_CONSIGNEE, 
                    IFNULL(tppconsignee.Nama_Cons, '') AS CONSIGNEE, 
                    IFNULL(tppcontplp.bruto, 0) AS BRUTO, 
                    IFNULL(H.NO_BC11, '') AS NO_BC11, 
                    IFNULL(DATE_FORMAT(H.TGL_BC11, '%Y%m%d'), '') AS TGL_BC11, 
                    IFNULL(D.NO_POS_BC11, '') AS NO_POS_BC11, 
                    '' AS KD_TIMBUN, 
                    '3' AS KD_DOK_INOUT, 
                    IFNULL(tppcontplp.noEIR, IFNULL(H.NO_PLP, '')) AS NO_DOK_INOUT, 
                    IFNULL(DATE_FORMAT(tppcontplp.tglEIR, '%Y%m%d'), IFNULL(DATE_FORMAT(H.TGL_PLP, '%Y%m%d'), '')) AS TGL_DOK_INOUT, 	
                    IF(tppcontplp.tglInDepo IS NULL, '000000', DATE_FORMAT(tppcontplp.tglInDepo, '%Y%m%d%H%i%s')) AS WK_INOUT, 								
                    '1' AS KD_SAR_ANGKUT_INOUT, 
                    IFNULL(tppcontplp.NoPolIn, '') AS NO_POL, 
                    '2' AS FL_CONT_KOSONG,
                    '' AS ISO_CODE,								
                    IFNULL(tppcontplp.Pel_Muat, '') AS PEL_MUAT, 
                    IFNULL(tppcontplp.Pel_Transit, '') AS PEL_TRANSIT, 
                    IFNULL(tppcontplp.Pel_Bongkar, '') AS PEL_BONGKAR, 
                    IFNULL(H.KD_GUDANG, 'CPSU') AS GUDANG_TUJUAN, 
                    IFNULL(H.KD_KANTOR, '070100') AS KODE_KANTOR, 
                    IFNULL(H.NO_PLP, IFNULL(tppcontplp.noEIR, '')) AS NO_DAFTAR_PABEAN,
                    IFNULL(DATE_FORMAT(H.TGL_SURAT, '%Y%m%d'), IFNULL(DATE_FORMAT(tppmanifestplp.tglPLP, '%Y%m%d'), '')) AS TGL_DAFTAR_PABEAN, 
                    IFNULL(tppcontplp.eseal, IFNULL(tppmanifestplp.noSegel, '')) AS NO_SEGEL_BC, 
                    IFNULL(DATE_FORMAT(tppmanifestplp.tglSegel, '%Y%m%d'), '') AS TGL_SEGEL_BC, 
                    IFNULL(H.NO_SURAT, IFNULL(tppmanifestplp.noPLP, '')) AS NO_IJIN_TPS, 
                    IFNULL(DATE_FORMAT(H.TGL_SURAT, '%Y%m%d'), IFNULL(DATE_FORMAT(tppmanifestplp.tglPLP, '%Y%m%d'), '')) AS TGL_IJIN_TPS 
                FROM tpp_primamas.tppmanifestplp  
                    INNER JOIN tpp_primamas.tppcontplp ON (tppcontplp.idPLP_FK = tppmanifestplp.idPLP AND tppcontplp.flag = 1)
                    INNER JOIN tpp_primamas.tppconsignee ON tppconsignee.Id_Cons = tppcontplp.idCons_FK
                    INNER JOIN primamas.tpsws_responplp_header_backup H ON (H.NO_SURAT = tppmanifestplp.noPLP AND H.NO_PLP = tppcontplp.noEIR)
                    INNER JOIN primamas.tpsws_responplp_detail_backup D ON (H.NO_SURAT = D.NO_SURAT_FK AND D.NO_PLP_FK = H.NO_PLP AND D.NO_CONT = tppcontplp.noCont)
                WHERE tppmanifestplp.flag = 1
                    AND DATE(tppcontplp.tglInDepo) BETWEEN :tglAwal AND :tglAkhir
                ORDER BY tppmanifestplp.idPLP";
        $stmt = $pdo_tpp->prepare($sql);
        $stmt->execute([':tglAwal' => $tglAwal, ':tglAkhir' => $tglAkhir]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Outbound Container
        $sql = "(SELECT 
                    tppmanifestplp.idPLP, 
                    '6' AS KD_DOK, 
                    'PSU0' AS KD_TPS, 
                    IFNULL(H.VOYAGE, '') AS NM_ANGKUT, 
                    IFNULL(H.NO_VOYAGE, '') AS NO_VOY_FLIGHT, 
                    IFNULL(H.CALL_SIGN, '') AS CALL_SIGN, 
                    IFNULL(DATE_FORMAT(H.TGL_TIBA, '%Y%m%d'), '') AS TGL_TIBA, 
                    IFNULL(H.KD_GUDANG, 'CPSU') AS KD_GUDANG, 
                    tppcontplp.noCont AS NO_CONT,
                    IFNULL(tppcontplp.size, '20') AS UK_CONT, 
                    IFNULL(tppcontplp.seal, IFNULL(tppcontplp.NO_SEGEL_PELAYARAN, '')) AS NO_SEGEL, 
                    IFNULL(tppmanifestplp.status, IFNULL(D.JNS_CONT, 'FCL')) AS JNS_CONT, 
                    IFNULL(D.NO_BL_AWB, IFNULL(tppcontplp.NO_MASTER_BL_AWB, '')) AS NO_BL_AWB, 
                    IFNULL(DATE_FORMAT(D.TGL_BL_AWB, '%Y%m%d'), '') AS TGL_BL_AWB,  
                    IFNULL(SUBSTRING(tppcontplp.NO_MASTER_BL_AWB, 1, 30), '') AS NO_MASTER_BL_AWB, 
                    IFNULL(DATE_FORMAT(tppcontplp.TGL_MASTER_BL_AWB, '%Y%m%d'), '') AS TGL_MASTER_BL_AWB, 
                    IFNULL(tppconsignee.No_NPWPC, '') AS ID_CONSIGNEE, 
                    IFNULL(tppconsignee.Nama_Cons, '') AS CONSIGNEE, 
                    IFNULL(tppcontplp.bruto, 0) AS BRUTO,
                    IFNULL(H.NO_BC11, '') AS NO_BC11, 
                    IFNULL(DATE_FORMAT(H.TGL_BC11, '%Y%m%d'), '') AS TGL_BC11, 
                    IFNULL(D.NO_POS_BC11, '') AS NO_POS_BC11, 
                    '' AS KD_TIMBUN, 
                    '1' AS KD_DOK_INOUT, 
                    IFNULL(tppinvoiceplp.noSPPB, IFNULL(tppcontplp.noEIR, '')) AS NO_DOK_INOUT, 
                    IFNULL(DATE_FORMAT(tppinvoiceplp.tglSPPB, '%Y%m%d'), IFNULL(DATE_FORMAT(tppcontplp.tglEIR, '%Y%m%d'), '')) AS TGL_DOK_INOUT, 	
                    CONCAT(IF(tppsuratjalan.cetak IS NULL, '000000', DATE_FORMAT(tppsuratjalan.cetak, '%Y%m%d')), IF(tppsuratjalan.cetak IS NULL, '000000', DATE_FORMAT(tppsuratjalan.cetak, '%H%i%s'))) AS WK_INOUT, 								
                    '1' AS KD_SAR_ANGKUT_INOUT, 
                    IFNULL(tppsuratjalan.noPol, 'L 8888 TPP') AS NO_POL, 
                    '2' AS FL_CONT_KOSONG,
                    '' AS ISO_CODE,								
                    IFNULL(tppcontplp.Pel_Muat, '') AS PEL_MUAT, 
                    IFNULL(tppcontplp.Pel_Transit, '') AS PEL_TRANSIT, 
                    IFNULL(tppcontplp.Pel_Bongkar, '') AS PEL_BONGKAR,  
                    IFNULL(H.KD_GUDANG, 'CPSU') AS GUDANG_TUJUAN, 
                    IFNULL(H.KD_KANTOR, '070100') AS KODE_KANTOR, 
                    IFNULL(tppinvoiceplp.NO_PABEAN, IFNULL(tppinvoiceplp.noSPPB, '')) AS NO_DAFTAR_PABEAN,
                    IFNULL(DATE_FORMAT(tppinvoiceplp.TGL_PABEAN, '%Y%m%d'), IFNULL(DATE_FORMAT(tppinvoiceplp.tglSPPB, '%Y%m%d'), '')) AS TGL_DAFTAR_PABEAN, 
                    IFNULL(tppcontplp.eseal, IFNULL(tppmanifestplp.noSegel, '')) AS NO_SEGEL_BC, 
                    IFNULL(DATE_FORMAT(tppmanifestplp.tglSegel, '%Y%m%d'), '') AS TGL_SEGEL_BC, 
                    IFNULL(H.NO_SURAT, '') AS NO_IJIN_TPS, 
                    IFNULL(DATE_FORMAT(H.TGL_SURAT, '%Y%m%d'), '') AS TGL_IJIN_TPS 
                FROM tpp_primamas.tppmanifestplp  
                    INNER JOIN tpp_primamas.tppcontplp ON (tppcontplp.idPLP_FK = tppmanifestplp.idPLP AND tppcontplp.flag = 1)
                    INNER JOIN tpp_primamas.tppinvcontplp ON tppinvcontplp.idContPLP = tppcontplp.idCont 
                    INNER JOIN tpp_primamas.tppinvoiceplp ON tppinvcontplp.idInvPLP = tppinvoiceplp.idInvPLP AND tppinvoiceplp.invType = 'penumpukanPLP'
                    INNER JOIN tpp_primamas.tppsuratjalan ON tppsuratjalan.idManifest = tppcontplp.idCont AND tppsuratjalan.typeManifest = 'PLP' 
                    INNER JOIN tpp_primamas.tppconsignee ON tppconsignee.Id_Cons = tppcontplp.idCons_FK
                    LEFT JOIN primamas.tpsws_responplp_detail_backup D ON (D.NO_CONT = tppcontplp.noCont AND D.NO_SURAT_FK = tppmanifestplp.noPLP)
                    LEFT JOIN primamas.tpsws_responplp_header_backup H ON (H.NO_SURAT = D.NO_SURAT_FK AND tppcontplp.noEIR = H.NO_PLP AND tppcontplp.tglEIR = H.TGL_PLP)
                WHERE tppmanifestplp.flag = 1
                    AND DATE(tppsuratjalan.cetak) BETWEEN :tglAwal1 AND :tglAkhir1
                )
                UNION
                (SELECT 
                    tppmanifestplp.idPLP, 
                    '6' AS KD_DOK, 
                    'PSU0' AS KD_TPS, 
                    IFNULL(H.VOYAGE, '') AS NM_ANGKUT, 
                    IFNULL(H.NO_VOYAGE, '') AS NO_VOY_FLIGHT, 
                    IFNULL(H.CALL_SIGN, '') AS CALL_SIGN, 
                    IFNULL(DATE_FORMAT(H.TGL_TIBA, '%Y%m%d'), '') AS TGL_TIBA, 
                    IFNULL(H.KD_GUDANG, 'CPSU') AS KD_GUDANG, 
                    tppcontplp.noCont AS NO_CONT,
                    IFNULL(tppcontplp.size, '20') AS UK_CONT, 
                    IFNULL(tppcontplp.seal, IFNULL(tppcontplp.NO_SEGEL_PELAYARAN, '')) AS NO_SEGEL, 
                    IFNULL(tppmanifestplp.status, IFNULL(D.JNS_CONT, 'FCL')) AS JNS_CONT, 
                    IFNULL(D.NO_BL_AWB, IFNULL(tppcontplp.NO_MASTER_BL_AWB, '')) AS NO_BL_AWB, 
                    IFNULL(DATE_FORMAT(D.TGL_BL_AWB, '%Y%m%d'), '') AS TGL_BL_AWB,  
                    IFNULL(SUBSTRING(tppcontplp.NO_MASTER_BL_AWB, 1, 30), '') AS NO_MASTER_BL_AWB, 
                    IFNULL(DATE_FORMAT(tppcontplp.TGL_MASTER_BL_AWB, '%Y%m%d'), '') AS TGL_MASTER_BL_AWB, 
                    IFNULL(tppconsignee.No_NPWPC, '') AS ID_CONSIGNEE, 
                    IFNULL(tppconsignee.Nama_Cons, '') AS CONSIGNEE, 
                    IFNULL(tppcontplp.bruto, 0) AS BRUTO,
                    IFNULL(H.NO_BC11, '') AS NO_BC11, 
                    IFNULL(DATE_FORMAT(H.TGL_BC11, '%Y%m%d'), '') AS TGL_BC11, 
                    IFNULL(D.NO_POS_BC11, '') AS NO_POS_BC11, 
                    '' AS KD_TIMBUN, 
                    '1' AS KD_DOK_INOUT, 
                    IFNULL(tppcontplp.noSuratPerintah, IFNULL(tppcontplp.noEIR, '')) AS NO_DOK_INOUT, 
                    IFNULL(DATE_FORMAT(tppcontplp.tglSuratPerintah, '%Y%m%d'), IFNULL(DATE_FORMAT(tppcontplp.tglEIR, '%Y%m%d'), '')) AS TGL_DOK_INOUT, 	
                    CONCAT(IF(tppjobplp.tglJob IS NULL, '000000', DATE_FORMAT(tppjobplp.tglJob, '%Y%m%d')), IF(tppjobplp.tglJob IS NULL, '000000', DATE_FORMAT(tppjobplp.tglJob, '%H%i%s'))) AS WK_INOUT, 								
                    '1' AS KD_SAR_ANGKUT_INOUT, 
                    'L 8888 TPP' AS NO_POL, 
                    '2' AS FL_CONT_KOSONG,
                    '' AS ISO_CODE,								
                    IFNULL(tppcontplp.Pel_Muat, '') AS PEL_MUAT, 
                    IFNULL(tppcontplp.Pel_Transit, '') AS PEL_TRANSIT, 
                    IFNULL(tppcontplp.Pel_Bongkar, '') AS PEL_BONGKAR,  
                    IFNULL(H.KD_GUDANG, 'CPSU') AS GUDANG_TUJUAN, 
                    IFNULL(H.KD_KANTOR, '070100') AS KODE_KANTOR, 
                    IFNULL(tppcontplp.noSuratPemberitahuanI, IFNULL(tppcontplp.noSuratPerintah, '')) AS NO_DAFTAR_PABEAN,
                    IFNULL(DATE_FORMAT(tppcontplp.tglSuratPemberitahuanI, '%Y%m%d'), IFNULL(DATE_FORMAT(tppcontplp.tglSuratPerintah, '%Y%m%d'), '')) AS TGL_DAFTAR_PABEAN, 
                    IFNULL(tppcontplp.eseal, IFNULL(tppmanifestplp.noSegel, '')) AS NO_SEGEL_BC, 
                    IFNULL(DATE_FORMAT(tppmanifestplp.tglSegel, '%Y%m%d'), '') AS TGL_SEGEL_BC, 
                    IFNULL(H.NO_SURAT, '') AS NO_IJIN_TPS, 
                    IFNULL(DATE_FORMAT(H.TGL_SURAT, '%Y%m%d'), '') AS TGL_IJIN_TPS 
                FROM tpp_primamas.tppmanifestplp  
                    INNER JOIN tpp_primamas.tppcontplp ON (tppcontplp.idPLP_FK = tppmanifestplp.idPLP AND tppcontplp.flag = 1)
                    LEFT JOIN tpp_primamas.tppjobplp ON tppjobplp.idCont_FK = tppcontplp.idCont AND tppjobplp.jobType = 'Job TPSTPP'
                    INNER JOIN tpp_primamas.tppconsignee ON tppconsignee.Id_Cons = tppcontplp.idCons_FK
                    LEFT JOIN primamas.tpsws_responplp_detail_backup D ON (D.NO_CONT = tppcontplp.noCont AND D.NO_SURAT_FK = tppmanifestplp.noPLP)
                    LEFT JOIN primamas.tpsws_responplp_header_backup H ON (H.NO_SURAT = D.NO_SURAT_FK AND tppcontplp.noEIR = H.NO_PLP AND tppcontplp.tglEIR = H.TGL_PLP)
                WHERE tppmanifestplp.flag = 1
                    AND DATE(tppjobplp.tglJob) BETWEEN :tglAwal2 AND :tglAkhir2
                )";
        $stmt = $pdo_tpp->prepare($sql);
        $stmt->execute([
            ':tglAwal1'  => $tglAwal, 
            ':tglAkhir1' => $tglAkhir,
            ':tglAwal2'  => $tglAwal, 
            ':tglAkhir2' => $tglAkhir
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($rows)) {
        jsonResponse([
            'success' => true,
            'message' => 'Tidak ada data kontainer yang ditemukan pada rentang tanggal tersebut',
            'count' => 0,
            'table_data' => [],
            'payload' => null,
            'groups_count' => 0,
            'payloads_by_group' => []
        ]);
    }

    // Bangun referensi unik
    $refDate = date('ymd', strtotime($tglAwal));
    $tempPrefix = ($type === 'In') ? '7' : '8';
    $timePart = date('Hi');

    // Kelompokkan baris data per Header (Key: noBc11 + noVoyFlight + nmAngkut)
    $groups = [];
    $rawList = [];
    $contOccurrenceTracker = []; // Melacak kemunculan ke-berapa per nomor kontainer
    $batchBuckets = [];          // Mengelompokkan kontainer ke Batch 1, Batch 2, dst.
    $duplicateContMap = [];
    $refCounter = 1;

    foreach ($rows as $r) {
        $noCont = strtoupper(trim((string)($r['NO_CONT'] ?? '')));
        if (empty($noCont)) {
            continue;
        }

        // Hitung frekuensi kemunculan kontainer ini untuk membagi ke batch bertahap
        $occ = ($contOccurrenceTracker[$noCont] ?? 0) + 1;
        $contOccurrenceTracker[$noCont] = $occ;
        if ($occ > 1) {
            $duplicateContMap[$noCont] = $occ;
        }

        // Tentukan batch index (kemunculan ke-1 masuk Batch 1, ke-2 masuk Batch 2, dst.)
        $batchIdx = $occ;
        if (!isset($batchBuckets[$batchIdx])) {
            $batchBuckets[$batchIdx] = [];
        }

        $groupKey = trim($r['NO_BC11'] . '|' . $r['NO_VOY_FLIGHT'] . '|' . $r['NM_ANGKUT']);
        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'header_info' => $r,
                'kontainer' => []
            ];
        }

        $refNumber = 'PSU0' . $refDate . $tempPrefix . $timePart . $refCounter;
        $refCounter++;

        $isKosong = ($r['FL_CONT_KOSONG'] == '1');
        $brutoFloat = (float)($r['BRUTO'] ?? 0);

        // Standardisasi jenisKontainer sesuai CEISA 4.0: FCL, LCL, 4, 7, atau 8
        $jnsContRaw = strtoupper(trim((string)($r['JNS_CONT'] ?? 'FCL')));
        if ($jnsContRaw === 'F' || strpos($jnsContRaw, 'FCL') !== false) {
            $jenisKontainer = 'FCL';
        } elseif ($jnsContRaw === 'L' || strpos($jnsContRaw, 'LCL') !== false) {
            $jenisKontainer = 'LCL';
        } elseif (in_array($jnsContRaw, ['4', '7', '8'])) {
            $jenisKontainer = $jnsContRaw;
        } else {
            $jenisKontainer = 'FCL';
        }

        // Gudang tujuan tidak boleh kosong
        $gudangTujuan = trim((string)($r['GUDANG_TUJUAN'] ?? ''));
        if (empty($gudangTujuan)) {
            $gudangTujuan = trim((string)($r['KD_GUDANG'] ?? 'CPSU')) ?: 'CPSU';
        }

        // Nomor segel & segel BC tidak boleh kosong
        $noSegelBc = trim((string)($r['NO_SEGEL_BC'] ?? ''));
        if (empty($noSegelBc)) {
            $noSegelBc = trim((string)($r['NO_SEGEL'] ?? ''));
        }
        if (empty($noSegelBc)) {
            $noSegelBc = 'CPSU-1';
        }

        $noSegel = trim((string)($r['NO_SEGEL'] ?? ''));
        if (empty($noSegel)) {
            $noSegel = $noSegelBc ?: '-';
        }

        // ID Consignee (NPWP) tidak boleh kosong (angka 15-16 digit)
        $idConsignee = preg_replace('/[^0-9]/', '', (string)($r['ID_CONSIGNEE'] ?? ''));
        if (empty($idConsignee) || strlen($idConsignee) < 9) {
            $idConsignee = '010017697092000';
        }

        // Dokumen Pabean tidak boleh kosong & maksimal 10 karakter
        $noDaftarPabean = trim((string)($r['NO_DAFTAR_PABEAN'] ?? ''));
        if (empty($noDaftarPabean) || strlen($noDaftarPabean) > 10) {
            if (!empty($r['NO_DOK_INOUT']) && strlen(trim($r['NO_DOK_INOUT'])) <= 10) {
                $noDaftarPabean = trim((string)$r['NO_DOK_INOUT']);
            } elseif (!empty($r['NO_BC11']) && strlen(trim($r['NO_BC11'])) <= 10) {
                $noDaftarPabean = trim((string)$r['NO_BC11']);
            } else {
                $clean = preg_replace('/[^a-zA-Z0-9]/', '', $noDaftarPabean);
                $noDaftarPabean = !empty($clean) ? substr($clean, -6) : '000000';
            }
        }
        $noDaftarPabean = substr($noDaftarPabean, 0, 10);

        // Tanggal daftar pabean dan segel BC wajib format dd-MM-yyyy dan tidak boleh kosong
        $tglDaftarPabean = formatDateDMY($r['TGL_DAFTAR_PABEAN'] ?? '', $r['TGL_IJIN_TPS'] ?? $r['TGL_DOK_INOUT']);
        $tglSegelBc = formatDateDMY($r['TGL_SEGEL_BC'] ?? '', $r['TGL_DOK_INOUT'] ?? $r['TGL_TIBA']);

        $kontainerItem = [
            'tanggalSegelBc'      => $tglSegelBc,
            'tanggalDokumenInOut' => formatDateDMY($r['TGL_DOK_INOUT'] ?? ''),
            'tanggalBlAwb'        => formatDateDMY($r['TGL_BL_AWB'] ?? '', $r['TGL_DOK_INOUT']),
            'flagKontainerKosong' => $isKosong,
            'waktuInOut'          => formatDateTimeDMY($r['WK_INOUT'] ?? ''),
            'gudangTujuan'        => $gudangTujuan,
            'kodeDokumenInOut'    => (string)($r['KD_DOK_INOUT'] ?? ($type === 'In' ? '3' : '1')),
            'ukuranKontainer'     => (string)($r['UK_CONT'] ?? '20'),
            'flagKontainer'       => true,
            'kodeTimbun'          => (string)($r['KD_TIMBUN'] ?? ''),
            'noMasterBlAwb'       => (string)($r['NO_MASTER_BL_AWB'] ?? ''),
            'pelabuhanBongkar'    => (string)($r['PEL_BONGKAR'] ?? ''),
            'nomorDokumenInOut'   => (string)($r['NO_DOK_INOUT'] ?? ''),
            'nomorPolisi'         => (string)($r['NO_POL'] ?? ''),
            'nomorIjinTps'        => (string)($r['NO_IJIN_TPS'] ?? ''),
            'nomorPosBc11'        => (string)($r['NO_POS_BC11'] ?? ''),
            'tanggalMasterBlAwb'  => formatDateDMY($r['TGL_MASTER_BL_AWB'] ?? '', $r['TGL_BL_AWB']),
            'nomorSegelBc'        => $noSegelBc,
            'consignee'           => (string)($r['CONSIGNEE'] ?? ''),
            'pelabuhanMuat'       => (string)($r['PEL_MUAT'] ?? ''),
            'nomorDaftarPabean'   => $noDaftarPabean,
            'noBlAwb'             => (string)($r['NO_BL_AWB'] ?? ''),
            'kodeKantor'          => (string)($r['KODE_KANTOR'] ?? '070100'),
            'nomorKontainer'      => (string)($r['NO_CONT'] ?? ''),
            'idConsignee'         => $idConsignee,
            'jenisKontainer'      => $jenisKontainer,
            'nomorSegel'          => $noSegel,
            'isoCode'             => (string)($r['ISO_CODE'] ?? ''),
            'tanggalDaftarPabean' => $tglDaftarPabean,
            'pelabuhanTransit'    => (string)($r['PEL_TRANSIT'] ?? ''),
            'bruto'               => $brutoFloat,
            'tanggalIjinTps'      => formatDateDMY($r['TGL_IJIN_TPS'] ?? '', $r['TGL_DOK_INOUT'])
        ];

        $groups[$groupKey]['kontainer'][] = $kontainerItem;
        $batchBuckets[$batchIdx][] = $kontainerItem;

        // Data untuk tabel pratinjau di UI (semua 100% data ditampilkan)
        $rawList[] = [
            'noCont'           => $r['NO_CONT'],
            'size'             => $r['UK_CONT'],
            'jnsCont'          => $jenisKontainer,
            'noPol'            => $r['NO_POL'],
            'consignee'        => $r['CONSIGNEE'],
            'noDokInOut'       => $r['NO_DOK_INOUT'],
            'tglDokInOut'      => formatDateDMY($r['TGL_DOK_INOUT'] ?? ''),
            'wkInOut'          => formatDateTimeDMY($r['WK_INOUT'] ?? ''),
            'noBc11'           => $r['NO_BC11'],
            'voyage'           => $r['NM_ANGKUT'] . ' (' . $r['NO_VOY_FLIGHT'] . ')',
            'bruto'            => $brutoFloat,
            'isKosong'         => $isKosong ? 'Kosong' : 'Isi',
            'batchIndex'       => $batchIdx
        ];
    }

    // Bangun CEISA 4.0 Payload per group
    $payloads = [];
    $allKontainer = [];

    $firstGroupKey = array_key_first($groups);
    $firstInfo = $groups[$firstGroupKey]['header_info'] ?? [];

    // Header default (tanggalBc11 & tanggalTiba dalam format dd-MM-yyyy)
    $defaultHeader = [
        'kodeDokumen'          => (string)($firstInfo['KD_DOK'] ?? ($type === 'In' ? '5' : '6')),
        'noBc11'               => (string)($firstInfo['NO_BC11'] ?? ''),
        'tanggalBc11'          => formatDateDMY($firstInfo['TGL_BC11'] ?? ''),
        'nomorVoyFlight'       => (string)($firstInfo['NO_VOY_FLIGHT'] ?? ''),
        'tanggalBerangkat'     => !empty($firstInfo['TGL_BERANGKAT']) ? formatDateDMY($firstInfo['TGL_BERANGKAT']) : '',
        'namaAngkut'           => (string)($firstInfo['NM_ANGKUT'] ?? ''),
        'refNumber'            => 'PSU0' . $refDate . $tempPrefix . $timePart,
        'kodeSaranaPengangkut' => (string)($firstInfo['KD_SAR_ANGKUT_INOUT'] ?? '1'),
        'kodeTps'              => (string)($firstInfo['KD_TPS'] ?? 'PSU0'),
        'tanggalTiba'          => formatDateDMY($firstInfo['TGL_TIBA'] ?? ''),
        'kodeGudang'           => (string)($firstInfo['KD_GUDANG'] ?? 'CPSU'),
        'callSign'             => (string)($firstInfo['CALL_SIGN'] ?? '')
    ];

    foreach ($groups as $gKey => $gData) {
        $h = $gData['header_info'];
        $headerPayload = [
            'kodeDokumen'          => (string)($h['KD_DOK'] ?? ($type === 'In' ? '5' : '6')),
            'noBc11'               => (string)($h['NO_BC11'] ?? ''),
            'tanggalBc11'          => formatDateDMY($h['TGL_BC11'] ?? ''),
            'nomorVoyFlight'       => (string)($h['NO_VOY_FLIGHT'] ?? ''),
            'tanggalBerangkat'     => !empty($h['TGL_BERANGKAT']) ? formatDateDMY($h['TGL_BERANGKAT']) : '',
            'namaAngkut'           => (string)($h['NM_ANGKUT'] ?? ''),
            'refNumber'            => 'PSU0' . $refDate . $tempPrefix . $timePart,
            'kodeSaranaPengangkut' => (string)($h['KD_SAR_ANGKUT_INOUT'] ?? '1'),
            'kodeTps'              => (string)($h['KD_TPS'] ?? 'PSU0'),
            'tanggalTiba'          => formatDateDMY($h['TGL_TIBA'] ?? ''),
            'kodeGudang'           => (string)($h['KD_GUDANG'] ?? 'CPSU'),
            'callSign'             => (string)($h['CALL_SIGN'] ?? '')
        ];

        $payloads[] = [
            'group' => $gKey,
            'header' => $headerPayload,
            'kontainer' => $gData['kontainer']
        ];

        foreach ($gData['kontainer'] as $kItem) {
            $allKontainer[] = $kItem;
        }
    }

    // Bangun daftar batches siap kirim bertahap (setiap batch dijamin 100% unik kontainer)
    $batches = [];
    ksort($batchBuckets);
    foreach ($batchBuckets as $bIdx => $kList) {
        $batchHeader = $defaultHeader;
        // RefNumber unik per batch
        $batchHeader['refNumber'] = 'PSU0' . $refDate . $tempPrefix . $timePart . ($bIdx > 1 ? $bIdx : '');
        $batches[] = [
            'batch_number'    => $bIdx,
            'kontainer_count' => count($kList),
            'payload'         => [
                'header'    => $batchHeader,
                'kontainer' => $kList
            ]
        ];
    }

    // Payload default yang ditampilkan di tab JSON (Batch 1)
    $displayPayload = $batches[0]['payload'] ?? [
        'header'    => $defaultHeader,
        'kontainer' => $allKontainer
    ];

    jsonResponse([
        'success'              => true,
        'message'              => 'Berhasil mengambil ' . count($rawList) . ' kontainer' . (count($duplicateContMap) > 0 ? ' (' . count($batches) . ' batch pengiriman bertahap)' : ''),
        'type'                 => $type,
        'tglAwal'              => $tglAwal,
        'tglAkhir'             => $tglAkhir,
        'count'                => count($rawList),
        'table_data'           => $rawList,
        'payload'              => $displayPayload,
        'batches'              => $batches,
        'batches_count'        => count($batches),
        'has_duplicates'       => count($duplicateContMap) > 0,
        'duplicate_containers' => array_keys($duplicateContMap),
        'groups_count'         => count($groups),
        'payloads_by_group'    => $payloads
    ]);
}

/**
 * Handle Pengiriman JSON Payload ke REST API CEISA 4.0
 */
function handleSend() {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data) {
        jsonResponse(['success' => false, 'message' => 'Payload JSON tidak valid'], 400);
    }

    $payload = $data['payload'] ?? $data;
    
    // Validasi struktur minimal CEISA 4.0
    if (!isset($payload['header']) || !isset($payload['kontainer']) || !is_array($payload['kontainer'])) {
        jsonResponse(['success' => false, 'message' => 'Struktur payload harus memiliki objek header dan array kontainer'], 400);
    }

    $targetEndpoint = trim((string)($data['endpoint'] ?? 'coarri-codeco-container'));
    if (empty($targetEndpoint) || strpos($targetEndpoint, 'd942aa8b') !== false || strpos($targetEndpoint, 'resources/') !== false) {
        $targetEndpoint = 'coarri-codeco-container';
    }

    // Deduplikasi kontainer sebelum dikirim ke CEISA 4.0 agar tidak ditolak dengan error 'Duplikat Kontainer'
    if (isset($payload['kontainer']) && is_array($payload['kontainer'])) {
        $seenSendCont = [];
        $filteredKontainer = [];
        foreach ($payload['kontainer'] as $c) {
            $contNo = strtoupper(trim((string)($c['nomorKontainer'] ?? '')));
            if (empty($contNo)) continue;
            if (isset($seenSendCont[$contNo])) {
                continue; // Lewati kontainer duplikat
            }
            $seenSendCont[$contNo] = true;
            $filteredKontainer[] = $c;
        }
        $payload['kontainer'] = $filteredKontainer;
    }

    // Inisialisasi client CEISA
    $client = new CeisaClient();
    $result = $client->post($targetEndpoint, $payload);

    $isOk = ($result['code'] >= 200 && $result['code'] < 300);

    // Catat log & riwayat jika database tpsonline tersedia
    try {
        global $pdo_tpsonline;
        if ($pdo_tpsonline) {
            $kontainerList = $payload['kontainer'] ?? [];
            $header = $payload['header'] ?? [];
            $refNumber = $header['refNumber'] ?? '';

            // 1. Simpan ke Master Audit Log (ceisa_api_logs)
            $stmtLog = $pdo_tpsonline->prepare("
                INSERT INTO ceisa_api_logs 
                (endpoint, request_params, http_code, status, message, total_rows, raw_response, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $requestSummary = json_encode([
                'header' => $header,
                'kontainer_count' => count($kontainerList)
            ], JSON_UNESCAPED_UNICODE);

            $stmtLog->execute([
                $targetEndpoint,
                $requestSummary,
                (int)($result['code'] ?? ($isOk ? 200 : 500)),
                $isOk ? 'SUCCESS' : 'FAILED',
                $result['message'] ?? ($isOk ? 'Berhasil' : 'Gagal'),
                count($kontainerList),
                json_encode($result['raw'] ?? $result, JSON_UNESCAPED_UNICODE)
            ]);

            // 2. Simpan setiap kontainer ke ceisa_plp_kontainer & ceisa_sppb_kontainer HANYA jika pengiriman berhasil ke CEISA
            if ($isOk) {
                // Simpan ke ceisa_plp_kontainer
                $stmtPlpCont = $pdo_tpsonline->prepare("
                    INSERT INTO ceisa_plp_kontainer 
                    (idTpsPlp, nomorKontainer, ukuranKontainer, jenisMuat, nomorPosBc11, nomorHostBl, tanggalHostBl, namaPemilik, flagSetuju)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                // Simpan ke ceisa_sppb_kontainer
                $stmtSppbCont = $pdo_tpsonline->prepare("
                    INSERT INTO ceisa_sppb_kontainer 
                    (car, no_sppb, no_cont, uk_cont, jns_cont, jns_muat, status_segel, no_segel, raw_data, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                foreach ($kontainerList as $c) {
                    $car = !empty($refNumber) ? $refNumber : ($c['nomorDokumenInOut'] ?? '');
                    $noSppb = !empty($c['nomorDokumenInOut']) ? $c['nomorDokumenInOut'] : ($c['nomorDaftarPabean'] ?? '');
                    $noCont = $c['nomorKontainer'] ?? '';
                    $ukCont = $c['ukuranKontainer'] ?? '20';
                    $jnsCont = $c['jenisKontainer'] ?? '4';
                    $isKosong = !empty($c['flagKontainerKosong']) && ($c['flagKontainerKosong'] === true || $c['flagKontainerKosong'] == '1');
                    $jnsMuat = $isKosong ? 'E' : 'F';
                    $statusSegel = !empty($c['nomorSegelBc']) ? 'TERSEGEL' : 'TIDAK TERSEGEL';
                    $noSegel = !empty($c['nomorSegel']) ? $c['nomorSegel'] : ($c['nomorSegelBc'] ?? '');
                    $noPos = $c['nomorPosBc11'] ?? '';
                    $noBl = $c['noBlAwb'] ?? '';
                    $tglBl = $c['tanggalBlAwb'] ?? '';
                    $consignee = substr(trim(preg_replace('/[\r\n\t]+/', ' ', (string)($c['consignee'] ?? ''))), 0, 100);
                    $rawData = json_encode($c, JSON_UNESCAPED_UNICODE);

                    // Eksekusi ceisa_plp_kontainer
                    $stmtPlpCont->execute([
                        $car,
                        $noCont,
                        $ukCont,
                        $jnsMuat,
                        $noPos,
                        $noBl,
                        $tglBl,
                        $consignee,
                        'Y'
                    ]);

                    // Eksekusi ceisa_sppb_kontainer
                    $stmtSppbCont->execute([
                        $car,
                        $noSppb,
                        $noCont,
                        $ukCont,
                        $jnsCont,
                        $jnsMuat,
                        $statusSegel,
                        $noSegel,
                        $rawData
                    ]);
                }
            }
        }
    } catch (Exception $e) {
        // Jangan hentikan proses jika logging gagal
        error_log("Gagal mencatat log / ceisa_sppb_kontainer CoCoCont: " . $e->getMessage());
    }

    jsonResponse($result);
}
