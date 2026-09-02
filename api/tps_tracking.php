<?php
/**
 * API Backend: TPS Tracking CEISA 4.0
 * Endpoint Target: POST /kirim-tps-tracking
 * Deskripsi: Merekam data tracking pergerakan kontainer di TPS (Gate In, Gate Out, Stacking, Truck In, Pickup, dll.)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/CeisaClient.php';
require_once __DIR__ . '/../includes/db.php';

requireAuth();
session_write_close(); // Segera rilis session lock agar pencarian AJAX tidak memblokir browser

$action = input('action', 'send');

function jsonResp($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================================================================
// ACTION 1: KIRIM DATA TRACKING KE GATEWAY CEISA 4.0 (/kirim-tps-tracking)
// =========================================================================
if ($action === 'send') {
    $rawInput = file_get_contents('php://input');
    $postData = json_decode($rawInput, true);
    if (!$postData && !empty($_POST)) {
        $postData = $_POST;
    }

    $payload = $postData['payload'] ?? $postData ?? null;

    if (empty($payload) || !is_array($payload)) {
        jsonResp(['success' => false, 'message' => 'Payload data tracking kosong atau format JSON tidak valid'], 400);
    }

    // Validasi field wajib sesuai OpenAPI spec (TdTpsTrackingRequest)
    $requiredFields = [
        'nomorKontainer'  => 'Nomor Kontainer',
        'ukuranKontainer' => 'Ukuran Kontainer (20/40/45)',
        'jenisKontainer'  => 'Jenis Kontainer',
        'kodeTps'         => 'Kode TPS',
        'kodeGudang'      => 'Kode Gudang',
        'kodeKegiatan'    => 'Kode Kegiatan',
        'waktuKegiatan'   => 'Waktu Kegiatan (dd-MM-yyyy HH:mm:ss)'
    ];

    $missing = [];
    foreach ($requiredFields as $field => $label) {
        if (!isset($payload[$field]) || trim((string)$payload[$field]) === '') {
            $missing[] = $label;
        }
    }

    if (!empty($missing)) {
        jsonResp([
            'success' => false,
            'message' => 'Parameter wajib belum lengkap: ' . implode(', ', $missing)
        ], 422);
    }

    // Bersihkan & format payload
    $cleanPayload = [
        'nomorKontainer'  => strtoupper(trim(str_replace([' ', '-'], '', (string)$payload['nomorKontainer']))),
        'ukuranKontainer' => (string)$payload['ukuranKontainer'],
        'jenisKontainer'  => (string)$payload['jenisKontainer'],
        'kodeTps'         => strtoupper(trim((string)$payload['kodeTps'])),
        'kodeGudang'      => strtoupper(trim((string)$payload['kodeGudang'])),
        'kodeKegiatan'    => (int)$payload['kodeKegiatan'],
        'waktuKegiatan'   => trim((string)$payload['waktuKegiatan'])
    ];

    // Validasi ukuran kontainer (20, 40, atau 45)
    if (!in_array($cleanPayload['ukuranKontainer'], ['20', '40', '45'])) {
        jsonResp(['success' => false, 'message' => 'Ukuran kontainer harus 20, 40, atau 45'], 422);
    }

    // Validasi format waktuKegiatan (harus dd-MM-yyyy HH:mm:ss)
    if (!preg_match('/^\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2}$/', $cleanPayload['waktuKegiatan'])) {
        // Coba konversi jika berupa Y-m-d atau format lain
        $timeTs = strtotime($cleanPayload['waktuKegiatan']);
        if ($timeTs) {
            $cleanPayload['waktuKegiatan'] = date('d-m-Y H:i:s', $timeTs);
        } else {
            jsonResp(['success' => false, 'message' => 'Format waktuKegiatan harus "dd-MM-yyyy HH:mm:ss"'], 422);
        }
    }

    // Optional fields
    $optionalFields = [
        'tanggalBlAwb',
        'nomorBlAwb',
        'kodeDokumen',
        'nomorDokumen',
        'tanggalDokumen',
        'block',
        'slot',
        'tier',
        'nomorPolisi',
        'stid'
    ];

    foreach ($optionalFields as $f) {
        if (isset($payload[$f]) && trim((string)$payload[$f]) !== '') {
            $val = trim((string)$payload[$f]);

            // Normalisasi tanggal opsional ke dd-MM-yyyy jika diisi
            if (in_array($f, ['tanggalBlAwb', 'tanggalDokumen'])) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                    $parts = explode('-', $val);
                    $val = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
                }
            }

            if ($f === 'nomorPolisi') {
                $val = strtoupper(str_replace(' ', '', $val));
            }

            $cleanPayload[$f] = $val;
        }
    }

    try {
        $client = new CeisaClient();
        // Route resmi CEISA 4.0: kirim-tps-tracking
        $endpoint = 'kirim-tps-tracking';
        $res = $client->post($endpoint, $cleanPayload);

        $isOk = ($res['code'] >= 200 && $res['code'] < 300);

        // 1. Simpan Audit Log ke ceisa_api_logs
        try {
            global $pdo_tpsonline;
            if ($pdo_tpsonline) {
                $stmtLog = $pdo_tpsonline->prepare("
                    INSERT INTO ceisa_api_logs 
                    (endpoint, request_params, http_code, status, message, total_rows, raw_response, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmtLog->execute([
                    'kirim-tps-tracking',
                    json_encode($cleanPayload),
                    $res['code'] ?? 0,
                    $isOk ? 'SUCCESS' : 'FAILED',
                    $res['message'] ?? ($isOk ? 'Berhasil merekam tracking TPS' : 'Gagal'),
                    1,
                    json_encode($res['raw'] ?? $res)
                ]);

                // 2. Jika Berhasil, Simpan ke ceisa_tracking
                if ($isOk) {
                    $stmtTrack = $pdo_tpsonline->prepare("
                        INSERT INTO ceisa_tracking 
                        (no_cont, no_bl_awb, tgl_bl_awb, status_tracking, waktu_status, keterangan, raw_data, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");

                    $waktuDb = null;
                    if (!empty($cleanPayload['waktuKegiatan'])) {
                        $waktuDb = date('Y-m-d H:i:s', strtotime($cleanPayload['waktuKegiatan']));
                    }

                    $tglBlDb = null;
                    if (!empty($cleanPayload['tanggalBlAwb'])) {
                        $tglBlDb = date('Y-m-d', strtotime($cleanPayload['tanggalBlAwb']));
                    }

                    $kegiatanLabel = getKegiatanLabel($cleanPayload['kodeKegiatan']);

                    $stmtTrack->execute([
                        $cleanPayload['nomorKontainer'],
                        $cleanPayload['nomorBlAwb'] ?? null,
                        $tglBlDb,
                        $kegiatanLabel,
                        $waktuDb,
                        "Kegiatan {$cleanPayload['kodeKegiatan']}: {$kegiatanLabel}" . (!empty($cleanPayload['nomorPolisi']) ? " (Nopol: {$cleanPayload['nomorPolisi']})" : ''),
                        json_encode([
                            'payload'  => $cleanPayload,
                            'response' => $res['data'] ?? $res
                        ])
                    ]);
                }
            }
        } catch (Exception $dbErr) {
            error_log("Database logging error in kirim-tps-tracking: " . $dbErr->getMessage());
        }

        $rawCeisa = $res['raw'] ?? $res;

        $responseOutput = [
            'success'   => $isOk,
            'code'      => $res['code'] ?? ($isOk ? 201 : 400),
            'message'   => $res['message'] ?? ($isOk ? 'Data tracking kontainer berhasil direkam di CEISA 4.0!' : 'Pengiriman tracking ditolak oleh CEISA 4.0'),
            'data'      => $res['data'] ?? null,
            'payload'   => $cleanPayload,
            'raw'       => $rawCeisa
        ];

        // Sertakan atribut respons resmi dari CEISA 4.0 (seperti 409 Conflict atau 201 Created)
        if (is_array($rawCeisa)) {
            if (isset($rawCeisa['result'])) $responseOutput['result'] = $rawCeisa['result'];
            if (isset($rawCeisa['detail'])) $responseOutput['detail'] = $rawCeisa['detail'];
            if (isset($rawCeisa['path'])) $responseOutput['path'] = $rawCeisa['path'];
            if (isset($rawCeisa['date'])) $responseOutput['date'] = $rawCeisa['date'];
            if (isset($rawCeisa['version'])) $responseOutput['version'] = $rawCeisa['version'];
        }

        jsonResp($responseOutput, $isOk ? 200 : ($res['code'] ?: 400));

    } catch (Exception $e) {
        error_log("Error send tps-tracking: " . $e->getMessage());
        jsonResp([
            'success' => false,
            'code'    => 500,
            'message' => 'Kesalahan koneksi ke server gateway CEISA: ' . $e->getMessage()
        ], 500);
    }
}

// =========================================================================
// ACTION 2: CARI DATA KONTAINER DARI DATABASE UNTUK AUTO-FILL FORM (SELECT2)
// =========================================================================
if ($action === 'search_container') {
    $q = strtoupper(trim(input('q', input('term', ''))));
    $results = [];

    try {
        global $pdo_tpp, $pdo_tpsonline;

        if ($pdo_tpp) {
            // Query langsung ke tabel tppcontplp (terindeks noCont, super cepat <20ms)
            if (strlen($q) >= 2) {
                $sql = "
                    SELECT 
                        idCont,
                        noCont AS container_no,
                        size AS size_type,
                        status,
                        location AS yard_block,
                        row,
                        slot,
                        tier,
                        COALESCE(NoPolIn, '') AS nopol,
                        COALESCE(NO_MASTER_BL_AWB, '') AS no_bl,
                        DATE_FORMAT(TGL_MASTER_BL_AWB, '%d-%m-%Y') AS tgl_bl,
                        COALESCE(NoBC11, '') AS no_dokumen,
                        DATE_FORMAT(tglBC11, '%d-%m-%Y') AS tgl_dokumen,
                        DATE_FORMAT(tglInDepo, '%d-%m-%Y %H:%i:%s') AS waktu_masuk,
                        COALESCE(shipper, '') AS shipper
                    FROM tppcontplp
                    WHERE noCont LIKE :q
                    ORDER BY idCont DESC
                    LIMIT 30
                ";
                $stmt = $pdo_tpp->prepare($sql);
                $stmt->execute([':q' => "%$q%"]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $sql = "
                    SELECT 
                        idCont,
                        noCont AS container_no,
                        size AS size_type,
                        status,
                        location AS yard_block,
                        row,
                        slot,
                        tier,
                        COALESCE(NoPolIn, '') AS nopol,
                        COALESCE(NO_MASTER_BL_AWB, '') AS no_bl,
                        DATE_FORMAT(TGL_MASTER_BL_AWB, '%d-%m-%Y') AS tgl_bl,
                        COALESCE(NoBC11, '') AS no_dokumen,
                        DATE_FORMAT(tglBC11, '%d-%m-%Y') AS tgl_dokumen,
                        DATE_FORMAT(tglInDepo, '%d-%m-%Y %H:%i:%s') AS waktu_masuk,
                        COALESCE(shipper, '') AS shipper
                    FROM tppcontplp
                    ORDER BY idCont DESC
                    LIMIT 25
                ";
                $stmt = $pdo_tpp->query($sql);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        error_log("Error search container from tppcontplp: " . $e->getMessage());
    }

    // Pengecekan status pernah terkirim dari ceisa_tracking hanya untuk kontainer yang ditemukan
    $alreadySentMap = [];
    if (!empty($results) && !empty($pdo_tpsonline)) {
        try {
            $contList = array_unique(array_filter(array_column($results, 'container_no')));
            if (!empty($contList)) {
                $placeholders = implode(',', array_fill(0, count($contList), '?'));
                $stmtTrack = $pdo_tpsonline->prepare("
                    SELECT no_cont, status_tracking, waktu_status 
                    FROM ceisa_tracking 
                    WHERE no_cont IN ($placeholders)
                    ORDER BY id DESC
                ");
                $stmtTrack->execute(array_values($contList));
                foreach ($stmtTrack->fetchAll(PDO::FETCH_ASSOC) as $tr) {
                    if (!isset($alreadySentMap[$tr['no_cont']])) {
                        $alreadySentMap[$tr['no_cont']] = $tr;
                    }
                }
            }
        } catch (Exception $e2) {
            // Abaikan jika error log tracking
        }
    }

    // Format hasil untuk Select2 AJAX
    $select2Results = [];
    foreach ($results as $r) {
        $contNo = $r['container_no'];
        $sz = $r['size_type'] ?: '40';
        $st = $r['status'] ?: 'FCL';
        $locParts = [];
        if (!empty($r['yard_block'])) $locParts[] = $r['yard_block'];
        if (!empty($r['slot'])) $locParts[] = "S:" . $r['slot'];
        if (!empty($r['tier'])) $locParts[] = "T:" . $r['tier'];
        $locStr = !empty($locParts) ? ' [' . implode(' ', $locParts) . ']' : '';
        $blStr = !empty($r['no_bl']) ? ' | BL: ' . $r['no_bl'] : '';

        $already = $alreadySentMap[$contNo] ?? null;
        $isSent = !empty($already);
        $sentStr = $isSent ? ' [Pernah Terkirim]' : '';

        $select2Results[] = [
            'id'                  => $contNo,
            'text'                => "{$contNo} — {$sz} ({$st}){$locStr}{$sentStr}{$blStr}",
            'container_no'        => $contNo,
            'size_type'           => $sz,
            'size'                => (strpos($sz, '20') !== false ? '20' : (strpos($sz, '45') !== false ? '45' : '40')),
            'status'              => $st,
            'yard_block'          => $r['yard_block'] ?: '',
            'row'                 => $r['row'] ?: '',
            'slot'                => $r['slot'] ?: '',
            'tier'                => $r['tier'] ?: '',
            'nopol'               => $r['nopol'] ?: '',
            'no_bl'               => $r['no_bl'] ?: '',
            'tgl_bl'              => $r['tgl_bl'] ?: '',
            'no_dokumen'          => $r['no_dokumen'] ?: '',
            'tgl_dokumen'         => $r['tgl_dokumen'] ?: '',
            'waktu_masuk'         => $r['waktu_masuk'] ?: '',
            'already_sent'        => $isSent,
            'last_tracked_waktu'  => $already ? $already['waktu_status'] : '',
            'last_tracked_status' => $already ? $already['status_tracking'] : ''
        ];
    }

    jsonResp(['results' => $select2Results]);
}

// =========================================================================
// ACTION 3: RIWAYAT PENGIRIMAN TRACKING LOKAL & LAPORAN
// =========================================================================
if ($action === 'history' || $action === 'report') {
    global $pdo_tpsonline;
    if (!$pdo_tpsonline) {
        jsonResp(['success' => true, 'rows' => [], 'summary' => ['total' => 0, 'gate_in' => 0, 'gate_out' => 0, 'stacking' => 0, 'today' => 0]]);
    }

    $startDate = trim((string)input('start_date', input('tanggalAwal', '')));
    $endDate = trim((string)input('end_date', input('tanggalAkhir', '')));
    $kegiatan = trim((string)input('kode_kegiatan', input('kegiatan', '')));
    $q = trim((string)input('q', input('search', '')));

    try {
        $where = [];
        $params = [];

        if (!empty($startDate)) {
            // normalisasi jika format DD-MM-YYYY
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $startDate, $m)) {
                $startDate = "{$m[3]}-{$m[2]}-{$m[1]}";
            }
            $where[] = "(DATE(waktu_status) >= :start_date OR DATE(created_at) >= :start_date2)";
            $params[':start_date'] = $startDate;
            $params[':start_date2'] = $startDate;
        }

        if (!empty($endDate)) {
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $endDate, $m)) {
                $endDate = "{$m[3]}-{$m[2]}-{$m[1]}";
            }
            $where[] = "(DATE(waktu_status) <= :end_date OR DATE(created_at) <= :end_date2)";
            $params[':end_date'] = $endDate;
            $params[':end_date2'] = $endDate;
        }

        if (!empty($kegiatan)) {
            $where[] = "(status_tracking LIKE :kegiatan OR keterangan LIKE :kegiatan2 OR raw_data LIKE :kegiatan3)";
            $params[':kegiatan'] = "%$kegiatan%";
            $params[':kegiatan2'] = "%Kegiatan $kegiatan:%";
            $params[':kegiatan3'] = "%\"kodeKegiatan\":$kegiatan,%";
        }

        if (!empty($q)) {
            $where[] = "(no_cont LIKE :q OR no_bl_awb LIKE :q2 OR keterangan LIKE :q3 OR raw_data LIKE :q4)";
            $params[':q'] = "%$q%";
            $params[':q2'] = "%$q%";
            $params[':q3'] = "%$q%";
            $params[':q4'] = "%$q%";
        }

        $whereSql = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

        $sql = "
            SELECT 
                id,
                no_cont,
                no_bl_awb,
                DATE_FORMAT(tgl_bl_awb, '%d-%m-%Y') AS tgl_bl_awb,
                status_tracking,
                DATE_FORMAT(waktu_status, '%d-%m-%Y %H:%i:%s') AS waktu_status,
                keterangan,
                raw_data,
                DATE_FORMAT(created_at, '%d-%m-%Y %H:%i:%s') AS created_at
            FROM ceisa_tracking
            {$whereSql}
            ORDER BY id DESC
            LIMIT 500
        ";

        $stmt = $pdo_tpsonline->prepare($sql);
        $stmt->execute($params);
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rows = [];
        $summary = [
            'total'    => count($rawRows),
            'gate_in'  => 0,
            'gate_out' => 0,
            'stacking' => 0,
            'other'    => 0,
            'today'    => 0
        ];

        $todayStr = date('d-m-Y');

        foreach ($rawRows as $r) {
            $rawParsed = !empty($r['raw_data']) ? json_decode($r['raw_data'], true) : [];
            $payload = $rawParsed['payload'] ?? [];
            $response = $rawParsed['response'] ?? [];

            $st = strtoupper($r['status_tracking'] . ' ' . $r['keterangan']);
            if (strpos($st, 'GATE IN') !== false) {
                $summary['gate_in']++;
                $cat = 'GATE_IN';
            } elseif (strpos($st, 'GATE OUT') !== false) {
                $summary['gate_out']++;
                $cat = 'GATE_OUT';
            } elseif (strpos($st, 'STACKING') !== false) {
                $summary['stacking']++;
                $cat = 'STACKING';
            } else {
                $summary['other']++;
                $cat = 'OTHER';
            }

            if (strpos($r['created_at'], $todayStr) !== false || strpos($r['waktu_status'], $todayStr) !== false) {
                $summary['today']++;
            }

            // Lokasi
            $loc = [];
            if (!empty($payload['block'])) $loc[] = $payload['block'];
            if (!empty($payload['slot'])) $loc[] = 'S:' . $payload['slot'];
            if (!empty($payload['tier'])) $loc[] = 'T:' . $payload['tier'];
            $yardPos = !empty($loc) ? implode(' ', $loc) : '-';

            $rows[] = [
                'id'               => $r['id'],
                'no_cont'          => $r['no_cont'],
                'no_bl_awb'        => $r['no_bl_awb'] ?: ($payload['nomorBlAwb'] ?? '-'),
                'tgl_bl_awb'       => $r['tgl_bl_awb'] ?: ($payload['tanggalBlAwb'] ?? '-'),
                'status_tracking'  => $r['status_tracking'],
                'waktu_status'     => $r['waktu_status'],
                'keterangan'       => $r['keterangan'],
                'created_at'       => $r['created_at'],
                'category'         => $cat,
                'ukuran'           => ($payload['ukuranKontainer'] ?? '40') . ' ft',
                'jenis'            => ((string)($payload['jenisKontainer'] ?? '8') === '4') ? 'Kosong (Empty)' : (((string)($payload['jenisKontainer'] ?? '8') === '7') ? 'LCL' : 'FCL (Full)'),
                'kode_kegiatan'    => $payload['kodeKegiatan'] ?? 5,
                'nopol'            => $payload['nomorPolisi'] ?? '-',
                'yard_pos'         => $yardPos,
                'dokumen_pabean'   => (!empty($payload['nomorDokumen']) ? ($payload['kodeDokumen'] ?? '20') . ' / ' . $payload['nomorDokumen'] : '-'),
                'ceisa_id'         => $response['id'] ?? '-',
                'ceisa_waktu_rekam'=> $response['waktuRekam'] ?? '-',
                'raw_payload'      => $payload,
                'raw_response'     => $response
            ];
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
// ACTION 4: DETAIL SATU DATA TRACKING UNTUK MODAL
// =========================================================================
if ($action === 'detail') {
    $id = (int)input('id', 0);
    if ($id <= 0) {
        jsonResp(['success' => false, 'message' => 'ID tracking tidak valid'], 400);
    }

    try {
        global $pdo_tpsonline;
        $stmt = $pdo_tpsonline->prepare("SELECT * FROM ceisa_tracking WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            jsonResp(['success' => false, 'message' => 'Data tracking tidak ditemukan'], 404);
        }

        $rawParsed = !empty($row['raw_data']) ? json_decode($row['raw_data'], true) : [];
        $payload = $rawParsed['payload'] ?? [];
        $response = $rawParsed['response'] ?? [];

        jsonResp([
            'success'     => true,
            'data'        => $row,
            'payload'     => $payload,
            'response'    => $response
        ]);
    } catch (Exception $e) {
        jsonResp(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// Helper: Nama Kegiatan Resmi CEISA 4.0 (Bab 8.1 Panduan Teknis Terpadu)
function getKegiatanLabel(int $kode): string {
    $map = [
        1  => 'DISCHARGE',
        2  => 'LOADING',
        3  => 'GATE OUT (Codeco Impor)',
        4  => 'GATE IN RECEIVING (Codeco Ekspor)',
        5  => 'GATE IN PLP',
        6  => 'GATE OUT LINI 2',
        7  => 'GATE IN EKSPOR LINI 2',
        8  => 'GATE OUT EKSPOR LINI 2',
        9  => 'GATE OUT BATAL EKSPOR',
        10 => 'STACKING DISCHARGE',
        11 => 'STACKING EKSPOR',
        12 => 'TRUCK IN',
        13 => 'PICKUP',
        14 => 'BEHANDLE',
        15 => 'SHIFTING',
        16 => 'STRIPPING STUFFING',
        17 => 'STACKING DISCHARGE LINI 2',
        18 => 'STACKING EKSPOR LINI 2',
        19 => 'TRUCK IN LINI 2',
        20 => 'PICKUP LINI 2',
        21 => 'BEHANDLE LINI 2',
        22 => 'SHIFTING LINI 2',
        23 => 'STRIPPING STUFFING LINI 2',
        24 => 'STUFFING KE GUDANG LINI 2'
    ];
    return $map[$kode] ?? "Kegiatan #$kode";
}
