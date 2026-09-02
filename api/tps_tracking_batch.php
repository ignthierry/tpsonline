<?php
/**
 * API Backend: TPS Tracking Batch CEISA 4.0
 * Endpoint Target: POST /tps-tracking/batch
 * Deskripsi: Merekam BANYAK data tracking pergerakan kontainer di TPS sekaligus
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

// =========================================================================
// ACTION 1: KIRIM BATCH TRACKING KE GATEWAY CEISA 4.0 (/tps-tracking/batch)
// =========================================================================
if ($action === 'send') {
    $rawInput = file_get_contents('php://input');
    $postData = json_decode($rawInput, true);

    $batchItems = $postData['items'] ?? $postData ?? null;

    if (empty($batchItems) || !is_array($batchItems) || !isset($batchItems[0])) {
        jsonResp(['success' => false, 'message' => 'Data batch kosong atau format JSON tidak valid. Harus berupa array of objects.'], 400);
    }

    // Validasi dan bersihkan setiap item
    $requiredFields = [
        'nomorKontainer'  => 'Nomor Kontainer',
        'ukuranKontainer' => 'Ukuran Kontainer',
        'jenisKontainer'  => 'Jenis Kontainer',
        'kodeTps'         => 'Kode TPS',
        'kodeGudang'      => 'Kode Gudang',
        'kodeKegiatan'    => 'Kode Kegiatan',
        'waktuKegiatan'   => 'Waktu Kegiatan'
    ];

    $optionalFields = [
        'tanggalBlAwb', 'nomorBlAwb', 'kodeDokumen', 'nomorDokumen',
        'tanggalDokumen', 'block', 'slot', 'tier', 'nomorPolisi', 'stid'
    ];

    $cleanBatch = [];
    $validationErrors = [];

    foreach ($batchItems as $idx => $item) {
        $rowNum = $idx + 1;
        $missing = [];

        foreach ($requiredFields as $field => $label) {
            if (!isset($item[$field]) || trim((string)$item[$field]) === '') {
                $missing[] = $label;
            }
        }

        if (!empty($missing)) {
            $validationErrors[] = "Baris #{$rowNum}: Field wajib belum lengkap (" . implode(', ', $missing) . ")";
            continue;
        }

        $clean = [
            'nomorKontainer'  => strtoupper(trim(str_replace([' ', '-'], '', (string)$item['nomorKontainer']))),
            'ukuranKontainer' => (string)$item['ukuranKontainer'],
            'jenisKontainer'  => (string)$item['jenisKontainer'],
            'kodeTps'         => strtoupper(trim((string)$item['kodeTps'])),
            'kodeGudang'      => strtoupper(trim((string)$item['kodeGudang'])),
            'kodeKegiatan'    => (int)$item['kodeKegiatan'],
            'waktuKegiatan'   => trim((string)$item['waktuKegiatan'])
        ];

        // Validasi ukuran
        if (!in_array($clean['ukuranKontainer'], ['20', '40', '45', '60'])) {
            $validationErrors[] = "Baris #{$rowNum} ({$clean['nomorKontainer']}): Ukuran kontainer harus 20, 40, 45, atau 60";
            continue;
        }

        // Validasi format waktu
        if (!preg_match('/^\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2}$/', $clean['waktuKegiatan'])) {
            $timeTs = strtotime($clean['waktuKegiatan']);
            if ($timeTs) {
                $clean['waktuKegiatan'] = date('d-m-Y H:i:s', $timeTs);
            } else {
                $validationErrors[] = "Baris #{$rowNum} ({$clean['nomorKontainer']}): Format waktuKegiatan harus dd-MM-yyyy HH:mm:ss";
                continue;
            }
        }

        // Optional fields
        foreach ($optionalFields as $f) {
            if (isset($item[$f]) && trim((string)$item[$f]) !== '') {
                $val = trim((string)$item[$f]);
                if (in_array($f, ['tanggalBlAwb', 'tanggalDokumen'])) {
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                        $parts = explode('-', $val);
                        $val = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
                    }
                }
                if ($f === 'nomorPolisi') {
                    $val = strtoupper(str_replace(' ', '', $val));
                }
                $clean[$f] = $val;
            }
        }

        $cleanBatch[] = $clean;
    }

    // Jika ada error validasi dan tidak ada data valid, kembalikan error
    if (empty($cleanBatch)) {
        jsonResp([
            'success'    => false,
            'message'    => 'Tidak ada data valid untuk dikirim.',
            'errors'     => $validationErrors,
            'total_input'=> count($batchItems),
            'total_valid'=> 0
        ], 422);
    }

    // Kirim ke CEISA 4.0
    try {
        $client = new CeisaClient();
        $endpoint = 'tps-tracking/batch';
        $res = $client->post($endpoint, $cleanBatch);

        $isOk = ($res['code'] >= 200 && $res['code'] < 300);
        $batchId = 'BATCH-' . date('Ymd-His') . '-' . count($cleanBatch);

        // Simpan ke Database
        try {
            global $pdo_tpsonline;
            if ($pdo_tpsonline) {
                // 1. Audit Log
                $stmtLog = $pdo_tpsonline->prepare("
                    INSERT INTO ceisa_api_logs 
                    (endpoint, request_params, http_code, status, message, total_rows, raw_response, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmtLog->execute([
                    'tps-tracking/batch',
                    json_encode($cleanBatch),
                    $res['code'] ?? 0,
                    $isOk ? 'SUCCESS' : 'FAILED',
                    $res['message'] ?? ($isOk ? 'Batch tracking berhasil' : 'Batch tracking gagal'),
                    count($cleanBatch),
                    json_encode($res['raw'] ?? $res)
                ]);

                // 2. Simpan setiap item ke ceisa_tracking
                if ($isOk) {
                    $stmtTrack = $pdo_tpsonline->prepare("
                        INSERT INTO ceisa_tracking 
                        (no_cont, no_bl_awb, tgl_bl_awb, status_tracking, waktu_status, keterangan, raw_data, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");

                    foreach ($cleanBatch as $itemClean) {
                        $waktuDb = null;
                        if (!empty($itemClean['waktuKegiatan'])) {
                            $waktuDb = date('Y-m-d H:i:s', strtotime($itemClean['waktuKegiatan']));
                        }
                        $tglBlDb = null;
                        if (!empty($itemClean['tanggalBlAwb'])) {
                            $tglBlDb = date('Y-m-d', strtotime($itemClean['tanggalBlAwb']));
                        }

                        $kegiatanLabel = getKegiatanLabel($itemClean['kodeKegiatan']);

                        $stmtTrack->execute([
                            $itemClean['nomorKontainer'],
                            $itemClean['nomorBlAwb'] ?? null,
                            $tglBlDb,
                            $kegiatanLabel,
                            $waktuDb,
                            "[BATCH] Kegiatan {$itemClean['kodeKegiatan']}: {$kegiatanLabel}" . (!empty($itemClean['nomorPolisi']) ? " (Nopol: {$itemClean['nomorPolisi']})" : '') . " | {$batchId}",
                            json_encode([
                                'payload'  => $itemClean,
                                'response' => $res['data'] ?? $res,
                                'batch_id' => $batchId
                            ])
                        ]);
                    }
                }
            }
        } catch (Exception $dbErr) {
            error_log("Database logging error in tps-tracking/batch: " . $dbErr->getMessage());
        }

        $rawCeisa = $res['raw'] ?? $res;

        $responseOutput = [
            'success'         => $isOk,
            'code'            => $res['code'] ?? ($isOk ? 201 : 400),
            'message'         => $res['message'] ?? ($isOk ? 'Batch tracking berhasil dikirim ke CEISA 4.0!' : 'Pengiriman batch tracking ditolak oleh CEISA 4.0'),
            'batch_id'        => $batchId,
            'total_input'     => count($batchItems),
            'total_valid'     => count($cleanBatch),
            'total_sent'      => count($cleanBatch),
            'validation_errors' => $validationErrors,
            'data'            => $res['data'] ?? null,
            'raw'             => $rawCeisa,
            'items_sent'      => $cleanBatch
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
        error_log("Error send tps-tracking/batch: " . $e->getMessage());
        jsonResp([
            'success' => false,
            'code'    => 500,
            'message' => 'Kesalahan koneksi ke server gateway CEISA: ' . $e->getMessage()
        ], 500);
    }
}

// =========================================================================
// ACTION 2: CARI KONTAINER UNTUK AUTO-FILL (REUSE DARI tps_tracking.php)
// =========================================================================
if ($action === 'search_containers') {
    $q = strtoupper(trim(input('q', input('term', ''))));
    $results = [];

    try {
        global $pdo_tpp, $pdo_tpsonline;

        if ($pdo_tpp) {
            if (strlen($q) >= 2) {
                $sql = "
                    SELECT 
                        idCont, noCont AS container_no, size AS size_type, status,
                        location AS yard_block, row, slot, tier,
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
                    LIMIT 50
                ";
                $stmt = $pdo_tpp->prepare($sql);
                $stmt->execute([':q' => "%$q%"]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $sql = "
                    SELECT 
                        idCont, noCont AS container_no, size AS size_type, status,
                        location AS yard_block, row, slot, tier,
                        COALESCE(NoPolIn, '') AS nopol,
                        COALESCE(NO_MASTER_BL_AWB, '') AS no_bl,
                        DATE_FORMAT(TGL_MASTER_BL_AWB, '%d-%m-%Y') AS tgl_bl,
                        COALESCE(NoBC11, '') AS no_dokumen,
                        DATE_FORMAT(tglBC11, '%d-%m-%Y') AS tgl_dokumen,
                        DATE_FORMAT(tglInDepo, '%d-%m-%Y %H:%i:%s') AS waktu_masuk,
                        COALESCE(shipper, '') AS shipper
                    FROM tppcontplp
                    ORDER BY idCont DESC
                    LIMIT 30
                ";
                $stmt = $pdo_tpp->query($sql);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        error_log("Error search containers batch: " . $e->getMessage());
    }

    // Format
    $formatted = [];
    foreach ($results as $r) {
        $formatted[] = [
            'container_no' => $r['container_no'],
            'size_type'    => $r['size_type'] ?: '40',
            'size'         => (strpos($r['size_type'] ?: '', '20') !== false ? '20' : (strpos($r['size_type'] ?: '', '45') !== false ? '45' : '40')),
            'status'       => $r['status'] ?: 'FCL',
            'yard_block'   => $r['yard_block'] ?: '',
            'slot'         => $r['slot'] ?: '',
            'tier'         => $r['tier'] ?: '',
            'nopol'        => $r['nopol'] ?: '',
            'no_bl'        => $r['no_bl'] ?: '',
            'tgl_bl'       => $r['tgl_bl'] ?: '',
            'no_dokumen'   => $r['no_dokumen'] ?: '',
            'tgl_dokumen'  => $r['tgl_dokumen'] ?: ''
        ];
    }

    jsonResp(['results' => $formatted]);
}

// =========================================================================
// ACTION 3: RIWAYAT BATCH TRACKING
// =========================================================================
if ($action === 'history' || $action === 'report') {
    global $pdo_tpsonline;
    if (!$pdo_tpsonline) {
        jsonResp(['success' => true, 'rows' => [], 'summary' => ['total' => 0, 'gate_in' => 0, 'gate_out' => 0, 'stacking' => 0, 'today' => 0]]);
    }

    $startDate = trim((string)input('start_date', input('tanggalAwal', '')));
    $endDate   = trim((string)input('end_date', input('tanggalAkhir', '')));
    $kegiatan  = trim((string)input('kode_kegiatan', input('kegiatan', '')));
    $q         = trim((string)input('q', input('search', '')));

    try {
        $where = ["keterangan LIKE '%[BATCH]%'"];
        $params = [];

        if (!empty($startDate)) {
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $startDate, $m)) {
                $startDate = "{$m[3]}-{$m[2]}-{$m[1]}";
            }
            $where[] = "(DATE(waktu_status) >= :sd OR DATE(created_at) >= :sd2)";
            $params[':sd'] = $startDate;
            $params[':sd2'] = $startDate;
        }

        if (!empty($endDate)) {
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $endDate, $m)) {
                $endDate = "{$m[3]}-{$m[2]}-{$m[1]}";
            }
            $where[] = "(DATE(waktu_status) <= :ed OR DATE(created_at) <= :ed2)";
            $params[':ed'] = $endDate;
            $params[':ed2'] = $endDate;
        }

        if (!empty($kegiatan)) {
            $where[] = "(status_tracking LIKE :keg OR keterangan LIKE :keg2)";
            $params[':keg'] = "%$kegiatan%";
            $params[':keg2'] = "%Kegiatan $kegiatan:%";
        }

        if (!empty($q)) {
            $where[] = "(no_cont LIKE :q OR no_bl_awb LIKE :q2 OR keterangan LIKE :q3)";
            $params[':q'] = "%$q%";
            $params[':q2'] = "%$q%";
            $params[':q3'] = "%$q%";
        }

        $whereSql = " WHERE " . implode(" AND ", $where);

        $sql = "
            SELECT 
                id, no_cont, no_bl_awb,
                DATE_FORMAT(tgl_bl_awb, '%d-%m-%Y') AS tgl_bl_awb,
                status_tracking,
                DATE_FORMAT(waktu_status, '%d-%m-%Y %H:%i:%s') AS waktu_status,
                keterangan, raw_data,
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
        $summary = ['total' => count($rawRows), 'gate_in' => 0, 'gate_out' => 0, 'stacking' => 0, 'other' => 0, 'today' => 0];
        $todayStr = date('d-m-Y');

        foreach ($rawRows as $r) {
            $rawParsed = !empty($r['raw_data']) ? json_decode($r['raw_data'], true) : [];
            $payload = $rawParsed['payload'] ?? [];
            $response = $rawParsed['response'] ?? [];
            $batchId = $rawParsed['batch_id'] ?? '-';

            $st = strtoupper($r['status_tracking'] . ' ' . $r['keterangan']);
            if (strpos($st, 'GATE IN') !== false) { $summary['gate_in']++; $cat = 'GATE_IN'; }
            elseif (strpos($st, 'GATE OUT') !== false) { $summary['gate_out']++; $cat = 'GATE_OUT'; }
            elseif (strpos($st, 'STACKING') !== false) { $summary['stacking']++; $cat = 'STACKING'; }
            else { $summary['other']++; $cat = 'OTHER'; }

            if (strpos($r['created_at'], $todayStr) !== false || strpos($r['waktu_status'], $todayStr) !== false) {
                $summary['today']++;
            }

            $loc = [];
            if (!empty($payload['block'])) $loc[] = $payload['block'];
            if (!empty($payload['slot'])) $loc[] = 'S:' . $payload['slot'];
            if (!empty($payload['tier'])) $loc[] = 'T:' . $payload['tier'];

            $rows[] = [
                'id'              => $r['id'],
                'no_cont'         => $r['no_cont'],
                'no_bl_awb'       => $r['no_bl_awb'] ?: ($payload['nomorBlAwb'] ?? '-'),
                'tgl_bl_awb'      => $r['tgl_bl_awb'] ?: ($payload['tanggalBlAwb'] ?? '-'),
                'status_tracking' => $r['status_tracking'],
                'waktu_status'    => $r['waktu_status'],
                'keterangan'      => $r['keterangan'],
                'created_at'      => $r['created_at'],
                'category'        => $cat,
                'batch_id'        => $batchId,
                'ukuran'          => ($payload['ukuranKontainer'] ?? '40') . ' ft',
                'jenis'           => ((string)($payload['jenisKontainer'] ?? '8') === '4') ? 'Kosong (Empty)' : (((string)($payload['jenisKontainer'] ?? '8') === '7') ? 'LCL' : 'FCL (Full)'),
                'kode_kegiatan'   => $payload['kodeKegiatan'] ?? 5,
                'nopol'           => $payload['nomorPolisi'] ?? '-',
                'yard_pos'        => !empty($loc) ? implode(' ', $loc) : '-',
                'dokumen_pabean'  => (!empty($payload['nomorDokumen']) ? ($payload['kodeDokumen'] ?? '20') . ' / ' . $payload['nomorDokumen'] : '-'),
                'raw_payload'     => $payload,
                'raw_response'    => $response
            ];
        }

        jsonResp(['success' => true, 'rows' => $rows, 'summary' => $summary]);
    } catch (Exception $e) {
        jsonResp(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// =========================================================================
// ACTION 4: DETAIL SATU DATA TRACKING
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
        jsonResp([
            'success'  => true,
            'data'     => $row,
            'payload'  => $rawParsed['payload'] ?? [],
            'response' => $rawParsed['response'] ?? [],
            'batch_id' => $rawParsed['batch_id'] ?? '-'
        ]);
    } catch (Exception $e) {
        jsonResp(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
