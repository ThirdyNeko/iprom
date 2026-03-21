<?php
require_once '../config/db.php';

$pdo = qa_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        EXEC add_employee 
            @first_name = :first_name,
            @last_name  = :last_name,
            @branch     = :branch,
            @brand      = :brand,
            @status     = :status
    ");

    $stmt->execute([
        ':first_name' => $_POST['first_name'],
        ':last_name'  => $_POST['last_name'],
        ':branch'     => $_POST['branch'] ?: null,
        ':brand'      => $_POST['brand'] ?: null,
        ':status'     => $_POST['status'] ?: null,
    ]);

    // 👇 FETCH THE RETURNED ID
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $newId = $result['new_id'] ?? null;

    // (optional) use it
    // echo $newId;

    header("Location: ../promodizers.php");
    exit;
}