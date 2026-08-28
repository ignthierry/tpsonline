<?php
/**
 * Export Handler
 * Export data ke CSV
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

// Ambil data JSON yang dikirim via POST
$input = json_decode(file_get_contents('php://input'), true);
$rows = $input['rows'] ?? [];
$filename = $input['filename'] ?? 'export_ceisa';
$filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);

if (empty($rows)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tidak ada data untuk di-export']);
    exit;
}

// Generate CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Ymd_His') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// BOM untuk UTF-8 Excel compatibility
fwrite($output, "\xEF\xBB\xBF");

// Header kolom
if (!empty($rows[0])) {
    $headers = array_keys($rows[0]);
    fputcsv($output, $headers);
}

// Baris data
foreach ($rows as $row) {
    $values = [];
    foreach ($row as $value) {
        if (is_array($value)) {
            $values[] = json_encode($value, JSON_UNESCAPED_UNICODE);
        } else {
            $values[] = $value;
        }
    }
    fputcsv($output, $values);
}

fclose($output);
exit;
