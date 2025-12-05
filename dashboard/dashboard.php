<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../html/login.html");
    exit();
}

$role = $_SESSION['user']['role'];

if ($role === 'admin') {
    header("Location: admin_dashboard.php");
} elseif ($role === 'librarian') {
    header("Location: librarian_dashboard.php");
} else {
    header("Location: member_dashboard.php");
}
exit();
