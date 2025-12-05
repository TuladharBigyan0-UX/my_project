<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'librarian') {
    header("Location: login.html");
    exit();
}

$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Librarian Dashboard</title>
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>

<div class="dashboard">

    <aside class="sidebar">
        <div class="profile-box">
            <h3><?= $user['fullname'] ?></h3>
            <p>Librarian</p>
        </div>

        <ul class="menu">
            <li><a href="#">Dashboard</a></li>
            <li><a href="#">Manage Books</a></li>
            <li><a href="#">Issue Books</a></li>
            <li><a href="#">Return Books</a></li>
            <li><a href="#">Manage Members</a></li>
            <li><a href="#">Profile</a></li>
            <li class="logout"><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <main class="content">
        <h1>Librarian Panel</h1>

        <div class="cards">
            <div class="card">Total Books<br><b>120</b></div>
            <div class="card">Issued Today<br><b>7</b></div>
        </div>
    </main>

</div>
</body>
</html>
