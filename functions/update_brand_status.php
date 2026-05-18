<?php
header('Content-Type: application/json');

include '../config/db.php';

$pdo = qa_db();

$id = $_POST['id'] ?? '';
$status = $_POST['status'] ?? '';

if ($id === '' || $status === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Missing data'
    ]);
    exit;
}

/* force valid status only */
if (!in_array($status, ['0', '1', 0, 1], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status value'
    ]);
    exit;
}

$status = (int)$status;

try {

    $stmt = $pdo->prepare("
        UPDATE brands
        SET status = :status
        WHERE id = :id
    ");

    $stmt->execute([
        ':status' => $status,
        ':id' => $id
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No rows updated (invalid branch_code or same value)'
        ]);
    }

} catch (PDOException $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}