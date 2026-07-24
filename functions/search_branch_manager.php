<?php
session_start();
header('Content-Type: application/json');
include '../config/db.php';
include '../auth/require_login.php';

$pdo = qa_db();

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$branchCode = trim($input['branch_code'] ?? '');
$fullName   = preg_replace('/\s+/', ' ', trim($input['full_name'] ?? ''));

if ($branchCode === '' || $fullName === '') {
    echo json_encode([
        'success' => false,
        'found'   => false,
        'message' => 'Branch and full name are required.',
    ]);
    exit;
}

try {
    // Bracket-quote reserved/ambiguous column names ([position],
    // [branch], [role], [status]) since the schema itself brackets
    // them — some are ODBC-reserved and can throw a syntax error
    // under ODBC Driver 17 if left unescaped.
    $sql = "SELECT TOP 1
            [id],
            [username],
            [first_name],
            [middle_name],
            [last_name],
            [suffix],
            [position],
            [branch]
        FROM [users]
        WHERE [role] = 'branch_manager'
          AND LTRIM(RTRIM([branch])) = LTRIM(RTRIM(:branch_code))
          AND LOWER(LTRIM(RTRIM(
                ISNULL([first_name], '') +
                ' ' +
                ISNULL([last_name], '') +
                CASE
                    WHEN [suffix] IS NOT NULL
                         AND LTRIM(RTRIM([suffix])) <> ''
                    THEN ' ' + LTRIM(RTRIM([suffix]))
                    ELSE ''
                END
          ))) = LOWER(LTRIM(RTRIM(:full_name)))";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':branch_code' => $branchCode,
        ':full_name'   => $fullName,
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode([
            'success'     => true,
            'found'       => true,
            'position'    => $user['position'],
            'first_name'  => $user['first_name'],
            'middle_name' => $user['middle_name'],
            'last_name'   => $user['last_name'],
            'suffix'      => $user['suffix'],
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'found'   => false,
            'message' => 'No branch manager found matching that name for this branch.',
        ]);
    }
} catch (PDOException $e) {
    error_log('search_branch_manager.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'found'   => false,
        'message' => 'Search failed. Please try again.',
        // TEMPORARY DEBUG — remove this key once the issue is confirmed fixed.
        'debug'   => $e->getMessage(),
    ]);
}