<?php
require_once __DIR__ . '/../includes/db.php';

echo "=== JENIS DOKUMEN (primamas) ===\n";
try {
    $rows = $pdo_primamas->query("SELECT * FROM jenis_dokumen")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo json_encode($r) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== SAMPLE MANIFEST / INVOICE DOCS (primamas) ===\n";
try {
    $rows2 = $pdo_primamas->query("SELECT JNS_SPPB, COUNT(*) as cnt FROM invoice_gudang GROUP BY JNS_SPPB ORDER BY cnt DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows2 as $r) {
        echo json_encode($r) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== SAMPLE MASTER BL (primamas) ===\n";
try {
    $rows3 = $pdo_primamas->query("SELECT No_Master_BL, BC11_No, BC11_Tgl FROM master_bl LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows3 as $r) {
        echo json_encode($r) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
