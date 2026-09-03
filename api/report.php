<?php
/**
 * API Laporan Pengiriman CEISA 4.0
 * Endpoint: /cek-data-terkirim
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/CeisaClient.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$action = input('action', 'cek_terkirim');

function normalizeDateDmy($d) {
    if (empty($d)) return date('d-m-Y');
    $d = trim($d);
    // Jika format YYYY-MM-DD dari HTML5 input date
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $matches)) {
        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }
    // Jika sudah format dd-MM-yyyy
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $d)) {
        return $d;
    }
    return date('d-m-Y');
}

if ($action === 'detail_cont_ref') {
    $refNumber = trim((string)input('refNumber'));
    if (empty($refNumber)) {
        jsonResponse(['success' => false, 'message' => 'Reference number tidak boleh kosong'], 400);
    }

    try {
        global $pdo_tpsonline;
        
        // 1. Ambil data dari ceisa_api_logs
        $stmtLog = $pdo_tpsonline->prepare("
            SELECT id, endpoint, http_code, status, message, total_rows, request_params, raw_response, created_at 
            FROM ceisa_api_logs 
            WHERE (request_params LIKE :ref OR raw_response LIKE :ref2)
            ORDER BY id DESC LIMIT 1
        ");
        $stmtLog->execute([':ref' => "%$refNumber%", ':ref2' => "%$refNumber%"]);
        $logData = $stmtLog->fetch(PDO::FETCH_ASSOC);

        $parsedRequest = $logData ? json_decode($logData['request_params'], true) : null;
        $parsedResponse = $logData ? json_decode($logData['raw_response'], true) : null;
        $header = $parsedRequest['header'] ?? [];

        // 2. Ambil rincian kontainer dari tabel khusus ceisa_cococont
        $stmtCoco = $pdo_tpsonline->prepare("
            SELECT * FROM ceisa_cococont 
            WHERE ref_number = ?
            ORDER BY id ASC
        ");
        $stmtCoco->execute([$refNumber]);
        $cocoRows = $stmtCoco->fetchAll(PDO::FETCH_ASSOC);

        $containers = [];

        if (!empty($cocoRows)) {
            foreach ($cocoRows as $c) {
                $rawDetail = !empty($c['raw_data']) ? json_decode($c['raw_data'], true) : [];
                $containers[] = [
                    'noCont'              => $c['no_kontainer'],
                    'ukuran'              => (!empty($c['ukuran']) ? $c['ukuran'] . ' ft' : '20 ft'),
                    'jenisCont'           => $c['jenis_kontainer'] ?? '4',
                    'jenisMuat'           => ($c['jenis_muat'] === 'E') ? 'Kosong (Empty)' : 'Isi (Full)',
                    'noSppb'              => $c['no_dok_inout'] ?: '-',
                    'statusSegel'         => $c['status_segel'] ?: '-',
                    'noSegel'             => $c['no_segel'] ?: '-',
                    'nomorPosBc11'        => $c['no_pos_bc11'] ?: '-',
                    'noBlAwb'             => $c['no_bl_awb'] ?: '-',
                    'tanggalBlAwb'        => $c['tgl_bl_awb'] ?: '-',
                    'consignee'           => $c['consignee'] ?: '-',
                    'nomorDokumenInOut'   => $c['no_dok_inout'] ?: '-',
                    'tanggalDokumenInOut' => $c['tgl_dok_inout'] ?: '-',
                    'waktuInOut'          => $c['wk_inout'] ?: '-',
                    'nomorPolisi'         => $c['no_polisi'] ?: '-',
                    'bruto'               => $rawDetail['bruto'] ?? 0,
                    'raw'                 => $rawDetail
                ];
            }
        } else {
            // Fallback ke ceisa_sppb_kontainer / ceisa_plp_kontainer (untuk data historis pengujian lama)
            $stmtSppb = $pdo_tpsonline->prepare("
                SELECT id, car, no_sppb, no_cont, uk_cont, jns_cont, jns_muat, status_segel, no_segel, raw_data, created_at 
                FROM ceisa_sppb_kontainer 
                WHERE car = ?
                ORDER BY id ASC
            ");
            $stmtSppb->execute([$refNumber]);
            $sppbRows = $stmtSppb->fetchAll(PDO::FETCH_ASSOC);

            $stmtPlp = $pdo_tpsonline->prepare("
                SELECT id, idTpsPlp, nomorKontainer, ukuranKontainer, jenisMuat, nomorPosBc11, nomorHostBl, tanggalHostBl, namaPemilik, flagSetuju 
                FROM ceisa_plp_kontainer 
                WHERE idTpsPlp = ?
                ORDER BY id ASC
            ");
            $stmtPlp->execute([$refNumber]);
            $plpRows = $stmtPlp->fetchAll(PDO::FETCH_ASSOC);

            $plpMap = [];
            foreach ($plpRows as $plp) {
                $cNo = strtoupper(trim($plp['nomorKontainer']));
                $plpMap[$cNo] = $plp;
            }

            if (!empty($sppbRows)) {
                foreach ($sppbRows as $s) {
                    $cNo = strtoupper(trim($s['no_cont']));
                    $plpInfo = $plpMap[$cNo] ?? [];
                    $rawDetail = !empty($s['raw_data']) ? json_decode($s['raw_data'], true) : [];

                    $containers[] = [
                        'noCont'              => $s['no_cont'],
                        'ukuran'              => $s['uk_cont'] . ' ft',
                        'jenisCont'           => $s['jns_cont'],
                        'jenisMuat'           => ($s['jns_muat'] === 'E') ? 'Kosong (Empty)' : 'Isi (Full)',
                        'noSppb'              => $s['no_sppb'],
                        'statusSegel'         => $s['status_segel'],
                        'noSegel'             => $s['no_segel'] ?: '-',
                        'nomorPosBc11'        => $rawDetail['nomorPosBc11'] ?? ($plpInfo['nomorPosBc11'] ?? '-'),
                        'noBlAwb'             => $rawDetail['noBlAwb'] ?? ($plpInfo['nomorHostBl'] ?? '-'),
                        'tanggalBlAwb'        => $rawDetail['tanggalBlAwb'] ?? ($plpInfo['tanggalHostBl'] ?? '-'),
                        'consignee'           => $rawDetail['consignee'] ?? ($plpInfo['namaPemilik'] ?? '-'),
                        'nomorDokumenInOut'   => $rawDetail['nomorDokumenInOut'] ?? ($s['no_sppb'] ?? '-'),
                        'tanggalDokumenInOut' => $rawDetail['tanggalDokumenInOut'] ?? '-',
                        'waktuInOut'          => $rawDetail['waktuInOut'] ?? '-',
                        'nomorPolisi'         => $rawDetail['nomorPolisi'] ?? '-',
                        'bruto'               => $rawDetail['bruto'] ?? 0,
                        'raw'                 => $rawDetail
                    ];
                }
            } elseif (!empty($plpRows)) {
                foreach ($plpRows as $plp) {
                    $containers[] = [
                        'noCont'              => $plp['nomorKontainer'],
                        'ukuran'              => $plp['ukuranKontainer'] . ' ft',
                        'jenisCont'           => '-',
                        'jenisMuat'           => ($plp['jenisMuat'] === 'E') ? 'Kosong (Empty)' : 'Isi (Full)',
                        'noSppb'              => '-',
                        'statusSegel'         => '-',
                        'noSegel'             => '-',
                        'nomorPosBc11'        => $plp['nomorPosBc11'] ?: '-',
                        'noBlAwb'             => $plp['nomorHostBl'] ?: '-',
                        'tanggalBlAwb'        => $plp['tanggalHostBl'] ?: '-',
                        'consignee'           => $plp['namaPemilik'] ?: '-',
                        'nomorDokumenInOut'   => '-',
                        'tanggalDokumenInOut' => '-',
                        'waktuInOut'          => '-',
                        'nomorPolisi'         => '-',
                        'bruto'               => 0,
                        'raw'                 => $plp
                    ];
                }
            }
        }

        jsonResponse([
            'success'          => true,
            'referenceNumber'  => $refNumber,
            'header'           => $header,
            'log'              => $logData ? [
                'id'         => $logData['id'],
                'endpoint'   => $logData['endpoint'],
                'http_code'  => $logData['http_code'],
                'status'     => $logData['status'],
                'message'    => $logData['message'],
                'total_rows' => $logData['total_rows'],
                'created_at' => $logData['created_at'],
                'response'   => $parsedResponse
            ] : null,
            'container_count'  => count($containers),
            'containers'       => $containers,
            'raw_payload'      => $parsedRequest
        ]);

    } catch (Exception $e) {
        jsonResponse([
            'success' => false,
            'message' => 'Gagal mengambil detail referensi: ' . $e->getMessage()
        ], 500);
    }
}

if ($action === 'detail_kms_ref') {
    $refNumber = trim((string)input('refNumber'));
    if (empty($refNumber)) {
        jsonResponse(['success' => false, 'message' => 'Reference number tidak boleh kosong'], 400);
    }

    try {
        global $pdo_tpsonline;
        
        // 1. Ambil data dari ceisa_api_logs
        $stmtLog = $pdo_tpsonline->prepare("
            SELECT id, endpoint, http_code, status, message, total_rows, request_params, raw_response, created_at 
            FROM ceisa_api_logs 
            WHERE (request_params LIKE :ref OR raw_response LIKE :ref2)
            ORDER BY id DESC LIMIT 1
        ");
        $stmtLog->execute([':ref' => "%$refNumber%", ':ref2' => "%$refNumber%"]);
        $logData = $stmtLog->fetch(PDO::FETCH_ASSOC);

        $parsedRequest = $logData ? json_decode($logData['request_params'], true) : null;
        $parsedResponse = $logData ? json_decode($logData['raw_response'], true) : null;
        $header = $parsedRequest['header'] ?? [];

        // 2. Ambil rincian kemasan dari tabel khusus ceisa_cocokms
        $stmtCocoKms = $pdo_tpsonline->prepare("
            SELECT * FROM ceisa_cocokms 
            WHERE ref_number = ?
            ORDER BY id ASC
        ");
        $stmtCocoKms->execute([$refNumber]);
        $cocoKmsRows = $stmtCocoKms->fetchAll(PDO::FETCH_ASSOC);

        $packages = [];

        if (!empty($cocoKmsRows)) {
            foreach ($cocoKmsRows as $k) {
                $rawDetail = !empty($k['raw_data']) ? json_decode($k['raw_data'], true) : [];
                $packages[] = [
                    'jenisKemasan'        => $k['jenis_kemasan'] ?: 'PK',
                    'jumlahKemasan'       => (float)($k['jumlah_kemasan'] ?? 0),
                    'noSppb'              => $k['no_dok_inout'] ?: '-',
                    'noBlAwb'             => $k['no_bl_awb'] ?: '-',
                    'tanggalBlAwb'        => $k['tgl_bl_awb'] ?: '-',
                    'nomorPosBc11'        => $k['no_pos_bc11'] ?: '-',
                    'consignee'           => $k['consignee'] ?: '-',
                    'kontainerAsal'       => $k['kontainer_asal'] ?: '-',
                    'nomorPolisi'         => $k['no_polisi'] ?: '-',
                    'waktuInOut'          => $k['wk_inout'] ?: '-',
                    'noSegelBc'           => $k['no_segel_bc'] ?: '-',
                    'bruto'               => (float)($k['bruto'] ?? 0),
                    'raw'                 => $rawDetail
                ];
            }
        } else {
            // Fallback ke ceisa_sppb_kemasan / ceisa_plp_kemasan (untuk data historis pengujian lama)
            $stmtSppb = $pdo_tpsonline->prepare("
                SELECT id, car, no_sppb, jml_kemasan, jns_kemasan, kd_jns_kemasan, raw_data, created_at 
                FROM ceisa_sppb_kemasan 
                WHERE car = ?
                ORDER BY id ASC
            ");
            $stmtSppb->execute([$refNumber]);
            $sppbRows = $stmtSppb->fetchAll(PDO::FETCH_ASSOC);

            $stmtPlp = $pdo_tpsonline->prepare("
                SELECT id, idTpsPlp, jenisKemasan, jumlahKemasan, nomorPosBc11, nomorBlAwb, tanggalBlAwb, consignee, flagSetuju 
                FROM ceisa_plp_kemasan 
                WHERE idTpsPlp = ?
                ORDER BY id ASC
            ");
            $stmtPlp->execute([$refNumber]);
            $plpRows = $stmtPlp->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($sppbRows)) {
                foreach ($sppbRows as $s) {
                    $rawDetail = !empty($s['raw_data']) ? json_decode($s['raw_data'], true) : [];
                    $packages[] = [
                        'jenisKemasan'        => $s['jns_kemasan'],
                        'jumlahKemasan'       => (float)($s['jml_kemasan'] ?? ($rawDetail['jumlahKemasan'] ?? 0)),
                        'noSppb'              => $s['no_sppb'],
                        'noBlAwb'             => $rawDetail['nomorBlAwb'] ?? ($rawDetail['noBlAwb'] ?? '-'),
                        'tanggalBlAwb'        => $rawDetail['tanggalBlAwb'] ?? '-',
                        'nomorPosBc11'        => $rawDetail['nomorPosBc11'] ?? '-',
                        'consignee'           => $rawDetail['consignee'] ?? '-',
                        'kontainerAsal'       => $rawDetail['kontainerAsal'] ?? '-',
                        'nomorPolisi'         => $rawDetail['nomorPolisi'] ?? '-',
                        'waktuInOut'          => $rawDetail['waktuInOut'] ?? '-',
                        'noSegelBc'           => $rawDetail['nomorSegelBc'] ?? '-',
                        'bruto'               => $rawDetail['bruto'] ?? 0,
                        'raw'                 => $rawDetail
                    ];
                }
            } elseif (!empty($plpRows)) {
                foreach ($plpRows as $plp) {
                    $packages[] = [
                        'jenisKemasan'        => $plp['jenisKemasan'],
                        'jumlahKemasan'       => (float)($plp['jumlahKemasan'] ?? 0),
                        'noSppb'              => '-',
                        'noBlAwb'             => $plp['nomorBlAwb'],
                        'tanggalBlAwb'        => $plp['tanggalBlAwb'],
                        'nomorPosBc11'        => $plp['nomorPosBc11'],
                        'consignee'           => $plp['consignee'],
                        'kontainerAsal'       => '-',
                        'nomorPolisi'         => '-',
                        'waktuInOut'          => '-',
                        'noSegelBc'           => '-',
                        'bruto'               => 0,
                        'raw'                 => $plp
                    ];
                }
            }
        }

        jsonResponse([
            'success'          => true,
            'referenceNumber'  => $refNumber,
            'header'           => $header,
            'log'              => $logData ? [
                'id'         => $logData['id'],
                'endpoint'   => $logData['endpoint'],
                'http_code'  => $logData['http_code'],
                'status'     => $logData['status'],
                'message'    => $logData['message'],
                'total_rows' => $logData['total_rows'],
                'created_at' => $logData['created_at'],
                'response'   => $parsedResponse
            ] : null,
            'package_count'    => count($packages),
            'packages'         => $packages,
            'raw_payload'      => $parsedRequest
        ]);

    } catch (Exception $e) {
        jsonResponse([
            'success' => false,
            'message' => 'Gagal mengambil detail referensi kemasan: ' . $e->getMessage()
        ], 500);
    }
}

if ($action === 'cek_terkirim') {
    $tglAwalRaw = input('tanggalAwal');
    $tglAkhirRaw = input('tanggalAkhir');

    $tglAwal = normalizeDateDmy($tglAwalRaw);
    $tglAkhir = normalizeDateDmy($tglAkhirRaw);

    $category = strtolower(trim($_REQUEST['category'] ?? $_GET['category'] ?? $_POST['category'] ?? $_REQUEST['service'] ?? $_GET['service'] ?? $_POST['service'] ?? ''));

    try {
        $client = new CeisaClient();
        $apiRes = $client->get('cek-data-terkirim', [
            'tanggalAwal' => $tglAwal,
            'tanggalAkhir' => $tglAkhir
        ]);

        $isSuccess = $apiRes['success'] ?? false;
        $httpCode = $apiRes['code'] ?? 200;
        $responData = $apiRes['data']['respon'] ?? [];

        $tableRows = [];
        $totalJumlah = 0;
        $serviceList = [];

        if (is_array($responData)) {
            foreach ($responData as $serviceKey => $serviceVal) {
                // Filter berdasarkan kategori jika ditentukan
                if ($category === 'container' || $category === 'coarri-codeco-container' || $category === 'cococont') {
                    if ($serviceKey !== 'coarri-codeco-container') continue;
                } elseif ($category === 'kemasan' || $category === 'coarri-codeco-kemasan' || $category === 'cocokms') {
                    if ($serviceKey !== 'coarri-codeco-kemasan') continue;
                }

                $serviceList[] = $serviceKey;
                $jumlah = $serviceVal['jumlah'] ?? 0;
                $totalJumlah += $jumlah;

                $refs = $serviceVal['referenceNumber'] ?? [];
                if (is_array($refs)) {
                    foreach ($refs as $refNo) {
                        $tableRows[] = [
                            'referenceNumber' => $refNo,
                            'service' => $serviceKey,
                            'serviceLabel' => ucwords(str_replace(['-', '_'], ' ', $serviceKey)),
                            'status' => 'TERKIRIM DI CEISA 4.0',
                            'tglAwal' => $tglAwal,
                            'tglAkhir' => $tglAkhir
                        ];
                    }
                }
            }
        }

        // Cek jika server merespons "Data not found"
        $rawDetail = $apiRes['raw']['detail'] ?? ($apiRes['message'] ?? '');
        $isNotFound = stripos($rawDetail, 'not found') !== false || stripos($rawDetail, 'tidak ada') !== false;

        jsonResponse([
            'success' => $isSuccess,
            'code' => $httpCode,
            'message' => $isNotFound ? 'Tidak ada data pengiriman pada rentang tanggal tersebut.' : ($apiRes['message'] ?? 'Berhasil mengambil data terkirim'),
            'total_jumlah' => $totalJumlah,
            'service_count' => count($serviceList),
            'services' => $serviceList,
            'tglAwal' => $tglAwal,
            'tglAkhir' => $tglAkhir,
            'count' => count($tableRows),
            'rows' => $tableRows,
            'raw' => $apiRes
        ]);

    } catch (Exception $e) {
        jsonResponse([
            'success' => false,
            'code' => 500,
            'message' => 'Kesalahan saat menghubungi API Gateway: ' . $e->getMessage(),
            'rows' => [],
            'count' => 0
        ], 500);
    }
}

jsonResponse(['success' => false, 'message' => 'Action tidak valid'], 400);
