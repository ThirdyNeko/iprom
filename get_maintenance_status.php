<?php
session_start();

header('Content-Type: application/json');

$maintenanceFile = __DIR__ . '/maintenance.flag';

if (!file_exists($maintenanceFile)) {
    echo json_encode(['active' => false]);
    exit;
}

$data             = json_decode(file_get_contents($maintenanceFile), true);
$kickAt           = $data['kick_at'] ?? 0;
$secondsRemaining = max(0, $kickAt - time());

echo json_encode([
    'active'            => true,
    'message'           => $data['message'] ?? 'The system is currently under maintenance.',
    'kick_at'           => $kickAt,
    'seconds_remaining' => $secondsRemaining,
]);