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

            // OpenAPI CEISA 4.0 Validasi Panjang Field Yard (block max 10, slot max 5, tier max 5)
            if ($f === 'block') {
                if (strlen($val) > 10) {
                    $cleanB = trim(preg_replace('/^blok\s+/i', '', $val));
                    $val = substr($cleanB, 0, 10);
                }
            }
            if ($f === 'slot') {
                $val = substr($val, 0, 5);
            }
            if ($f === 'tier') {
                $val = substr($val, 0, 5);
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
                    $deptLabel = !empty($payload['departemen']) ? '[' . strtoupper(trim($payload['departemen'])) . '] ' : '[TPP] ';

                    $stmtTrack->execute([
                        $cleanPayload['nomorKontainer'],
                        $cleanPayload['nomorBlAwb'] ?? null,
                        $tglBlDb,
                        $kegiatanLabel,
                        $waktuDb,
                        $deptLabel . "Kegiatan {$cleanPayload['kodeKegiatan']}: {$kegiatanLabel}" . (!empty($cleanPayload['nomorPolisi']) ? " (Nopol: {$cleanPayload['nomorPolisi']})" : ''),
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
// Mendukung TPP (PLP / tpp_primamas) dan GUDANG (LCL / primamas)
// =========================================================================
if ($action === 'search_container') {
    $q    = strtoupper(trim(input('q', input('term', ''))));
    $dept = strtolower(trim((string)input('dept', 'tpp'))); // 'tpp' atau 'gudang'
    $results = [];

    try {
        global $pdo_tpp, $pdo_primamas, $pdo_tpsonline;

        if ($dept === 'gudang') {
            // PENCARIAN KONTAINER DEPARTEMEN GUDANG (LCL / DATABASE PRIMAMAS)
            if ($pdo_primamas) {
                $qClean = str_replace([' ', '-'], '', $q);
                $whereClause = (strlen($q) >= 2) ? "WHERE (REPLACE(REPLACE(k.No_Cont, '-', ''), ' ', '') LIKE :qClean OR k.No_Cont LIKE :q OR m.No_MasBL LIKE :q2)" : "";
                $sql = "
                    SELECT 
                        m.Id_MasBL,
                        REPLACE(REPLACE(k.No_Cont, '-', ''), ' ', '') AS container_no,
                        k.No_Cont AS raw_container_no,
                        k.Size AS size_type,
                        COALESCE(k.Type, 'LCL') AS status,
                        man.blok AS yard_block,
                        '' AS row,
                        '' AS slot,
                        '' AS tier,
                        COALESCE(m.nopol_in, m.nopol_out, '') AS nopol,
                        COALESCE(m.No_MasBL, '') AS no_bl,
                        DATE_FORMAT(m.Tgl_MasBL, '%d-%m-%Y') AS tgl_bl,
                        COALESCE(H.NO_BC11, '') AS no_dokumen,
                        DATE_FORMAT(H.TGL_BC11, '%d-%m-%Y') AS tgl_dokumen,
                        COALESCE(H.NO_PLP, '') AS no_plp,
                        DATE_FORMAT(H.TGL_PLP, '%d-%m-%Y') AS tgl_plp,
                        DATE_FORMAT(CONCAT(m.tgl_datang_cont, ' ', IFNULL(m.jam_datang_cont, '00:00:00')), '%d-%m-%Y %H:%i:%s') AS waktu_masuk,
                        DATE_FORMAT(CONCAT(man.Tgl_StrippingBC, ' ', IFNULL(man.jamStrippingBC, '00:00:00')), '%d-%m-%Y %H:%i:%s') AS waktu_stripping,
                        DATE_FORMAT(CONCAT(m.tgl_keluar_cont, ' ', IFNULL(m.jam_keluar_cont, '00:00:00')), '%d-%m-%Y %H:%i:%s') AS waktu_keluar,
                        COALESCE(m.No_SegelBC, '') AS no_segel,
                        'GUDANG' AS departemen
                    FROM master_bl m
                    INNER JOIN kontainer k ON m.Id_Kontainer_FK = k.Id_Kontainer
                    LEFT JOIN manifest man ON man.Id_MasBL_FK = m.Id_MasBL
                    LEFT JOIN tpsws_responplp_detail_backup D ON D.NO_BL_AWB = man.No_BL
                    LEFT JOIN tpsws_responplp_header_backup H ON H.NO_SURAT = D.NO_SURAT_FK AND H.NO_PLP = D.NO_PLP_FK
                    {$whereClause}
                    GROUP BY m.Id_MasBL
                    ORDER BY m.Id_MasBL DESC
                    LIMIT 30
                ";
                $stmt = $pdo_primamas->prepare($sql);
                if (strlen($q) >= 2) {
                    $stmt->execute([':qClean' => "%$qClean%", ':q' => "%$q%", ':q2' => "%$q%"]);
                } else {
                    $stmt->execute();
                }
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            // PENCARIAN KONTAINER DEPARTEMEN TPP (PLP / DATABASE TPP_PRIMAMAS)
            if ($pdo_tpp) {
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
                            '' AS waktu_stripping,
                            DATE_FORMAT(tglOUT_truckingKosong, '%d-%m-%Y %H:%i:%s') AS waktu_keluar,
                            COALESCE(shipper, '') AS shipper,
                            'TPP' AS departemen
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
                            '' AS waktu_stripping,
                            DATE_FORMAT(tglOUT_truckingKosong, '%d-%m-%Y %H:%i:%s') AS waktu_keluar,
                            COALESCE(shipper, '') AS shipper,
                            'TPP' AS departemen
                        FROM tppcontplp
                        ORDER BY idCont DESC
                        LIMIT 25
                    ";
                    $stmt = $pdo_tpp->query($sql);
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error search container (dept: $dept): " . $e->getMessage());
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
        $contNo = strtoupper(str_replace([' ', '-'], '', (string)$r['container_no']));
        $contNoRaw = $r['raw_container_no'] ?? $r['container_no'];
        $sz = $r['size_type'] ?: '40';
        $st = $r['status'] ?: ($dept === 'gudang' ? 'LCL' : 'FCL');
        $deptTag = $r['departemen'] ?? strtoupper($dept);
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
            'text'                => "{$contNo} — {$sz} ({$st}) [{$deptTag}]{$locStr}{$sentStr}{$blStr}",
            'container_no'        => $contNo,
            'size_type'           => $sz,
            'size'                => (strpos($sz, '20') !== false ? '20' : (strpos($sz, '45') !== false ? '45' : '40')),
            'status'              => $st,
            'departemen'          => $deptTag,
            'yard_block'          => $r['yard_block'] ?: '',
            'row'                 => $r['row'] ?: '',
            'slot'                => $r['slot'] ?: '',
            'tier'                => $r['tier'] ?: '',
            'nopol'               => $r['nopol'] ?: '',
            'no_bl'               => $r['no_bl'] ?: '',
            'tgl_bl'              => $r['tgl_bl'] ?: '',
            'no_dokumen'          => $r['no_dokumen'] ?: ($r['no_plp'] ?? ''),
            'tgl_dokumen'         => $r['tgl_dokumen'] ?: ($r['tgl_plp'] ?? ''),
            'no_plp'              => $r['no_plp'] ?? '',
            'tgl_plp'             => $r['tgl_plp'] ?? '',
            'waktu_masuk'         => $r['waktu_masuk'] ?: '',
            'waktu_stripping'     => $r['waktu_stripping'] ?: '',
            'waktu_keluar'        => $r['waktu_keluar'] ?: '',
            'no_segel'            => $r['no_segel'] ?? '',
            'already_sent'        => $isSent,
            'last_tracked_waktu'  => $already ? $already['waktu_status'] : '',
            'last_tracked_status' => $already ? $already['status_tracking'] : ''
        ];
    }

    jsonResp(['results' => $select2Results]);
}

// =========================================================================
// ACTION 2B: RIWAYAT 6 ALUR OPERASIONAL KONTAINER (TIMELINE TRACKER)
// Menelusuri seluruh riwayat operasional kontainer (In Trailer, Stacking,
// Behandle, Stripping, Truck In Penjemput, Out Trailer) dari database.
// =========================================================================
if ($action === 'get_container_timeline') {
    $noContRaw = trim((string)input('no_cont', input('container_no', input('q', ''))));
    $noContClean = strtoupper(trim(str_replace([' ', '-'], '', $noContRaw)));
    $dept = strtolower(trim((string)input('dept', 'tpp'))); // 'tpp' atau 'gudang'

    if (empty($noContClean)) {
        jsonResp(['success' => false, 'message' => 'Nomor kontainer belum diisi'], 400);
    }

    try {
        global $pdo_tpp, $pdo_primamas, $pdo_tpsonline;

        // Helper konversi format tanggal
        $formatDmyHis = function($dtStr) {
            if (empty($dtStr) || $dtStr === '0000-00-00' || $dtStr === '0000-00-00 00:00:00') return '';
            $ts = strtotime($dtStr);
            return $ts ? date('d-m-Y H:i:s', $ts) : '';
        };
        $formatDmy = function($dStr) {
            if (empty($dStr) || $dStr === '0000-00-00' || $dStr === '0000-00-00 00:00:00') return '';
            $ts = strtotime($dStr);
            return $ts ? date('d-m-Y', $ts) : '';
        };

        // 1. Cek status pengiriman ke CEISA dari ceisa_api_logs
        $sentKegiatan = [];
        if (!empty($pdo_tpsonline)) {
            try {
                $stmtLogs = $pdo_tpsonline->prepare("
                    SELECT request_params, status, created_at 
                    FROM ceisa_api_logs 
                    WHERE endpoint = 'kirim-tps-tracking' 
                      AND request_params LIKE :q
                      AND status = 'SUCCESS'
                    ORDER BY id DESC
                ");
                $stmtLogs->execute([':q' => "%{$noContClean}%"]);
                $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
                foreach ($logs as $l) {
                    $parsed = json_decode($l['request_params'], true);
                    if (!empty($parsed['kodeKegiatan'])) {
                        $k = (int)$parsed['kodeKegiatan'];
                        if (!isset($sentKegiatan[$k])) {
                            $sentKegiatan[$k] = [
                                'sent_at'       => date('d-m-Y H:i', strtotime($l['created_at'])),
                                'waktuKegiatan' => $parsed['waktuKegiatan'] ?? '',
                                'status'        => 'SUCCESS'
                            ];
                        }
                    }
                }
            } catch (Exception $eLogs) {
                error_log("Error check CEISA logs: " . $eLogs->getMessage());
            }
        }

        if ($dept === 'tpp') {
            // =================================================================
            // ALUR KONTANER TPP (PLP / DATABASE TPP_PRIMAMAS)
            // =================================================================
            if (!$pdo_tpp) {
                jsonResp(['success' => false, 'message' => 'Koneksi database TPP tidak tersedia'], 500);
            }

            // 1. Query Kontainer dari tppcontplp
            $sqlCont = "
                SELECT 
                    c.idCont,
                    c.noCont,
                    c.size,
                    c.status,
                    c.type,
                    c.location,
                    c.row,
                    c.slot,
                    c.tier,
                    c.tglInDepo,
                    c.NoPolIn,
                    c.tglGateOutLini1,
                    c.tglOUT_truckingKosong,
                    c.NO_MASTER_BL_AWB,
                    c.TGL_MASTER_BL_AWB,
                    c.NoBC11,
                    c.tglBC11,
                    c.NoPosBC11,
                    c.idPLP_FK,
                    c.ketstatus,
                    c.updateby,
                    c.updateby_name,
                    c.updateby_date,
                    c.shipper,
                    p.noPLP,
                    p.tglPLP,
                    p.asalPLP
                FROM tppcontplp c
                LEFT JOIN tppmanifestplp p ON c.idPLP_FK = p.idPLP
                WHERE REPLACE(REPLACE(c.noCont, ' ', ''), '-', '') = :noCont
                ORDER BY c.idCont DESC
                LIMIT 1
            ";
            $stmt = $pdo_tpp->prepare($sqlCont);
            $stmt->execute([':noCont' => $noContClean]);
            $cont = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cont) {
                jsonResp([
                    'success' => false,
                    'message' => "Kontainer {$noContClean} tidak ditemukan di database TPP."
                ], 404);
            }

            $idCont = (int)$cont['idCont'];
            $contSize = (strpos($cont['size'], '20') !== false) ? '20' : ((strpos($cont['size'], '45') !== false) ? '45' : '40');
            $contStatus = ($cont['status'] === 'EMPTY') ? '4' : (($cont['status'] === 'LCL') ? '7' : '8');

            // 2. Query Behandle (Pemeriksaan Fisik Pabean / Buka Segel)
            $sqlBehandle = "
                SELECT inv.*
                FROM tppinvcontplp ic
                JOIN tppinvoiceplp inv ON ic.idInvPLP = inv.idInvPLP
                WHERE ic.idContPLP = :idCont
                  AND (inv.invType LIKE '%behandle%' OR (inv.behandleType IS NOT NULL AND inv.behandleType != ''))
                ORDER BY inv.idInvPLP DESC
                LIMIT 1
            ";
            $stmtBe = $pdo_tpp->prepare($sqlBehandle);
            $stmtBe->execute([':idCont' => $idCont]);
            $behandle = $stmtBe->fetch(PDO::FETCH_ASSOC);

            // 3. Query Stripping (Bongkar Muatan Kargo)
            $sqlStripping = "
                SELECT inv.*
                FROM tppinvcontplp ic
                JOIN tppinvoiceplp inv ON ic.idInvPLP = inv.idInvPLP
                WHERE ic.idContPLP = :idCont
                  AND inv.invType LIKE '%stripping%'
                ORDER BY inv.idInvPLP DESC
                LIMIT 1
            ";
            $stmtStr = $pdo_tpp->prepare($sqlStripping);
            $stmtStr->execute([':idCont' => $idCont]);
            $stripping = $stmtStr->fetch(PDO::FETCH_ASSOC);

            // 4. Query Invoice Pengeluaran / SPPB Delivery
            $sqlDeliveryInv = "
                SELECT inv.*
                FROM tppinvcontplp ic
                JOIN tppinvoiceplp inv ON ic.idInvPLP = inv.idInvPLP
                WHERE ic.idContPLP = :idCont
                  AND (inv.invType LIKE '%penumpukan%' OR inv.invType LIKE '%storage%' OR (inv.noSPPB IS NOT NULL AND inv.noSPPB != ''))
                ORDER BY 
                    (CASE WHEN inv.jenisSPPB IS NOT NULL AND inv.jenisSPPB != '' THEN 1 ELSE 2 END),
                    inv.idInvPLP DESC
                LIMIT 1
            ";
            $stmtDel = $pdo_tpp->prepare($sqlDeliveryInv);
            $stmtDel->execute([':idCont' => $idCont]);
            $deliveryInv = $stmtDel->fetch(PDO::FETCH_ASSOC);

            // 5. Query Surat Jalan (Truck In Penjemput & Out Trailer Gate Out)
            $sqlSJ = "
                SELECT *
                FROM tppsuratjalan
                WHERE idManifest = :idCont
                  AND typeManifest = 'PLP'
                ORDER BY idSuratJalan DESC
                LIMIT 1
            ";
            $stmtSJ = $pdo_tpp->prepare($sqlSJ);
            $stmtSJ->execute([':idCont' => $idCont]);
            $sj = $stmtSJ->fetch(PDO::FETCH_ASSOC);

            // Evaluasi Dokumen Pengeluaran Pabean (Alur 5 & 6) berdasarkan Referensi_Kode_Dokumen_TPS.pdf
            $kodeDokOut = '99'; // Default: 99 = Dokumen Pengeluaran Lainnya....
            $namaDokOut = 'Dokumen Pengeluaran Lainnya (Surat Jalan)';
            $noDokOut = $sj ? trim((string)$sj['noSuratJalan']) : '';
            $tglDokOut = $sj ? $formatDmy($sj['tglSuratJalan'] ?: $sj['cetak']) : '';

            $sppbJns = strtoupper(trim((string)($deliveryInv['jenisSPPB'] ?? '')));
            $sppbNo = trim((string)($deliveryInv['noSPPB'] ?? ''));
            $sppbTgl = $formatDmy($deliveryInv['tglSPPB'] ?? null);
            $pibNo = trim((string)($deliveryInv['noPIB'] ?? ''));
            $pibTgl = $formatDmy($deliveryInv['tglPIB'] ?? null);
            $reexpJns = strtoupper(trim((string)($deliveryInv['jenisSurat_reexp'] ?? '')));
            $reexpNo = trim((string)($deliveryInv['noSurat_reexp'] ?? ''));
            $kdDokInv = trim((string)($deliveryInv['kd_Dok'] ?? ''));

            if ($kdDokInv && in_array($kdDokInv, ['1', '2', '3', '4', '8', '9', '10', '13', '14', '20', '21', '26', '28', '35', '40', '41', '44', '64', '99'])) {
                $kodeDokOut = $kdDokInv;
                $noDokOut = $sppbNo ?: ($pibNo ?: $noDokOut);
                $tglDokOut = $sppbTgl ?: ($pibTgl ?: $tglDokOut);
                $namaDokOut = "Dokumen Pabean (Kode {$kodeDokOut})";
            } elseif (strpos($sppbJns, 'RE-EXP') !== false || strpos($reexpJns, 'RE-EXP') !== false || $reexpNo || (stripos($sppbNo, 'KBC') !== false && stripos($sppbNo, 'S-') !== false && ($sppbJns === 'RE-EXP' || strpos($sppbJns, 'EXP') !== false))) {
                // Kode 28: Dokumen BC 1.2 - Re-Ekspor (BC 1.2) Belum aju PIB
                $kodeDokOut = '28';
                $namaDokOut = 'Dokumen BC 1.2 - Re-Ekspor (BC 1.2)';
                $noDokOut = $sppbNo ?: ($reexpNo ?: $noDokOut);
                $tglDokOut = $sppbTgl ?: ($formatDmy($deliveryInv['tglSurat_reexp'] ?? null) ?: $tglDokOut);
            } elseif (strpos($sppbJns, 'BC 2.3') !== false || strpos($sppbJns, 'BC2.3') !== false || strpos($sppbJns, '2.3') !== false) {
                // Kode 2: SPPB BC 2.3 - Dok. SPPB BC 2.3
                $kodeDokOut = '2';
                $namaDokOut = 'SPPB BC 2.3';
                $noDokOut = $sppbNo ?: $noDokOut;
                $tglDokOut = $sppbTgl ?: $tglDokOut;
            } elseif (strpos($sppbJns, 'BC 1.5') !== false || strpos($sppbJns, '1.5') !== false) {
                // Kode 9: BCF 1.5 / Barang Tidak Dikuasai - Container Timbun Lewat Waktu
                $kodeDokOut = '9';
                $namaDokOut = 'BCF 1.5 (Barang Tidak Dikuasai)';
                $noDokOut = $sppbNo ?: $noDokOut;
                $tglDokOut = $sppbTgl ?: $tglDokOut;
            } elseif (strpos($sppbJns, 'BC 1.6') !== false || strpos($sppbJns, 'PLB') !== false) {
                // Kode 41: BC 1.6 - SPPB PLB - BC 1.6
                $kodeDokOut = '41';
                $namaDokOut = 'SPPB PLB - BC 1.6';
                $noDokOut = $sppbNo ?: $noDokOut;
                $tglDokOut = $sppbTgl ?: $tglDokOut;
            } elseif (strpos($sppbJns, 'LELANG') !== false) {
                // Kode 26: Surat Ijin Pengeluaran Barang Untuk Lelang
                $kodeDokOut = '26';
                $namaDokOut = 'Surat Ijin Pengeluaran Barang Untuk Lelang';
                $noDokOut = $sppbNo ?: $noDokOut;
                $tglDokOut = $sppbTgl ?: $tglDokOut;
            } elseif (strpos($sppbJns, 'CARNET') !== false) {
                // Kode 35: ATA CARNET Impor
                $kodeDokOut = '35';
                $namaDokOut = 'ATA CARNET Impor';
                $noDokOut = $sppbNo ?: $noDokOut;
                $tglDokOut = $sppbTgl ?: $tglDokOut;
            } elseif (strpos($sppbJns, 'RETURN') !== false) {
                // Kode 14: Returnable Package - Ijin Impor Returnable Package
                $kodeDokOut = '14';
                $namaDokOut = 'Ijin Impor Returnable Package';
                $noDokOut = $sppbNo ?: $noDokOut;
                $tglDokOut = $sppbTgl ?: $tglDokOut;
            } elseif (strpos($sppbJns, 'PPKEK') !== false) {
                // Kode 64: KEK - Pengeluaran ke KEK/TPB/FTZ
                $kodeDokOut = '64';
                $namaDokOut = 'Pengeluaran KEK';
                $noDokOut = $sppbNo ?: $noDokOut;
                $tglDokOut = $sppbTgl ?: $tglDokOut;
            } elseif ($contStatus === '4' || $cont['status'] === 'EMPTY') {
                // Kode 40: Pengeluaran Empty Container ex. Stripping
                $kodeDokOut = '40';
                $namaDokOut = 'Pengeluaran Empty Container ex. Stripping';
                $noDokOut = $sppbNo ?: ($noSJ ?: $noContClean);
                $tglDokOut = $sppbTgl ?: $tglSJ;
            } elseif (!empty($sppbNo) || !empty($pibNo)) {
                // Kode 1: SPPB BC 2.0 - Dok. SPPB PIB BC 2.0
                $kodeDokOut = '1';
                $namaDokOut = 'SPPB BC 2.0';
                $noDokOut = $sppbNo ?: $pibNo;
                $tglDokOut = $sppbTgl ?: ($pibTgl ?: $tglDokOut);
            }

            // Susun 6 Alur Riwayat Kontainer TPP
            $timeline = [];

            // -------------------------------------------------------------
            // ALUR 1: GATE IN PLP (IN TRAILER TRUK MASUK)
            // -------------------------------------------------------------
            $waktuGateIn = $formatDmyHis($cont['tglInDepo']);
            $hasGateIn = !empty($waktuGateIn);
            $nopolIn = trim((string)$cont['NoPolIn']);
            $noPlpDoc = $cont['noPLP'] ?: ($cont['NoBC11'] ?: '');
            $tglPlpDoc = $formatDmy($cont['tglPLP'] ?: $cont['tglBC11']);
            $noBl = $cont['NO_MASTER_BL_AWB'] ?: '';
            $tglBl = $formatDmy($cont['TGL_MASTER_BL_AWB']);

            // Sanitasi lokasi block untuk OpenAPI CEISA 4.0 (max 10 karakter)
            $yardBlockClean = trim((string)$cont['location']);
            if (strlen($yardBlockClean) > 10) {
                $yardBlockClean = trim(preg_replace('/^blok\s+/i', '', $yardBlockClean));
                if (strlen($yardBlockClean) > 10) {
                    $yardBlockClean = substr($yardBlockClean, 0, 10);
                }
            }

            $payload1 = [
                'departemen'      => 'TPP',
                'nomorKontainer'  => $noContClean,
                'ukuranKontainer' => $contSize,
                'jenisKontainer'  => $contStatus,
                'kodeTps'         => 'PSU0',
                'kodeGudang'      => 'CPSU',
                'kodeKegiatan'    => 5,
                'waktuKegiatan'   => $waktuGateIn
            ];
            if ($nopolIn) $payload1['nomorPolisi'] = $nopolIn;
            // Catatan: Pada Gate In PLP kontainer masih di atas trailer masuk (In Trailer), belum ditempatkan di yard (block/slot/tier tidak dikirim)
            if ($noPlpDoc) {
                $payload1['kodeDokumen'] = '3'; // Referensi PDF: Kode 3 = PLP - Dok. PLP/OB (A11)
                $payload1['nomorDokumen'] = $noPlpDoc;
                if ($tglPlpDoc) $payload1['tanggalDokumen'] = $tglPlpDoc;
            }
            if ($noBl) {
                $payload1['nomorBlAwb'] = $noBl;
                if ($tglBl) $payload1['tanggalBlAwb'] = $tglBl;
            }

            $timeline[] = [
                'step'            => 1,
                'kodeKegiatan'    => 5,
                'kegiatanLabel'   => 'Gate In PLP (In Trailer Truk Masuk)',
                'icon'            => '🚚',
                'badgeCategory'   => 'Masuk Depo',
                'available'       => $hasGateIn,
                'waktuKegiatan'   => $waktuGateIn,
                'nomorPolisi'     => $nopolIn,
                'nopolLabel'      => $nopolIn ? "In Trailer: {$nopolIn}" : '-',
                'kodeDokumen'     => '3',
                'namaDokumen'     => 'PLP - Dok. PLP/OB (A11)',
                'dokumenLabel'    => $noPlpDoc ? "PLP (Kode 3): {$noPlpDoc}" : ($noBl ? "B/L: {$noBl}" : '-'),
                'lokasiYard'      => '-', // Kontainer masih di trailer, belum di yard
                'deskripsi'       => "Truk masuk membawa kontainer dari Lini 1 ke Depo TPP (Waktu In Depo: {$waktuGateIn})",
                'is_sent'         => isset($sentKegiatan[5]),
                'sent_info'       => $sentKegiatan[5] ?? null,
                'payload'         => $hasGateIn ? $payload1 : null
            ];

            // -------------------------------------------------------------
            // ALUR 2: STACKING DISCHARGE LINI 2 (PENUMPUKAN YARD)
            // -------------------------------------------------------------
            $waktuStacking = $waktuGateIn;
            $hasStacking = $hasGateIn;
            $payload2 = [
                'departemen'      => 'TPP',
                'nomorKontainer'  => $noContClean,
                'ukuranKontainer' => $contSize,
                'jenisKontainer'  => $contStatus,
                'kodeTps'         => 'PSU0',
                'kodeGudang'      => 'CPSU',
                'kodeKegiatan'    => 17,
                'waktuKegiatan'   => $waktuStacking
            ];
            if ($yardBlockClean) $payload2['block'] = $yardBlockClean;
            if ($cont['slot']) $payload2['slot'] = substr(trim((string)$cont['slot']), 0, 5);
            if ($cont['tier']) $payload2['tier'] = substr(trim((string)$cont['tier']), 0, 5);
            if ($noPlpDoc) {
                $payload2['kodeDokumen'] = '3'; // Referensi PDF: Kode 3 = PLP - Dok. PLP/OB (A11)
                $payload2['nomorDokumen'] = $noPlpDoc;
                if ($tglPlpDoc) $payload2['tanggalDokumen'] = $tglPlpDoc;
            }
            if ($noBl) {
                $payload2['nomorBlAwb'] = $noBl;
                if ($tglBl) $payload2['tanggalBlAwb'] = $tglBl;
            }

            $timeline[] = [
                'step'            => 2,
                'kodeKegiatan'    => 17,
                'kegiatanLabel'   => 'Stacking Discharge Lapangan (Yard)',
                'icon'            => '🏗️',
                'badgeCategory'   => 'Yard Stacking',
                'available'       => $hasStacking,
                'waktuKegiatan'   => $waktuStacking,
                'nomorPolisi'     => '',
                'nopolLabel'      => '-',
                'kodeDokumen'     => '3',
                'namaDokumen'     => 'PLP - Dok. PLP/OB (A11)',
                'dokumenLabel'    => $noPlpDoc ? "PLP (Kode 3): {$noPlpDoc}" : '-',
                'lokasiYard'      => $cont['location'] ?: 'Blok Yard Depo',
                'deskripsi'       => "Penempatan kontainer di yard depo (" . ($cont['location'] ?: 'Yard Lapangan') . ")",
                'is_sent'         => isset($sentKegiatan[17]),
                'sent_info'       => $sentKegiatan[17] ?? null,
                'payload'         => $hasStacking ? $payload2 : null
            ];

            // -------------------------------------------------------------
            // ALUR 3: BEHANDLE LINI 2 (PEMERIKSAAN FISIK PABEAN)
            // -------------------------------------------------------------
            $waktuBehandle = '';
            $docBehandle = '';
            $tglDocBehandle = '';
            $hasBehandle = false;

            if ($behandle) {
                $waktuBehandle = $formatDmyHis($behandle['inputTime'] ?: ($behandle['tglInvPLP'] . ' 09:00:00'));
                $docBehandle = $behandle['noSPPB'] ?: ($behandle['noInvPLP'] ?: '');
                $tglDocBehandle = $formatDmy($behandle['tglSPPB'] ?: $behandle['tglInvPLP']);
                $hasBehandle = true;
            } elseif (!empty($cont['updateby_date']) && $cont['updateby_date'] !== '0000-00-00 00:00:00') {
                $waktuBehandle = $formatDmyHis($cont['updateby_date']);
                $docBehandle = 'Buka Segel / P2';
                $hasBehandle = true;
            }

            $payload3 = null;
            if ($hasBehandle) {
                $payload3 = [
                    'departemen'      => 'TPP',
                    'nomorKontainer'  => $noContClean,
                    'ukuranKontainer' => $contSize,
                    'jenisKontainer'  => $contStatus,
                    'kodeTps'         => 'PSU0',
                    'kodeGudang'      => 'CPSU',
                    'kodeKegiatan'    => 21,
                    'waktuKegiatan'   => $waktuBehandle
                ];
                // Referensi PDF: Kode 8 = PPB - Dok. Periksa Fisik
                $payload3['kodeDokumen'] = '8';
                $payload3['nomorDokumen'] = $docBehandle ?: ($noPlpDoc ?: 'PPB/BEHANDLE');
                if ($tglDocBehandle) {
                    $payload3['tanggalDokumen'] = $tglDocBehandle;
                } elseif ($tglPlpDoc) {
                    $payload3['tanggalDokumen'] = $tglPlpDoc;
                }
            }

            $timeline[] = [
                'step'            => 3,
                'kodeKegiatan'    => 21,
                'kegiatanLabel'   => 'Behandle Lini 2 (Pemeriksaan Fisik)',
                'icon'            => '🔍',
                'badgeCategory'   => 'Pemeriksaan Pabean',
                'available'       => $hasBehandle,
                'waktuKegiatan'   => $waktuBehandle ?: '-',
                'nomorPolisi'     => '',
                'nopolLabel'      => '-',
                'kodeDokumen'     => '8',
                'namaDokumen'     => 'PPB - Dok. Periksa Fisik',
                'dokumenLabel'    => $docBehandle ? "PPB (Kode 8): {$docBehandle}" : ($noPlpDoc ? "PLP (Kode 3): {$noPlpDoc}" : '-'),
                'lokasiYard'      => '-',
                'deskripsi'       => $hasBehandle ? "Pemeriksaan fisik pabean / buka segel (" . ($behandle['invType'] ?? 'Behandle') . ")" : 'Pemeriksaan fisik belum dilaksanakan / tidak ada data Behandle',
                'is_sent'         => isset($sentKegiatan[21]),
                'sent_info'       => $sentKegiatan[21] ?? null,
                'payload'         => $payload3
            ];

            // -------------------------------------------------------------
            // ALUR 4: STRIPPING STUFFING LINI 2 (BONGKAR KARGO)
            // -------------------------------------------------------------
            $waktuStripping = '';
            $docStripping = '';
            $tglDocStripping = '';
            $hasStripping = false;

            if ($stripping) {
                $waktuStripping = $formatDmyHis($stripping['inputTime'] ?: ($stripping['tglInvPLP'] . ' 08:30:00'));
                $docStripping = $stripping['noInvPLP'] ?: ($stripping['noSPPB'] ?: '');
                $tglDocStripping = $formatDmy($stripping['tglInvPLP'] ?: $stripping['tglSPPB']);
                $hasStripping = true;
            }

            $payload4 = null;
            if ($hasStripping) {
                $payload4 = [
                    'departemen'      => 'TPP',
                    'nomorKontainer'  => $noContClean,
                    'ukuranKontainer' => $contSize,
                    'jenisKontainer'  => $contStatus,
                    'kodeTps'         => 'PSU0',
                    'kodeGudang'      => 'CPSU',
                    'kodeKegiatan'    => 23,
                    'waktuKegiatan'   => $waktuStripping
                ];
                // Referensi PDF: Kode 3 = PLP - Dok. PLP/OB (A11) (Persetujuan Stripping Lini 2)
                $payload4['kodeDokumen'] = '3';
                $payload4['nomorDokumen'] = $noPlpDoc ?: ($docStripping ?: 'PLP/STRIPPING');
                if ($tglPlpDoc) {
                    $payload4['tanggalDokumen'] = $tglPlpDoc;
                } elseif ($tglDocStripping) {
                    $payload4['tanggalDokumen'] = $tglDocStripping;
                }
            }

            $cargoNote = $stripping ? trim(($stripping['jumlah'] ? $stripping['jumlah'] . ' ' . $stripping['satuan'] : '') . ' ' . ($stripping['namaBarang'] ?? '')) : '';

            $timeline[] = [
                'step'            => 4,
                'kodeKegiatan'    => 23,
                'kegiatanLabel'   => 'Stripping Stuffing Lini 2 (Bongkar Kargo)',
                'icon'            => '📦',
                'badgeCategory'   => 'Stripping Depo',
                'available'       => $hasStripping,
                'waktuKegiatan'   => $waktuStripping ?: '-',
                'nomorPolisi'     => '',
                'nopolLabel'      => '-',
                'kodeDokumen'     => '3',
                'namaDokumen'     => 'PLP - Dok. PLP/OB (A11)',
                'dokumenLabel'    => $docStripping ? "PLP / Inv: {$docStripping}" : ($noPlpDoc ? "PLP (Kode 3): {$noPlpDoc}" : '-'),
                'lokasiYard'      => '-',
                'deskripsi'       => $hasStripping ? "Pembongkaran muatan kargo (" . ($cargoNote ? substr($cargoNote, 0, 45) . '...' : 'Stripping PLP') . ")" : 'Bongkar kargo belum dilaksanakan / tidak ada data Stripping',
                'is_sent'         => isset($sentKegiatan[23]),
                'sent_info'       => $sentKegiatan[23] ?? null,
                'payload'         => $payload4
            ];

            // -------------------------------------------------------------
            // ALUR 5: TRUCK IN PENJEMPUT / PICKUP LINI 2
            // -------------------------------------------------------------
            $waktuTruckIn = '';
            $nopolPenjemput = '';
            $noSJ = '';
            $tglSJ = '';
            $hasTruckIn = false;

            if ($sj) {
                $waktuTruckIn = $formatDmyHis($sj['tglIN_truckingKosong'] ?: ($sj['tglSuratJalan'] ?: $sj['cetak']));
                $nopolPenjemput = trim((string)$sj['noPol']);
                $noSJ = trim((string)$sj['noSuratJalan']);
                $tglSJ = $formatDmy($sj['tglSuratJalan'] ?: $sj['cetak']);
                $hasTruckIn = !empty($waktuTruckIn);
            }

            $payload5 = null;
            if ($hasTruckIn) {
                $payload5 = [
                    'departemen'      => 'TPP',
                    'nomorKontainer'  => $noContClean,
                    'ukuranKontainer' => $contSize,
                    'jenisKontainer'  => $contStatus,
                    'kodeTps'         => 'PSU0',
                    'kodeGudang'      => 'CPSU',
                    'kodeKegiatan'    => 19, // 19 = TRUCK IN LINI 2
                    'waktuKegiatan'   => $waktuTruckIn
                ];
                if ($nopolPenjemput) $payload5['nomorPolisi'] = $nopolPenjemput;
                // Dokumen Pengeluaran resmi sesuai Referensi_Kode_Dokumen_TPS.pdf
                if ($noDokOut) {
                    $payload5['kodeDokumen'] = $kodeDokOut;
                    $payload5['nomorDokumen'] = $noDokOut;
                    if ($tglDokOut) $payload5['tanggalDokumen'] = $tglDokOut;
                }
            }

            $timeline[] = [
                'step'            => 5,
                'kodeKegiatan'    => 19,
                'kegiatanLabel'   => 'Truck In Penjemput / Pickup Lini 2',
                'icon'            => '🚛',
                'badgeCategory'   => 'Armada Penjemput',
                'available'       => $hasTruckIn,
                'waktuKegiatan'   => $waktuTruckIn ?: '-',
                'nomorPolisi'     => $nopolPenjemput,
                'nopolLabel'      => $nopolPenjemput ? "Truk Penjemput: {$nopolPenjemput}" : '-',
                'kodeDokumen'     => $kodeDokOut,
                'namaDokumen'     => $namaDokOut,
                'dokumenLabel'    => $noDokOut ? "{$namaDokOut} [Kode {$kodeDokOut}]: {$noDokOut}" : ($noSJ ? "SJ: {$noSJ}" : '-'),
                'lokasiYard'      => '-',
                'deskripsi'       => $hasTruckIn ? "Truk sasis kosong masuk depo untuk memuat kontainer (" . ($nopolPenjemput ?: 'Armada Penjemput') . ")" : 'Belum ada truk penjemput / Surat Jalan belum diterbitkan',
                'is_sent'         => isset($sentKegiatan[19]) || isset($sentKegiatan[20]),
                'sent_info'       => $sentKegiatan[19] ?? ($sentKegiatan[20] ?? null),
                'payload'         => $payload5
            ];

            // -------------------------------------------------------------
            // ALUR 6: GATE OUT LINI 2 (OUT TRAILER TRUK KELUAR)
            // -------------------------------------------------------------
            $waktuGateOut = '';
            $hasGateOut = false;

            if ($sj) {
                $waktuGateOut = $formatDmyHis($sj['tglSuratJalan'] ?: ($sj['cetak'] ?: $sj['tglIN_truckingKosong']));
                $hasGateOut = !empty($waktuGateOut);
            }

            $payload6 = null;
            if ($hasGateOut) {
                $payload6 = [
                    'departemen'      => 'TPP',
                    'nomorKontainer'  => $noContClean,
                    'ukuranKontainer' => $contSize,
                    'jenisKontainer'  => $contStatus,
                    'kodeTps'         => 'PSU0',
                    'kodeGudang'      => 'CPSU',
                    'kodeKegiatan'    => 6, // 6 = GATE OUT LINI 2
                    'waktuKegiatan'   => $waktuGateOut
                ];
                if ($nopolPenjemput) $payload6['nomorPolisi'] = $nopolPenjemput;
                // Dokumen Pengeluaran resmi sesuai Referensi_Kode_Dokumen_TPS.pdf
                if ($noDokOut) {
                    $payload6['kodeDokumen'] = $kodeDokOut;
                    $payload6['nomorDokumen'] = $noDokOut;
                    if ($tglDokOut) $payload6['tanggalDokumen'] = $tglDokOut;
                }
            }

            $timeline[] = [
                'step'            => 6,
                'kodeKegiatan'    => 6,
                'kegiatanLabel'   => 'Gate Out Lini 2 (Out Trailer Truk Keluar)',
                'icon'            => '🚚',
                'badgeCategory'   => 'Keluar Depo',
                'available'       => $hasGateOut,
                'waktuKegiatan'   => $waktuGateOut ?: '-',
                'nomorPolisi'     => $nopolPenjemput,
                'nopolLabel'      => $nopolPenjemput ? "Out Trailer: {$nopolPenjemput}" : '-',
                'kodeDokumen'     => $kodeDokOut,
                'namaDokumen'     => $namaDokOut,
                'dokumenLabel'    => $noDokOut ? "{$namaDokOut} [Kode {$kodeDokOut}]: {$noDokOut}" : ($noSJ ? "SJ: {$noSJ}" : '-'),
                'lokasiYard'      => '-', // Kosongkan posisi yard saat gate out
                'deskripsi'       => $hasGateOut ? "Truk keluar membawa kontainer meninggalkan depo (Dokumen: {$namaDokOut} - {$noDokOut})" : 'Kontainer belum keluar / Surat Jalan belum terbit',
                'is_sent'         => isset($sentKegiatan[6]),
                'sent_info'       => $sentKegiatan[6] ?? null,
                'payload'         => $payload6
            ];

            jsonResp([
                'success'    => true,
                'departemen' => 'TPP',
                'container'  => [
                    'nomorKontainer'  => $noContClean,
                    'ukuranKontainer' => $contSize,
                    'jenisKontainer'  => $contStatus,
                    'statusKontainer' => $cont['status'] ?: 'FCL',
                    'lokasiYard'      => $cont['location'] ?: '-',
                    'shipper'         => $cont['shipper'] ?: '-',
                    'inTrailer'       => $nopolIn ?: '-',
                    'outTrailer'      => $nopolPenjemput ?: '-',
                    'suratPlp'        => $noPlpDoc ?: '-',
                    'noBl'            => $noBl ?: '-',
                    'dokumenPengeluaran' => ($noDokOut ? "{$namaDokOut} ({$noDokOut})" : '-')
                ],
                'timeline'   => $timeline
            ]);

        } else {
            // =================================================================
            // ALUR KONTAINER GUDANG (LCL / DATABASE PRIMAMAS)
            // =================================================================
            if (!$pdo_primamas) {
                jsonResp(['success' => false, 'message' => 'Koneksi database Gudang (primamas) tidak tersedia'], 500);
            }

            $sqlGudang = "
                SELECT 
                    m.Id_MasBL,
                    REPLACE(REPLACE(k.No_Cont, '-', ''), ' ', '') AS container_no,
                    k.No_Cont AS raw_container_no,
                    k.Size AS size_type,
                    COALESCE(k.Type, 'LCL') AS status,
                    man.blok AS yard_block,
                    m.nopol_in,
                    m.nopol_out,
                    m.No_MasBL AS no_bl,
                    m.Tgl_MasBL AS tgl_bl,
                    H.NO_BC11 AS no_dokumen,
                    H.TGL_BC11 AS tgl_dokumen,
                    H.NO_PLP AS no_plp,
                    H.TGL_PLP AS tgl_plp,
                    CONCAT(m.tgl_datang_cont, ' ', IFNULL(m.jam_datang_cont, '00:00:00')) AS waktu_masuk,
                    CONCAT(man.Tgl_StrippingBC, ' ', IFNULL(man.jamStrippingBC, '00:00:00')) AS waktu_stripping,
                    CONCAT(m.tgl_keluar_cont, ' ', IFNULL(m.jam_keluar_cont, '00:00:00')) AS waktu_keluar
                FROM master_bl m
                INNER JOIN kontainer k ON m.Id_Kontainer_FK = k.Id_Kontainer
                LEFT JOIN manifest man ON man.Id_MasBL_FK = m.Id_MasBL
                LEFT JOIN tpsws_responplp_detail_backup D ON D.NO_BL_AWB = man.No_BL
                LEFT JOIN tpsws_responplp_header_backup H ON H.NO_SURAT = D.NO_SURAT_FK AND H.NO_PLP = D.NO_PLP_FK
                WHERE REPLACE(REPLACE(k.No_Cont, '-', ''), ' ', '') = :noCont
                ORDER BY m.Id_MasBL DESC
                LIMIT 1
            ";
            $stmtG = $pdo_primamas->prepare($sqlGudang);
            $stmtG->execute([':noCont' => $noContClean]);
            $gRow = $stmtG->fetch(PDO::FETCH_ASSOC);

            if (!$gRow) {
                jsonResp(['success' => false, 'message' => "Kontainer {$noContClean} tidak ditemukan di database Gudang."], 404);
            }

            $sz = (strpos($gRow['size_type'], '20') !== false) ? '20' : ((strpos($gRow['size_type'], '45') !== false) ? '45' : '40');
            $wMasuk = $formatDmyHis($gRow['waktu_masuk']);
            $wStripping = $formatDmyHis($gRow['waktu_stripping']);
            $wKeluar = $formatDmyHis($gRow['waktu_keluar']);
            $noBl = $gRow['no_bl'] ?: '';
            $tglBl = $formatDmy($gRow['tgl_bl']);

            $timelineG = [];

            // 1. Gate In Gudang LCL
            $dokInGudang = $gRow['no_plp'] ? '3' : '704';
            $noDokInGudang = $gRow['no_plp'] ?: $noBl;
            $tglDokInGudang = $gRow['no_plp'] ? $formatDmy($gRow['tgl_plp']) : $tglBl;

            $pIn = [
                'departemen'      => 'GUDANG',
                'nomorKontainer'  => $noContClean,
                'ukuranKontainer' => $sz,
                'jenisKontainer'  => '7', // 7 = LCL
                'kodeTps'         => 'PSU0',
                'kodeGudang'      => 'GPSU',
                'kodeKegiatan'    => 5,
                'waktuKegiatan'   => $wMasuk ?: date('d-m-Y H:i:s')
            ];
            if ($gRow['nopol_in']) $pIn['nomorPolisi'] = $gRow['nopol_in'];
            if ($noDokInGudang) {
                $pIn['kodeDokumen'] = $dokInGudang;
                $pIn['nomorDokumen'] = $noDokInGudang;
                if ($tglDokInGudang) $pIn['tanggalDokumen'] = $tglDokInGudang;
            }
            if ($noBl) {
                $pIn['nomorBlAwb'] = $noBl;
                if ($tglBl) $pIn['tanggalBlAwb'] = $tglBl;
            }
            $timelineG[] = [
                'step'          => 1,
                'kodeKegiatan'  => 5,
                'kegiatanLabel' => 'Gate In LCL Gudang (In Trailer)',
                'icon'          => '🚚',
                'badgeCategory' => 'Masuk Gudang',
                'available'     => !empty($wMasuk),
                'waktuKegiatan' => $wMasuk ?: '-',
                'nomorPolisi'   => $gRow['nopol_in'] ?: '',
                'nopolLabel'    => $gRow['nopol_in'] ? "In Trailer: {$gRow['nopol_in']}" : '-',
                'kodeDokumen'   => $dokInGudang,
                'namaDokumen'   => ($dokInGudang === '3' ? 'PLP - Dok. PLP/OB (A11)' : 'Master B/L'),
                'dokumenLabel'  => $gRow['no_plp'] ? "PLP (Kode 3): {$gRow['no_plp']}" : ($noBl ? "Master B/L: {$noBl}" : '-'),
                'lokasiYard'    => '-',
                'deskripsi'     => "Kontainer LCL masuk ke gudang untuk stripping muatan",
                'is_sent'       => isset($sentKegiatan[5]),
                'sent_info'     => $sentKegiatan[5] ?? null,
                'payload'       => !empty($wMasuk) ? $pIn : null
            ];

            // 2. Stripping Stuffing LCL Lini 2
            $pStr = [
                'departemen'      => 'GUDANG',
                'nomorKontainer'  => $noContClean,
                'ukuranKontainer' => $sz,
                'jenisKontainer'  => '7',
                'kodeTps'         => 'PSU0',
                'kodeGudang'      => 'GPSU',
                'kodeKegiatan'    => 23,
                'waktuKegiatan'   => $wStripping ?: ($wMasuk ?: date('d-m-Y H:i:s'))
            ];
            if ($noDokInGudang) {
                $pStr['kodeDokumen'] = $dokInGudang;
                $pStr['nomorDokumen'] = $noDokInGudang;
                if ($tglDokInGudang) $pStr['tanggalDokumen'] = $tglDokInGudang;
            }
            if ($noBl) {
                $pStr['nomorBlAwb'] = $noBl;
                if ($tglBl) $pStr['tanggalBlAwb'] = $tglBl;
            }
            $timelineG[] = [
                'step'          => 2,
                'kodeKegiatan'  => 23,
                'kegiatanLabel' => 'Stripping Stuffing LCL Lini 2',
                'icon'          => '📦',
                'badgeCategory' => 'Bongkar LCL',
                'available'     => !empty($wStripping),
                'waktuKegiatan' => $wStripping ?: '-',
                'nomorPolisi'   => '',
                'nopolLabel'    => '-',
                'kodeDokumen'   => $dokInGudang,
                'namaDokumen'   => ($dokInGudang === '3' ? 'PLP - Dok. PLP/OB (A11)' : 'Master B/L'),
                'dokumenLabel'  => $gRow['no_plp'] ? "PLP (Kode 3): {$gRow['no_plp']}" : ($noBl ? "Master B/L: {$noBl}" : '-'),
                'lokasiYard'    => '-',
                'deskripsi'     => "Pembongkaran kargo LCL di dalam gudang",
                'is_sent'       => isset($sentKegiatan[23]),
                'sent_info'     => $sentKegiatan[23] ?? null,
                'payload'       => !empty($wStripping) ? $pStr : null
            ];

            // 3. Gate Out Gudang (Empty Kontainer Keluar ex Stripping)
            // Referensi PDF: Kode 40 = Pengeluaran Empty Container ex. Stripping
            $pOut = [
                'departemen'      => 'GUDANG',
                'nomorKontainer'  => $noContClean,
                'ukuranKontainer' => $sz,
                'jenisKontainer'  => '4', // 4 = EMPTY
                'kodeTps'         => 'PSU0',
                'kodeGudang'      => 'GPSU',
                'kodeKegiatan'    => 6,
                'waktuKegiatan'   => $wKeluar ?: ($wStripping ?: date('d-m-Y H:i:s'))
            ];
            if ($gRow['nopol_out']) $pOut['nomorPolisi'] = $gRow['nopol_out'];
            $pOut['kodeDokumen'] = '40';
            $pOut['nomorDokumen'] = $noBl ?: ($gRow['no_dokumen'] ?: $noContClean);
            if ($tglBl) $pOut['tanggalDokumen'] = $tglBl;
            if ($noBl) {
                $pOut['nomorBlAwb'] = $noBl;
                if ($tglBl) $pOut['tanggalBlAwb'] = $tglBl;
            }

            $timelineG[] = [
                'step'          => 3,
                'kodeKegiatan'  => 6,
                'kegiatanLabel' => 'Gate Out Empty Gudang (Out Trailer)',
                'icon'          => '🚚',
                'badgeCategory' => 'Keluar Kosong',
                'available'     => !empty($wKeluar),
                'waktuKegiatan' => $wKeluar ?: '-',
                'nomorPolisi'   => $gRow['nopol_out'] ?: '',
                'nopolLabel'    => $gRow['nopol_out'] ? "Out Trailer: {$gRow['nopol_out']}" : '-',
                'kodeDokumen'   => '40',
                'namaDokumen'   => 'Pengeluaran Empty Container ex. Stripping',
                'dokumenLabel'  => "Empty ex. Stripping [Kode 40]: " . ($noBl ?: ($gRow['no_dokumen'] ?: $noContClean)),
                'lokasiYard'    => '-',
                'deskripsi'     => "Kontainer kosong (Empty) ex stripping keluar meninggalkan gudang/depo",
                'is_sent'       => isset($sentKegiatan[6]),
                'sent_info'     => $sentKegiatan[6] ?? null,
                'payload'       => !empty($wKeluar) ? $pOut : null
            ];

            jsonResp([
                'success'    => true,
                'departemen' => 'GUDANG',
                'container'  => [
                    'nomorKontainer'  => $noContClean,
                    'ukuranKontainer' => $sz,
                    'jenisKontainer'  => '7',
                    'statusKontainer' => 'LCL',
                    'lokasiYard'      => $gRow['yard_block'] ?: '-',
                    'inTrailer'       => $gRow['nopol_in'] ?: '-',
                    'outTrailer'      => $gRow['nopol_out'] ?: '-',
                    'noBl'            => $noBl ?: '-'
                ],
                'timeline'   => $timelineG
            ]);
        }

    } catch (Exception $e) {
        error_log("Error get_container_timeline: " . $e->getMessage());
        jsonResp([
            'success' => false,
            'message' => 'Gagal menelusuri alur kontainer: ' . $e->getMessage()
        ], 500);
    }
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

        // Filter Departemen Operasional (TPP vs GUDANG)
        $deptParam = strtolower(trim((string)input('dept', input('kodeGudang', ''))));
        if ($deptParam === 'tpp' || $deptParam === 'cpsu') {
            $where[] = "(raw_data LIKE '%\"kodeGudang\"%\"CPSU\"%' OR keterangan LIKE '%[TPP]%' OR (raw_data NOT LIKE '%\"kodeGudang\"%\"GPSU\"%' AND keterangan NOT LIKE '%[GUDANG]%'))";
        } elseif ($deptParam === 'gudang' || $deptParam === 'gpsu') {
            $where[] = "(raw_data LIKE '%\"kodeGudang\"%\"GPSU\"%' OR keterangan LIKE '%[GUDANG]%')";
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
            'tpp'      => 0,
            'gudang'   => 0,
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

            // Identifikasi Departemen (TPP vs GUDANG)
            $kg = strtoupper(trim((string)($payload['kodeGudang'] ?? '')));
            if ($kg === 'GPSU' || stripos($r['keterangan'], '[GUDANG]') !== false) {
                $deptName = 'GUDANG';
                $kodeGud = 'GPSU';
                $summary['gudang']++;
            } else {
                $deptName = 'TPP';
                $kodeGud = 'CPSU';
                $summary['tpp']++;
            }

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
                'dept'             => $deptName,
                'kode_gudang'      => $kodeGud,
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
