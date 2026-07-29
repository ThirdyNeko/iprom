<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

$pdo = qa_db();

$id            = trim($_POST['id'] ?? '');
$categoryCode  = strtoupper(trim($_POST['category_code'] ?? ''));
$category      = strtoupper(trim($_POST['category'] ?? ''));

if ($categoryCode === '' || $category === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Category code and category are required.'
    ]);
    exit;
}

try {

    $sql = "SELECT COUNT(*) FROM categories
            WHERE category_code = :code";

    $params = [
        ':code' => $categoryCode
    ];

    if ($id !== '') {
        $sql .= " AND id <> :id";
        $params[':id'] = $id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetchColumn() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Category code already exists.'
        ]);
        exit;
    }
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
        $stmt = $pdo->prepare("UPDATE categories
            SET category_code = :category_code,
                categories = :category
            WHERE id = :id");
        $stmt->execute([':category_code' => $categoryCode, ':category' => $category, ':id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Category updated successfully.']);
    } else {
        // INSERT
        $stmt = $pdo->prepare("INSERT INTO categories (category_code, categories) VALUES (:category_code, :category)");
        $stmt->execute([':category_code' => $categoryCode, ':category' => $category]);

        echo json_encode(['success' => true, 'message' => 'Category added successfully.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}