<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$pdo = qa_db();

try {

    // Call stored procedure
    $stmt = $pdo->query("EXEC ImperialBranchDetails_Complete");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $inserted = 0;
    $updated = 0;

    foreach ($rows as $row) {

        $branchCode = $row['BranchCode'] ?? null;
        if (!$branchCode) continue;

        // Check existing
        $check = $pdo->prepare("
            SELECT COUNT(*) 
            FROM branches 
            WHERE branch_code = :code
        ");
        $check->execute([':code' => $branchCode]);

        $exists = $check->fetchColumn();

        if ($exists) {

            // UPDATE
            $update = $pdo->prepare("
                UPDATE branches
                SET
                    branch = :branch,
                    region = :region,
                    corpo = :corpo,
                    area = :area
                WHERE branch_code = :code
            ");

            $update->execute([
                ':branch' => $row['Branch'],
                ':region' => $row['Location'],
                ':corpo'  => $row['Company'],
                ':area'   => $row['DM'],   // ✅ IMPORTANT FIX
                ':code'   => $branchCode
            ]);

            $updated++;

        } else {

            // INSERT
            $insert = $pdo->prepare("
                INSERT INTO branches (
                    branch_code,
                    branch,
                    region,
                    corpo,
                    area,
                    status
                )
                VALUES (
                    :BranchCode,
                    :Branch,
                    :Location,
                    :Company,
                    :area,
                    1
                )
            ");

            $insert->execute([
                ':BranchCode' => $row['BranchCode'],
                ':Branch' => $row['Branch'],
                ':Location' => $row['Location'],
                ':Company' => $row['Company'],
                ':area'   => $row['DM']   // ✅ IMPORTANT FIX
            ]);

            $inserted++;
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Sync completed. Inserted: $inserted | Updated: $updated"
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}