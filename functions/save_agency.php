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
$email = trim($_POST['email'] ?? '');

// ARRAYS
$numbers = $_POST['contact_numbers'] ?? [];
$tels = $_POST['tel_numbers'] ?? [];

// CLEAN ARRAYS
$numbers = array_values(array_filter(array_map('trim', $numbers)));
$tels = array_values(array_filter(array_map('trim', $tels)));

// CONVERT TO STRING
$number = implode(' | ', $numbers);
$tel = implode(' | ', $tels);

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

if (count($numbers) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'At least one mobile number is required.'
    ]);
    exit;
}

if (count($tels) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'At least one telephone number is required.'
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
        INSERT INTO agencies (
            agencies,
            contact_person,
            contact_number,
            tel_number,
            email,
            status
        )
        VALUES (?, ?, ?, ?, ?, 1)
    ");

    $stmt->execute([
        $agency,
        $person,
        $number,
        $tel,
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
            tel_number = ?,
            email = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $agency,
        $person,
        $number,
        $tel,
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