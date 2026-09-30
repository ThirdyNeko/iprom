<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

$pdo  = qa_db();
$data = json_decode(file_get_contents('php://input'), true) ?: [];

$userId = (int)($data['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user.',
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE users
        SET status = 'INACTIVE',
            updated_at = GETDATE()
        WHERE id = ?
    ");
    $stmt->execute([$userId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found.',
        ]);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}