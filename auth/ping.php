<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

include_once __DIR__ . '/../config/db.php';
$pdo = qa_db();

// 🚧 Kick users if maintenance timer has elapsed
$maintenanceFile  = __DIR__ . '/../maintenance.flag';
$allowedUsernames = ['QA_HR_ADMIN', 'QA_HR_SUPERVISOR', 'QA_HR_STAFF'];
$isSuperAdmin     = ($_SESSION['role'] ?? '') === 'super_admin';
$isAllowed        = in_array($_SESSION['username'] ?? '', $allowedUsernames);

if (file_exists($maintenanceFile) && !$isSuperAdmin && !$isAllowed) {
    $flagData = json_decode(file_get_contents($maintenanceFile), true);
    $kickAt   = $flagData['kick_at'] ?? 0;

    if (time() >= $kickAt) {
        // Remove from active sessions before kicking
        $pdo->prepare("DELETE FROM active_sessions WHERE user_id = ?")
            ->execute([$_SESSION['user_id']]);

        session_destroy();
        http_response_code(401);
        exit;
    }
}

// ✅ Upsert active session (SQL Server 2012 MERGE)
$stmt = $pdo->prepare("
    MERGE active_sessions AS target
    USING (SELECT :user_id AS user_id) AS source ON target.user_id = source.user_id
    WHEN MATCHED THEN
        UPDATE SET last_seen = GETDATE()
    WHEN NOT MATCHED THEN
        INSERT (user_id, username, role, last_seen)
        VALUES (:user_id2, :username, :role, GETDATE());
");
$stmt->execute([
    ':user_id'   => $_SESSION['user_id'],
    ':user_id2'  => $_SESSION['user_id'],
    ':username'  => $_SESSION['username'],
    ':role'      => $_SESSION['role'],
]);

// Clean up stale sessions (no ping in last 2 minutes)
$pdo->exec("DELETE FROM active_sessions WHERE last_seen < DATEADD(MINUTE, -2, GETDATE())");

http_response_code(200);
exit;