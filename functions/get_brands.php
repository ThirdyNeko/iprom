<?php
header('Content-Type: application/json');

include '../config/db.php';

$pdo = qa_db();

$sql = "
    SELECT brand_name FROM (
        -- From OMRC
        SELECT b.FirmName COLLATE SQL_Latin1_General_CP1_CI_AS AS brand_name
        FROM VIAC_PROD_NEW.dbo.OMRC b
        WHERE b.FirmName IS NOT NULL

        UNION

        -- From local brands table
        SELECT br.brand COLLATE SQL_Latin1_General_CP1_CI_AS AS brand_name
        FROM dbo.brands br
        WHERE br.status = 1
    ) AS combined
    ORDER BY brand_name
";

$stmt = $pdo->query($sql);
$brands = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode($brands);