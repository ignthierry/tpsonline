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

/**
 * Helper deteksi Alur Pergerakan (In/Out) dan Kategori (kemasan, container_lcl, container_pjt)
 */
function detectRefTypeAndSubType($refNo, $serviceKey, $pdo) {
    $refNo = trim((string)$refNo);
    $detectedType = '';
    $detectedSubType = '';

    // Pola penomoran resmi dari cocokms.php:
    // PSU0 + 6 digit tanggal (yymmdd) + 1 digit prefix (1-6) + 6 digit waktu (His)
    // 1: Kemasan In | 2: Kemasan Out | 3: Cont LCL In | 4: Cont LCL Out | 5: Cont PJT In | 6: Cont PJT Out
    if (preg_match('/^PSU0\d{6}([1-6])\d{6}/i', $refNo, $m)) {
        $p = $m[1];
        if ($p === '1') { $detectedType = 'In';  $detectedSubType = 'kemasan'; }
        elseif ($p === '2') { $detectedType = 'Out'; $detectedSubType = 'kemasan'; }
        elseif ($p === '3') { $detectedType = 'In';  $detectedSubType = 'container_lcl'; }
        elseif ($p === '4') { $detectedType = 'Out'; $detectedSubType = 'container_lcl'; }
        elseif ($p === '5') { $detectedType = 'In';  $detectedSubType = 'container_pjt'; }
        elseif ($p === '6') { $detectedType = 'Out'; $detectedSubType = 'container_pjt'; }
    }

    // Jika pola tidak terbaca, periksa database tpsonline
    if (empty($detectedType) || empty($detectedSubType)) {
        if ($pdo) {
            // Cek di ceisa_cocokms
            $stmtK = $pdo->prepare("SELECT kode_dokumen FROM ceisa_cocokms WHERE ref_number = ? LIMIT 1");
            $stmtK->execute([$refNo]);
            $kRow = $stmtK->fetch(PDO::FETCH_ASSOC);
            if ($kRow) {
                $detectedSubType = 'kemasan';
                $detectedType = ($kRow['kode_dokumen'] === '5') ? 'In' : 'Out';
            } else {
                // Cek di ceisa_cococont
                $stmtC = $pdo->prepare("SELECT kode_dokumen, jenis_kontainer FROM ceisa_cococont WHERE ref_number = ? LIMIT 1");
                $stmtC->execute([$refNo]);
                $cRow = $stmtC->fetch(PDO::FETCH_ASSOC);
                if ($cRow) {
                    $detectedType = ($cRow['kode_dokumen'] === '5') ? 'In' : 'Out';
                    $jns = strtoupper((string)($cRow['jenis_kontainer'] ?? ''));
                    if (stripos($jns, 'PJT') !== false || stripos($refNo, 'PJT') !== false) {
                        $detectedSubType = 'container_pjt';
                    } else {
                        $detectedSubType = 'container_lcl';
                    }
                }
            }
        }
    }

    // Fallback berdasarkan service gateway
    if (empty($detectedSubType)) {
        if ($serviceKey === 'coarri-codeco-kemasan') {
            $detectedSubType = 'kemasan';
        } elseif ($serviceKey === 'coarri-codeco-container') {
            if (stripos($refNo, 'PJT') !== false) {
                $detectedSubType = 'container_pjt';
            } else {
                $detectedSubType = 'container_lcl';
            }
        }
    }
    if (empty($detectedType)) {
        $detectedType = 'In';
    }

    return [
        'type' => $detectedType,
        'subType' => $detectedSubType
    ];
}

