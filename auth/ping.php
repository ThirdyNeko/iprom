<?php
// ping.php
define('SESSION_TIMEOUT', 600);

session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

// ✅ Check timeout BEFORE resetting — don't rescue an expired session
if (isset($_SESSION['LAST_ACTIVITY']) &&
    (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {

    $_SESSION = [];
    session_destroy();
    http_response_code(401);
    exit;
}

$_SESSION['LAST_ACTIVITY'] = time();
http_response_code(204);