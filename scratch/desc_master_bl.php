<?php
require_once __DIR__ . '/../includes/db.php';
$stmt = $pdo_primamas->query("DESCRIBE master_bl");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
