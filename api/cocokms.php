<?php
/**
 * Backend API Coarri Codeco Kemasan (CoCoKms) CEISA 4.0
 * Melayani:
 * 1. action=fetch -> Menarik data kemasan dari DB primamas (In_kms / Out_kms) dan menghasilkan JSON standar CEISA 4.0
 * 2. action=send  -> Mengirim payload JSON ke REST API CEISA 4.0 Gateway (/coarri-codeco-kemasan)
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/CeisaClient.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$action = input('action', 'fetch');

/**
 * Normalisasi format tanggal input HTML5 YYYY-MM-DD ke YYYY-MM-DD
 */
function normalizeInputDate($dateStr) {
    $dateStr = trim((string)$dateStr);
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
 * Konversi tanggal database ke format CEISA 4.0: dd-MM-yyyy
 */
function toCeisaDmy($val, $fallback = '') {
    $val = trim((string)$val);
    if (empty($val) || $val === '00000000' || $val === '0000-00-00' || $val === '00-00-0000' || $val === '0000-00-00 00:00:00') {
        return !empty($fallback) ? toCeisaDmy($fallback, '') : '';
    }
    // Format YYYYMMDD (8 digit)
    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $val, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    // Format YYYY-MM-DD
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $val, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    // Format dd-MM-yyyy
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $val)) {
        return $val;
    }
    $ts = strtotime($val);
    return ($ts && $ts > 0) ? date('d-m-Y', $ts) : '';
}

/**
 * Konversi waktu database ke format CEISA 4.0: dd-MM-yyyy HH:mm:ss
 */
function toCeisaDateTime($val, $fallback = '') {
    $val = trim((string)$val);
    if (empty($val) || $val === '00000000000000' || $val === '0000-00-00 00:00:00') {
        return !empty($fallback) ? toCeisaDateTime($fallback, '') : date('d-m-Y H:i:s');
    }
    // Format YYYYMMDDHHiiss (14 digit)
    if (preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$/', $val, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]} {$m[4]}:{$m[5]}:{$m[6]}";
    }
    // Format YYYY-MM-DD HH:ii:ss
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/', $val, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]} {$m[4]}:{$m[5]}:{$m[6]}";
    }
    $ts = strtotime($val);
    return ($ts && $ts > 0) ? date('d-m-Y H:i:s', $ts) : date('d-m-Y H:i:s');
}

/**
 * Batasi nomor daftar pabean maksimal 7 karakter sesuai batasan skema CEISA 4.0 Kemasan
 */
function sanitizeNoDaftarPabean($no) {
    $no = trim((string)$no);
    if (empty($no)) return '';
    // Jika mengandung format nomor/kantor/tahun (e.g. 003704/KBC.1104/2026), ambil nomor pokok sebelum slash
    if (strpos($no, '/') !== false) {
        $parts = explode('/', $no);
        $no = trim($parts[0]);
    }
    return substr($no, 0, 7);
}

