<?php
session_start();
header('Content-Type: application/json');
include '../config/db.php';
include '../auth/require_login.php';

$pdo = qa_db();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request payload.']);
    exit;
}

$required = ['first_name', 'last_name', 'birthdate', 'branch', 'brand'];
foreach ($required as $field) {
    if (!isset($input[$field]) || $input[$field] === '') {
        echo json_encode(['success' => false, 'message' => "Missing required field: {$field}"]);
        exit;
    }
}

$middleName = trim($input['middle_name'] ?? '');

try {
    // Mirrors the exact matching logic in dbo.add_blacklisted — read-only,
    // no cascade. Used to confirm with the user before the real insert.
    $sql = "
        SELECT TOP 1
            employee_id,
            first_name,
            middle_name,
            last_name,
            branch,
            brand,
            status
        FROM employee_info
        WHERE LTRIM(RTRIM(UPPER(first_name))) = LTRIM(RTRIM(UPPER(:first_name)))
          AND LTRIM(RTRIM(UPPER(last_name)))  = LTRIM(RTRIM(UPPER(:last_name)))
          AND ISNULL(LTRIM(RTRIM(UPPER(middle_name))), '') = ISNULL(LTRIM(RTRIM(UPPER(:middle_name))), '')
          AND birthday = :birthdate
          AND branch = :branch
          AND brand = :brand
        ORDER BY id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':first_name', $input['first_name'], PDO::PARAM_STR);
    $stmt->bindValue(':last_name', $input['last_name'], PDO::PARAM_STR);
    $stmt->bindValue(':middle_name', $middleName, PDO::PARAM_STR);
    $stmt->bindValue(':birthdate', $input['birthdate'], PDO::PARAM_STR);
    $stmt->bindValue(':branch', $input['branch'], PDO::PARAM_STR);
    $stmt->bindValue(':brand', $input['brand'], PDO::PARAM_STR);
    $stmt->execute();

    $match = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'match'   => $match ?: null,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}