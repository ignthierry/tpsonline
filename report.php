<?php
/**
 * Redirect report.php to report_cont.php
 */
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: report_cont.php" . $qs);
exit;