// ==========================================
// ACTION 1: FETCH DATA DARI DATABASE PRIMAMAS
// ==========================================
if ($action === 'fetch') {
    global $pdo_primamas;

    if (!$pdo_primamas) {
        jsonResponse([
            'success' => false,
            'message' => 'Koneksi database primamas tidak tersedia. Periksa konfigurasi db.php.'
        ], 500);
    }

    $type     = input('type', 'In'); // 'In' (Stripping) atau 'Out' (Pengeluaran Gudang)
    $tglAwal  = normalizeInputDate(input('tglAwal'));
    $tglAkhir = normalizeInputDate(input('tglAkhir'));

    if ($type !== 'In' && $type !== 'Out') {
        jsonResponse(['success' => false, 'message' => 'Tipe harus In atau Out'], 400);
    }

    try {
        if ($type === 'In') {
            // Query Gate-In Kemasan (Stripping Inbound) dari In_kms.php
            $sql = "SELECT 
                        master_bl.Id_MasBL, 
                        '5' AS KD_DOK, 
                        'PSU0' AS KD_TPS, 
                        IFNULL(H.VOYAGE, '') AS NM_ANGKUT, 
                        IFNULL(H.NO_VOYAGE, '') AS NO_VOY_FLIGHT, 
                        IFNULL(H.CALL_SIGN, '') AS CALL_SIGN, 
                        IFNULL(DATE_FORMAT(H.TGL_TIBA,'%Y%m%d'), '') AS TGL_TIBA, 
                        IFNULL(H.KD_GUDANG, 'GPSU') AS KD_GUDANG, 
                        D.NO_BL_AWB AS NO_BL_AWB, 
                        DATE_FORMAT(D.TGL_BL_AWB,'%Y%m%d') AS TGL_BL_AWB, 
                        SUBSTRING(IFNULL(master_bl.No_MasBL, ''),1,30) AS NO_MASTER_BL_AWB, 
                        DATE_FORMAT(master_bl.Tgl_MasBL,'%Y%m%d') AS TGL_MASTER_BL_AWB, 
                        IFNULL(consignee.No_NPWPC, '') AS ID_CONSIGNEE, 
                        IFNULL(consignee.Nama_Cons, '') AS CONSIGNEE, 
                        IFNULL(manifest.BERAT, 0) AS BRUTO, 
                        IFNULL(H.NO_BC11, '') AS NO_BC11, 
                        DATE_FORMAT(H.TGL_BC11,'%Y%m%d') AS TGL_BC11, 
                        IFNULL(D.NO_POS_BC11, '') AS NO_POS_BC11, 
                        REPLACE(REPLACE(IFNULL(kontainer.No_Cont, ''),'-',''),' ','') AS CONT_ASAL,
                        '1' AS SERI_KEMAS, 
                        IFNULL(manifest.Kemasan, 'PK') AS KD_KEMAS, 
                        IFNULL(manifest.JML_Kemasan, 1) AS JML_KEMAS, 
                        IFNULL(H.KD_GUDANG, 'GPSU') AS KD_TIMBUN, 
                        '3' AS KD_DOK_INOUT, 
                        IFNULL(H.NO_PLP, '') AS NO_DOK_INOUT, 
                        DATE_FORMAT(H.TGL_PLP,'%Y%m%d') AS TGL_DOK_INOUT,
                        CONCAT(DATE_FORMAT(manifest.Tgl_StrippingBC,'%Y%m%d'),IF(ISNULL(manifest.jamStrippingBC),'000000',DATE_FORMAT(manifest.jamStrippingBC,'%H%i%s'))) AS WK_INOUT, 
                        '1' AS KD_SAR_ANGKUT_INOUT, 
                        IFNULL(master_bl.nopol_in, '') AS NO_POL, 
                        IFNULL(manifest.Pel_Asal, '') AS PEL_MUAT, 
                        IFNULL(manifest.Pel_Transit, '') AS PEL_TRANSIT, 
                        IFNULL(manifest.Pel_Bongkar, '') AS PEL_BONGKAR, 
                        IFNULL(H.KD_GUDANG, 'GPSU') AS GUDANG_TUJUAN, 
                        IFNULL(H.KD_KANTOR, '070100') AS KODE_KANTOR, 
                        IFNULL(H.NO_PLP, '') AS NO_DAFTAR_PABEAN,
                        DATE_FORMAT(H.TGL_PLP,'%Y%m%d') AS TGL_DAFTAR_PABEAN, 
                        IF(ISNULL(master_bl.No_SegelBC) OR master_bl.No_SegelBC='', IFNULL(H.NO_PLP, 'SGLBC1'), master_bl.No_SegelBC) AS NO_SEGEL_BC, 
                        IF(ISNULL(master_bl.Tgl_SegelBC) OR master_bl.Tgl_SegelBC='', DATE_FORMAT(H.TGL_PLP,'%Y%m%d'), DATE_FORMAT(master_bl.Tgl_SegelBC,'%Y%m%d')) AS TGL_SEGEL_BC, 
                        IFNULL(H.NO_SURAT, '') AS NO_IJIN_TPS, 
                        DATE_FORMAT(H.TGL_SURAT,'%Y%m%d') AS TGL_IJIN_TPS 
                    FROM master_bl  
                        INNER JOIN manifest ON manifest.Id_MasBL_FK = master_bl.Id_MasBL 
                        INNER JOIN kontainer ON master_bl.Id_Kontainer_FK = kontainer.Id_Kontainer
                        LEFT JOIN tpsws_responplp_detail_backup D ON D.NO_BL_AWB = manifest.No_BL 
                        INNER JOIN tpsws_responplp_header_backup H ON H.NO_SURAT = D.NO_SURAT_FK AND H.NO_PLP = D.NO_PLP_FK
                        INNER JOIN consignee ON consignee.Id_Cons = manifest.Id_Cons_FK
                    WHERE manifest.Tgl_StrippingBC BETWEEN :tglAwal AND :tglAkhir
                    ORDER BY master_bl.Id_MasBL";

            $stmt = $pdo_primamas->prepare($sql);
            $stmt->execute([
                ':tglAwal'  => $tglAwal,
                ':tglAkhir' => $tglAkhir
            ]);
            $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } else {
            // Query Gate-Out Kemasan (Pengeluaran Gudang) dari Out_kms.php
            $sql = "SELECT 
                        master_bl.Id_MasBL, 
                        '6' AS KD_DOK, 
                        'PSU0' AS KD_TPS, 
                        IFNULL(H.VOYAGE, '') AS NM_ANGKUT, 
                        IFNULL(H.NO_VOYAGE, '') AS NO_VOY_FLIGHT, 
                        IFNULL(H.CALL_SIGN, '') AS CALL_SIGN, 
                        IFNULL(DATE_FORMAT(H.TGL_TIBA,'%Y%m%d'), '') AS TGL_TIBA, 
                        IFNULL(H.KD_GUDANG, 'GPSU') AS KD_GUDANG, 
                        D.NO_BL_AWB AS NO_BL_AWB, 
                        DATE_FORMAT(D.TGL_BL_AWB,'%Y%m%d') AS TGL_BL_AWB, 
                        SUBSTRING(IFNULL(master_bl.No_MasBL, ''),1,30) AS NO_MASTER_BL_AWB, 
                        DATE_FORMAT(master_bl.Tgl_MasBL,'%Y%m%d') AS TGL_MASTER_BL_AWB, 
                        IFNULL(consignee.No_NPWPC, '') AS ID_CONSIGNEE, 
                        IFNULL(consignee.Nama_Cons, '') AS CONSIGNEE, 
                        IFNULL(manifest.BERAT, 0) AS BRUTO, 
                        IFNULL(H.NO_BC11, '') AS NO_BC11, 
                        DATE_FORMAT(H.TGL_BC11,'%Y%m%d') AS TGL_BC11, 
                        IFNULL(D.NO_POS_BC11, '') AS NO_POS_BC11, 
                        REPLACE(REPLACE(IFNULL(kontainer.No_Cont, ''),'-',''),' ','') AS CONT_ASAL, 
                        '1' AS SERI_KEMAS, 
                        IFNULL(manifest.Kemasan, 'PK') AS KD_KEMAS, 
                        IFNULL(manifest.JML_Kemasan, 1) AS JML_KEMAS, 
                        IFNULL(H.KD_GUDANG, 'GPSU') AS KD_TIMBUN, 
                        IF(ISNULL(jenis_dokumen.Kode_Dok_BC),'1',jenis_dokumen.Kode_Dok_BC) AS KD_DOK_INOUT, 
                        IFNULL(invoice_gudang.No_SPPB, '') AS NO_DOK_INOUT, 
                        DATE_FORMAT(invoice_gudang.Tgl_SPPB,'%Y%m%d') AS TGL_DOK_INOUT,
                        DATE_FORMAT(sj.Tgl_Out,'%Y%m%d%H%i%s') AS WK_INOUT,
                        '1' AS KD_SAR_ANGKUT_INOUT, 
                        REPLACE(IFNULL(sj.Nopol, ''),' ','') AS NO_POL, 
                        IFNULL(manifest.Pel_Asal, '') AS PEL_MUAT, 
                        IFNULL(manifest.Pel_Transit, '') AS PEL_TRANSIT, 
                        IFNULL(manifest.Pel_Bongkar, '') AS PEL_BONGKAR, 
                        IFNULL(H.KD_GUDANG, 'GPSU') AS GUDANG_TUJUAN, 
                        IFNULL(H.KD_KANTOR, '070100') AS KODE_KANTOR, 
                        IF(ISNULL(invoice_gudang.no_daftar_pabean) OR invoice_gudang.no_daftar_pabean='', IFNULL(invoice_gudang.No_SPPB, ''), invoice_gudang.no_daftar_pabean) AS NO_DAFTAR_PABEAN,
                        IF(ISNULL(invoice_gudang.tgl_daftar_pabean), DATE_FORMAT(invoice_gudang.Tgl_SPPB,'%Y%m%d'), DATE_FORMAT(invoice_gudang.tgl_daftar_pabean,'%Y%m%d')) AS TGL_DAFTAR_PABEAN, 
                        IF(ISNULL(master_bl.No_SegelBC) OR master_bl.No_SegelBC='', IFNULL(invoice_gudang.No_SPPB, 'SGLBC1'), master_bl.No_SegelBC) AS NO_SEGEL_BC, 
                        IF(ISNULL(master_bl.Tgl_SegelBC) OR master_bl.Tgl_SegelBC='', DATE_FORMAT(invoice_gudang.Tgl_SPPB,'%Y%m%d'), DATE_FORMAT(master_bl.Tgl_SegelBC,'%Y%m%d')) AS TGL_SEGEL_BC, 
                        '' AS NO_IJIN_TPS, 
                        '' AS TGL_IJIN_TPS 
                    FROM master_bl  
                        INNER JOIN manifest ON manifest.Id_MasBL_FK = master_bl.Id_MasBL 
                        INNER JOIN kontainer ON master_bl.Id_Kontainer_FK = kontainer.Id_Kontainer
                        INNER JOIN sj ON sj.ID_OB_FK = manifest.Id_OB 
                        INNER JOIN invoice_gudang ON invoice_gudang.Id_OB_FK = manifest.Id_OB 
                        INNER JOIN jenis_dokumen ON jenis_dokumen.Kode_Dok = invoice_gudang.JNS_SPPB
                        LEFT JOIN tpsws_responplp_detail_backup D ON D.NO_BL_AWB = manifest.No_BL 
                        INNER JOIN tpsws_responplp_header_backup H ON H.NO_SURAT = D.NO_SURAT_FK AND H.NO_PLP = D.NO_PLP_FK
                        INNER JOIN consignee ON consignee.Id_Cons = manifest.Id_Cons_FK
                    WHERE sj.Tgl_Out BETWEEN :tglAwalOut AND :tglAkhirOut
                    ORDER BY master_bl.Id_MasBL";

            $stmt = $pdo_primamas->prepare($sql);
            $stmt->execute([
                ':tglAwalOut'  => $tglAwal . ' 00:00:00',
                ':tglAkhirOut' => $tglAkhir . ' 23:59:59'
            ]);
            $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $totalCount = count($rawRows);
        if ($totalCount === 0) {
            jsonResponse([
                'success' => true,
                'count'   => 0,
                'message' => 'Tidak ditemukan data kemasan untuk filter tanggal ' . toCeisaDmy($tglAwal) . ' s/d ' . toCeisaDmy($tglAkhir),
                'rows'    => [],
                'payload' => null
            ]);
        }

        // Ambil header dari baris pertama
        $first = $rawRows[0];
        $shortDate = date('ymd', strtotime($tglAkhir));
        $refNumber = 'PSU0' . $shortDate . ($type === 'In' ? '1' : '2') . date('His');

        $header = [
            'kodeDokumen'               => (string)($first['KD_DOK'] ?? ($type === 'In' ? '5' : '6')),
            'kodeTps'                   => (string)($first['KD_TPS'] ?? 'PSU0'),
            'namaAngkut'                => (string)($first['NM_ANGKUT'] ?? ''),
            'nomorVoyFlight'            => (string)($first['NO_VOY_FLIGHT'] ?? ''),
            'callSign'                  => (string)($first['CALL_SIGN'] ?? ''),
            'tanggalTiba'               => toCeisaDmy($first['TGL_TIBA'] ?? ''),
            'kodeGudang'                => (string)($first['KD_GUDANG'] ?? 'GPSU'),
            'refNumber'                 => $refNumber,
            'nomorBc11'                 => (string)($first['NO_BC11'] ?? ''),
            'tanggalBc11'               => toCeisaDmy($first['TGL_BC11'] ?? ''),
            'nomorPosBc11'              => (string)($first['NO_POS_BC11'] ?? ''),
            'tanggalBerangkat'          => '',
            'tanggalPerkiraanBerangkat' => '',
            'kodeSaranaAngkutInOut'     => (string)($first['KD_SAR_ANGKUT_INOUT'] ?? '1')
        ];

        $detil = [];
        $tableRows = [];
        $blOccurrences = [];
        $duplicateBLs = [];
        $batches = [];

        foreach ($rawRows as $idx => $r) {
            $bl = trim((string)($r['NO_BL_AWB'] ?? ''));
            if (empty($bl)) {
                continue;
            }

            // Hitung kemunculan B/L untuk partisi multi-batch otomatis (agar tidak ada data yang dibuang)
            $blOccurrences[$bl] = ($blOccurrences[$bl] ?? 0) + 1;
            $occurrence = $blOccurrences[$bl];
            $batchIndex = $occurrence - 1;

            if ($occurrence > 1) {
                $duplicateBLs[$bl] = $occurrence;
            }

            $tglBlAwb        = toCeisaDmy($r['TGL_BL_AWB'] ?? '');
            $tglMasterBlAwb  = toCeisaDmy($r['TGL_MASTER_BL_AWB'] ?? '');
            $tglBc11         = toCeisaDmy($r['TGL_BC11'] ?? '');
            $tglDokInOut     = toCeisaDmy($r['TGL_DOK_INOUT'] ?? '');
            $waktuInOut      = toCeisaDateTime($r['WK_INOUT'] ?? '');
            $tglDaftarPabean = toCeisaDmy($r['TGL_DAFTAR_PABEAN'] ?? '');
            $tglSegelBc      = toCeisaDmy($r['TGL_SEGEL_BC'] ?? '');
            $tglIjinTps      = toCeisaDmy($r['TGL_IJIN_TPS'] ?? '');
            $noDaftarPabean  = sanitizeNoDaftarPabean($r['NO_DAFTAR_PABEAN'] ?? '');
            if (empty($noDaftarPabean)) {
                $noDaftarPabean = sanitizeNoDaftarPabean($r['NO_DOK_INOUT'] ?? '000000');
            }
            if (empty($tglDaftarPabean)) {
                $tglDaftarPabean = !empty($tglDokInOut) ? $tglDokInOut : date('d-m-Y');
            }

            $rawIdCons = trim((string)($r['ID_CONSIGNEE'] ?? ''));
            // CEISA 4.0 mensyaratkan idConsignee tidak boleh kosong
            $idConsignee = !empty($rawIdCons) ? $rawIdCons : '000000000000000';

            $rawKdGudang = trim((string)($r['KD_GUDANG'] ?? 'GPSU'));
            $rawKdTimbun = trim((string)($r['KD_TIMBUN'] ?? ''));
            // CEISA 4.0 mensyaratkan kodeTimbun tidak boleh kosong
            $kodeTimbun  = !empty($rawKdTimbun) ? $rawKdTimbun : (!empty($rawKdGudang) ? $rawKdGudang : 'GPSU');

            $gudangTujuan = trim((string)($r['GUDANG_TUJUAN'] ?? ''));
            if (empty($gudangTujuan)) {
                $gudangTujuan = !empty($rawKdGudang) ? $rawKdGudang : 'GPSU';
            }

            $nomorDokInOut = trim((string)($r['NO_DOK_INOUT'] ?? ''));

            $nomorSegelBc = trim((string)($r['NO_SEGEL_BC'] ?? ''));
            if (empty($nomorSegelBc)) {
                $nomorSegelBc = !empty($nomorDokInOut) ? $nomorDokInOut : 'SGLBC1';
            }

            if (empty($tglSegelBc)) {
                $tglSegelBc = !empty($tglDokInOut) ? $tglDokInOut : date('d-m-Y');
            }

            // Bersihkan enter / newline / tab dari nama consignee
            $consigneeClean = trim(preg_replace('/[\r\n\t]+/', ' ', (string)($r['CONSIGNEE'] ?? '')));

            // JSON item CEISA 4.0
            $item = [
                'nomorBlAwb'            => $bl,
                'tanggalBlAwb'          => $tglBlAwb,
                'nomorMasterBlAwb'      => (string)($r['NO_MASTER_BL_AWB'] ?? ''),
                'tanggalMasterBlAwb'    => $tglMasterBlAwb,
                'idConsignee'           => $idConsignee,
                'consignee'             => $consigneeClean,
                'bruto'                 => (string)($r['BRUTO'] ?? '0'),
                'nomorBc11'             => (string)($r['NO_BC11'] ?? ''),
                'tanggalBc11'           => $tglBc11,
                'nomorPosBc11'          => (string)($r['NO_POS_BC11'] ?? ''),
                'kontainerAsal'         => (string)($r['CONT_ASAL'] ?? ''),
                'seriKemasan'           => (string)($occurrence),
                'kodeKemasan'           => (string)($r['KD_KEMAS'] ?? 'PK'),
                'jumlahKemasan'         => (string)($r['JML_KEMAS'] ?? '1'),
                'kodeTimbun'            => $kodeTimbun,
                'kodeDokumenInOut'      => (string)($r['KD_DOK_INOUT'] ?? '1'),
                'nomorDokumenInOut'     => $nomorDokInOut,
                'tanggalDokumenInOut'   => $tglDokInOut,
                'waktuInOut'            => $waktuInOut,
                'kodeSaranaAngkutInOut' => (string)($r['KD_SAR_ANGKUT_INOUT'] ?? '1'),
                'nomorPolisi'           => (string)($r['NO_POL'] ?? ''),
                'pelabuhanMuat'         => (string)($r['PEL_MUAT'] ?? ''),
                'pelabuhanTransit'      => (string)($r['PEL_TRANSIT'] ?? ''),
                'pelabuhanBongkar'      => (string)($r['PEL_BONGKAR'] ?? ''),
                'gudangTujuan'          => $gudangTujuan,
                'kodeKantor'            => (string)($r['KODE_KANTOR'] ?? '070100'),
                'nomorDaftarPabean'     => $noDaftarPabean,
                'tanggalDaftarPabean'   => $tglDaftarPabean,
                'nomorSegelBc'          => $nomorSegelBc,
                'tanggalSegelBc'        => $tglSegelBc,
                'nomorIjinTps'          => (string)($r['NO_IJIN_TPS'] ?? ''),
                'tanggalIjinTps'        => $tglIjinTps
            ];

            // Inisialisasi batch jika belum ada
            if (!isset($batches[$batchIndex])) {
                $batchRef = ($batchIndex === 0) ? $refNumber : ($refNumber . ($batchIndex + 1));
                $batchHeader = array_merge($header, ['refNumber' => $batchRef]);
                $batches[$batchIndex] = [
                    'batch_number'   => $batchIndex + 1,
                    'refNumber'      => $batchRef,
                    'kemasan_count'  => 0,
                    'payload'        => [
                        'header' => $batchHeader,
                        'detil'  => []
                    ]
                ];
            }

            $batches[$batchIndex]['payload']['detil'][] = $item;
            $batches[$batchIndex]['kemasan_count']++;

            // Baris tabel DataTables (seluruh data ditampilkan tanpa ada yang dibuang)
            $tableRows[] = [
                'no'               => count($tableRows) + 1,
                'idMasBl'          => $r['Id_MasBL'] ?? '',
                'nomorBlAwb'       => $item['nomorBlAwb'],
                'tanggalBlAwb'     => $item['tanggalBlAwb'],
                'nomorMasterBlAwb' => $item['nomorMasterBlAwb'],
                'jumlahKemasan'    => $item['jumlahKemasan'] . ' ' . $item['kodeKemasan'],
                'bruto'            => $item['bruto'],
                'nomorPosBc11'     => $item['nomorPosBc11'],
                'kontainerAsal'    => $item['kontainerAsal'],
                'nomorPolisi'      => $item['nomorPolisi'],
                'nomorDokInOut'    => $item['nomorDokumenInOut'] . ($item['tanggalDokumenInOut'] ? ' (' . $item['tanggalDokumenInOut'] . ')' : ''),
                'consignee'        => $item['consignee'],
                'waktuInOut'       => $item['waktuInOut'],
                'batch'            => $occurrence,
                'batchLabel'       => 'Batch ' . $occurrence,
                'is_duplicate'     => ($occurrence > 1)
            ];
        }

        $batchList = array_values($batches);
        $totalBatches = count($batchList);
        $hasDuplicates = !empty($duplicateBLs);

        jsonResponse([
            'success'          => true,
            'count'            => count($tableRows),
            'message'          => 'Berhasil memuat ' . count($tableRows) . ' data kemasan' . ($hasDuplicates ? " (dibagi $totalBatches batch karena ada B/L ganda)" : ''),
            'rows'             => $tableRows,
            'payload'          => $batchList[0]['payload'] ?? null,
            'has_duplicates'   => $hasDuplicates,
            'duplicate_bls'    => array_keys($duplicateBLs),
            'duplicate_count'  => count($duplicateBLs),
            'total_batches'    => $totalBatches,
            'batches'          => $batchList
        ]);


    } catch (Exception $e) {
        error_log("Error cocokms fetch: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Gagal mengambil data kemasan: ' . $e->getMessage()
        ], 500);
    }
}

