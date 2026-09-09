<?php
/**
 * Backend API Coarri Codeco (CoCoKms & CoCoCont) CEISA 4.0
 * Melayani 3 Sub-Tab (Kemasan, Container LCL, Container PJT) untuk In & Out:
 * 1. action=fetch -> Menarik data dari DB primamas dan menghasilkan JSON standar CEISA 4.0
 *    - Kemasan: POST /coarri-codeco-kemasan (header + detil kemasan)
 *    - Container LCL & Container PJT: POST /coarri-codeco-container (header + kontainer)
 * 2. action=send  -> Mengirim payload JSON ke REST API CEISA 4.0 Gateway
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
    if (empty($val) || $val === '000000' || $val === '00000000000000' || $val === '0000-00-00 00:00:00') {
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
 * Batasi nomor daftar pabean maksimal 6 karakter alphanumeric sesuai skema CEISA 4.0
 * Jika melebihi 6 karakter atau berisi simbol seperti underscore, CEISA 4.0 melempar 500 Internal Server Error
 */
function sanitizeNoDaftarPabean($no, $maxLen = 6) {
    $no = trim((string)$no);
    if (empty($no)) return '';
    if (strpos($no, '/') !== false) {
        $parts = explode('/', $no);
        $no = trim($parts[0]);
    }
    $clean = preg_replace('/[^a-zA-Z0-9]/', '', $no);
    if (empty($clean)) return '';
    if (strlen($clean) > $maxLen) {
        return substr($clean, -$maxLen);
    }
    return $clean;
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

    $type     = input('type', 'In'); // 'In' atau 'Out'
    $subType  = input('subType', 'kemasan'); // 'kemasan', 'container_lcl', 'container_pjt'
    $tglAwal  = normalizeInputDate(input('tglAwal'));
    $tglAkhir = normalizeInputDate(input('tglAkhir'));

    if ($type !== 'In' && $type !== 'Out') {
        jsonResponse(['success' => false, 'message' => 'Tipe pergerakan harus In atau Out'], 400);
    }

    if (!in_array($subType, ['kemasan', 'container_lcl', 'container_pjt'])) {
        jsonResponse(['success' => false, 'message' => 'Sub-tipe tidak valid. Harus kemasan, container_lcl, atau container_pjt'], 400);
    }

    try {
        $rawRows = [];

        // -------------------------------------------------------------
        // 1. SUBTYPE: KEMASAN
        // -------------------------------------------------------------
        if ($subType === 'kemasan') {
            if ($type === 'In') {
                // Inbound Kemasan (In_kms)
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
                // Outbound Kemasan (Out_kms)
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

        // -------------------------------------------------------------
        // 2. SUBTYPE: CONTAINER LCL
        // -------------------------------------------------------------
        } elseif ($subType === 'container_lcl') {
            if ($type === 'In') {
                // Inbound Container LCL (In_container_lcl)
                $sql = "SELECT 
                            master_bl.Id_MasBL, 
                            '5' AS KD_DOK, 
                            'PSU0' AS KD_TPS, 
                            IFNULL(H.VOYAGE, '') AS NM_ANGKUT, 
                            IFNULL(H.NO_VOYAGE, '') AS NO_VOY_FLIGHT, 
                            IFNULL(H.CALL_SIGN, '') AS CALL_SIGN, 
                            IFNULL(DATE_FORMAT(H.TGL_TIBA,'%Y%m%d'), '') AS TGL_TIBA, 
                            IFNULL(H.KD_GUDANG, 'GPSU') AS KD_GUDANG, 
                            REPLACE(REPLACE(IFNULL(kontainer.No_Cont, ''), '-', ''), ' ', '') AS NO_CONT,
                            IFNULL(kontainer.Size, '20') AS UK_CONT, 
                            IFNULL(master_bl.no_segel_pelayaran, '') AS NO_SEGEL, 
                            SUBSTRING(IFNULL(kontainer.Type, 'L'), 1, 1) AS JNS_CONT, 
                            D.NO_BL_AWB AS NO_BL_AWB, 
                            DATE_FORMAT(D.TGL_BL_AWB,'%Y%m%d') AS TGL_BL_AWB, 
                            SUBSTRING(IFNULL(master_bl.No_MasBL, ''), 1, 30) AS NO_MASTER_BL_AWB, 
                            DATE_FORMAT(master_bl.Tgl_MasBL,'%Y%m%d') AS TGL_MASTER_BL_AWB, 
                            IFNULL(consignee.No_NPWPC, '') AS ID_CONSIGNEE, 
                            IFNULL(consignee.Nama_Cons, '') AS CONSIGNEE, 
                            IFNULL(manifest.BERAT, 0) AS BRUTO, 
                            IFNULL(H.NO_BC11, '') AS NO_BC11, 
                            DATE_FORMAT(H.TGL_BC11,'%Y%m%d') AS TGL_BC11, 
                            IFNULL(D.NO_POS_BC11, '') AS NO_POS_BC11, 
                            '' AS KD_TIMBUN, 
                            '3' AS KD_DOK_INOUT, 
                            IFNULL(H.NO_PLP, '') AS NO_DOK_INOUT, 
                            DATE_FORMAT(H.TGL_PLP,'%Y%m%d') AS TGL_DOK_INOUT, 	
                            CONCAT(IF(ISNULL(master_bl.tgl_datang_cont),'000000',DATE_FORMAT(master_bl.tgl_datang_cont,'%Y%m%d')),IF(ISNULL(master_bl.jam_datang_cont),'000000',DATE_FORMAT(master_bl.jam_datang_cont,'%H%i%s'))) AS WK_INOUT, 								
                            '1' AS KD_SAR_ANGKUT_INOUT, 
                            IFNULL(master_bl.nopol_in, '') AS NO_POL, 
                            '2' AS FL_CONT_KOSONG,
                            '' AS ISO_CODE,								
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
                        WHERE master_bl.tgl_datang_cont BETWEEN :tglAwal AND :tglAkhir
                        GROUP BY kontainer.No_Cont
                        ORDER BY master_bl.Id_MasBL";

                $stmt = $pdo_primamas->prepare($sql);
                $stmt->execute([
                    ':tglAwal'  => $tglAwal,
                    ':tglAkhir' => $tglAkhir
                ]);
                $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } else {
                // Outbound Container LCL (Out_container_lcl)
                $sql = "SELECT 
                            master_bl.Id_MasBL, 
                            '6' AS KD_DOK, 
                            'PSU0' AS KD_TPS, 
                            IFNULL(H.VOYAGE, '') AS NM_ANGKUT, 
                            IFNULL(H.NO_VOYAGE, '') AS NO_VOY_FLIGHT, 
                            IFNULL(H.CALL_SIGN, '') AS CALL_SIGN, 
                            IFNULL(DATE_FORMAT(H.TGL_TIBA,'%Y%m%d'), '') AS TGL_TIBA, 
                            IFNULL(H.KD_GUDANG, 'GPSU') AS KD_GUDANG, 
                            REPLACE(REPLACE(IFNULL(kontainer.No_Cont, ''), '-', ''), ' ', '') AS NO_CONT,
                            IFNULL(kontainer.Size, '20') AS UK_CONT, 
                            IFNULL(master_bl.no_segel_pelayaran, '') AS NO_SEGEL, 
                            SUBSTRING(IFNULL(kontainer.Type, 'L'), 1, 1) AS JNS_CONT, 
                            D.NO_BL_AWB AS NO_BL_AWB, 
                            DATE_FORMAT(D.TGL_BL_AWB,'%Y%m%d') AS TGL_BL_AWB, 
                            SUBSTRING(IFNULL(master_bl.No_MasBL, ''), 1, 30) AS NO_MASTER_BL_AWB, 
                            DATE_FORMAT(master_bl.Tgl_MasBL,'%Y%m%d') AS TGL_MASTER_BL_AWB, 
                            IFNULL(consignee.No_NPWPC, '') AS ID_CONSIGNEE, 
                            IFNULL(consignee.Nama_Cons, '') AS CONSIGNEE, 
                            IFNULL(manifest.BERAT, 0) AS BRUTO, 
                            IFNULL(H.NO_BC11, '') AS NO_BC11, 
                            DATE_FORMAT(H.TGL_BC11,'%Y%m%d') AS TGL_BC11, 
                            IFNULL(D.NO_POS_BC11, '') AS NO_POS_BC11, 
                            '' AS KD_TIMBUN, 
                            '40' AS KD_DOK_INOUT, 
                            CONCAT('SJ_', IFNULL(CONCAT(DATE_FORMAT(master_bl.tgl_keluar_cont,'%Y%m%d'), DATE_FORMAT(master_bl.jam_keluar_cont,'%H%i%s')), '')) AS NO_DOK_INOUT, 
                            IFNULL(DATE_FORMAT(master_bl.tgl_keluar_cont,'%Y%m%d'), '00000000') AS TGL_DOK_INOUT,
                            CONCAT(IF(ISNULL(master_bl.tgl_keluar_cont),'000000',DATE_FORMAT(master_bl.tgl_keluar_cont,'%Y%m%d')), IF(ISNULL(master_bl.jam_keluar_cont),'000000',DATE_FORMAT(master_bl.jam_keluar_cont,'%H%i%s'))) AS WK_INOUT, 								
                            '1' AS KD_SAR_ANGKUT_INOUT, 
                            IFNULL(master_bl.nopol_out, '') AS NO_POL, 
                            '1' AS FL_CONT_KOSONG,
                            '' AS ISO_CODE,								
                            IFNULL(manifest.Pel_Asal, '') AS PEL_MUAT, 
                            IFNULL(manifest.Pel_Transit, '') AS PEL_TRANSIT, 
                            IFNULL(manifest.Pel_Bongkar, '') AS PEL_BONGKAR, 
                            IFNULL(H.KD_GUDANG, 'GPSU') AS GUDANG_TUJUAN, 
                            IFNULL(H.KD_KANTOR, '070100') AS KODE_KANTOR, 
                            CONCAT('SJ_', IFNULL(CONCAT(DATE_FORMAT(master_bl.tgl_keluar_cont,'%Y%m%d'), DATE_FORMAT(master_bl.jam_keluar_cont,'%H%i%s')), '')) AS NO_DAFTAR_PABEAN,
                            IFNULL(DATE_FORMAT(master_bl.tgl_keluar_cont,'%Y%m%d'), '00000000') AS TGL_DAFTAR_PABEAN, 
                            IF(ISNULL(master_bl.No_SegelBC) OR master_bl.No_SegelBC='', IFNULL(H.NO_PLP, 'SGLBC1'), master_bl.No_SegelBC) AS NO_SEGEL_BC, 
                            IF(ISNULL(master_bl.Tgl_SegelBC) OR master_bl.Tgl_SegelBC='', DATE_FORMAT(master_bl.tgl_keluar_cont,'%Y%m%d'), DATE_FORMAT(master_bl.Tgl_SegelBC,'%Y%m%d')) AS TGL_SEGEL_BC, 
                            IFNULL(H.NO_SURAT, '') AS NO_IJIN_TPS, 
                            DATE_FORMAT(H.TGL_SURAT,'%Y%m%d') AS TGL_IJIN_TPS 
                        FROM master_bl  
                            INNER JOIN manifest ON manifest.Id_MasBL_FK = master_bl.Id_MasBL 
                            INNER JOIN kontainer ON master_bl.Id_Kontainer_FK = kontainer.Id_Kontainer
                            LEFT JOIN tpsws_responplp_detail_backup D ON D.NO_BL_AWB = manifest.No_BL 
                            INNER JOIN tpsws_responplp_header_backup H ON H.NO_SURAT = D.NO_SURAT_FK 
                            INNER JOIN consignee ON consignee.Id_Cons = manifest.Id_Cons_FK
                        WHERE master_bl.tgl_keluar_cont BETWEEN :tglAwal AND :tglAkhir
                        GROUP BY kontainer.No_Cont
                        ORDER BY master_bl.Id_MasBL";

                $stmt = $pdo_primamas->prepare($sql);
                $stmt->execute([
                    ':tglAwal'  => $tglAwal,
                    ':tglAkhir' => $tglAkhir
                ]);
                $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

        // -------------------------------------------------------------
        // 3. SUBTYPE: CONTAINER PJT
        // -------------------------------------------------------------
        } elseif ($subType === 'container_pjt') {
            if ($type === 'In') {
                // Inbound Container PJT (In_container_pjt)
                $sql = "SELECT 
                            m.idManifestPJT AS Id_MasBL, 
                            '5' AS KD_DOK, 
                            'PSU0' AS KD_TPS, 
                            IFNULL(H.VOYAGE, '') AS NM_ANGKUT, 
                            IFNULL(H.NO_VOYAGE, '') AS NO_VOY_FLIGHT, 
                            IFNULL(H.CALL_SIGN, '') AS CALL_SIGN, 
                            IFNULL(DATE_FORMAT(H.TGL_TIBA,'%Y%m%d'), '') AS TGL_TIBA, 
                            IFNULL(H.KD_GUDANG, 'GPSU') AS KD_GUDANG, 
                            REPLACE(REPLACE(m.noCont,'-',''),' ','') AS NO_CONT,
                            IFNULL(D.UK_CONT, '20') AS UK_CONT, 
                            '' AS NO_SEGEL, 
                            IFNULL(D.JNS_CONT, 'L') AS JNS_CONT, 
                            IFNULL(D.NO_BL_AWB, IFNULL(m.noUT, '')) AS NO_BL_AWB, 
                            IFNULL(DATE_FORMAT(D.TGL_BL_AWB,'%Y%m%d'), '') AS TGL_BL_AWB, 
                            SUBSTRING(IFNULL(D.NO_BL_AWB, IFNULL(m.noUT, '')),1,30) AS NO_MASTER_BL_AWB, 
                            IFNULL(DATE_FORMAT(D.TGL_BL_AWB,'%Y%m%d'), '') AS TGL_MASTER_BL_AWB, 
                            IFNULL(consignee.npwp, '') AS ID_CONSIGNEE, 
                            IFNULL(consignee.Nama_Cons, '') AS CONSIGNEE, 
                            0 AS BRUTO, 
                            IFNULL(H.NO_BC11, '') AS NO_BC11, 
                            IFNULL(DATE_FORMAT(H.TGL_BC11,'%Y%m%d'), '') AS TGL_BC11, 
                            IFNULL(D.NO_POS_BC11, '') AS NO_POS_BC11, 
                            '' AS KD_TIMBUN, 
                            '3' AS KD_DOK_INOUT, 
                            IFNULL(H.NO_PLP, '') AS NO_DOK_INOUT, 
                            IFNULL(DATE_FORMAT(H.TGL_PLP,'%Y%m%d'), '') AS TGL_DOK_INOUT, 	
                            CONCAT(IF(ISNULL(m.tglMasukPrimamas),'000000',DATE_FORMAT(m.tglMasukPrimamas,'%Y%m%d')),IF(ISNULL(m.jam_in_cont),'000000',DATE_FORMAT(m.jam_in_cont,'%H%i%s'))) AS WK_INOUT, 								
                            '1' AS KD_SAR_ANGKUT_INOUT, 
                            IF(ISNULL(m.nopol_in),'',REPLACE(m.nopol_in,' ','')) AS NO_POL, 
                            '2' AS FL_CONT_KOSONG,
                            '' AS ISO_CODE,								
                            '' AS PEL_MUAT, 
                            '' AS PEL_TRANSIT, 
                            '' AS PEL_BONGKAR, 
                            IFNULL(H.KD_GUDANG, 'GPSU') AS GUDANG_TUJUAN, 
                            IFNULL(H.KD_KANTOR, '070100') AS KODE_KANTOR, 
                            IFNULL(H.NO_PLP, '') AS NO_DAFTAR_PABEAN,
                            IFNULL(DATE_FORMAT(H.TGL_PLP,'%Y%m%d'), '') AS TGL_DAFTAR_PABEAN, 
                            IFNULL(H.NO_PLP, 'SGLBC1') AS NO_SEGEL_BC, 
                            IFNULL(DATE_FORMAT(H.TGL_PLP,'%Y%m%d'), '') AS TGL_SEGEL_BC, 
                            IFNULL(H.NO_SURAT, '') AS NO_IJIN_TPS, 
                            IFNULL(DATE_FORMAT(H.TGL_SURAT,'%Y%m%d'), '') AS TGL_IJIN_TPS 
                        FROM gudang_manifestpjt m 
                            INNER JOIN gudang_consigneepjt consignee ON consignee.idConsPJT = m.idConsPJT_FK
                            LEFT JOIN tpsws_responplp_detail_backup D ON (D.NO_CONT = REPLACE(REPLACE(m.noCont,'-',''),' ','') AND (m.noUT = D.NO_BL_AWB OR m.blsender = D.NO_BL_AWB))
                            INNER JOIN tpsws_responplp_header_backup H ON (H.NO_SURAT = D.NO_SURAT_FK and H.NO_plp = D.NO_plp_FK)
                        WHERE m.tglMasukPrimamas BETWEEN :tglAwal AND :tglAkhir
                        GROUP BY m.noCont
                        ORDER BY m.idManifestPJT";

                $stmt = $pdo_primamas->prepare($sql);
                $stmt->execute([
                    ':tglAwal'  => $tglAwal,
                    ':tglAkhir' => $tglAkhir
                ]);
                $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } else {
                // Outbound Container PJT (Out_container_pjt)
                $sql = "SELECT 
                            m.idManifestPJT AS Id_MasBL, 
                            '6' AS KD_DOK, 
                            'PSU0' AS KD_TPS, 
                            IFNULL(H.VOYAGE, '') AS NM_ANGKUT, 
                            IFNULL(H.NO_VOYAGE, '') AS NO_VOY_FLIGHT, 
                            IFNULL(H.CALL_SIGN, '') AS CALL_SIGN, 
                            IFNULL(DATE_FORMAT(H.TGL_TIBA,'%Y%m%d'), '') AS TGL_TIBA, 
                            IFNULL(H.KD_GUDANG, 'GPSU') AS KD_GUDANG, 
                            REPLACE(REPLACE(m.noCont,'-',''),' ','') AS NO_CONT,
                            IFNULL(D.UK_CONT, '20') AS UK_CONT, 
                            '' AS NO_SEGEL, 
                            IFNULL(D.JNS_CONT, 'L') AS JNS_CONT, 
                            IFNULL(D.NO_BL_AWB, IFNULL(m.noUT, '')) AS NO_BL_AWB, 
                            IFNULL(DATE_FORMAT(D.TGL_BL_AWB,'%Y%m%d'), '') AS TGL_BL_AWB, 
                            '' AS NO_MASTER_BL_AWB, 
                            '' AS TGL_MASTER_BL_AWB, 
                            IFNULL(consignee.npwp, '') AS ID_CONSIGNEE, 
                            IFNULL(consignee.Nama_Cons, '') AS CONSIGNEE, 
                            0 AS BRUTO, 
                            IFNULL(H.NO_BC11, '') AS NO_BC11, 
                            IFNULL(DATE_FORMAT(H.TGL_BC11,'%Y%m%d'), '') AS TGL_BC11, 
                            IFNULL(D.NO_POS_BC11, '') AS NO_POS_BC11, 
                            '' AS KD_TIMBUN, 
                            IF(ISNULL(jenis_dokumen.Kode_Dok_BC),'1',jenis_dokumen.Kode_Dok_BC) AS KD_DOK_INOUT, 
                            SUBSTRING(IFNULL(i.NO_SPPB, ''), 1, 29) AS NO_DOK_INOUT, 
                            IFNULL(DATE_FORMAT(i.TGL_SPPB,'%Y%m%d'), '') AS TGL_DOK_INOUT, 		
                            DATE_FORMAT(sj.jamKeluar,'%Y%m%d%H%i%s') AS WK_INOUT, 								
                            '1' AS KD_SAR_ANGKUT_INOUT, 
                            IFNULL(sj.nopol_out, '') AS NO_POL, 
                            '1' AS FL_CONT_KOSONG,
                            '' AS ISO_CODE,								
                            '' AS PEL_MUAT, 
                            '' AS PEL_TRANSIT, 
                            '' AS PEL_BONGKAR, 
                            IFNULL(H.KD_GUDANG, 'GPSU') AS GUDANG_TUJUAN, 
                            IFNULL(H.KD_KANTOR, '070100') AS KODE_KANTOR, 
                            IF(ISNULL(i.no_daftar_pabean) OR i.no_daftar_pabean='', IFNULL(i.NO_SPPB, ''), i.no_daftar_pabean) AS NO_DAFTAR_PABEAN,
                            IF(ISNULL(i.tgl_daftar_pabean), DATE_FORMAT(i.TGL_SPPB,'%Y%m%d'), DATE_FORMAT(i.tgl_daftar_pabean,'%Y%m%d')) AS TGL_DAFTAR_PABEAN, 
                            '' AS NO_SEGEL_BC, 
                            '' AS TGL_SEGEL_BC, 
                            IFNULL(H.NO_SURAT, '') AS NO_IJIN_TPS, 
                            IFNULL(DATE_FORMAT(H.TGL_SURAT,'%Y%m%d'), '') AS TGL_IJIN_TPS 
                        FROM gudang_manifestpjt m 
                            INNER JOIN gudang_consigneepjt consignee ON consignee.idConsPJT = m.idConsPJT_FK
                            INNER JOIN gudang_invoicepjt i ON i.idManifestPJT_FK = m.idManifestPJT 
                            INNER JOIN sj_pjt sj ON sj.id_manifest_pjt_fk = m.idManifestPJT 
                            INNER JOIN jenis_dokumen ON jenis_dokumen.Kode_Dok = i.JNS_SPPB 
                            LEFT JOIN tpsws_responplp_detail_backup D ON D.NO_CONT = REPLACE(REPLACE(m.noCont,'-',''),' ','')
                            INNER JOIN tpsws_responplp_header_backup H ON H.NO_PLP = D.NO_PLP_FK AND H.NO_SURAT = D.NO_SURAT_FK
                        WHERE sj.jamKeluar BETWEEN :tglAwalOut AND :tglAkhirOut AND (i.status_tambah_storage=0 OR i.status_tambah_storage=1)
                        GROUP BY D.NO_CONT
                        ORDER BY m.idManifestPJT";

                $stmt = $pdo_primamas->prepare($sql);
                $stmt->execute([
                    ':tglAwalOut'  => $tglAwal . ' 00:00:00',
                    ':tglAkhirOut' => $tglAkhir . ' 23:59:59'
                ]);
                $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        $totalCount = count($rawRows);
        $subTypeLabel = ($subType === 'kemasan') ? 'kemasan' : (($subType === 'container_lcl') ? 'kontainer LCL' : 'kontainer PJT');
        $targetEndpoint = ($subType === 'kemasan') ? 'coarri-codeco-kemasan' : 'coarri-codeco-container';

        if ($totalCount === 0) {
            jsonResponse([
                'success'        => true,
                'count'          => 0,
                'subType'        => $subType,
                'targetEndpoint' => $targetEndpoint,
                'message'        => "Tidak ditemukan data $subTypeLabel untuk filter tanggal " . toCeisaDmy($tglAwal) . " s/d " . toCeisaDmy($tglAkhir),
                'rows'           => [],
                'payload'        => null,
                'batches'        => []
            ]);
        }

        // Siapkan Header
        $first = $rawRows[0];
        $shortDate = date('ymd', strtotime($tglAkhir));
        $prefixRef = ($type === 'In') ? '1' : '2';
        if ($subType === 'container_lcl') $prefixRef = ($type === 'In') ? '3' : '4';
        if ($subType === 'container_pjt') $prefixRef = ($type === 'In') ? '5' : '6';
        $refNumber = 'PSU0' . $shortDate . $prefixRef . date('His');

        // Cari nomor BC 1.1 yang tidak kosong dari deretan data jika baris pertama kosong
        $bc11Value = (string)($first['NO_BC11'] ?? '');
        $tglBc11Value = toCeisaDmy($first['TGL_BC11'] ?? '');
        if (empty($bc11Value)) {
            foreach ($rawRows as $rCandidate) {
                if (!empty($rCandidate['NO_BC11'])) {
                    $bc11Value = (string)$rCandidate['NO_BC11'];
                    if (!empty($rCandidate['TGL_BC11'])) {
                        $tglBc11Value = toCeisaDmy($rCandidate['TGL_BC11']);
                    }
                    break;
                }
            }
        }

        if ($subType === 'kemasan') {
            $header = [
                'kodeDokumen'               => (string)($first['KD_DOK'] ?? ($type === 'In' ? '5' : '6')),
                'kodeTps'                   => (string)($first['KD_TPS'] ?? 'PSU0'),
                'namaAngkut'                => (string)($first['NM_ANGKUT'] ?? ''),
                'nomorVoyFlight'            => (string)($first['NO_VOY_FLIGHT'] ?? ''),
                'callSign'                  => (string)($first['CALL_SIGN'] ?? ''),
                'tanggalTiba'               => toCeisaDmy($first['TGL_TIBA'] ?? ''),
                'kodeGudang'                => (string)($first['KD_GUDANG'] ?? 'GPSU'),
                'refNumber'                 => $refNumber,
                'nomorBc11'                 => $bc11Value,
                'noBc11'                    => $bc11Value,
                'tanggalBc11'               => $tglBc11Value,
                'nomorPosBc11'              => (string)($first['NO_POS_BC11'] ?? ''),
                'tanggalBerangkat'          => '',
                'tanggalPerkiraanBerangkat' => '',
                'kodeSaranaAngkutInOut'     => (string)($first['KD_SAR_ANGKUT_INOUT'] ?? '1')
            ];
        } else {
            // Header resmi untuk endpoint coarri-codeco-container
            $header = [
                'kodeDokumen'          => (string)($first['KD_DOK'] ?? ($type === 'In' ? '5' : '6')),
                'noBc11'               => $bc11Value,
                'nomorBc11'            => $bc11Value,
                'tanggalBc11'          => $tglBc11Value,
                'nomorVoyFlight'       => (string)($first['NO_VOY_FLIGHT'] ?? ''),
                'tanggalBerangkat'     => '',
                'namaAngkut'           => (string)($first['NM_ANGKUT'] ?? ''),
                'refNumber'            => $refNumber,
                'kodeSaranaPengangkut' => (string)($first['KD_SAR_ANGKUT_INOUT'] ?? '1'),
                'kodeSaranaAngkutInOut'=> (string)($first['KD_SAR_ANGKUT_INOUT'] ?? '1'),
                'kodeTps'              => (string)($first['KD_TPS'] ?? 'PSU0'),
                'tanggalTiba'          => toCeisaDmy($first['TGL_TIBA'] ?? ''),
                'kodeGudang'           => (string)($first['KD_GUDANG'] ?? 'GPSU'),
                'callSign'             => (string)($first['CALL_SIGN'] ?? '')
            ];
        }

        $tableRows = [];
        $batches = [];
        $occurrenceTracker = [];
        $duplicatesMap = [];

        // =========================================================================
        // CABANG A: PEMBENTUKAN DATA & PAYLOAD UNTUK KEMASAN
        // =========================================================================
        if ($subType === 'kemasan') {
            foreach ($rawRows as $r) {
                $bl = trim((string)($r['NO_BL_AWB'] ?? ''));
                if (empty($bl)) continue;

                $occurrenceTracker[$bl] = ($occurrenceTracker[$bl] ?? 0) + 1;
                $occurrence = $occurrenceTracker[$bl];
                $batchIndex = $occurrence - 1;
                if ($occurrence > 1) {
                    $duplicatesMap[$bl] = $occurrence;
                }

                $tglBlAwb        = toCeisaDmy($r['TGL_BL_AWB'] ?? '');
                $tglMasterBlAwb  = toCeisaDmy($r['TGL_MASTER_BL_AWB'] ?? '');
                $tglBc11         = toCeisaDmy($r['TGL_BC11'] ?? '');
                $tglDokInOut     = toCeisaDmy($r['TGL_DOK_INOUT'] ?? '');
                $waktuInOut      = toCeisaDateTime($r['WK_INOUT'] ?? '');
                $tglDaftarPabean = toCeisaDmy($r['TGL_DAFTAR_PABEAN'] ?? '');
                $tglSegelBc      = toCeisaDmy($r['TGL_SEGEL_BC'] ?? '');
                $tglIjinTps      = toCeisaDmy($r['TGL_IJIN_TPS'] ?? '');
                $noDaftarPabean  = sanitizeNoDaftarPabean($r['NO_DAFTAR_PABEAN'] ?? '', 6);
                if (empty($noDaftarPabean)) {
                    $noDaftarPabean = sanitizeNoDaftarPabean($r['NO_BC11'] ?? '', 6);
                }
                if (empty($noDaftarPabean)) {
                    $noDaftarPabean = sanitizeNoDaftarPabean($r['NO_DOK_INOUT'] ?? '000000', 6);
                }
                if (empty($tglDaftarPabean)) {
                    $tglDaftarPabean = !empty($tglDokInOut) ? $tglDokInOut : date('d-m-Y');
                }

                $rawIdCons = trim((string)($r['ID_CONSIGNEE'] ?? ''));
                $idConsignee = !empty($rawIdCons) ? $rawIdCons : '000000000000000';

                $rawKdGudang = trim((string)($r['KD_GUDANG'] ?? 'GPSU'));
                $rawKdTimbun = trim((string)($r['KD_TIMBUN'] ?? ''));
                $kodeTimbun  = !empty($rawKdTimbun) ? $rawKdTimbun : (!empty($rawKdGudang) ? $rawKdGudang : 'GPSU');

                $gudangTujuan = trim((string)($r['GUDANG_TUJUAN'] ?? ''));
                if (empty($gudangTujuan)) {
                    $gudangTujuan = !empty($rawKdGudang) ? $rawKdGudang : 'GPSU';
                }

                $nomorDokInOut = trim((string)($r['NO_DOK_INOUT'] ?? ''));
                $nomorSegelBc  = trim((string)($r['NO_SEGEL_BC'] ?? ''));
                if (empty($nomorSegelBc)) {
                    $nomorSegelBc = !empty($nomorDokInOut) ? $nomorDokInOut : 'SGLBC1';
                }
                if (empty($tglSegelBc)) {
                    $tglSegelBc = !empty($tglDokInOut) ? $tglDokInOut : date('d-m-Y');
                }

                $consigneeClean = trim(preg_replace('/[\r\n\t]+/', ' ', (string)($r['CONSIGNEE'] ?? '')));

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

                if (!isset($batches[$batchIndex])) {
                    $batchRef = ($batchIndex === 0) ? $refNumber : ($refNumber . ($batchIndex + 1));
                    $batchHeader = array_merge($header, ['refNumber' => $batchRef]);
                    $batches[$batchIndex] = [
                        'batch_number' => $batchIndex + 1,
                        'refNumber'    => $batchRef,
                        'item_count'   => 0,
                        'payload'      => [
                            'header' => $batchHeader,
                            'detil'  => []
                        ]
                    ];
                }

                $batches[$batchIndex]['payload']['detil'][] = $item;
                $batches[$batchIndex]['item_count']++;

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

        // =========================================================================
        // CABANG B: PEMBENTUKAN DATA & PAYLOAD UNTUK CONTAINER LCL & CONTAINER PJT
        // =========================================================================
        } else {
            foreach ($rawRows as $r) {
                $noCont = strtoupper(trim((string)($r['NO_CONT'] ?? '')));
                if (empty($noCont)) continue;

                $occurrenceTracker[$noCont] = ($occurrenceTracker[$noCont] ?? 0) + 1;
                $occurrence = $occurrenceTracker[$noCont];
                $batchIndex = $occurrence - 1;
                if ($occurrence > 1) {
                    $duplicatesMap[$noCont] = $occurrence;
                }

                $isKosong = ($r['FL_CONT_KOSONG'] == '1');
                $brutoFloat = (float)($r['BRUTO'] ?? 0);

                // Standardisasi jenisKontainer
                $jnsContRaw = strtoupper(trim((string)($r['JNS_CONT'] ?? 'LCL')));
                if ($jnsContRaw === 'L' || strpos($jnsContRaw, 'LCL') !== false) {
                    $jenisKontainer = 'LCL';
                } elseif ($jnsContRaw === 'F' || strpos($jnsContRaw, 'FCL') !== false) {
                    $jenisKontainer = 'FCL';
                } elseif (in_array($jnsContRaw, ['4', '7', '8'])) {
                    $jenisKontainer = $jnsContRaw;
                } else {
                    $jenisKontainer = 'LCL';
                }

                $tglBlAwb        = toCeisaDmy($r['TGL_BL_AWB'] ?? '');
                $tglMasterBlAwb  = toCeisaDmy($r['TGL_MASTER_BL_AWB'] ?? '');
                $tglBc11         = toCeisaDmy($r['TGL_BC11'] ?? '');
                $tglDokInOut     = toCeisaDmy($r['TGL_DOK_INOUT'] ?? '');
                $waktuInOut      = toCeisaDateTime($r['WK_INOUT'] ?? '');
                $tglDaftarPabean = toCeisaDmy($r['TGL_DAFTAR_PABEAN'] ?? '', $tglDokInOut);
                $tglSegelBc      = toCeisaDmy($r['TGL_SEGEL_BC'] ?? '', $tglDokInOut);
                $tglIjinTps      = toCeisaDmy($r['TGL_IJIN_TPS'] ?? '', $tglDokInOut);

                $noDaftarPabean  = sanitizeNoDaftarPabean($r['NO_DAFTAR_PABEAN'] ?? '', 6);
                if (empty($noDaftarPabean)) {
                    $noDaftarPabean = sanitizeNoDaftarPabean($r['NO_BC11'] ?? '', 6);
                }
                if (empty($noDaftarPabean)) {
                    $noDaftarPabean = sanitizeNoDaftarPabean($r['NO_DOK_INOUT'] ?? '000000', 6);
                }

                $rawIdCons = preg_replace('/[^0-9]/', '', (string)($r['ID_CONSIGNEE'] ?? ''));
                $idConsignee = (!empty($rawIdCons) && strlen($rawIdCons) >= 9) ? $rawIdCons : '000000000000000';

                $rawKdGudang = trim((string)($r['KD_GUDANG'] ?? 'GPSU'));
                $gudangTujuan = trim((string)($r['GUDANG_TUJUAN'] ?? ''));
                if (empty($gudangTujuan)) {
                    $gudangTujuan = !empty($rawKdGudang) ? $rawKdGudang : 'GPSU';
                }
                $kodeTimbun = trim((string)($r['KD_TIMBUN'] ?? ''));
                if (empty($kodeTimbun)) {
                    $kodeTimbun = $gudangTujuan;
                }

                $nomorDokInOut = trim((string)($r['NO_DOK_INOUT'] ?? ''));
                $nomorSegelBc  = trim((string)($r['NO_SEGEL_BC'] ?? ''));
                if (empty($nomorSegelBc)) {
                    $nomorSegelBc = !empty($r['NO_SEGEL']) ? trim((string)$r['NO_SEGEL']) : (!empty($nomorDokInOut) ? $nomorDokInOut : 'SGLBC1');
                }
                $nomorSegel = trim((string)($r['NO_SEGEL'] ?? ''));
                if (empty($nomorSegel)) {
                    $nomorSegel = $nomorSegelBc ?: '-';
                }

                $consigneeClean = trim(preg_replace('/[\r\n\t]+/', ' ', (string)($r['CONSIGNEE'] ?? '')));

                $item = [
                    'nomorKontainer'      => $noCont,
                    'ukuranKontainer'     => (string)($r['UK_CONT'] ?? '20'),
                    'jenisKontainer'      => $jenisKontainer,
                    'nomorSegel'          => $nomorSegel,
                    'noBlAwb'             => (string)($r['NO_BL_AWB'] ?? ''),
                    'tanggalBlAwb'        => $tglBlAwb,
                    'noMasterBlAwb'       => (string)($r['NO_MASTER_BL_AWB'] ?? ''),
                    'tanggalMasterBlAwb'  => $tglMasterBlAwb,
                    'idConsignee'         => $idConsignee,
                    'consignee'           => $consigneeClean,
                    'bruto'               => $brutoFloat,
                    'nomorBc11'           => (string)($r['NO_BC11'] ?? ''),
                    'tanggalBc11'         => $tglBc11,
                    'nomorPosBc11'        => (string)($r['NO_POS_BC11'] ?? ''),
                    'kodeTimbun'          => $kodeTimbun,
                    'kodeDokumenInOut'    => (string)($r['KD_DOK_INOUT'] ?? ($type === 'In' ? '3' : '1')),
                    'nomorDokumenInOut'   => $nomorDokInOut,
                    'tanggalDokumenInOut' => $tglDokInOut,
                    'waktuInOut'          => $waktuInOut,
                    'kodeSaranaAngkutInOut' => (string)($r['KD_SAR_ANGKUT_INOUT'] ?? '1'),
                    'nomorPolisi'         => (string)($r['NO_POL'] ?? ''),
                    'flagKontainerKosong' => $isKosong,
                    'flagKontainer'       => true,
                    'isoCode'             => (string)($r['ISO_CODE'] ?? ''),
                    'pelabuhanMuat'       => (string)($r['PEL_MUAT'] ?? ''),
                    'pelabuhanTransit'    => (string)($r['PEL_TRANSIT'] ?? ''),
                    'pelabuhanBongkar'    => (string)($r['PEL_BONGKAR'] ?? ''),
                    'gudangTujuan'        => $gudangTujuan,
                    'kodeKantor'          => (string)($r['KODE_KANTOR'] ?? '070100'),
                    'nomorDaftarPabean'   => $noDaftarPabean,
                    'tanggalDaftarPabean' => $tglDaftarPabean,
                    'nomorSegelBc'        => $nomorSegelBc,
                    'tanggalSegelBc'      => $tglSegelBc,
                    'nomorIjinTps'        => (string)($r['NO_IJIN_TPS'] ?? ''),
                    'tanggalIjinTps'      => $tglIjinTps
                ];

                if (!isset($batches[$batchIndex])) {
                    $batchRef = ($batchIndex === 0) ? $refNumber : ($refNumber . ($batchIndex + 1));
                    $batchHeader = array_merge($header, ['refNumber' => $batchRef]);
                    $batches[$batchIndex] = [
                        'batch_number' => $batchIndex + 1,
                        'refNumber'    => $batchRef,
                        'item_count'   => 0,
                        'payload'      => [
                            'header'    => $batchHeader,
                            'kontainer' => []
                        ]
                    ];
                }

                $batches[$batchIndex]['payload']['kontainer'][] = $item;
                $batches[$batchIndex]['item_count']++;

                // Format row untuk tabel DataTables kontainer
                $tableRows[] = [
                    'no'               => count($tableRows) + 1,
                    'idMasBl'          => $r['Id_MasBL'] ?? '',
                    'noCont'           => $noCont,
                    'ukuranKontainer'  => $item['ukuranKontainer'] . 'ft ' . $item['jenisKontainer'],
                    'noBlAwb'          => $item['noBlAwb'] ?: '-',
                    'tanggalBlAwb'     => $item['tanggalBlAwb'],
                    'noMasterBlAwb'    => $item['noMasterBlAwb'] ?: '-',
                    'bruto'            => number_format($brutoFloat, 0, ',', '.') . ' KG',
                    'nomorBc11'        => $item['nomorBc11'] ?: '-',
                    'nomorPosBc11'     => $item['nomorPosBc11'] ?: '-',
                    'nomorSegel'       => $item['nomorSegel'],
                    'nomorPolisi'      => $item['nomorPolisi'],
                    'nomorDokInOut'    => $item['nomorDokumenInOut'] . ($item['tanggalDokumenInOut'] ? ' (' . $item['tanggalDokumenInOut'] . ')' : ''),
                    'consignee'        => $item['consignee'],
                    'waktuInOut'       => $item['waktuInOut'],
                    'statusKosong'     => $isKosong ? 'KOSONG' : 'ISI',
                    'batch'            => $occurrence,
                    'batchLabel'       => 'Batch ' . $occurrence,
                    'is_duplicate'     => ($occurrence > 1)
                ];
            }
        }

        $batchList = array_values($batches);
        $totalBatches = count($batchList);
        $hasDuplicates = !empty($duplicatesMap);
        $dupKeyName = ($subType === 'kemasan') ? 'B/L' : 'Kontainer';

        jsonResponse([
            'success'          => true,
            'count'            => count($tableRows),
            'subType'          => $subType,
            'targetEndpoint'   => $targetEndpoint,
            'message'          => "Berhasil memuat " . count($tableRows) . " data $subTypeLabel" . ($hasDuplicates ? " (dibagi $totalBatches batch karena ada $dupKeyName ganda)" : ''),
            'rows'             => $tableRows,
            'payload'          => $batchList[0]['payload'] ?? null,
            'has_duplicates'   => $hasDuplicates,
            'duplicate_keys'   => array_keys($duplicatesMap),
            'duplicate_count'  => count($duplicatesMap),
            'total_batches'    => $totalBatches,
            'batches'          => $batchList
        ]);

    } catch (Exception $e) {
        error_log("Error cocokms fetch: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Gagal mengambil data Coarri Codeco: ' . $e->getMessage()
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
    $targetEndpoint = trim((string)($postData['endpoint'] ?? ''));

    if (empty($payload) || !is_array($payload)) {
        jsonResponse(['success' => false, 'message' => 'Payload JSON kosong atau tidak valid'], 400);
    }

    $isContainerPayload = isset($payload['kontainer']) && is_array($payload['kontainer']);
    $isKemasanPayload   = isset($payload['detil']) && is_array($payload['detil']);

    if (empty($targetEndpoint)) {
        $targetEndpoint = $isContainerPayload ? 'coarri-codeco-container' : 'coarri-codeco-kemasan';
    }

    if (empty($payload['header']) || (!$isContainerPayload && !$isKemasanPayload)) {
        jsonResponse(['success' => false, 'message' => 'Format payload tidak memenuhi struktur CEISA 4.0 (header & detil/kontainer required)'], 422);
    }

    $kodeGudangDefault = !empty($payload['header']['kodeGudang']) ? $payload['header']['kodeGudang'] : 'GPSU';

    // -------------------------------------------------------------
    // Sanitasi Payload Kontainer
    // -------------------------------------------------------------
    if ($isContainerPayload) {
        // Normalisasi Header wajib untuk endpoint coarri-codeco-container
        if (empty($payload['header']['noBc11']) && !empty($payload['header']['nomorBc11'])) {
            $payload['header']['noBc11'] = (string)$payload['header']['nomorBc11'];
        }
        if (empty($payload['header']['noBc11'])) {
            foreach ($payload['kontainer'] as $tempC) {
                if (!empty($tempC['nomorBc11'])) {
                    $payload['header']['noBc11'] = (string)$tempC['nomorBc11'];
                    break;
                }
                if (!empty($tempC['noBc11'])) {
                    $payload['header']['noBc11'] = (string)$tempC['noBc11'];
                    break;
                }
            }
        }
        if (empty($payload['header']['kodeSaranaPengangkut']) && !empty($payload['header']['kodeSaranaAngkutInOut'])) {
            $payload['header']['kodeSaranaPengangkut'] = (string)$payload['header']['kodeSaranaAngkutInOut'];
        }

        $seenCont = [];
        $filteredCont = [];

        foreach ($payload['kontainer'] as &$c) {
            $contKey = strtoupper(trim((string)($c['nomorKontainer'] ?? '')));
            if (empty($contKey) || isset($seenCont[$contKey])) {
                continue;
            }
            $seenCont[$contKey] = true;

            if (empty($c['idConsignee'])) {
                $c['idConsignee'] = '000000000000000';
            }
            if (empty($c['kodeTimbun'])) {
                $c['kodeTimbun'] = $kodeGudangDefault;
            }
            if (isset($c['consignee'])) {
                $c['consignee'] = trim(preg_replace('/[\r\n\t]+/', ' ', (string)$c['consignee']));
            }
            if (empty($c['gudangTujuan'])) {
                $c['gudangTujuan'] = $kodeGudangDefault;
            }
            if (empty($c['nomorSegelBc'])) {
                $c['nomorSegelBc'] = !empty($c['nomorSegel']) ? $c['nomorSegel'] : (!empty($c['nomorDokumenInOut']) ? $c['nomorDokumenInOut'] : 'SGLBC1');
            }
            if (empty($c['tanggalSegelBc'])) {
                $c['tanggalSegelBc'] = !empty($c['tanggalDokumenInOut']) ? $c['tanggalDokumenInOut'] : date('d-m-Y');
            }
            $rawNoDaftar = !empty($c['nomorDaftarPabean']) ? $c['nomorDaftarPabean'] : (!empty($c['nomorBc11']) ? $c['nomorBc11'] : (!empty($c['nomorDokumenInOut']) ? $c['nomorDokumenInOut'] : '000000'));
            $c['nomorDaftarPabean'] = sanitizeNoDaftarPabean($rawNoDaftar, 6);
            if (empty($c['nomorDaftarPabean'])) {
                $c['nomorDaftarPabean'] = '000000';
            }

            if (empty($c['tanggalDaftarPabean'])) {
                $c['tanggalDaftarPabean'] = !empty($c['tanggalDokumenInOut']) ? $c['tanggalDokumenInOut'] : date('d-m-Y');
            }

            $filteredCont[] = $c;
        }
        unset($c);
        $payload['kontainer'] = $filteredCont;

    // -------------------------------------------------------------
    // Sanitasi Payload Kemasan
    // -------------------------------------------------------------
    } else {
        $seenSendBL = [];
        $filteredDetil = [];

        foreach ($payload['detil'] as &$d) {
            $blKey = trim((string)($d['nomorBlAwb'] ?? ''));
            if (empty($blKey) || isset($seenSendBL[$blKey])) {
                continue;
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
            $rawNoDaftar = !empty($d['nomorDaftarPabean']) ? $d['nomorDaftarPabean'] : (!empty($d['nomorBc11']) ? $d['nomorBc11'] : (!empty($d['nomorDokumenInOut']) ? $d['nomorDokumenInOut'] : '000000'));
            $d['nomorDaftarPabean'] = sanitizeNoDaftarPabean($rawNoDaftar, 6);
            if (empty($d['nomorDaftarPabean'])) {
                $d['nomorDaftarPabean'] = '000000';
            }

            if (empty($d['tanggalDaftarPabean'])) {
                $d['tanggalDaftarPabean'] = !empty($d['tanggalDokumenInOut']) ? $d['tanggalDokumenInOut'] : date('d-m-Y');
            }

            $filteredDetil[] = $d;
        }
        unset($d);
        $payload['detil'] = $filteredDetil;
    }

    try {
        $client = new CeisaClient();
        $res = $client->post($targetEndpoint, $payload);

        $isOk = ($res['code'] >= 200 && $res['code'] < 300);

        // Catat log & riwayat jika database tpsonline tersedia
        try {
            global $pdo_tpsonline;
            if ($pdo_tpsonline) {
                $header = $payload['header'] ?? [];
                $refNumber = $header['refNumber'] ?? '';
                $itemCount = $isContainerPayload ? count($payload['kontainer'] ?? []) : count($payload['detil'] ?? []);

                // 1. Simpan ke Master Audit Log (ceisa_api_logs)
                $stmtLog = $pdo_tpsonline->prepare("
                    INSERT INTO ceisa_api_logs 
                    (endpoint, request_params, http_code, status, message, total_rows, raw_response, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $requestSummary = json_encode([
                    'header' => $header,
                    'item_count' => $itemCount,
                    'target_endpoint' => $targetEndpoint
                ], JSON_UNESCAPED_UNICODE);

                $stmtLog->execute([
                    $targetEndpoint,
                    $requestSummary,
                    (int)($res['code'] ?? ($isOk ? 200 : 400)),
                    $isOk ? 'SUCCESS' : 'FAILED',
                    $res['message'] ?? ($isOk ? 'Berhasil' : 'Gagal'),
                    $itemCount,
                    json_encode($res['raw'] ?? $res, JSON_UNESCAPED_UNICODE)
                ]);

                // 2. Simpan ke tabel riwayat sesuai tipe objek jika berhasil
                if ($isOk) {
                    $kdTps = $header['kodeTps'] ?? 'PSU0';
                    $kdGudang = $header['kodeGudang'] ?? 'GPSU';

                    if ($isKemasanPayload) {
                        $stmtCocokms = $pdo_tpsonline->prepare("
                            INSERT INTO ceisa_cocokms 
                            (ref_number, kode_dokumen, kd_tps, kd_gudang, jenis_kemasan, jumlah_kemasan, seri_kemasan, no_bl_awb, tgl_bl_awb, no_pos_bc11, consignee, kontainer_asal, no_dok_inout, tgl_dok_inout, wk_inout, no_polisi, pel_muat, pel_transit, pel_bongkar, no_segel_bc, tgl_segel_bc, bruto, raw_data, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");

                        foreach ($payload['detil'] as $item) {
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
                                $refNumber, $kodeDokumen, $kdTps, $kdGudang, $jnsKemasan, $jmlKemasan,
                                $seriKemasan, $nomorBlAwb, $tanggalBlAwb, $nomorPosBc11, $consignee,
                                $kontainerAsal, $noDokInOut, $tglDokInOut, $wkInOut, $noPolisi,
                                $pelMuat, $pelTransit, $pelBongkar, $noSegelBc, $tglSegelBc,
                                $bruto, $rawData
                            ]);
                        }
                    } elseif ($isContainerPayload) {
                        $stmtCococont = $pdo_tpsonline->prepare("
                            INSERT INTO ceisa_cococont 
                            (ref_number, kode_dokumen, kd_tps, kd_gudang, no_kontainer, ukuran, jenis_kontainer, no_segel, no_bl_awb, tgl_bl_awb, no_pos_bc11, consignee, no_dok_inout, tgl_dok_inout, wk_inout, no_polisi, pel_muat, pel_transit, pel_bongkar, raw_data, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");

                        foreach ($payload['kontainer'] as $item) {
                            $kodeDokumen = (string)($item['kodeDokumen'] ?? ($header['kodeDokumen'] ?? ''));
                            $noCont = $item['nomorKontainer'] ?? '';
                            $ukuran = $item['ukuranKontainer'] ?? '20';
                            $jenisCont = $item['jenisKontainer'] ?? 'LCL';
                            $noSegel = $item['nomorSegel'] ?? ($item['nomorSegelBc'] ?? '');
                            $noBl = $item['noBlAwb'] ?? ($item['nomorBlAwb'] ?? '');
                            $tglBl = $item['tanggalBlAwb'] ?? '';
                            $noPos = $item['nomorPosBc11'] ?? '';
                            $consignee = substr(trim(preg_replace('/[\r\n\t]+/', ' ', (string)($item['consignee'] ?? ''))), 0, 150);
                            $noDokInOut = $item['nomorDokumenInOut'] ?? ($item['nomorDaftarPabean'] ?? '');
                            $tglDokInOut = $item['tanggalDokumenInOut'] ?? ($item['tanggalDaftarPabean'] ?? '');
                            $wkInOut = $item['waktuInOut'] ?? '';
                            $noPolisi = $item['nomorPolisi'] ?? '';
                            $pelMuat = $item['pelabuhanMuat'] ?? '';
                            $pelTransit = $item['pelabuhanTransit'] ?? '';
                            $pelBongkar = $item['pelabuhanBongkar'] ?? '';
                            $rawData = json_encode($item, JSON_UNESCAPED_UNICODE);

                            $stmtCococont->execute([
                                $refNumber, $kodeDokumen, $kdTps, $kdGudang, $noCont, $ukuran,
                                $jenisCont, $noSegel, $noBl, $tglBl, $noPos, $consignee,
                                $noDokInOut, $tglDokInOut, $wkInOut, $noPolisi,
                                $pelMuat, $pelTransit, $pelBongkar, $rawData
                            ]);
                        }
                    }
                }
            }
        } catch (Exception $dbEx) {
            error_log("Gagal mencatat log ceisa_api_logs / ceisa_cocokms / ceisa_cococont: " . $dbEx->getMessage());
        }

        $objName = $isContainerPayload ? 'Kontainer' : 'Kemasan';
        jsonResponse([
            'success' => $isOk,
            'code'    => $res['code'] ?? ($isOk ? 200 : 400),
            'message' => $res['message'] ?? ($isOk ? "Data Coarri Codeco $objName berhasil dikirim ke CEISA 4.0" : 'Gagal mengirim data'),
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
