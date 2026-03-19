<?php
define('BASE_URL', '/iprom/');
define('SESSION_TIMEOUT', 3600);

// Not logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

// Timeout
if (isset($_SESSION['LAST_ACTIVITY']) &&
    (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {

    $_SESSION = [];
    session_destroy();

    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

$_SESSION['LAST_ACTIVITY'] = time();