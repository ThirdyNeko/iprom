<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/require_login.php';

header('Content-Type: application/json');

$pdo = qa_db();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

/**
 * Resolve the logged-in user's role from the session.
 * Tries the common key names; add yours to the list if it isn't there.
 */
function get_session_role(): string
{
    $candidates = [
        $_SESSION['user_role']         ?? null,
        $_SESSION['role']              ?? null,
        $_SESSION['userRole']          ?? null,
        $_SESSION['user_type']         ?? null,
        $_SESSION['access_level']      ?? null,
        $_SESSION['user']['role']      ?? null,
        $_SESSION['user']['user_role'] ?? null,
    ];

    foreach ($candidates as $c) {
        if ($c !== null && $c !== '') {
            // "Super Admin" / "super_admin" / "SUPERADMIN" -> "superadmin"
            return preg_replace('/[\s_\-]+/', '', strtolower(trim((string)$c)));
        }
    }
    return '';
}

$sql = "SELECT 
            bl.id,
            bl.first_name,
            bl.middle_name,
            bl.last_name,
            bl.suffix,
            bl.gender,
            bl.birthday,
            bl.marital_status,
            COALESCE(br.branch, bl.branch) AS branch,
            bl.brand,
            bl.employment_status,
            bl.end_date,
            bl.remarks,
            bl.employee_id,
            bl.encoded_by,
            br.region
        FROM blacklisted bl
        LEFT JOIN branches br ON br.branch_code = bl.branch
        WHERE bl.id = :id";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    // Only these roles may read the real remarks (already normalized form)
    $allowedRoles   = ['admin', 'superadmin', 'supervisor', 'assistant_admin', 'audit_manager', 'audit_supervisor'];
    $userRole       = get_session_role();
    $canViewRemarks = in_array($userRole, $allowedRoles, true);

    if (!$canViewRemarks && !empty($row['remarks'])) {
        // One asterisk per character of the real remark
        $row['remarks'] = str_repeat('*', mb_strlen((string)$row['remarks'], 'UTF-8'));
    }

    $response = ['success' => true, 'data' => $row];

    // TEMPORARY: uncomment to see what the session holds, then remove
    // $response['debug'] = ['detected_role' => $userRole, 'session' => $_SESSION];

    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => 'Record not found']);
}