// ==========================================
// ACTION 2: KIRIM DATA KE GATEWAY CEISA 4.0
// ==========================================
if ($action === 'send') {
    $rawInput = file_get_contents('php://input');
    $postData = json_decode($rawInput, true);

    $payload = $postData['payload'] ?? null;

    if (empty($payload) || !is_array($payload)) {
        jsonResponse(['success' => false, 'message' => 'Payload JSON kemasan kosong atau tidak valid'], 400);
    }

    if (empty($payload['header']) || empty($payload['detil']) || !is_array($payload['detil'])) {
        jsonResponse(['success' => false, 'message' => 'Format payload tidak memenuhi struktur CEISA 4.0 (header & detil required)'], 422);
    }

    // Normalisasi pengaman: pastikan idConsignee, kodeTimbun, gudangTujuan, segel, dan nomorDaftarPabean valid
    $kodeGudangDefault = !empty($payload['header']['kodeGudang']) ? $payload['header']['kodeGudang'] : 'GPSU';
    $seenSendBL = [];
    $filteredDetil = [];

    foreach ($payload['detil'] as &$d) {
        $blKey = trim((string)($d['nomorBlAwb'] ?? ''));
        if (empty($blKey) || isset($seenSendBL[$blKey])) {
            continue; // Deduplikasi BL agar tidak terjadi error duplikat BL di CEISA 4.0
        }
        $seenSendBL[$blKey] = true;

        if (empty($d['idConsignee'])) {
            $d['idConsignee'] = '000000000000000';
        }
        if (empty($d['kodeTimbun'])) {
            $d['kodeTimbun'] = $kodeGudangDefault;
        }
        if (isset($d['consignee'])) {
            $d['consignee'] = trim(preg_replace('/[\r\n\t]+/', ' ', (string)$d['consignee']));
        }
        if (empty($d['gudangTujuan'])) {
            $d['gudangTujuan'] = $kodeGudangDefault;
        }
        if (empty($d['nomorSegelBc'])) {
            $d['nomorSegelBc'] = !empty($d['nomorDokumenInOut']) ? $d['nomorDokumenInOut'] : 'SGLBC1';
        }
        if (empty($d['tanggalSegelBc'])) {
            $d['tanggalSegelBc'] = !empty($d['tanggalDokumenInOut']) ? $d['tanggalDokumenInOut'] : date('d-m-Y');
        }
        $rawNoDaftar = !empty($d['nomorDaftarPabean']) ? $d['nomorDaftarPabean'] : (!empty($d['nomorDokumenInOut']) ? $d['nomorDokumenInOut'] : '000000');
        $d['nomorDaftarPabean'] = sanitizeNoDaftarPabean($rawNoDaftar);

        if (empty($d['tanggalDaftarPabean'])) {
            $d['tanggalDaftarPabean'] = !empty($d['tanggalDokumenInOut']) ? $d['tanggalDokumenInOut'] : date('d-m-Y');
        }

        $filteredDetil[] = $d;
    }
    unset($d);
    $payload['detil'] = $filteredDetil;

    try {
        $client = new CeisaClient();
        // Route resmi CEISA 4.0 Coarri Codeco Kemasan: coarri-codeco-kemasan
        $res = $client->post('coarri-codeco-kemasan', $payload);

        $isOk = ($res['code'] >= 200 && $res['code'] < 300);

        // Catat log & riwayat jika database tpsonline tersedia
        try {
            global $pdo_tpsonline;
            if ($pdo_tpsonline) {
                $detilList = $payload['detil'] ?? [];
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
                    'detil_count' => count($detilList)
                ], JSON_UNESCAPED_UNICODE);

                $stmtLog->execute([
                    'coarri-codeco-kemasan',
                    $requestSummary,
                    (int)($res['code'] ?? ($isOk ? 200 : 400)),
                    $isOk ? 'SUCCESS' : 'FAILED',
                    $res['message'] ?? ($isOk ? 'Berhasil' : 'Gagal'),
                    count($detilList),
                    json_encode($res['raw'] ?? $res, JSON_UNESCAPED_UNICODE)
                ]);

                // 2. Simpan setiap kemasan ke tabel khusus ceisa_cocokms HANYA jika pengiriman berhasil ke CEISA
                if ($isOk) {
                    $stmtCocokms = $pdo_tpsonline->prepare("
                        INSERT INTO ceisa_cocokms 
                        (ref_number, kode_dokumen, kd_tps, kd_gudang, jenis_kemasan, jumlah_kemasan, seri_kemasan, no_bl_awb, tgl_bl_awb, no_pos_bc11, consignee, kontainer_asal, no_dok_inout, tgl_dok_inout, wk_inout, no_polisi, pel_muat, pel_transit, pel_bongkar, no_segel_bc, tgl_segel_bc, bruto, raw_data, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");

                    $kdTps = $header['kodeTps'] ?? 'PSU0';
                    $kdGudang = $header['kodeGudang'] ?? 'GPSU';

                    foreach ($detilList as $item) {
                        $kodeDokumen = (string)($item['kodeDokumen'] ?? ($header['kodeDokumen'] ?? ''));
                        $jnsKemasan = $item['kodeKemasan'] ?? ($item['jenisKemasan'] ?? 'PK');
                        $jmlKemasan = floatval($item['jumlahKemasan'] ?? 1);
                        $seriKemasan = $item['seriKemasan'] ?? '';
                        $nomorBlAwb = $item['nomorBlAwb'] ?? ($item['noBlAwb'] ?? '');
                        $tanggalBlAwb = $item['tanggalBlAwb'] ?? '';
                        $nomorPosBc11 = $item['nomorPosBc11'] ?? '';
                        $consignee = substr(trim(preg_replace('/[\r\n\t]+/', ' ', (string)($item['consignee'] ?? ''))), 0, 150);
                        $kontainerAsal = $item['kontainerAsal'] ?? ($item['nomorKontainer'] ?? '');
                        $noDokInOut = $item['nomorDokumenInOut'] ?? ($item['nomorDaftarPabean'] ?? '');
                        $tglDokInOut = $item['tanggalDokumenInOut'] ?? ($item['tanggalDaftarPabean'] ?? '');
                        $wkInOut = $item['waktuInOut'] ?? '';
                        $noPolisi = $item['nomorPolisi'] ?? '';
                        $pelMuat = $item['pelabuhanMuat'] ?? '';
                        $pelTransit = $item['pelabuhanTransit'] ?? '';
                        $pelBongkar = $item['pelabuhanBongkar'] ?? '';
                        $noSegelBc = $item['nomorSegelBc'] ?? '';
                        $tglSegelBc = $item['tanggalSegelBc'] ?? '';
                        $bruto = floatval($item['bruto'] ?? 0);
                        $rawData = json_encode($item, JSON_UNESCAPED_UNICODE);

                        $stmtCocokms->execute([
                            $refNumber,
                            $kodeDokumen,
                            $kdTps,
                            $kdGudang,
                            $jnsKemasan,
                            $jmlKemasan,
                            $seriKemasan,
                            $nomorBlAwb,
                            $tanggalBlAwb,
                            $nomorPosBc11,
                            $consignee,
                            $kontainerAsal,
                            $noDokInOut,
                            $tglDokInOut,
                            $wkInOut,
                            $noPolisi,
                            $pelMuat,
                            $pelTransit,
                            $pelBongkar,
                            $noSegelBc,
                            $tglSegelBc,
                            $bruto,
                            $rawData
                        ]);
                    }
                }
            }
        } catch (Exception $dbEx) {
            error_log("Gagal mencatat log / simpan ceisa_plp_kemasan / ceisa_sppb_kemasan: " . $dbEx->getMessage());
        }

        jsonResponse([
            'success' => $isOk,
            'code'    => $res['code'] ?? ($isOk ? 200 : 400),
            'message' => $res['message'] ?? ($isOk ? 'Data Coarri Codeco Kemasan berhasil dikirim ke CEISA 4.0' : 'Gagal mengirim data'),
            'data'    => $res['data'] ?? null,
            'raw'     => $res['raw'] ?? $res
        ], $isOk ? 200 : ($res['code'] ?: 400));

    } catch (Exception $e) {
        error_log("Error cocokms send: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Kesalahan sistem saat mengirim ke CEISA 4.0: ' . $e->getMessage()
        ], 500);
    }
}

jsonResponse(['success' => false, 'message' => 'Aksi tidak dikenali'], 400);
