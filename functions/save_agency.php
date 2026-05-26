<?php
header('Content-Type: application/json');

include '../config/db.php';

$pdo = qa_db();

// =========================
// INPUTS
// =========================
$id = $_POST['id'] ?? null;
$agency = trim($_POST['agency'] ?? '');
$person = trim($_POST['contact_person'] ?? '');
$number = trim($_POST['contact_number'] ?? '');
$email = trim($_POST['email'] ?? '');

// =========================
// VALIDATION
// =========================
if ($agency === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Agency name is required.'
    ]);
    exit;
}

if ($person === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Contact person is required.'
    ]);
    exit;
}

if ($number === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Contact number is required.'
    ]);
    exit;
}

if ($email === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Email is required.'
    ]);
    exit;
}

// =========================
// DUPLICATE CHECK (AGENCY NAME)
// =========================
$check = $pdo->prepare("
    SELECT COUNT(*)
    FROM agencies
    WHERE UPPER(LTRIM(RTRIM(agencies))) = UPPER(LTRIM(RTRIM(?)))
    AND (? IS NULL OR id != ?)
");

$check->execute([
    $agency,
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
        INSERT INTO agencies (agencies, contact_person, contact_number, email, status)
        VALUES (?, ?, ?, 1)
    ");

    $stmt->execute([
        $agency,
        $person,
        $number,
        $email
    ]);

} else {

    // =========================
    // UPDATE
    // =========================
    $stmt = $pdo->prepare("
        UPDATE agencies
        SET 
            agencies = ?,
            contact_person = ?,
            contact_number = ?,
            email = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $agency,
        $person,
        $number,
        $email,
        $id
    ]);
}

// =========================
// RESPONSE
// =========================
echo json_encode([
    'success' => true,
    'message' => empty($id) ? 'Agency added successfully.' : 'Agency updated successfully.'
]);