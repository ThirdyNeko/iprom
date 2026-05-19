<?php
header('Content-Type: application/json');

include '../config/db.php';

$pdo = qa_db();

$id = $_POST['id'] ?? null;
$name = trim($_POST['agency'] ?? '');

if ($name === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Agency name is required.'
    ]);
    exit;
}

// =========================
// CHECK DUPLICATE
// =========================
$check = $pdo->prepare("
    SELECT COUNT(*)
    FROM agencies
    WHERE UPPER(LTRIM(RTRIM(agencies))) = UPPER(LTRIM(RTRIM(?)))
    AND (? IS NULL OR id != ?)
");

$check->execute([
    $name,
    $id,
    $id
]);

if ($check->fetchColumn() > 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Agency already exists.'
    ]);

    exit;
}

// =========================
// INSERT
// =========================
if (empty($id)) {

    $stmt = $pdo->prepare("
        INSERT INTO agencies (agencies)
        VALUES (?)
    ");

    $stmt->execute([$name]);

} else {

    // =========================
    // UPDATE
    // =========================
    $stmt = $pdo->prepare("
        UPDATE agencies
        SET agencies = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $name,
        $id
    ]);
}

echo json_encode([
    'success' => true
]);