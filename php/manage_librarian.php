<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("connection.php");

// Admin protection
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];

// =======================
// DELETE LIBRARIAN
// =======================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM librarians WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: manage_librarian.php");
    exit();
}

// =======================
// FETCH LIBRARIANS
// =======================
$result = $conn->query("SELECT * FROM librarians ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Librarians</title>
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>

<div class="dashboard">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="profile-box">
            <h3><?= htmlspecialchars($user['fullname']); ?></h3>
            <p>Admin</p>
        </div>

        <ul class="menu">
            <li><a href="admin_dashboard.php">Dashboard</a></li>
            <li><a href="manage_librarian.php" class="active">Manage Librarians</a></li>
            <li><a href="manage_member.php">Manage Members</a></li>
            <li><a href="#">View Reports</a></li>
            <li><a href="#">Profile</a></li>
            <li class="logout"><a href="../php/logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="content">
        <h1>Manage Librarians</h1>

        <a href="add_edit_librarian.php" class="btn">➕ Add Librarian</a>

        <div class="table-wrapper">
            <table class="librarian-table">
                <thead>
                    <tr>
                        <th>SN</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                         <?php $sn = 1; ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                           <td><?= $sn++; ?></td>
                            <td><?= htmlspecialchars($row['fullname']); ?></td>
                            <td><?= htmlspecialchars($row['email']); ?></td>
                            <td><?= htmlspecialchars($row['phone']); ?></td>
                            <td>
                                <a href="add_edit_librarian.php?id=<?= $row['id']; ?>">Edit</a> |
                                <a href="?delete=<?= $row['id']; ?>"
                                   onclick="return confirm('Delete this librarian?')">
                                   Delete
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center;">No librarians found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

</div>

</body>
</html>
