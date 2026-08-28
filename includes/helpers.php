<?php
/**
 * Helper Functions
 * Fungsi utilitas untuk dashboard
 */

/**
 * Kirim JSON response
 */
function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Format tanggal Indonesia (dd-MM-yyyy)
 */
function formatTanggal(?string $date): string
{
    if (empty($date)) return '-';
    try {
        $dt = new DateTime($date);
        return $dt->format('d-m-Y');
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Format angka Indonesia
 */
function formatAngka($number): string
{
    if (!is_numeric($number)) return (string)$number;
    return number_format((float)$number, 0, ',', '.');
}

/**
 * Sanitize output untuk mencegah XSS
 */
function e(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Ambil input GET/POST yang sudah di-sanitize
 */
function input(string $key, string $default = ''): string
{
    $value = $_REQUEST[$key] ?? $default;
    return trim(strip_tags($value));
}

/**
 * Generate breadcrumb dari endpoint path
 */
function endpointToTitle(string $endpoint): string
{
    return ucwords(str_replace(['-', '_'], ' ', $endpoint));
}

/**
 * Flatten nested JSON data ke array 1 dimensi untuk tabel
 */
function flattenData(array $data, string $prefix = ''): array
{
    $result = [];
    foreach ($data as $key => $value) {
        $newKey = $prefix ? $prefix . '.' . $key : $key;
        if (is_array($value) && !isSequentialArray($value)) {
            $result = array_merge($result, flattenData($value, $newKey));
        } else {
            $result[$newKey] = is_array($value) ? json_encode($value) : $value;
        }
    }
    return $result;
}

/**
 * Cek apakah array adalah sequential (indexed) atau associative
 */
function isSequentialArray(array $arr): bool
{
    if (empty($arr)) return true;
    return array_keys($arr) === range(0, count($arr) - 1);
}

/**
 * Definisi semua endpoint GET yang tersedia
 */
function getEndpointDefinitions(): array
{
    return [
        'impor' => [
            'label' => 'Impor',
            'icon' => '📦',
            'endpoints' => [
                'get-impor-sppb' => [
                    'label' => 'SPPB Impor',
                    'description' => 'Download data SPPB berdasarkan nomor dokumen, tanggal, dan NPWP importir',
                    'params' => [
                        ['name' => 'nomorDokumen', 'label' => 'Nomor Dokumen', 'type' => 'text', 'required' => true, 'placeholder' => '150404/KPU.1/2026'],
                        ['name' => 'tanggalDokumen', 'label' => 'Tanggal Dokumen', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                        ['name' => 'npwpImp', 'label' => 'NPWP Importir', 'type' => 'text', 'required' => true, 'placeholder' => '0013859574091000000000'],
                    ],
                ],
                'get-impor-permit' => [
                    'label' => 'SPPB Permit (Gudang)',
                    'description' => 'Download data SPPB berdasarkan kode gudang',
                    'params' => [
                        ['name' => 'kodeGudang', 'label' => 'Kode Gudang', 'type' => 'text', 'required' => true, 'placeholder' => 'TPK1'],
                    ],
                ],
                'get-impor-permit-fasp' => [
                    'label' => 'SPPB Permit FASP (TPS)',
                    'description' => 'Download data SPPB berdasarkan kode TPS',
                    'params' => [
                        ['name' => 'kodeTps', 'label' => 'Kode TPS', 'type' => 'text', 'required' => true, 'placeholder' => 'KOJA'],
                    ],
                ],
                'get-impor-permit-200' => [
                    'label' => 'SPPB Permit 200 Rows',
                    'description' => 'Download data SPPB (max 200 rows) berdasarkan kode gudang',
                    'params' => [
                        ['name' => 'kodeGudang', 'label' => 'Kode Gudang', 'type' => 'text', 'required' => true, 'placeholder' => 'TPK1'],
                    ],
                ],
                'get-impor-bc11' => [
                    'label' => 'Data BC11 Impor',
                    'description' => 'Download data BC11 berdasarkan nomor dan tanggal BC11',
                    'params' => [
                        ['name' => 'nomorBc11', 'label' => 'Nomor BC11', 'type' => 'text', 'required' => true],
                        ['name' => 'tanggalBc11', 'label' => 'Tanggal BC11', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                    ],
                ],
                'get-sppb-bc23' => [
                    'label' => 'SPPB BC23',
                    'description' => 'Data SPPB BC 2.3',
                    'params' => [
                        ['name' => 'noSppb', 'label' => 'Nomor SPPB', 'type' => 'text', 'required' => true],
                        ['name' => 'tglSppb', 'label' => 'Tanggal SPPB', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                        ['name' => 'npwpImp', 'label' => 'NPWP Importir', 'type' => 'text', 'required' => true],
                    ],
                ],
                'get-bc23-permit' => [
                    'label' => 'BC23 Permit (Gudang)',
                    'description' => 'Data BC23 berdasarkan kode gudang',
                    'params' => [
                        ['name' => 'kodeGudang', 'label' => 'Kode Gudang', 'type' => 'text', 'required' => true, 'placeholder' => 'DOJA'],
                    ],
                ],
                'get-bc23-permit-fasp' => [
                    'label' => 'BC23 Permit FASP (TPS)',
                    'description' => 'Data BC23 berdasarkan kode TPS',
                    'params' => [
                        ['name' => 'kodeTps', 'label' => 'Kode TPS', 'type' => 'text', 'required' => true],
                    ],
                ],
                'get-sppb12-tps-asal' => [
                    'label' => 'SPPB BC1.2 TPS Asal',
                    'description' => 'Get SPPB BC1.2 data by Kode TPS Asal',
                    'params' => [
                        ['name' => 'kodeTpsAsal', 'label' => 'Kode TPS Asal', 'type' => 'text', 'required' => true],
                    ],
                ],
                'get-sppb12-tps-tujuan' => [
                    'label' => 'SPPB BC1.2 TPS Tujuan',
                    'description' => 'Get SPPB BC1.2 data by Kode TPS Tujuan',
                    'params' => [
                        ['name' => 'kodeTpsTujuan', 'label' => 'Kode TPS Tujuan', 'type' => 'text', 'required' => true],
                    ],
                ],
                'get-respon-penolakan-bc12' => [
                    'label' => 'Respon Penolakan BC12',
                    'description' => 'Download data Respon NP4 Bc12 yang sudah diproses',
                    'params' => [
                        ['name' => 'kodeTps', 'label' => 'Kode TPS', 'type' => 'text', 'required' => true],
                    ],
                ],
            ],
        ],
        'ekspor' => [
            'label' => 'Ekspor',
            'icon' => '🚢',
            'endpoints' => [
                'get-ekspor-npe' => [
                    'label' => 'NPE Ekspor',
                    'description' => 'Download data NPE berdasarkan NPWP, nomor NPE, dan kode kantor',
                    'params' => [
                        ['name' => 'nomorNpe', 'label' => 'Nomor NPE', 'type' => 'text', 'required' => true, 'placeholder' => '223460/PM/KPU.1/2026'],
                        ['name' => 'kodeKantor', 'label' => 'Kode Kantor', 'type' => 'text', 'required' => true, 'placeholder' => '040300'],
                        ['name' => 'npwp', 'label' => 'NPWP Eksportir', 'type' => 'text', 'required' => true],
                    ],
                ],
                'get-ekspor-peb' => [
                    'label' => 'PEB Ekspor',
                    'description' => 'Download data PEB berdasarkan NPWP, nomor PEB, dan tanggal PEB',
                    'params' => [
                        ['name' => 'nomorPeb', 'label' => 'Nomor PEB', 'type' => 'text', 'required' => true],
                        ['name' => 'tanggalPeb', 'label' => 'Tanggal PEB', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                        ['name' => 'npwp', 'label' => 'NPWP', 'type' => 'text', 'required' => true],
                    ],
                ],
                'get-ekspor-pkbe' => [
                    'label' => 'PKBE Ekspor',
                    'description' => 'Download data PKBE berdasarkan kode kantor, nomor PKBE, dan tanggal',
                    'params' => [
                        ['name' => 'nomorPkbe', 'label' => 'Nomor PKBE', 'type' => 'text', 'required' => true],
                        ['name' => 'tanggalPkbe', 'label' => 'Tanggal PKBE', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                        ['name' => 'kodeKantor', 'label' => 'Kode Kantor', 'type' => 'text', 'required' => true, 'placeholder' => '040300'],
                    ],
                ],
                'get-ekspor-permit-fnpe' => [
                    'label' => 'NPE Permit FNPE',
                    'description' => 'Download data NPE berdasarkan nomor NPE, NPWP, dan kode kantor',
                    'params' => [
                        ['name' => 'nomorNpe', 'label' => 'Nomor NPE', 'type' => 'text', 'required' => true],
                        ['name' => 'npwp', 'label' => 'NPWP', 'type' => 'text', 'required' => true],
                        ['name' => 'kodeKantor', 'label' => 'Kode Kantor', 'type' => 'text', 'required' => true, 'placeholder' => '040300'],
                    ],
                ],
                'get-npe' => [
                    'label' => 'Cek NPE',
                    'description' => 'Mendapatkan nomor dan tanggal NPE',
                    'params' => [
                        ['name' => 'kodeKantor', 'label' => 'Kode Kantor', 'type' => 'text', 'required' => false],
                        ['name' => 'nomorNpe', 'label' => 'Nomor NPE (6 digit)', 'type' => 'text', 'required' => false],
                        ['name' => 'npwpEksportir', 'label' => 'NPWP Eksportir', 'type' => 'text', 'required' => false],
                    ],
                ],
                'cek-npe' => [
                    'label' => 'Validasi NPE',
                    'description' => 'Cek validitas data NPE',
                    'params' => [
                        ['name' => 'npwpEks', 'label' => 'NPWP Eksportir', 'type' => 'text', 'required' => true],
                        ['name' => 'nomorNpe', 'label' => 'Nomor NPE', 'type' => 'text', 'required' => true],
                        ['name' => 'tanggalNpe', 'label' => 'Tanggal NPE', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                    ],
                ],
            ],
        ],
        'plp' => [
            'label' => 'PLP',
            'icon' => '📋',
            'endpoints' => [
                'get-respon-plp' => [
                    'label' => 'Respon PLP',
                    'description' => 'Download data respon PLP yang sudah diproses',
                    'params' => [
                        ['name' => 'kodeTps', 'label' => 'Kode TPS', 'type' => 'text', 'required' => true, 'placeholder' => 'KOJA'],
                    ],
                ],
                'get-respon-plp-tujuan' => [
                    'label' => 'Respon PLP Tujuan',
                    'description' => 'Download data respon PLP yang sudah disetujui oleh TPS tujuan',
                    'params' => [
                        ['name' => 'kodeTps', 'label' => 'Kode TPS', 'type' => 'text', 'required' => true],
                    ],
                ],
                'get-respon-plp-tujuan-v2' => [
                    'label' => 'Respon PLP Tujuan V2',
                    'description' => 'Download data respon PLP tujuan (versi 2)',
                    'params' => [
                        ['name' => 'kodeTps', 'label' => 'Kode TPS', 'type' => 'text', 'required' => true, 'placeholder' => 'JICT'],
                    ],
                ],
                'get-respon-plp-on-demand' => [
                    'label' => 'PLP On-Demand',
                    'description' => 'Download data PLP berdasarkan nomor/tanggal PLP atau RefNumber',
                    'params' => [
                        ['name' => 'nomorPlp', 'label' => 'Nomor PLP', 'type' => 'text', 'required' => false],
                        ['name' => 'tanggalPlp', 'label' => 'Tanggal PLP', 'type' => 'date', 'required' => false, 'format' => 'dd-MM-yyyy'],
                        ['name' => 'nomorReference', 'label' => 'Nomor Reference', 'type' => 'text', 'required' => false],
                        ['name' => 'kodeGudang', 'label' => 'Kode Gudang', 'type' => 'text', 'required' => true],
                    ],
                ],
                'get-respon-batal-plp' => [
                    'label' => 'Respon Batal PLP',
                    'description' => 'Ambil data persetujuan pembatalan PLP',
                    'params' => [],
                ],
                'get-respon-batal-plp-tujuan' => [
                    'label' => 'Respon Batal PLP Tujuan',
                    'description' => 'Ambil data respon batal PLP tujuan',
                    'params' => [],
                ],
                'get-respon-batal-plp-on-demand' => [
                    'label' => 'Batal PLP On-Demand',
                    'description' => 'Download data batal PLP on demand',
                    'params' => [
                        ['name' => 'nomorBatalPlp', 'label' => 'Nomor Batal PLP', 'type' => 'text', 'required' => true],
                        ['name' => 'tanggalBatalPlp', 'label' => 'Tanggal Batal PLP', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                        ['name' => 'kodeGudang', 'label' => 'Kode Gudang', 'type' => 'text', 'required' => true],
                        ['name' => 'refNumber', 'label' => 'Ref Number', 'type' => 'text', 'required' => false],
                    ],
                ],
                'get-pendukung-plp' => [
                    'label' => 'Data Pendukung PLP',
                    'description' => 'Download data pendukung PLP',
                    'params' => [
                        ['name' => 'nomorBc11', 'label' => 'Nomor BC11', 'type' => 'text', 'required' => true],
                        ['name' => 'tanggalBc11', 'label' => 'Tanggal BC11', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                        ['name' => 'nomorKontainer', 'label' => 'Nomor Kontainer', 'type' => 'text', 'required' => true],
                    ],
                ],
                'get-pendukung-plp-bl' => [
                    'label' => 'Pendukung PLP + BL',
                    'description' => 'Download data pendukung PLP dengan data BL',
                    'params' => [
                        ['name' => 'nomorBc11', 'label' => 'Nomor BC11', 'type' => 'text', 'required' => false],
                        ['name' => 'tanggalBc11', 'label' => 'Tanggal BC11', 'type' => 'date', 'required' => false, 'format' => 'dd-MM-yyyy'],
                        ['name' => 'nomorKontainer', 'label' => 'Nomor Kontainer', 'type' => 'text', 'required' => false],
                    ],
                ],
            ],
        ],
        'dokumen' => [
            'label' => 'Dokumen Pabean',
            'icon' => '📄',
            'endpoints' => [
                'get-dokumen-pabean-permit' => [
                    'label' => 'Dokumen Pabean (Gudang)',
                    'description' => 'Download data dokumen pabean berdasarkan kode gudang',
                    'params' => [
                        ['name' => 'kodeGudang', 'label' => 'Kode Gudang', 'type' => 'text', 'required' => true],
                    ],
                ],
                'get-dokumen-pabean-permit-fasp' => [
                    'label' => 'Dokumen Pabean FASP (TPS)',
                    'description' => 'Download data dokumen pabean berdasarkan kode TPS',
                    'params' => [
                        ['name' => 'kodeTps', 'label' => 'Kode TPS', 'type' => 'text', 'required' => true, 'placeholder' => 'DOJA'],
                    ],
                ],
                'get-dokumen-pabean-ondemand' => [
                    'label' => 'Dokumen Pabean On-Demand',
                    'description' => 'Download data dokumen pabean on demand',
                    'params' => [
                        ['name' => 'kodeDokumen', 'label' => 'Kode Dokumen', 'type' => 'text', 'required' => true],
                        ['name' => 'nomorDokumen', 'label' => 'Nomor Dokumen', 'type' => 'text', 'required' => true],
                        ['name' => 'tanggalDokumen', 'label' => 'Tanggal Dokumen', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                    ],
                ],
                'get-dokumen-manual' => [
                    'label' => 'Dokumen Manual',
                    'description' => 'Download data dokumen manual',
                    'params' => [
                        ['name' => 'kodeTps', 'label' => 'Kode TPS', 'type' => 'text', 'required' => true, 'placeholder' => 'KOJA'],
                    ],
                ],
                'get-dokumen-manual-ondemand' => [
                    'label' => 'Dokumen Manual On-Demand',
                    'description' => 'Download data dokumen manual on demand',
                    'params' => [
                        ['name' => 'kodeDokumen', 'label' => 'Kode Dokumen', 'type' => 'text', 'required' => true, 'placeholder' => '18'],
                        ['name' => 'nomorDokumen', 'label' => 'Nomor Dokumen', 'type' => 'text', 'required' => true],
                        ['name' => 'tanggalDokumen', 'label' => 'Tanggal Dokumen', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                    ],
                ],
                'get-batal-pabean-permit' => [
                    'label' => 'Batal Pabean Permit',
                    'description' => 'Ambil data batal pabean permit',
                    'params' => [
                        ['name' => 'kodeGudang', 'label' => 'Kode Gudang', 'type' => 'text', 'required' => true, 'placeholder' => 'KOJA'],
                    ],
                ],
                'get-batal-pabean-on-demand' => [
                    'label' => 'Batal Pabean On-Demand',
                    'description' => 'Ambil data gagal/batal pabean on demand',
                    'params' => [
                        ['name' => 'nomorSppb', 'label' => 'Nomor SPPB', 'type' => 'text', 'required' => true],
                        ['name' => 'tanggalSppb', 'label' => 'Tanggal SPPB', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                    ],
                ],
                'get-sp3b-pel-bongkar-akhir' => [
                    'label' => 'SP3B Pelabuhan Bongkar Akhir',
                    'description' => 'Download data SP3B berdasarkan Kode Pelabuhan Bongkar Akhir',
                    'params' => [
                        ['name' => 'kodePelabuhanAkhir', 'label' => 'Kode Pelabuhan Akhir', 'type' => 'text', 'required' => true],
                    ],
                ],
                'get-sp3b-ondemand' => [
                    'label' => 'SP3B On Demand',
                    'description' => 'Download data SP3B on demand',
                    'params' => [
                        ['name' => 'kodeDokumen', 'label' => 'Kode Dokumen', 'type' => 'text', 'required' => false],
                        ['name' => 'nomorSP3B', 'label' => 'Nomor SP3B', 'type' => 'text', 'required' => false],
                        ['name' => 'tanggalSP3B', 'label' => 'Tanggal SP3B', 'type' => 'date', 'required' => false, 'format' => 'dd-MM-yyyy'],
                    ],
                ],
                'get-sp3b-tps-bongkar' => [
                    'label' => 'SP3B TPS Bongkar',
                    'description' => 'Download data SP3B berdasarkan TPS Bongkar (BC11)',
                    'params' => [
                        ['name' => 'nomorBc11', 'label' => 'Nomor BC11', 'type' => 'text', 'required' => true],
                        ['name' => 'tanggalBc11', 'label' => 'Tanggal BC11', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                    ],
                ],
                'get-manifes' => [
                    'label' => 'Manifes',
                    'description' => 'Mendapatkan data manifes berdasarkan BC 1.1',
                    'params' => [
                        ['name' => 'nomorBc11', 'label' => 'Nomor BC11', 'type' => 'text', 'required' => true],
                        ['name' => 'tanggalBc11', 'label' => 'Tanggal BC11', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                        ['name' => 'nomorPos', 'label' => 'Nomor Pos', 'type' => 'text', 'required' => true],
                        ['name' => 'kelompokPos', 'label' => 'Kelompok Pos', 'type' => 'text', 'required' => true],
                        ['name' => 'kodeKantor', 'label' => 'Kode Kantor', 'type' => 'text', 'required' => true],
                    ],
                ],
            ],
        ],
        'monitoring' => [
            'label' => 'Monitoring',
            'icon' => '📊',
            'endpoints' => [
                'cek-data-terkirim' => [
                    'label' => 'Cek Data Terkirim',
                    'description' => 'Cek jumlah data yang sudah terkirim',
                    'params' => [
                        ['name' => 'tanggalAwal', 'label' => 'Tanggal Awal', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                        ['name' => 'tanggalAkhir', 'label' => 'Tanggal Akhir', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                    ],
                ],
                'cek-data-sppb' => [
                    'label' => 'Cek Data SPPB',
                    'description' => 'Cek jumlah data SPPB berdasarkan tanggal',
                    'params' => [
                        ['name' => 'tanggalSPPB', 'label' => 'Tanggal SPPB', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                    ],
                ],
                'cek-data-sppb-tpb' => [
                    'label' => 'Cek Data SPPB TPB',
                    'description' => 'Cek jumlah data SPPB TPB berdasarkan tanggal',
                    'params' => [
                        ['name' => 'tanggalSPPB', 'label' => 'Tanggal SPPB', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                    ],
                ],
                'cek-data-gagal-kirim' => [
                    'label' => 'Cek Data Gagal Kirim',
                    'description' => 'Cek jumlah data yang gagal terkirim',
                    'params' => [
                        ['name' => 'tanggalAwal', 'label' => 'Tanggal Awal', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                        ['name' => 'tanggalAkhir', 'label' => 'Tanggal Akhir', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                    ],
                ],
                'get-reject-data' => [
                    'label' => 'Data Reject',
                    'description' => 'Ambil data yang di-reject',
                    'params' => [
                        ['name' => 'kodeTps', 'label' => 'Kode TPS', 'type' => 'text', 'required' => true, 'placeholder' => 'KOJA'],
                    ],
                ],
                'get-info-nomor-bc11' => [
                    'label' => 'Info Nomor BC11',
                    'description' => 'Mendapatkan info nomor BC11 berdasarkan tanggal tiba kapal/pesawat',
                    'params' => [
                        ['name' => 'tanggalTibaAwal', 'label' => 'Tanggal Tiba Awal', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                        ['name' => 'tanggalTibaAkhir', 'label' => 'Tanggal Tiba Akhir', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                    ],
                ],
                'get-spjm' => [
                    'label' => 'Data SPJM',
                    'description' => 'Download data barang terkena SPJM',
                    'params' => [
                        ['name' => 'kodeTps', 'label' => 'Kode TPS', 'type' => 'text', 'required' => false, 'placeholder' => 'DOJA'],
                    ],
                ],
                'get-spjm-ondemand' => [
                    'label' => 'SPJM On-Demand',
                    'description' => 'Download data SPJM berdasarkan No.PIB dan Tgl.PIB',
                    'params' => [
                        ['name' => 'nomorDaftar', 'label' => 'Nomor Daftar (PIB)', 'type' => 'text', 'required' => true],
                        ['name' => 'tanggalDaftar', 'label' => 'Tanggal Daftar', 'type' => 'date', 'required' => true, 'format' => 'dd-MM-yyyy'],
                    ],
                ],
                'get-data-ob' => [
                    'label' => 'Data OB / Pindah TPS',
                    'description' => 'Download data OB/Pindah TPS',
                    'params' => [
                        ['name' => 'kodeTps', 'label' => 'Kode TPS', 'type' => 'text', 'required' => true, 'placeholder' => 'DOJA'],
                    ],
                ],
                'tps-tracking' => [
                    'label' => 'Tracking TPS',
                    'description' => 'Mengambil riwayat tracking pergerakan kontainer',
                    'params' => [
                        ['name' => 'nomorKontainer', 'label' => 'Nomor Kontainer', 'type' => 'text', 'required' => true],
                        ['name' => 'nomorBlAwb', 'label' => 'Nomor BL/AWB', 'type' => 'text', 'required' => false],
                        ['name' => 'tanggalBlAwb', 'label' => 'Tanggal BL/AWB', 'type' => 'date', 'required' => false, 'format' => 'dd-MM-yyyy'],
                        ['name' => 'limit', 'label' => 'Limit', 'type' => 'number', 'required' => false],
                    ],
                ],
            ],
        ],
    ];

}
