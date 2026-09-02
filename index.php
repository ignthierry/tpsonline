<?php
/**
 * Index Page — CEISA 4.0 TPS Online Dashboard
 * Routing ke dashboard jika sudah login, atau ke login.php jika belum
 */
require_once __DIR__ . '/includes/session.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

header('Location: login.php');
exit;
