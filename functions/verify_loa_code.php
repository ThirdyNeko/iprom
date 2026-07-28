<?php
// ─────────────────────────────────────────────────────────────
// functions/verify_loa_code.php
// Checks that the entered LOA code matches a letters_of_advice
// row belonging to the given employee.
// Expects JSON POST: { employee_id, loa_code }
// Returns: { success: bool, loa_id?: int, message?: string }
// ─────────────────────────────────────────────────────────────

session_start();
header('Content-Type: application/json');
include '../config/db.php';
include '../auth/require_login.php';

$pdo = qa_db();

$input = json_decode(file_get_contents('php://input'), true);

$employeeId = trim($input['employee_id'] ?? '');
$loaCode    = trim($input['loa_code'] ?? '');

if ($employeeId === '' || $loaCode === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields.',
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT TOP 1 [id]
        FROM letters_of_advice
        WHERE employee_id = :employee_id
          AND loa_code = :loa_code
        ORDER BY created_at DESC
    ");
    $stmt->execute([
        ':employee_id' => $employeeId,
        ':loa_code'    => $loaCode,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode([
            'success' => true,
            'loa_id'  => (int)$row['id'],
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'LOA code does not match our records for this employee.',
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}