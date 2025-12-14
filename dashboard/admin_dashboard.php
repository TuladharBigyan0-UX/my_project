<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location:login.php");
    exit();
}

$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>`
        <main class="content">
        <h1>Welcome <?= $user['fullname'] ?></h1>
        <div class="dashboard">

    <aside class="sidebar">
        <div class="profile-box">
            <h3><?= $user['fullname'] ?></h3>
            <p>Admin</p>
        </div>

        <ul class="menu">
            <li><a href="#" class="active">Dashboard</a></li>
            <li><a href="../php/manage_librarian.php">Manage Librarians</a></li>
            <li><a href="../php/manage_member.php">Manage Members</a></li>
            <li><a href="#">View Reports</a></li>
            <li><a href="#">Profile</a></li>
            <li class="logout"><a href="../php/logout.php">Logout</a></li>
        </ul>
    </aside>

    <main class="content">
        <h1>Admin Control Panel</h1>

        <div class="cards">
            <div class="card">Total Librarians<br><b></b></div>
            <div class="card">Total Members<br><b></b></div>
        </div>
    </main>
</body>
</html>