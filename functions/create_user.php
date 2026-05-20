<?php
session_start();
include '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized'
    ]);
    exit;
}

$pdo = qa_db();

try {

    $username   = strtoupper(trim($_POST['username'] ?? ''));
    $role       = $_POST['role'] ?? null;

    $branch     = !empty($_POST['branch']) ? $_POST['branch'] : null;
    $brand      = !empty($_POST['brand']) ? $_POST['brand'] : null;

    $first_name = strtoupper(trim($_POST['first_name'] ?? ''));
    $last_name  = strtoupper(trim($_POST['last_name'] ?? ''));
    $position   = strtoupper(trim($_POST['position'] ?? ''));
    $department = strtoupper(trim($_POST['department'] ?? ''));
    $status = "ACTIVE";

    if (!$username || !$role || !$first_name || !$last_name || !$position) {

        echo json_encode([
            'status' => 'error',
            'message' => 'Please fill in required fields'
        ]);

        exit;
    }

    // duplicate check
    $check = $pdo->prepare("
        SELECT COUNT(*) 
        FROM users 
        WHERE username = ?
    ");

    $check->execute([$username]);

    if ($check->fetchColumn() > 0) {

        echo json_encode([
            'status' => 'error',
            'message' => 'Username already exists'
        ]);

        exit;
    }

    $defaultPassword = 'Password123';

    $hashedPassword = password_hash(
        $defaultPassword,
        PASSWORD_DEFAULT
    );

    $stmt = $pdo->prepare("
        INSERT INTO users (
            username,
            password,
            role,
            branch,
            brand,
            first_name,
            last_name,
            position,
            department,
            status
        )
        VALUES (
            :username,
            :password,
            :role,
            :branch,
            :brand,
            :first_name,
            :last_name,
            :position,
            :department,
            :status
        )
    ");

    $stmt->execute([
        ':username'   => $username,
        ':password'   => $hashedPassword,
        ':role'       => $role,
        ':branch'     => $branch,
        ':brand'      => $brand,
        ':first_name' => $first_name,
        ':last_name'  => $last_name,
        ':position'   => $position,
        ':department' => $department,
        ':status'     => $status
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'User created successfully'
    ]);

} catch (PDOException $e) {

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}