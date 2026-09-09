<?php
/**
 * API Handler: Permohonan PLP (POST /permohonan-plp)
 * CEISA 4.0 TPS Online
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/CeisaClient.php';

requireAuth();

header('Content-Type: application/json; charset=utf-8');

$action = input('action', 'send');

try {
    // Pastikan tabel ceisa_permohonan_plp ada
    ensurePermohonanTable($pdo_tpsonline);

    if ($action === 'send') {
        handleSend();
    } elseif ($action === 'history') {
        handleHistory();
    } elseif ($action === 'generate_ref') {
        handleGenerateRef();
    } else {
        jsonResponse(['success' => false, 'message' => 'Aksi tidak valid: ' . $action], 400);
    }
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Kesalahan server: ' . $e->getMessage()
    ], 500);
}

function ensurePermohonanTable($pdo)
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ceisa_permohonan_plp (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ref_number VARCHAR(50) NOT NULL,
            no_surat VARCHAR(100) NULL,
            tgl_surat DATE NULL,
            kd_tps_asal VARCHAR(10) NULL,
            kd_gudang_asal VARCHAR(10) NULL,
            kd_tps_tujuan VARCHAR(10) NULL,
            kd_gudang_tujuan VARCHAR(10) NULL,
            no_bc11 VARCHAR(20) NULL,
            tgl_bc11 DATE NULL,
            nama_angkut VARCHAR(100) NULL,
            no_voy_flight VARCHAR(50) NULL,
            total_kontainer INT DEFAULT 0,
            total_kemasan INT DEFAULT 0,
            status_kirim VARCHAR(30) DEFAULT 'DRAFT',
            http_code INT NULL,
            raw_payload JSON NULL,
            raw_response JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ref (ref_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

function handleGenerateRef()
{
    $date = date('ymd');
    $refs = [
        "PSU0-PLP-{$date}-001",
        "PSU0-PLP-{$date}-002",
        "PSU0-PLP-{$date}-003"
    ];
    jsonResponse(['success' => true, 'refNumbers' => $refs]);
}

function handleHistory()
{
    global $pdo_tpsonline;
    $stmt = $pdo_tpsonline->query("SELECT * FROM ceisa_permohonan_plp ORDER BY id DESC LIMIT 50");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(['success' => true, 'data' => $rows]);
}

function handleSend()
{
    global $pdo_tpsonline;

    $rawInput = file_get_contents('php://input');
    $payload = json_decode($rawInput, true);

    if (empty($payload) || !isset($payload['header'])) {
        jsonResponse(['success' => false, 'message' => 'Format payload tidak valid. Diperlukan objek header & detil.'], 400);
    }

    $header = $payload['header'];
    $refNumber = $header['refNumber'] ?? ('PSU0-PLP-' . date('ymd') . '-001');
    $noSurat = $header['nomorSurat'] ?? '-';
    $tglSurat = parseDateDb($header['tanggalSurat'] ?? date('d-m-Y'));
    $tpsAsal = $header['kodeTpsAsal'] ?? 'KOJA';
    $gdgAsal = $header['gudangAsal'] ?? 'TPK1';
    $tpsTujuan = $header['kodeTpsTujuan'] ?? 'PSU0';
    $gdgTujuan = $header['gudangTujuan'] ?? 'GPSU';
    $noBc11 = $header['nomorBc11'] ?? '-';
    $tglBc11 = parseDateDb($header['tanggalBc11'] ?? date('d-m-Y'));
    $namaAngkut = $header['namaAngkut'] ?? '-';
    $voy = $header['noVoyFlight'] ?? '-';

    $detil = $payload['detil'] ?? [];
    $totalCont = 0;
    $totalKem = 0;
    if (isset($detil[0])) {
        $totalCont = count($detil[0]['kontainer'] ?? []);
        $totalKem = count($detil[0]['kemasan'] ?? []);
    } elseif (isset($detil['kontainer']) || isset($detil['kemasan'])) {
        $totalCont = count($detil['kontainer'] ?? []);
        $totalKem = count($detil['kemasan'] ?? []);
    }

    // Kirim via CeisaClient
    $client = new CeisaClient();
    $res = $client->post('permohonan-plp', $payload);

    $httpCode = $res['code'] ?? 0;
    $statusKirim = ($res['success'] ?? false) ? 'SUCCESS' : 'FAILED';

    // Simpan ke database
    try {
        $stmt = $pdo_tpsonline->prepare("
            INSERT INTO ceisa_permohonan_plp (
                ref_number, no_surat, tgl_surat, kd_tps_asal, kd_gudang_asal,
                kd_tps_tujuan, kd_gudang_tujuan, no_bc11, tgl_bc11, nama_angkut,
                no_voy_flight, total_kontainer, total_kemasan, status_kirim, http_code,
                raw_payload, raw_response, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $refNumber, $noSurat, $tglSurat, $tpsAsal, $gdgAsal,
            $tpsTujuan, $gdgTujuan, $noBc11, $tglBc11, $namaAngkut,
            $voy, $totalCont, $totalKem, $statusKirim, $httpCode,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            json_encode($res, JSON_UNESCAPED_UNICODE)
        ]);

        // Simpan juga ke ceisa_api_logs
        $stmtLog = $pdo_tpsonline->prepare("
            INSERT INTO ceisa_api_logs (endpoint, method, status, request_data, response_data, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmtLog->execute([
            'permohonan-plp', 'POST', $httpCode,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            json_encode($res, JSON_UNESCAPED_UNICODE)
        ]);
    } catch (Exception $dbErr) {
        error_log("Gagal menyimpan riwayat permohonan-plp: " . $dbErr->getMessage());
    }

    jsonResponse([
        'success' => $res['success'] ?? false,
        'code' => $httpCode,
        'message' => $res['message'] ?? 'Response diterima dari Bea Cukai',
        'result' => $res['result'] ?? '',
        'data' => $res['data'] ?? null,
        'raw_response' => $res
    ], $httpCode > 0 ? $httpCode : 200);
}

function parseDateDb($dateStr)
{
    if (empty($dateStr)) return null;
    $parts = explode('-', trim($dateStr));
    if (count($parts) === 3) {
        if (strlen($parts[2]) === 4) {
            return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
    }
    return $dateStr;
}
