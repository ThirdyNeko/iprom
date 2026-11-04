
<?php
session_start();

header('Content-Type: application/json');

include '../config/db.php';
include '../auth/require_login.php';

$pdo = qa_db();

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$branchCode = trim($input['branch_code'] ?? '');
$fullName = preg_replace('/\s+/', ' ', trim($input['full_name'] ?? ''));

if ($branchCode === '' || $fullName === '') {
    echo json_encode([
        'success' => false,
        'found' => false,
        'message' => 'Branch and full name are required.',
    ]);
    exit;
}

try {

    /*
    ============================================================
    SEARCH BRANCH MANAGER AND REGIONAL MANAGER
    ============================================================

    BRANCH MANAGER:
        users.branch = branches.branch_code

    REGIONAL MANAGER:
        users.region = branches.region

    Both must have ACTIVE status and matching full name.

    Branch manager gets priority if multiple matches exist.
    */

    $sql = "
        SELECT TOP 1
            u.[id],
            u.[username],
            u.[first_name],
            u.[middle_name],
            u.[last_name],
            u.[suffix],
            u.[position],
            u.[branch],
            u.[region],
            u.[role]

        FROM [users] u

        INNER JOIN [branches] b
            ON LTRIM(RTRIM(b.[branch_code])) =
               LTRIM(RTRIM(:branch_code))

        WHERE
            LOWER(LTRIM(RTRIM(u.[role]))) IN (
                'branch_manager',
                'regional_manager'
            )

            AND LTRIM(RTRIM(u.[status])) = 'ACTIVE'

            AND (
                (
                    LOWER(LTRIM(RTRIM(u.[role]))) = 'branch_manager'
                    AND LTRIM(RTRIM(u.[branch])) =
                        LTRIM(RTRIM(b.[branch_code]))
                )

                OR

                (
                    LOWER(LTRIM(RTRIM(u.[role]))) = 'regional_manager'
                    AND LTRIM(RTRIM(u.[region])) =
                        LTRIM(RTRIM(b.[region]))
                )
            )

            AND LOWER(LTRIM(RTRIM(
                ISNULL(u.[first_name], '') +
                ' ' +
                ISNULL(u.[last_name], '') +
                CASE
                    WHEN u.[suffix] IS NOT NULL
                         AND LTRIM(RTRIM(u.[suffix])) <> ''
                    THEN ' ' + LTRIM(RTRIM(u.[suffix]))
                    ELSE ''
                END
            ))) = LOWER(LTRIM(RTRIM(:full_name)))

        ORDER BY
            CASE
                WHEN LOWER(LTRIM(RTRIM(u.[role]))) = 'branch_manager'
                THEN 1
                ELSE 2
            END
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':branch_code' => $branchCode,
        ':full_name' => $fullName,
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {

        echo json_encode([
            'success' => true,
            'found' => true,
            'position' => $user['position'],
            'role' => $user['role'],
            'first_name' => $user['first_name'],
            'middle_name' => $user['middle_name'],
            'last_name' => $user['last_name'],
            'suffix' => $user['suffix'],
            'region' => $user['region'],
        ]);

    } else {

        echo json_encode([
            'success' => true,
            'found' => false,
            'message' => 'No active branch manager or regional manager found matching that name for this branch.',
        ]);

    }

} catch (PDOException $e) {

    error_log('search_branch_manager.php: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'found' => false,
        'message' => 'Search failed. Please try again.',
        'debug' => $e->getMessage(),
    ]);

}
