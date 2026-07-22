<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

$pdo = qa_db();

$id       = trim($_POST['id'] ?? '');
$category = trim($_POST['category'] ?? '');

if ($category === '') {
    echo json_encode(['success' => false, 'message' => 'Category is required.']);
    exit;
}

try {
    // DUPLICATE CHECK (excluding self when editing)
    $dupSql    = "SELECT COUNT(*) FROM categories WHERE categories = :category";
    $dupParams = [':category' => $category];

    if ($id !== '') {
        $dupSql .= " AND id != :id";
        $dupParams[':id'] = $id;
    }

    $dupStmt = $pdo->prepare($dupSql);
    $dupStmt->execute($dupParams);

    if ((int) $dupStmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'This category already exists.']);
        exit;
    }

    if ($id !== '') {
        // UPDATE
        $stmt = $pdo->prepare("UPDATE categories SET categories = :category WHERE id = :id");
        $stmt->execute([':category' => $category, ':id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Category updated successfully.']);
    } else {
        // INSERT
        $stmt = $pdo->prepare("INSERT INTO categories (categories) VALUES (:category)");
        $stmt->execute([':category' => $category]);

        echo json_encode(['success' => true, 'message' => 'Category added successfully.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}