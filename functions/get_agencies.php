<?php
require_once '../config/db.php';

header('Content-Type: application/json');

$pdo = qa_db();

$stmt = $pdo->query("
    SELECT DISTINCT agencies
    FROM agencies
    WHERE agencies IS NOT NULL
      AND status = 1
    ORDER BY agencies
");

echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));