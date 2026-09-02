<?php
/**
 * Logout Handler
 */
require_once __DIR__ . '/includes/session.php';

clearSession();
header('Location: login.php');
exit;
