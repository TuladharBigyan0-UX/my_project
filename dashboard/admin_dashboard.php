<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.html");
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
<body>

<div class="dashboard">

    <aside class="sidebar">
        <div class="profile-box">
            <h3><?= $user['fullname'] ?></h3>
            <p>Admin</p>
        </div>

        <ul class="menu">
            <li><a href="#">Dashboard</a></li>
            <li><a href="#">Manage Librarians</a></li>
            <li><a href="#">Manage Members</a></li>
            <li><a href="#">View Reports</a></li>
            <li><a href="#">Profile</a></li>
            <li class="logout"><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <main class="content">
        <h1>Admin Control Panel</h1>

        <div class="cards">
            <div class="card">Total Librarians<br><b>5</b></div>
            <div class="card">Total Members<br><b>210</b></div>
        </div>
    </main>

</div>
</body>
</html>