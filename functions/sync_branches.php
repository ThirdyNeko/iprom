<?php
header('Content-Type: application/json');

require_once '../config/db.php';

$pdo = qa_db();

function cleanValue($value, $fallback = null)
{
    $value = trim((string)$value);

    if ($value === '' || strtolower($value) === 'null') {
        return $fallback;
    }

    return $value;
}

try {

    $pdo->beginTransaction();

    /*
     * Call stored procedure
     */
    $stmt = $pdo->query("EXEC ImperialBranchDetails_Complete");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $inserted = 0;
    $updated  = 0;

    // Prevent duplicate BranchCode values from the SP result
    $seen = [];

    /*
     * Check if branch already exists
     */
    $checkStmt = $pdo->prepare("
        SELECT branch, region, corpo, area
        FROM branches
        WHERE branch_code = ?
    ");

    /*
     * Update existing branch
     */
    $updateStmt = $pdo->prepare("
        UPDATE branches
        SET branch   = ?,
            region   = ?,
            corpo    = ?,
            area     = ?
        WHERE branch_code = ?
    ");

    /*
     * Insert new branch
     */
    $insertStmt = $pdo->prepare("
        INSERT INTO branches (
            branch_code,
            branch,
            region,
            corpo,
            area,
            status,
            deployed
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            1,
            0
        )
    ");

    foreach ($rows as $row) {

        $branchCode = cleanValue($row['BranchCode'] ?? null);

        if (!$branchCode) {
            continue;
        }

        // Skip duplicates from stored procedure output
        if (isset($seen[$branchCode])) {
            continue;
        }

        $seen[$branchCode] = true;

        /*
         * Prepare values
         */
        $branch = cleanValue($row['Branch'] ?? null);
        $region = cleanValue($row['Location'] ?? null);
        $corpo  = cleanValue($row['Company'] ?? null, 'NO COMPANY');
        $area   = cleanValue($row['DM'] ?? null);

        /*
         * Check existing record
         */
        $checkStmt->execute([
            $branchCode
        ]);

        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {

            /*
             * Determine whether anything actually changed
             */
            $hasChanges =
                $existing['branch'] !== $branch ||
                $existing['region'] !== $region ||
                $existing['corpo']  !== $corpo ||
                $existing['area']   !== $area;

            if ($hasChanges) {

                $updateStmt->execute([
                    $branch,
                    $region,
                    $corpo,
                    $area,
                    $branchCode
                ]);

                $updated++;
            }

        } else {

            /*
             * Insert new branch
             */
            $insertStmt->execute([
                $branchCode,
                $branch,
                $region,
                $corpo,
                $area
            ]);

            $inserted++;
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Sync completed. Inserted: $inserted | Updated: $updated"
    ]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine()
    ]);
}