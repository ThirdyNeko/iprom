<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

$pdo = qa_db();

try {
    $stmt = $pdo->prepare("EXEC dbo.sync_blacklisted_from_employees");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'        => true,
        'insertedCount'  => (int)($result['InsertedCount'] ?? 0),
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database error',
        'message' => $e->getMessage(),
    ]);
}