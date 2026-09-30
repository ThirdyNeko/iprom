<?php
session_start();
header('Content-Type: application/json');
include '../config/db.php';

$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['admin', 'super_admin', 'assistant_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

$input   = json_decode(file_get_contents('php://input'), true);
$id      = (int)($input['id'] ?? 0);
$remarks = trim($input['remarks'] ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}
if ($remarks === '' || mb_strlen($remarks) > 100) {
    echo json_encode(['success' => false, 'message' => 'Remarks are required (max 100 characters).']);
    exit;
}

$by = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? '');

try {
    $pdo  = qa_db();
    $stmt = $pdo->prepare("SET NOCOUNT ON; EXEC blacklist_flagged_request ?, ?, ?, ?");
    $stmt->execute([$id, $by, $role, $remarks]);

    // Walk every result set: this forces any SQL error raised inside the
    // proc to throw, and lets us confirm the proc returned its OK marker.
    $ok = false;
    do {
        if ($stmt->columnCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && ($row['result'] ?? '') === 'OK') {
                $ok = true;
            }
        }
    } while ($stmt->nextRowset());

    if (!$ok) {
        echo json_encode(['success' => false, 'message' => 'Blacklist did not complete.']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $raw = $e->getMessage();

    // Only our own validation messages (prefixed [BL]) are shown to the user
    if (preg_match('/\[BL\]\s*(.+)$/s', $raw, $m)) {
        echo json_encode(['success' => false, 'message' => trim($m[1])]);
    } else {
        error_log('blacklist_request: ' . $raw);
        echo json_encode(['success' => false, 'message' => 'Failed to blacklist employee.', 'debug' => $raw]);
    }
}