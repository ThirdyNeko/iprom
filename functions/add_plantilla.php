<?php
header('Content-Type: application/json');
$response = ['success' => false];

session_start();
include '../config/db.php';

try {
    if(!empty($_POST['branch']) && !empty($_POST['brand']) && !empty($_POST['required_count'])){
        $branch = $_POST['branch'];
        $brand  = $_POST['brand'];
        $required_count = intval($_POST['required_count']);
        $updated_by = $_SESSION['username'] ?? 0;

        // Call stored procedure
        $stmt = $pdo->prepare("EXEC dbo.AddPlantilla ?, ?, ?, ?");
        if($stmt->execute([$branch, $brand, $required_count, $updated_by])){
            $response['success'] = true;
        } else {
            $response['message'] = 'Failed to insert plantilla via stored procedure.';
        }
    } else {
        $response['message'] = 'All fields are required.';
    }
} catch(PDOException $e){
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;