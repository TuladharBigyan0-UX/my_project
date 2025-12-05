<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../html/login.html");
    exit();
}

$role = $_SESSION['role']; // admin, librarian, member
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Main Dashboard</title>
</head>
<body>

<?php
// ✅ Load dashboard based on role
if ($role === 'admin') {
    include "admin_dashboard.php";
} 
elseif ($role === 'librarian') {
    include "librarian_dashboard.php";
} 
else {
    include "member_dashboard.php"; // students & teachers
}
?>

</body>
</html>

