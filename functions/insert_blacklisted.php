<?php
session_start();
header('Content-Type: application/json');
include '../config/db.php';
include '../auth/require_login.php';

$pdo = qa_db();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request payload.']);
    exit;
}

function nullIfEmpty($val) {
    $val = trim($val ?? '');
    return $val === '' ? null : $val;
}

// NOTE: employment_status is intentionally NOT required here — Direct Hire
// records are saved with employment_status = NULL since that field only
// applies to Promodiser records.
$required = [
    'first_name', 'last_name', 'gender',
    'birthdate', 'marital_status', 'branch', 'brand',
    'end_date', 'remarks'
];

foreach ($required as $field) {
    if (!isset($input[$field]) || $input[$field] === '') {
        echo json_encode(['success' => false, 'message' => "Missing required field: {$field}"]);
        exit;
    }
}

if (mb_strlen($input['remarks'], 'UTF-8') > 100) {
    echo json_encode(['success' => false, 'message' => 'Remarks must not exceed 100 characters.']);
    exit;
}

try {
    // Positional (?) placeholders — ODBC stored-procedure calls don't
    // support named parameters, and they must match add_blacklisted's
    // declared parameter order exactly.
    $sql = "{CALL add_blacklisted (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        mb_strtoupper($input['first_name'], 'UTF-8'),
        mb_strtoupper($input['middle_name'], 'UTF-8'),
        mb_strtoupper($input['last_name'], 'UTF-8'),
        $input['suffix'],
        $input['gender'],
        $input['birthdate'],
        $input['marital_status'],
        $input['branch'],
        $input['brand'],
        nullIfEmpty($input['employment_status'] ?? ''),
        $input['end_date'],
        $input['remarks'],
        $_SESSION['username'] ?? 'SYSTEM',
    ]);

    // The SP returns matched_id / matched_employee_id so the frontend
    // can tell whether this person also existed in employee_info.
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'matched_employee_id' => $result['matched_employee_id'] ?? null,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}