<?php
/**
 * functions/submit_blacklist_request.php
 *
 * Handles submission of a new blacklist request from the
 * "Request Blacklist" modal.
 *
 * NOTE: role check happens here regardless of what the client-side JS
 * shows/hides — never trust CURRENT_USER_ROLE alone (see verification.php
 * comment for the same rule applied to LOA actions).
 */

session_start();
header('Content-Type: application/json');

require '../config/db.php';
require '../auth/require_login.php';

if (!function_exists('nullIfEmpty')) {
    function nullIfEmpty($value) {
        return ($value === null || $value === '') ? null : $value;
    }
}

$pdo = qa_db();

$user_role     = $_SESSION['role'] ?? '';
$user_fullname = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'Unknown');

$allowed_roles = ['audit_manager', 'audit_supervisor', 'branch_manager'];

if (!in_array(strtolower($user_role), $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not authorized to submit blacklist requests.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Required fields
$required = ['first_name', 'last_name', 'branch', 'employee_id'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "Missing required field: {$field}"]);
        exit;
    }
}

try {
    $stmt = $pdo->prepare("{CALL add_blacklist_request(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}");
    $stmt->execute([
        $input['first_name'],
        nullIfEmpty($input['middle_name'] ?? null),
        $input['last_name'],
        nullIfEmpty($input['birthday'] ?? null),
        nullIfEmpty($input['suffix'] ?? null),
        nullIfEmpty($input['gender'] ?? null),
        nullIfEmpty($input['marital_status'] ?? null),
        $input['branch'],
        nullIfEmpty($input['brand'] ?? null),
        nullIfEmpty($input['employment_status'] ?? null),
        nullIfEmpty($input['end_date'] ?? null),
        nullIfEmpty($input['remarks'] ?? null),
        $input['employee_id'],
        $user_fullname,
        strtolower($user_role),
    ]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Blacklist request submitted successfully.',
        'new_id'  => $result['new_id'] ?? null,
    ]);
} catch (PDOException $e) {
    // Duplicate-pending-request guard raises an error inside the SP
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'A pending blacklist request already exists for this employee, or the request could not be saved.',
    ]);
}