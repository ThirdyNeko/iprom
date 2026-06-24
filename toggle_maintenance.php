<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$maintenanceFile = __DIR__ . '/maintenance.flag';

if (file_exists($maintenanceFile)) {
    unlink($maintenanceFile);
} else {
    file_put_contents($maintenanceFile, date('Y-m-d H:i:s'));
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;