<?php
header('Content-Type: application/json');
session_start();
include '../config/db.php';

try {
    if (!isset($_GET['branch']) || empty($_GET['branch'])) {
        echo json_encode([]);
        exit;
    }

    $branch = $_GET['branch'];

    $stmt = $pdo->prepare("
        EXEC dbo.get_branches_brands @branch = ?
    ");
    $stmt->execute([$branch]);

    // Skip the first result set (branches) and fetch the second (brands)
    if ($stmt->nextRowset()) {
        $brands = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $brands = [];
    }

    echo json_encode($brands);

} catch (PDOException $e) {
    echo json_encode([]);
}