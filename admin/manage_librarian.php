<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("../php/connection.php");

// Admin protection
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../php/login.php");
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
    <link rel="stylesheet" href="../css/responsive.css">
    <style>
        /* ── Top bar ── */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        /* ── Responsive table card ── */
        .table-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
        }

        .librarian-table {
            width: 100%;
            border-collapse: collapse;
            background: transparent;
            color: var(--text-primary);
            box-shadow: none;
        }

        .librarian-table th {
            background: var(--border-color);
            color: var(--text-secondary);
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #3a3f4e;
        }

        .librarian-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            color: var(--text-primary);
        }

        .librarian-table tbody tr:last-child td {
            border-bottom: none;
        }

        .librarian-table tbody tr:hover {
            background: rgba(255,255,255,0.03);
        }

        /* ── Action buttons ── */
        .action-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 7px 14px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .action-btn.edit {
            background: rgba(59,130,246,0.15);
            color: #3b82f6;
        }

        .action-btn.edit:hover {
            background: #3b82f6;
            color: #fff;
        }

        .action-btn.delete {
            background: rgba(239,68,68,0.15);
            color: #ef4444;
        }

        .action-btn.delete:hover {
            background: #ef4444;
            color: #fff;
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        /* ── Card layout for very small screens ── */
        @media (max-width: 600px) {
            .librarian-table thead {
                display: none;
            }

            .librarian-table,
            .librarian-table tbody,
            .librarian-table tr,
            .librarian-table td {
                display: block;
                width: 100%;
            }

            .librarian-table tr {
                border-bottom: 2px solid var(--border-color);
                padding: 12px 0;
            }

            .librarian-table tr:last-child {
                border-bottom: none;
            }

            .librarian-table td {
                border-bottom: none;
                padding: 6px 16px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 8px;
                font-size: 13px;
            }

            .librarian-table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--text-secondary);
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                flex-shrink: 0;
                min-width: 60px;
            }

            .action-cell {
                justify-content: flex-end;
            }
        }
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
            <li><a href="manage_librarian.php" class="active">Manage Librarians</a></li>
            <li><a href="manage_member.php">Manage Members</a></li>
            <li><a href="view_reports.php">View Reports</a></li>
            <li><a href="../php/view_members.php">View Members</a></li>
            <li><a href="../librarian/book_list.php">Manage Books</a></li>
            <li><a href="../php/issue_books.php">Issue Books</a></li>
            <li><a href="../php/return_books.php">Return Books</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li class="logout"><a href="../php/logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="content">
        <div class="top-bar">
            <h1>Manage Librarians</h1>
            <a href="add_edit_librarian.php" class="btn">➕ Add Librarian</a>
        </div>

        <div class="table-card">
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
                                <td data-label="SN"><?= $sn++; ?></td>
                                <td data-label="Name"><?= htmlspecialchars($row['fullname']); ?></td>
                                <td data-label="Email"><?= htmlspecialchars($row['email']); ?></td>
                                <td data-label="Phone"><?= htmlspecialchars($row['phone']); ?></td>
                                <td data-label="Action">
                                    <div class="action-cell">
                                        <a href="add_edit_librarian.php?id=<?= $row['id']; ?>" class="action-btn edit">✏️ Edit</a>
                                        <a href="?delete=<?= $row['id']; ?>"
                                           class="action-btn delete"
                                           onclick="return confirm('Delete this librarian?')">
                                           🗑️ Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">No librarians found.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</div>

<script src="../js/mobile_menu.js"></script>
</body>
</html>