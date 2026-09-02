<?php
/**
 * API Backend: Laporan YOR (Yard Occupancy Rate) CEISA 4.0
 * Endpoint Target: POST /kirim-laporan-yor
 * Deskripsi: Mengirimkan Informasi Laporan YOR Tempat Penimbunan Sementara (TPS)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/CeisaClient.php';
require_once __DIR__ . '/../includes/db.php';

requireAuth();
session_write_close();

$action = input('action', 'send');

function jsonResp($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================================================================
// ACTION 1: KIRIM LAPORAN YOR KE GATEWAY CEISA 4.0 (/kirim-laporan-yor)
// =========================================================================
if ($action === 'send') {
    $rawInput = file_get_contents('php://input');
    $postData = json_decode($rawInput, true);
    if (!$postData && !empty($_POST)) {
        $postData = $_POST;
    }

    $payload = $postData['payload'] ?? $postData ?? null;

    if (empty($payload) || !is_array($payload)) {
        jsonResp(['success' => false, 'message' => 'Payload laporan YOR kosong atau format JSON tidak valid'], 400);
    }

    // Validasi field wajib root level (ekspor opsional dari UI, akan otomatis diset 0 jika kosong)
    $requiredRoot = [
        'kodeTps'        => 'Kode TPS',
        'kodeGudang'     => 'Kode Gudang',
        'refNumber'      => 'Nomor Referensi (refNumber)',
        'tanggalLaporan' => 'Tanggal Laporan (dd-MM-yyyy)',
        'impor'          => 'Data YOR Impor'
    ];

    $missing = [];
    foreach ($requiredRoot as $f => $label) {
        if (!isset($payload[$f]) || (is_string($payload[$f]) && trim($payload[$f]) === '')) {
            $missing[] = $label;
        }
    }

    if (!empty($missing)) {
        jsonResp([
            'success' => false,
            'message' => 'Parameter wajib belum lengkap: ' . implode(', ', $missing)
        ], 422);
    }

    // Normalisasi tanggalLaporan (harus dd-MM-yyyy)
    $tglLaporan = trim((string)$payload['tanggalLaporan']);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tglLaporan, $m)) {
        $tglLaporan = "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $tglLaporan)) {
        jsonResp(['success' => false, 'message' => 'Format tanggalLaporan harus "dd-MM-yyyy" (contoh: ' . date('d-m-Y') . ')'], 422);
    }

    // Bangun payload bersih & presisi tipe data
    // Karena TPS Primamas hanya melayani Impor, objek ekspor diset 0 sesuai instruksi
    $imporData = is_array($payload['impor'] ?? null) ? $payload['impor'] : [];
    $eksporData = is_array($payload['ekspor'] ?? null) ? $payload['ekspor'] : [];

    $kodeGudang = strtoupper(trim((string)$payload['kodeGudang']));
    $isCPSU = ($kodeGudang === 'CPSU' || empty($kodeGudang));

    $cleanPayload = [
        'kodeTps'        => strtoupper(trim((string)$payload['kodeTps'])),
        'kodeGudang'     => $kodeGudang,
        'refNumber'      => trim((string)$payload['refNumber']),
        'tanggalLaporan' => $tglLaporan,
        'impor'          => [
            'jumlahKontainer20f' => (int)($imporData['jumlahKontainer20f'] ?? 0),
            'jumlahKontainer40f' => (int)($imporData['jumlahKontainer40f'] ?? 0),
            'jumlahKontainer45f' => (int)($imporData['jumlahKontainer45f'] ?? 0),
            'totalKontainer'     => (int)($imporData['totalKontainer'] ?? ((int)($imporData['jumlahKontainer20f'] ?? 0) + (int)($imporData['jumlahKontainer40f'] ?? 0) + (int)($imporData['jumlahKontainer45f'] ?? 0))),
            'totalKemasan'       => $isCPSU ? 0 : (float)($imporData['totalKemasan'] ?? 0),
            'kapasitasLapangan'  => (float)($imporData['kapasitasLapangan'] ?? 0),
            'kapasitasGudang'    => $isCPSU ? 0 : (float)($imporData['kapasitasGudang'] ?? 0),
            'yor'                => (float)($imporData['yor'] ?? 0) // Sesuai RPLP_YOR: float presisi penuh, tidak dibulatkan
        ],
        'ekspor'         => [
            'jumlahKontainer20f' => (int)($eksporData['jumlahKontainer20f'] ?? 0),
            'jumlahKontainer40f' => (int)($eksporData['jumlahKontainer40f'] ?? 0),
            'jumlahKontainer45f' => (int)($eksporData['jumlahKontainer45f'] ?? 0),
            'totalKontainer'     => (int)($eksporData['totalKontainer'] ?? 0),
            'totalKemasan'       => (float)($eksporData['totalKemasan'] ?? 0),
            'kapasitasLapangan'  => (float)($eksporData['kapasitasLapangan'] ?? 0),
            'kapasitasGudang'    => (float)($eksporData['kapasitasGudang'] ?? 0),
            'yor'                => (float)($eksporData['yor'] ?? 0)
        ]
    ];

    try {
        $client = new CeisaClient();
        $endpoint = 'kirim-laporan-yor';
        $res = $client->post($endpoint, $cleanPayload);

        $isOk = ($res['code'] >= 200 && $res['code'] < 300);
        $rawCeisa = $res['raw'] ?? $res;

        // 1. Audit Log ke ceisa_api_logs
        try {
            global $pdo_tpsonline;
            if ($pdo_tpsonline) {
                $stmtLog = $pdo_tpsonline->prepare("
                    INSERT INTO ceisa_api_logs 
                    (endpoint, request_params, http_code, status, message, total_rows, raw_response, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmtLog->execute([
                    'kirim-laporan-yor',
                    json_encode($cleanPayload),
                    $res['code'] ?? 0,
                    $isOk ? 'SUCCESS' : 'FAILED',
                    $res['message'] ?? ($isOk ? 'Laporan YOR berhasil direkam' : 'Laporan YOR ditolak'),
                    1,
                    json_encode($rawCeisa)
                ]);

                // 2. Simpan ke ceisa_laporan_yor di database tpsonline
                $stmtYor = $pdo_tpsonline->prepare("
                    INSERT INTO ceisa_laporan_yor
                    (ref_number, kode_tps, kode_gudang, tanggal_laporan,
                     impor_yor, impor_total_kontainer, impor_total_kemasan,
                     ekspor_yor, ekspor_total_kontainer, ekspor_total_kemasan,
                     status_kirim, http_code, message, raw_payload, raw_response, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmtYor->execute([
                    $cleanPayload['refNumber'],
                    $cleanPayload['kodeTps'],
                    $cleanPayload['kodeGudang'],
                    $cleanPayload['tanggalLaporan'],
                    $cleanPayload['impor']['yor'],
                    $cleanPayload['impor']['totalKontainer'],
                    $cleanPayload['impor']['totalKemasan'],
                    $cleanPayload['ekspor']['yor'],
                    $cleanPayload['ekspor']['totalKontainer'],
                    $cleanPayload['ekspor']['totalKemasan'],
                    $isOk ? 'SUCCESS' : 'FAILED',
                    $res['code'] ?? 0,
                    $res['message'] ?? '',
                    json_encode($cleanPayload),
                    json_encode($rawCeisa)
                ]);
            }

            // 3. Simpan ke tps_laporan_yor_log di database tpp_primamas jika tersedia
            global $pdo_tpp;
            if ($pdo_tpp) {
                $stmtTpp = $pdo_tpp->prepare("
                    INSERT INTO tps_laporan_yor_log
                    (refNumber, kodeTps, kodeGudang, tanggalLaporan, payload, receivedAt)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmtTpp->execute([
                    $cleanPayload['refNumber'],
                    $cleanPayload['kodeTps'],
                    $cleanPayload['kodeGudang'],
                    $cleanPayload['tanggalLaporan'],
                    json_encode([
                        'payload'  => $cleanPayload,
                        'response' => $rawCeisa
                    ])
                ]);
            }
        } catch (Exception $dbErr) {
            error_log("Database logging error in kirim-laporan-yor: " . $dbErr->getMessage());
        }

        $responseOutput = [
            'success' => $isOk,
            'code'    => $res['code'] ?? ($isOk ? 200 : 400),
            'message' => $res['message'] ?? ($isOk ? 'Informasi Laporan YOR berhasil direkam di CEISA 4.0!' : 'Pengiriman Laporan YOR ditolak oleh CEISA 4.0'),
            'data'    => $res['data'] ?? null,
            'payload' => $cleanPayload,
            'raw'     => $rawCeisa
        ];

        if (is_array($rawCeisa)) {
            if (isset($rawCeisa['result'])) $responseOutput['result'] = $rawCeisa['result'];
            if (isset($rawCeisa['detail'])) $responseOutput['detail'] = $rawCeisa['detail'];
            if (isset($rawCeisa['path'])) $responseOutput['path'] = $rawCeisa['path'];
            if (isset($rawCeisa['date'])) $responseOutput['date'] = $rawCeisa['date'];
            if (isset($rawCeisa['version'])) $responseOutput['version'] = $rawCeisa['version'];
        }

        jsonResp($responseOutput, $isOk ? 200 : ($res['code'] ?: 400));

    } catch (Exception $e) {
        error_log("Error send kirim-laporan-yor: " . $e->getMessage());
        jsonResp([
            'success' => false,
            'code'    => 500,
            'message' => 'Kesalahan koneksi ke server gateway CEISA: ' . $e->getMessage()
        ], 500);
    }
}

// =========================================================================
// ACTION 2: TARIK STOK RIIL KONTAINER DARI DEPO & MASTER CONSTANTA
// =========================================================================
if ($action === 'fetch_stock') {
    $tglLaporan = input('tanggalLaporan', date('d-m-Y'));
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tglLaporan, $m)) {
        $tglLaporan = "{$m[3]}-{$m[2]}-{$m[1]}";
    }

    $kodeGudang = strtoupper(trim((string)input('kodeGudang', 'CPSU')));
    $isCPSU = ($kodeGudang === 'CPSU' || empty($kodeGudang));

    $resultStock = [
        'impor' => [
            'c20'               => 0,
            'c40'               => 0,
            'c45'               => 0,
            'total'             => 0,
            'teus'              => 0,
            'totalKemasan'      => 0,
            'kapasitasLapangan' => 1090, // Default Master_Constanta: YOR
            'kapasitasGudang'   => $isCPSU ? 0 : 3750, // Untuk CPSU = 0 karena Container Yard
            'yor'               => 0.0
        ],
        'ekspor' => [
            'c20'               => 0,
            'c40'               => 0,
            'c45'               => 0,
            'total'             => 0,
            'teus'              => 0,
            'totalKemasan'      => 0,
            'kapasitasLapangan' => 0,
            'kapasitasGudang'   => 0,
            'yor'               => 0.0
        ],
        'constanta' => [
            'YOR'     => 1090,
            'SOR_lcl' => 3750
        ]
    ];

    try {
        global $pdo_tpp;
        if ($pdo_tpp) {
            // 1. Ambil Konfigurasi dari Master_Constanta (tppconstanta)
            $stmtCons = $pdo_tpp->query("SELECT YOR, SOR_lcl, YOR_RTPP, y_20, y_40, y_45 FROM tppconstanta LIMIT 1");
            if ($stmtCons) {
                $consRow = $stmtCons->fetch(PDO::FETCH_ASSOC);
                if ($consRow) {
                    $kapLap = (float)($consRow['YOR'] ?? 1090);
                    $kapGud = (float)($consRow['SOR_lcl'] ?? 3750);
                    $resultStock['impor']['kapasitasLapangan'] = $kapLap > 0 ? $kapLap : 1090;
                    $resultStock['impor']['kapasitasGudang']   = $isCPSU ? 0 : ($kapGud > 0 ? $kapGud : 3750);
                    $resultStock['constanta'] = $consRow;
                }
            }

            // 2. Hitung Stok Kontainer Riil Depo sesuai logika RPLP_YOR & Master_Constanta
            $sqlStock = "
                SELECT
                    SUM(IF(tppcontplp.size = 20, 1, 0)) AS jml20,
                    SUM(IF(tppcontplp.size = 40, 1, 0)) AS jml40,
                    SUM(IF(tppcontplp.size = 45, 1, 0)) AS jml45
                FROM tppcontplp
                JOIN tppconsignee ON tppconsignee.Id_Cons = tppcontplp.idCons_FK
                JOIN tppmanifestplp ON tppmanifestplp.idPLP = tppcontplp.idPLP_FK
                WHERE tppcontplp.flag = 1
                  AND tppcontplp.SBCF = 0
                  AND tppcontplp.keterangan IS NULL
                  AND tppcontplp.BCF IS NULL
                  AND tppcontplp.tglInDepo IS NOT NULL 
                  AND tppcontplp.tglInDepo > '1970-01-01'
                  AND DATE(tppcontplp.tglInDepo) <= STR_TO_DATE(:tglAkhir1, '%d-%m-%Y')
                  AND NOT EXISTS (
                    SELECT 1 
                    FROM tppsuratjalan 
                    WHERE tppsuratjalan.idManifest = tppcontplp.idCont
                      AND tppsuratjalan.typeManifest = 'PLP'
                      AND tppsuratjalan.tglSuratJalan <= STR_TO_DATE(:tglAkhir2, '%d-%m-%Y')
                  )
            ";
            $stmtStock = $pdo_tpp->prepare($sqlStock);
            $stmtStock->execute([':tglAkhir1' => $tglLaporan, ':tglAkhir2' => $tglLaporan]);
            $stockRow = $stmtStock->fetch(PDO::FETCH_ASSOC);

            if ($stockRow) {
                $c20 = (int)($stockRow['jml20'] ?? 0);
                $c40 = (int)($stockRow['jml40'] ?? 0);
                $c45 = (int)($stockRow['jml45'] ?? 0);
                $resultStock['impor']['c20'] = $c20;
                $resultStock['impor']['c40'] = $c40;
                $resultStock['impor']['c45'] = $c45;
                $resultStock['impor']['total'] = $c20 + $c40 + $c45;

                // Formula TEUs baku dari RPLP_YOR: (jml20 * 1) + (jml40 * 2) + (jml45 * 2)
                $teus = ($c20 * 1) + ($c40 * 2) + ($c45 * 2);
                $resultStock['impor']['teus'] = $teus;

                $kapLap = $resultStock['impor']['kapasitasLapangan'];
                if ($kapLap > 0) {
                    // Sesuai RPLP_YOR: tidak dibulatkan, floating point murni
                    $resultStock['impor']['yor'] = (float)(($teus / $kapLap) * 100);
                }
            }

            // 3. Hitung Total Kemasan (untuk CPSU otomatis 0 karena Container Yard)
            if ($isCPSU) {
                $resultStock['impor']['totalKemasan'] = 0;
                $resultStock['impor']['kapasitasGudang'] = 0;
            } else {
                $sqlLcl = '
                    SELECT COUNT(*) as totalKemasan, SUM(volume) as totalVolume 
                    FROM tppmanifestlcl m 
                    WHERE m.flag = 1 
                    AND NOT EXISTS (
                        SELECT 1 FROM cont_temp_out temp 
                        WHERE (temp.type = "LCL" OR temp.type IS NULL OR temp.type = "")
                          AND ((temp.idmanifest IS NOT NULL AND temp.idmanifest = m.idManifestLCL) OR temp.noCont = m.noCont)
                    )
                    AND m.idManifestLCL NOT IN (
                        SELECT idManifest FROM tppsuratjalan WHERE typeManifest = "LCL"
                    )
                    AND m.lokasi LIKE "%GD%"
                ';
                $stmtLcl = $pdo_tpp->query($sqlLcl);
                if ($stmtLcl) {
                    $lclRow = $stmtLcl->fetch(PDO::FETCH_ASSOC);
                    if ($lclRow) {
                        $resultStock['impor']['totalKemasan'] = (int)($lclRow['totalKemasan'] ?? 0);
                        $resultStock['impor']['totalVolume']  = (float)($lclRow['totalVolume'] ?? 0);
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error fetch_stock YOR: " . $e->getMessage());
    }

    jsonResp([
        'success' => true,
        'stock'   => $resultStock
    ]);
}

// =========================================================================
// ACTION 3: RIWAYAT PENGIRIMAN LAPORAN YOR
// =========================================================================
if ($action === 'history' || $action === 'report') {
    global $pdo_tpsonline;
    if (!$pdo_tpsonline) {
        jsonResp(['success' => true, 'rows' => [], 'summary' => ['total' => 0, 'avg_impor' => 0, 'avg_ekspor' => 0, 'today' => 0]]);
    }

    $startDate = trim((string)input('start_date', input('tanggalAwal', '')));
    $endDate   = trim((string)input('end_date', input('tanggalAkhir', '')));
    $q         = trim((string)input('q', input('search', '')));

    try {
        $where = [];
        $params = [];

        if (!empty($startDate)) {
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $startDate, $m)) {
                $startDate = "{$m[3]}-{$m[2]}-{$m[1]}";
            }
            $where[] = "DATE(created_at) >= :sd";
            $params[':sd'] = $startDate;
        }

        if (!empty($endDate)) {
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $endDate, $m)) {
                $endDate = "{$m[3]}-{$m[2]}-{$m[1]}";
            }
            $where[] = "DATE(created_at) <= :ed";
            $params[':ed'] = $endDate;
        }

        if (!empty($q)) {
            $where[] = "(ref_number LIKE :q OR tanggal_laporan LIKE :q2 OR message LIKE :q3)";
            $params[':q'] = "%$q%";
            $params[':q2'] = "%$q%";
            $params[':q3'] = "%$q%";
        }

        $whereSql = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

        $sql = "
            SELECT 
                id, ref_number, kode_tps, kode_gudang, tanggal_laporan,
                impor_yor, impor_total_kontainer, impor_total_kemasan,
                ekspor_yor, ekspor_total_kontainer, ekspor_total_kemasan,
                status_kirim, http_code, message, raw_payload, raw_response,
                DATE_FORMAT(created_at, '%d-%m-%Y %H:%i:%s') AS created_at
            FROM ceisa_laporan_yor
            {$whereSql}
            ORDER BY id DESC
            LIMIT 500
        ";

        $stmt = $pdo_tpsonline->prepare($sql);
        $stmt->execute($params);
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rows = [];
        $summary = [
            'total'          => count($rawRows),
            'avg_impor'      => 0,
            'avg_ekspor'     => 0,
            'total_kontainer'=> 0,
            'today'          => 0
        ];

        $todayStr = date('d-m-Y');
        $sumYorImpor = 0;
        $sumYorEkspor = 0;

        foreach ($rawRows as $r) {
            $payload = !empty($r['raw_payload']) ? json_decode($r['raw_payload'], true) : [];
            $response = !empty($r['raw_response']) ? json_decode($r['raw_response'], true) : [];

            $sumYorImpor += (float)$r['impor_yor'];
            $sumYorEkspor += (float)$r['ekspor_yor'];
            $summary['total_kontainer'] += (int)$r['impor_total_kontainer'] + (int)$r['ekspor_total_kontainer'];

            if (strpos($r['created_at'], $todayStr) !== false || $r['tanggal_laporan'] === $todayStr) {
                $summary['today']++;
            }

            $rows[] = [
                'id'              => $r['id'],
                'ref_number'      => $r['ref_number'],
                'kode_tps'        => $r['kode_tps'],
                'kode_gudang'     => $r['kode_gudang'],
                'tanggal_laporan' => $r['tanggal_laporan'],
                'impor_yor'       => (float)$r['impor_yor'],
                'impor_kontainer' => (int)$r['impor_total_kontainer'],
                'ekspor_yor'      => (float)$r['ekspor_yor'],
                'ekspor_kontainer'=> (int)$r['ekspor_total_kontainer'],
                'status_kirim'    => $r['status_kirim'],
                'http_code'       => (int)$r['http_code'],
                'message'         => $r['message'],
                'created_at'      => $r['created_at'],
                'raw_payload'     => $payload,
                'raw_response'    => $response
            ];
        }

        if ($summary['total'] > 0) {
            $summary['avg_impor'] = round($sumYorImpor / $summary['total'], 2);
            $summary['avg_ekspor'] = round($sumYorEkspor / $summary['total'], 2);
        }

        jsonResp([
            'success' => true,
            'rows'    => $rows,
            'summary' => $summary
        ]);
    } catch (Exception $e) {
        jsonResp(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// =========================================================================
// ACTION 4: DETAIL SATU LAPORAN YOR
// =========================================================================
if ($action === 'detail') {
    $id = (int)input('id', 0);
    if ($id <= 0) {
        jsonResp(['success' => false, 'message' => 'ID laporan YOR tidak valid'], 400);
    }

    try {
        global $pdo_tpsonline;
        $stmt = $pdo_tpsonline->prepare("SELECT * FROM ceisa_laporan_yor WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            jsonResp(['success' => false, 'message' => 'Data laporan YOR tidak ditemukan'], 404);
        }

        $payload = !empty($row['raw_payload']) ? json_decode($row['raw_payload'], true) : [];
        $response = !empty($row['raw_response']) ? json_decode($row['raw_response'], true) : [];

        jsonResp([
            'success'  => true,
            'data'     => $row,
            'payload'  => $payload,
            'response' => $response
        ]);
    } catch (Exception $e) {
        jsonResp(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
