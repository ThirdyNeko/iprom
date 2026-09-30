<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

$pdo  = qa_db();
$data = json_decode(file_get_contents('php://input'), true) ?: [];

$first  = strtoupper(trim($data['first_name']  ?? ''));
$middle = strtoupper(trim($data['middle_name'] ?? ''));
$last   = strtoupper(trim($data['last_name']   ?? ''));

if ($first === '' || $last === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'First and last name are required.',
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT TOP 1
            id, username, first_name, middle_name, last_name,
            position, branch, brand, status
        FROM users
        WHERE LTRIM(RTRIM(UPPER(first_name))) = ?
          AND LTRIM(RTRIM(UPPER(last_name)))  = ?
          AND ISNULL(LTRIM(RTRIM(UPPER(middle_name))), '') = ?
        ORDER BY id DESC
    ");
    $stmt->execute([$first, $last, $middle]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'match'   => $row ?: null,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}