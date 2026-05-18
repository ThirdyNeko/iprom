<?php
header('Content-Type: application/json');

include '../config/db.php';

$pdo = qa_db();

$branch_code = $_POST['branch_code'] ?? '';
$status = $_POST['status'] ?? '';

if ($branch_code === '' || $status === '') {
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
        UPDATE branches
        SET status = :status
        WHERE branch_code = :branch_code
    ");

    $stmt->execute([
        ':status' => $status,
        ':branch_code' => $branch_code
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