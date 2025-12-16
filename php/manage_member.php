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
// APPROVE / REJECT MEMBER
// =======================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE users SET status='approved' WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    header("Location: manage_member.php");
    exit();
}

// =======================
// FETCH MEMBERS
// =======================
$filter = $_GET['filter'] ?? '';

if ($filter === 'pending') {
    $result = $conn->query("SELECT * FROM users WHERE role='member' AND status='pending' ORDER BY id DESC");
} else {
    $result = $conn->query("SELECT * FROM users WHERE role='member' ORDER BY id DESC");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Members</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        .filter-buttons { margin-bottom: 15px; }
        .filter-buttons .btn { margin-right: 5px; text-decoration: none; padding: 5px 10px; border: 1px solid #333; border-radius: 4px; background: #0ae064; }
        .filter-buttons .btn:hover { background:#0ae064; }
    </style>
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
            <li><a href="../dashboard/admin_dashboard.php">Dashboard</a></li>
            <li><a href="manage_librarian.php">Manage Librarians</a></li>
            <li><a href="manage_member.php" class="active">Manage Members</a></li>
            <li><a href="return_books.php">Return Books</a></li>
            <li><a href="view_reports.php">View Reports</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li class="logout"><a href="../php/logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="content">
        <h1>Manage Members</h1>
         <!-- Filter Buttons -->
        <div class="filter-buttons">
            <a href="manage_member.php" class="btn">All Members</a>
            <a href="manage_member.php?filter=pending" class="btn">Pending Members</a>
        </div>

        <div class="table-wrapper">
            <table class="librarian-table">
                <thead>
                    <tr>
                        <th>SN</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php $sn = 1; ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr<?= $row['status'] === 'pending' ? 'style="background-color: #fff3cd;"' : ''; ?>>
                            <td><?= $sn++; ?></td>
                            <td><?= htmlspecialchars($row['fullname']); ?></td>
                            <td><?= htmlspecialchars($row['email']); ?></td>
                            <td><?= htmlspecialchars($row['contact']); ?></td>
                            <td>
                                <?= $row['status'] === 'approved' ? 'Approved' : 'Pending'; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] !== 'approved'): ?>
                                    <a href="?action=approve&id=<?= $row['id']; ?>" onclick="return confirm('Approve this member?')">Approve</a> |
                                    <a href="?action=reject&id=<?= $row['id']; ?>" onclick="return confirm('Reject this member?')">Reject</a>
                                <?php else: ?>
                                    <span style="color: green;">✅</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center;">No members found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

</div>

</body>
</html>
