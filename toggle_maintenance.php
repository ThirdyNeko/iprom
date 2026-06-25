<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$maintenanceFile = __DIR__ . '/maintenance.flag';

if (file_exists($maintenanceFile)) {
    // Disable — delete flag
    unlink($maintenanceFile);
} else {
    // Enable — save message + kick timestamp
    $message   = trim($_POST['message']    ?? 'The system is currently under maintenance. Please try again later.');
    $kickAfter = intval($_POST['kick_after'] ?? 5);

    $data = [
        'enabled_at' => date('Y-m-d H:i:s'),
        'message'    => $message,
        'kick_at'    => time() + ($kickAfter * 60), // Unix timestamp
    ];

    file_put_contents($maintenanceFile, json_encode($data));
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;