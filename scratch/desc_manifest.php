<?php
require_once __DIR__ . '/../includes/db.php';
$stmt = $pdo_primamas->query("DESCRIBE manifest");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "{$col['Field']} ({$col['Type']})\n";
}
