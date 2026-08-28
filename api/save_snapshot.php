<?php
/**
 * API untuk menyimpan snapshot dari CCTV (Motion Detection)
 * ke hardisk server
 */
require_once __DIR__ . '/../includes/session.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['image']) || !preg_match('/^data:image\/(\w+);base64,/', $input['image'], $type)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid image data']));
}

$image_type = strtolower($type[1]); // jpg, jpeg, png
if (!in_array($image_type, ['jpg', 'jpeg', 'png'])) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Unsupported image type']));
}

$image_data = substr($input['image'], strpos($input['image'], ',') + 1);
$image_data = base64_decode($image_data);

if ($image_data === false) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Base64 decode failed']));
}

$upload_dir = __DIR__ . '/../uploads/cctv_motion/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Format: cam101_20260828_154219.jpg
$channel = isset($input['channel']) ? preg_replace('/[^0-9]/', '', $input['channel']) : 'unknown';
$filename = 'cam' . $channel . '_' . date('Ymd_His') . '_' . uniqid() . '.' . $image_type;
$file_path = $upload_dir . $filename;

if (file_put_contents($file_path, $image_data)) {
    echo json_encode([
        'status' => 'success', 
        'message' => 'Snapshot saved', 
        'file' => 'uploads/cctv_motion/' . $filename
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save file on server']);
}
