<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);
include 'config/db.php';
include 'auth/require_login.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

$isAllowed =
    (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin');

if (!$isAllowed) {
    header('Location: dashboard.php');
    exit;
}   

?>