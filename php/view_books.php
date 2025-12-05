<?php
session_start();
require_once "connection.php"; // your OOP DB connection

if (!isset($_SESSION['user'])) {
    header("Location: login.html");
    exit();
}

$result = $db->query("SELECT * FROM books");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Available Books</title>
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>

<div class="dashboard">

    <aside class="sidebar">
        <div class="profile-box">
            <h3><?= $_SESSION['user']['fullname'] ?></h3>
            <p><?= ucfirst($_SESSION['user']['role']) ?></p>
        </div>

        <ul class="menu">
            <li><a href="#">Dashboard</a></li>
            <li><a href="view_books.php">View Books</a></li>

            <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                <li><a href="#">Manage Librarians</a></li>
                <li><a href="#">Manage Members</a></li>
            <?php elseif ($_SESSION['user']['role'] === 'librarian'): ?>
                <li><a href="#">Manage Books</a></li>
                <li><a href="#">Issue Books</a></li>
            <?php else: ?>
                <li><a href="#">My Issued Books</a></li>
            <?php endif; ?>

            <li class="logout"><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <main class="content">
        <h1>Available Books</h1>

        <table class="book-table">
            <tr>
                <th>Title</th>
                <th>Author</th>
                <th>Category</th>
                <th>Total</th>
                <th>Available</th>
                <th>Status</th>
            </tr>

            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['title'] ?></td>
                <td><?= $row['author'] ?></td>
                <td><?= $row['category'] ?></td>
                <td><?= $row['total_copies'] ?></td>
                <td><?= $row['available_copies'] ?></td>
                <td>
                    <?php if ($row['available_copies'] > 0): ?>
                        <span style="color:lightgreen;">Available</span>
                    <?php else: ?>
                        <span style="color:red;">Not Available</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>

        </table>
    </main>

</div>

</body>
</html>
