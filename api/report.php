<?php
/**
 * API Laporan Pengiriman CEISA 4.0
 * Endpoint: /cek-data-terkirim
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/CeisaClient.php';

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
