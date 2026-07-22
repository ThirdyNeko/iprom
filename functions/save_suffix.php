<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

$pdo = qa_db();

$id     = trim($_POST['id'] ?? '');
$suffix = trim($_POST['suffix'] ?? '');

if ($suffix === '') {
    echo json_encode(['success' => false, 'message' => 'Suffix is required.']);
    exit;
}

try {
    // DUPLICATE CHECK (excluding self when editing)
    $dupSql    = "SELECT COUNT(*) FROM suffix WHERE suffix = :suffix";
    $dupParams = [':suffix' => $suffix];

    if ($id !== '') {
        $dupSql .= " AND id != :id";
        $dupParams[':id'] = $id;
    }

    $dupStmt = $pdo->prepare($dupSql);
    $dupStmt->execute($dupParams);

    if ((int) $dupStmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'This suffix already exists.']);
        exit;
    }

    if ($id !== '') {
        // UPDATE
        $stmt = $pdo->prepare("UPDATE suffix SET suffix = :suffix WHERE id = :id");
        $stmt->execute([':suffix' => $suffix, ':id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Suffix updated successfully.']);
    } else {
        // INSERT
        $stmt = $pdo->prepare("INSERT INTO suffix (suffix) VALUES (:suffix)");
        $stmt->execute([':suffix' => $suffix]);

        echo json_encode(['success' => true, 'message' => 'Suffix added successfully.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}