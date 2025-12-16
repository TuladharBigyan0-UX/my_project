<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'librarian') {
    header("Location: ../php/login.php");
    exit();
}

$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Librarian Dashboard</title>
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>

<div class="dashboard">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="profile-box">
            <h3><?= htmlspecialchars($user['fullname']); ?></h3>
            <p>Librarian</p>
        </div>

        <ul class="menu">
            <li><a href="librarian_dashboard.php" class="active">Dashboard</a></li>
            <li><a href="#">Manage Books</a></li>
            <li><a href="#">Issue Books</a></li>
            <li><a href="../php/return_books.php">Return Books</a></li>
            <li><a href="view_reports.php">View Reports</a></li>
            <li><a href="#">Manage Members</a></li>
            <li><a href="#">Profile</a></li>
            <li class="logout"><a href="../php/logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="content">
        <h1>Librarian Panel</h1>

        <div class="cards">
            <div class="card">
                Total Books<br>
                <b>120</b>
            </div>
            <div class="card">
                Issued Today<br>
                <b>7</b>
            </div>
        </div>
    </main>

</div>

</body>
</html>
