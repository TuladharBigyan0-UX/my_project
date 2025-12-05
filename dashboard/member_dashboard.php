<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'member') {
    header("Location: login.html");
    exit();
}

$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Member Dashboard</title>
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>

<div class="dashboard">

    <aside class="sidebar">
        <div class="profile-box">
            <h3><?= $user['fullname'] ?></h3>
            <p>Member</p>
        </div>

        <ul class="menu">
            <li><a href="#">My Dashboard</a></li>
            <li><a href="#">My Issued Books</a></li>
            <li><a href="#">Issue History</a></li>
            <li><a href="#">Profile</a></li>
            <li class="logout"><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <main class="content">
        <h1>Welcome, <?= $user['fullname'] ?></h1>
    </main>

</div>
</body>
</html>