if ($action === 'detail_kms_ref' || $action === 'detail_ref') {
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
        $containers = [];
        $dataType = 'kemasan';

        if (!empty($cocoKmsRows)) {
            $dataType = 'kemasan';
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
            // Cek apakah ref ini merupakan kontainer (ceisa_cococont)
            $stmtCocoCont = $pdo_tpsonline->prepare("
                SELECT * FROM ceisa_cococont 
                WHERE ref_number = ?
                ORDER BY id ASC
            ");
            $stmtCocoCont->execute([$refNumber]);
            $cocoContRows = $stmtCocoCont->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($cocoContRows)) {
                $dataType = 'container';
                foreach ($cocoContRows as $c) {
                    $rawDetail = !empty($c['raw_data']) ? json_decode($c['raw_data'], true) : [];
                    $containers[] = [
                        'noCont'              => $c['no_kontainer'],
                        'ukuran'              => (!empty($c['ukuran']) ? $c['ukuran'] . ' ft' : '20 ft'),
                        'jenisCont'           => $c['jenis_kontainer'] ?? 'LCL',
                        'jenisMuat'           => 'Isi (LCL)',
                        'noSppb'              => $c['no_dok_inout'] ?: '-',
                        'statusSegel'         => $c['no_segel'] ? 'Tersegel' : '-',
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
                // Fallback ke ceisa_sppb_kemasan / ceisa_plp_kemasan
                $stmtSppb = $pdo_tpsonline->prepare("
                    SELECT id, car, no_sppb, jml_kemasan, jns_kemasan, kd_jns_kemasan, raw_data, created_at 
                    FROM ceisa_sppb_kemasan 
                    WHERE car = ?
                    ORDER BY id ASC
                ");
                $stmtSppb->execute([$refNumber]);
                $sppbRows = $stmtSppb->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($sppbRows)) {
                    $dataType = 'kemasan';
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
                } elseif (!empty($parsedRequest['detil']) && is_array($parsedRequest['detil'])) {
                    // Fallback langsung dari payload log kemasan
                    $dataType = 'kemasan';
                    foreach ($parsedRequest['detil'] as $d) {
                        $packages[] = [
                            'jenisKemasan'        => $d['kodeKemasan'] ?? ($d['jenisKemasan'] ?? 'PK'),
                            'jumlahKemasan'       => (float)($d['jumlahKemasan'] ?? 1),
                            'noSppb'              => $d['nomorDokumenInOut'] ?? '-',
                            'noBlAwb'             => $d['nomorBlAwb'] ?? ($d['noBlAwb'] ?? '-'),
                            'tanggalBlAwb'        => $d['tanggalBlAwb'] ?? '-',
                            'nomorPosBc11'        => $d['nomorPosBc11'] ?? '-',
                            'consignee'           => $d['consignee'] ?? '-',
                            'kontainerAsal'       => $d['kontainerAsal'] ?? '-',
                            'nomorPolisi'         => $d['nomorPolisi'] ?? '-',
                            'waktuInOut'          => $d['waktuInOut'] ?? '-',
                            'noSegelBc'           => $d['nomorSegelBc'] ?? '-',
                            'bruto'               => (float)($d['bruto'] ?? 0),
                            'raw'                 => $d
                        ];
                    }
                } elseif (!empty($parsedRequest['kontainer']) && is_array($parsedRequest['kontainer'])) {
                    // Fallback langsung dari payload log kontainer
                    $dataType = 'container';
                    foreach ($parsedRequest['kontainer'] as $c) {
                        $containers[] = [
                            'noCont'              => $c['nomorKontainer'] ?? '-',
                            'ukuran'              => (!empty($c['ukuranKontainer']) ? $c['ukuranKontainer'] . ' ft' : '20 ft'),
                            'jenisCont'           => $c['jenisKontainer'] ?? 'LCL',
                            'jenisMuat'           => 'Isi (LCL)',
                            'noSppb'              => $c['nomorDokumenInOut'] ?? '-',
                            'statusSegel'         => !empty($c['nomorSegelBc']) ? 'Tersegel' : '-',
                            'noSegel'             => $c['nomorSegelBc'] ?? ($c['nomorSegel'] ?? '-'),
                            'nomorPosBc11'        => $c['nomorPosBc11'] ?? '-',
                            'noBlAwb'             => $c['nomorBlAwb'] ?? ($c['noBlAwb'] ?? '-'),
                            'tanggalBlAwb'        => $c['tanggalBlAwb'] ?? '-',
                            'consignee'           => $c['consignee'] ?? '-',
                            'nomorDokumenInOut'   => $c['nomorDokumenInOut'] ?? '-',
                            'tanggalDokumenInOut' => $c['tanggalDokumenInOut'] ?? '-',
                            'waktuInOut'          => $c['waktuInOut'] ?? '-',
                            'nomorPolisi'         => $c['nomorPolisi'] ?? '-',
                            'bruto'               => $c['bruto'] ?? 0,
                            'raw'                 => $c
                        ];
                    }
                } else {
                    $refMeta = detectRefTypeAndSubType($refNumber, '', $pdo_tpsonline);
                    $dataType = ($refMeta['subType'] === 'kemasan') ? 'kemasan' : 'container';
                }
            }
        }

        jsonResponse([
            'success'          => true,
            'dataType'         => $dataType,
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

if ($action === 'cek_terkirim') {
    $tglAwalRaw = input('tanggalAwal');
    $tglAkhirRaw = input('tanggalAkhir');

    $tglAwal = normalizeDateDmy($tglAwalRaw);
    $tglAkhir = normalizeDateDmy($tglAkhirRaw);

    $type = strtoupper(trim((string)input('type', ''))); // 'IN', 'OUT', atau ''
    $subType = strtolower(trim((string)input('subType', ''))); // 'kemasan', 'container_lcl', 'container_pjt', 'container', atau ''
    $category = strtolower(trim($_REQUEST['category'] ?? $_GET['category'] ?? $_POST['category'] ?? $_REQUEST['service'] ?? $_GET['service'] ?? $_POST['service'] ?? ''));

    // Normalisasi subType dari parameter category jika subType tidak disertakan
    if (empty($subType)) {
        if ($category === 'kemasan' || $category === 'coarri-codeco-kemasan' || $category === 'cocokms') {
            $subType = 'kemasan';
        } elseif ($category === 'container_lcl') {
            $subType = 'container_lcl';
        } elseif ($category === 'container_pjt') {
            $subType = 'container_pjt';
        } elseif ($category === 'container' || $category === 'coarri-codeco-container' || $category === 'cococont') {
            $subType = 'container';
        }
    }

    try {
        global $pdo_tpsonline;
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
                // Filter awal berdasarkan endpoint service bila subType spesifik
                if ($subType === 'kemasan' && $serviceKey !== 'coarri-codeco-kemasan') {
                    continue;
                }
                if (in_array($subType, ['container_lcl', 'container_pjt', 'container']) && $serviceKey !== 'coarri-codeco-container') {
                    continue;
                }

                $refs = $serviceVal['referenceNumber'] ?? [];
                if (!is_array($refs)) continue;

                foreach ($refs as $refNo) {
                    $refNo = trim((string)$refNo);
                    if (empty($refNo)) continue;

                    $meta = detectRefTypeAndSubType($refNo, $serviceKey, $pdo_tpsonline);

                    // Filter Kategori / SubType jika ditentukan
                    if ($subType === 'kemasan' && $meta['subType'] !== 'kemasan') {
                        continue;
                    }
                    if ($subType === 'container_lcl' && $meta['subType'] !== 'container_lcl') {
                        continue;
                    }
                    if ($subType === 'container_pjt' && $meta['subType'] !== 'container_pjt') {
                        continue;
                    }

                    // Filter Alur Pergerakan (In / Out) jika ditentukan
                    if (!empty($type)) {
                        if (strcasecmp($type, $meta['type']) !== 0) {
                            continue;
                        }
                    }

                    $subTypeLabel = ($meta['subType'] === 'kemasan') ? 'Kemasan' : (($meta['subType'] === 'container_pjt') ? 'Container PJT' : 'Container LCL');
                    $typeLabel = ($meta['type'] === 'In') ? 'Gate-In (Pemasukan)' : 'Gate-Out (Pengeluaran)';

                    $tableRows[] = [
                        'referenceNumber' => $refNo,
                        'service'         => $serviceKey,
                        'serviceLabel'    => ucwords(str_replace(['-', '_'], ' ', $serviceKey)),
                        'type'            => $meta['type'],
                        'typeLabel'       => $typeLabel,
                        'subType'         => $meta['subType'],
                        'subTypeLabel'    => $subTypeLabel,
                        'status'          => 'TERKIRIM DI CEISA 4.0',
                        'tglAwal'         => $tglAwal,
                        'tglAkhir'        => $tglAkhir
                    ];
                    $totalJumlah++;
                    if (!in_array($serviceKey, $serviceList)) {
                        $serviceList[] = $serviceKey;
                    }
                }
            }
        }

        // Cek jika server merespons "Data not found"
        $rawDetail = $apiRes['raw']['detail'] ?? ($apiRes['message'] ?? '');
        $isNotFound = stripos($rawDetail, 'not found') !== false || stripos($rawDetail, 'tidak ada') !== false;

        jsonResponse([
            'success'      => $isSuccess,
            'code'         => $httpCode,
            'message'      => $isNotFound ? 'Tidak ada data pengiriman pada rentang tanggal tersebut.' : ($apiRes['message'] ?? 'Berhasil mengambil data terkirim'),
            'total_jumlah' => $totalJumlah,
            'service_count'=> count($serviceList),
            'services'     => $serviceList,
            'type'         => $type ?: 'ALL',
            'subType'      => $subType ?: 'ALL',
            'tglAwal'      => $tglAwal,
            'tglAkhir'     => $tglAkhir,
            'count'        => count($tableRows),
            'rows'         => $tableRows,
            'raw'          => $apiRes
        ]);

    } catch (Exception $e) {
        jsonResponse([
            'success' => false,
            'code'    => 500,
            'message' => 'Kesalahan saat menghubungi API Gateway: ' . $e->getMessage(),
            'rows'    => [],
            'count'   => 0
        ], 500);
    }
}

jsonResponse(['success' => false, 'message' => 'Action tidak valid'], 400);
