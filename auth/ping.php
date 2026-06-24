<?php
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$_SESSION['LAST_ACTIVITY'] = time();
http_response_code(204); // No Content